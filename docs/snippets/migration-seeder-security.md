# Migration & Seeder Security Guide

> **Scope**: All central (`database/migrations/`) and tenant (`database/migrations/tenant/`) migrations, central seeders (`database/seeders/`), tenant seeders (`database/seeders/Tenant/`), and factories (`database/factories/`).
>
> **Applies to**: Laravel 10 + `stancl/tenancy` v3 multi-tenant SaaS

---

## 1. Seeder Password Policy

### Rule
Every seeder that creates authenticatable users **must** resolve passwords from an environment variable, never hardcode them.

### Implementation pattern

```php
// ✅ Correct — env variable with production guard
$password = env('TENANT_PASSWORD');
if (! $password) {
    if (app()->environment('production')) {
        throw new \RuntimeException(
            'TENANT_PASSWORD environment variable is not set.'
        );
    }
    $password = 'password'; // dev-only fallback
}
$hashedPassword = Hash::make($password);
```

### Env variables used by seeders

| Env variable | Used by | Creates |
|---|---|---|
| `ADMIN_PASSWORD` | `AdminUserSeeder`, `StaffSeeder` | Central admin users (`AdminUser`) |
| `TENANT_PASSWORD` | `Tenant\UserSeeder` | Tenant users (`User` via `UserFactory`) |

Both **must** be defined in `.env` (see `.env.example`). In production, the seeder **throws** if the variable is missing — never falls back to a default.

### Factories

Factories in `database/factories/` are used by tests and seeders. They may hardcode `'password'` as a test convenience — this is **acceptable** only because:

- Factories are never called directly in production seeding logic (only via `UserSeeder` or test `setUp()`).
- The factory password is overridden when the seeder passes a specific `$hashedPassword`.

However, for maximum defense-in-depth, factories should use a static property:

```php
protected static ?string $password;

'password' => static::$password ??= Hash::make('password'),
```

(This is already implemented in `UserFactory.php`.)

### Checklist for new seeders

```
[ ] Password comes from env() with production guard
[ ] Env variable documented in .env.example
[ ] Production guard throws RuntimeException with clear message
[ ] Static factory password if UserFactory::class is used
[ ] No hardcoded secrets (API keys, tokens) in seeder data arrays
```

---

## 2. Encryption Requirements

### When to encrypt

Any column storing data that meets **any** of these criteria **must** be encrypted at the application level:

- API keys, secret keys, tokens, or credentials for external services
- Personal Identifiable Information (PII) — email, phone, address
- Financial data — payment gateway credentials (not PCI tokens which are already handled by the gateway)

### Implementation

Use Laravel's `Crypt::encryptString()` / `Crypt::decryptString()`:

```php
use Illuminate\Support\Facades\Crypt;

// Encrypt (store)
$model->api_key = Crypt::encryptString($plaintextKey);

// Decrypt (read)
$plaintextKey = Crypt::decryptString($model->api_key);
```

### Migration encryption pattern (data migration)

When retroactively encrypting existing data, the migration must:

1. **Be idempotent** — detect already-encrypted values and skip them:
   ```php
   if (! str_starts_with($value, 'eyJpdiI6')) {
       // Not yet encrypted — proceed
   }
   ```
   > `eyJpdiI6` is the base64-encoded prefix of Laravel's encrypted payload format (`{"iv":...`). This check is fragile but reliable within the same Laravel version. Alternatively, add a boolean `keys_encrypted` column.

2. **Use chunked/each iteration** — not a single raw `UPDATE`:
   ```php
   DB::table('table')->orderBy('id')->each(function ($row) { ... });
   ```

3. **Log and skip on decryption failure** in `down()`:
   ```php
   try {
       $decrypted = Crypt::decryptString($row->api_key);
   } catch (\Exception $e) {
       Log::warning('Cannot decrypt row {id}: {msg}', ['id' => $row->id, 'msg' => $e->getMessage()]);
       continue;  // skip — partial rollback is better than failure
   }
   ```

4. **Warn in docblock** that down() exposes plaintext:
   ```php
   /**
    * WARNING: Rolling back this migration will store API keys
    * as plaintext in the database. Ensure the deployment supports
    * reading plaintext keys before running down().
    */
   ```

### Schema column comment

The **create table migration** must include a column comment for encrypted fields:

```php
$table->text('api_key')->nullable()->comment('Encrypted via Crypt::encryptString()');
$table->text('secret_key')->nullable()->comment('Encrypted via Crypt::encryptString()');
```

This is a **schema contract** — future developers know the column must never be read as plaintext.

### Encryption checklist

```
[ ] Column comment documents encryption requirement
[ ] Data migration is idempotent (skips already-encrypted values)
[ ] Iterates rows individually (no mass UPDATE)
[ ] down() wraps decryption in try/catch with logging
[ ] down() docblock warns about plaintext exposure
[ ] All code paths that read the column use Crypt::decryptString()
```

---

## 3. Migration Rollback Safety

### Golden rule

A `down()` method must **always** produce a valid database state that is consistent with the schema before `up()` ran.

### Specific risks in this codebase

#### 3a. Data loss on rollback

**Never** drop a column in `down()` that contains data from an `up()` migration without first copying it back.

```php
// ❌ INCORRECT — data_placeholder is lost
public function down(): void
{
    Schema::table('tenants', function (Blueprint $table) {
        $table->dropColumn('data_placeholder'); // DATA LOST
    });
}

// ✅ CORRECT — migrate data back first
public function down(): void
{
    // Copy data_placeholder back to data
    DB::table('tenants')
        ->whereNotNull('data_placeholder')
        ->orderBy('id')
        ->each(function ($tenant) {
            DB::table('tenants')
                ->where('id', $tenant->id)
                ->update(['data' => $tenant->data_placeholder]);
        });

    // Now safe to drop
    Schema::table('tenants', function (Blueprint $table) {
        $table->dropColumn('data_placeholder');
    });
}
```

#### 3b. Silent decryption failure

A `down()` that decrypts encrypted columns must **never** silently produce partial plaintext. Logging then continuing is an acceptable trade-off only if:

- The failure is logged at `WARNING` level (visible in production monitoring)
- The next `up()` re-encrypts whatever was missed

#### 3c. Check-before-drop pattern

Always check if a column exists before dropping it — prevents fatal errors on re-runs:

```php
Schema::table('tenants', function (Blueprint $table) {
    $columns = Schema::getColumnListing('tenants');
    if (in_array('legacy_col', $columns)) {
        $table->dropColumn('legacy_col');
    }
});
```

#### 3d. Expand/Contract for destructive changes

Never `dropColumn`, `renameColumn`, or change a column type in one step on production tables. Use two deployments:

1. **Expand**: Add new column (nullable), deploy code that writes to both old and new
2. **Contract**: Backfill, drop old column, deploy code that reads only new

---

## 4. PII Handling

### Policy

| Data type | Storage requirement | Example |
|---|---|---|
| Email (auth) | Plaintext — used for login, requires functional access | `users.email`, `tenants.email` |
| Email (contact/notification) | Encrypted or hashed if not used for auth | `contact@example.com` in seeders |
| Name | Plaintext — low sensitivity | `tenants.name` |
| API keys / secrets | **Must** be encrypted | `payment_methods.api_key` |
| Address, phone | Encrypted at rest | Tenant-specific models |
| Financial credentials | **Must** be encrypted | Payment gateway keys |

### Current assessment

| Column | Storage | Risk | Recommendation |
|---|---|---|---|
| `tenants.email` | Plaintext | **Medium** — email is PII. Central DB contains all tenant admin emails in plaintext. | Encrypt via `Crypt::encryptString()` in a data migration, or accept as-is if the central DB access is sufficiently restricted. |
| `payment_methods.api_key` | Encrypted (via migration) | **Low** — properly encrypted | Ensure all read paths use `Crypt::decryptString()`. |
| `payment_methods.secret_key` | Encrypted (via migration) | **Low** — properly encrypted | Same as above. |

### Recommendation for `tenants.email`

The `tenants.email` column is the primary contact email used for tenant communications (not login). Encrypting it would require:

```php
// Read
$email = Crypt::decryptString($tenant->email);

// Write
$tenant->email = Crypt::encryptString($request->email);
```

**Decision factors**:
- If the central DB is in a restricted network with limited DBA access → plaintext may be acceptable
- If the central DB is shared or has broader access → **encrypt immediately**
- Emails are used for lookups (`WHERE email = ?`) → encrypting breaks this; consider hashing a searchable index separately

---

## 5. Raw SQL in Migrations

### Rule

**Never** interpolate user input or dynamic values into raw SQL strings.

```php
// ❌ INCORRECT — SQL injection vector
DB::statement("DROP DATABASE IF EXISTS `{$databaseName}`");

// ✅ CORRECT — only use parameterized queries
DB::statement('DROP DATABASE IF EXISTS `'.$databaseName.'`');
// (DDL does not support parameter binding, so ensure $databaseName is validated/whitelisted)
```

For DDL statements that cannot use parameter binding:

1. **Validate** the input against an allowlist:
   ```php
   $allowed = config('tenancy.database.prefix');
   if (! str_starts_with($databaseName, $allowed)) {
       throw new \RuntimeException("Invalid database name: $databaseName");
   }
   ```

2. **Use Laravel Schema Builder** whenever possible — it escapes identifiers automatically.

3. **For DML (`SELECT`, `UPDATE`, `DELETE`, `INSERT`)**, always use the Query Builder or Eloquent — never `DB::statement()` with concatenated values.

### Raw SQL allowed uses (with caution)

- `DB::statement('CREATE DATABASE ...')` — only in tenant provisioning, where the DB name is generated by the system, not user input
- `DB::statement('ALTER TABLE ...')` — only in migrations with static identifiers
- Index operations that Schema Builder doesn't support

### Checklist for raw SQL

```
[ ] No user input interpolated in SQL string
[ ] Dynamic identifiers are validated/whitelisted
[ ] Prefer Schema Builder over DB::statement()
[ ] DML queries always use Query Builder or Eloquent
```

---

## Quick Reference: Security Checklist for Any New Migration or Seeder

```text
[ ] Does this create authenticatable users? → Follow Seeder Password Policy (§1)
[ ] Does this store API keys, secrets, or credentials? → Encrypt (§2)
[ ] Does this store PII (email, phone, address)? → Encrypt or document exception (§4)
[ ] Does the down() method reverse up() without data loss? → Rollback Safety (§3)
[ ] Does this use raw SQL? → Parameterize or validate identifiers (§5)
[ ] Is the migration in the correct directory (central vs. tenant)?
[ ] Is a column comment added for encrypted fields?
[ ] Is the seeder/env variable documented in .env.example?
```

---

> **Last reviewed**: 2026-06-18
> **Maintainers**: Security Team, Platform Team
