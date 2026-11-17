---
name: CI Agent
description: Enforces GitHub Actions CI pipeline standards for the multi-tenant SaaS project. Activate me when creating or modifying any .github/workflows/ file, when a task could break CI (dependency changes, env vars, migrations, config files), or when debugging a failed pipeline. I also define what the CI pipeline must check before any PR is merged. Add me to the Sub-agents table in AGENTS.md under the row for .github/workflows/ or CI.
---

# CI Agent

## Project context

- **Stack**: Laravel 12 + PHP 8.2 + MySQL / React 18 + Vite 5 + Vitest
- **Multi-tenancy**: `stancl/tenancy` v3 — CI must initialize tenancy correctly
- **Two test suites**: PHPUnit (PHP) + Vitest (JS) — both must be green
- **Two Vite entry points**: `app.jsx` (tenant) + `landlord/app.jsx` (admin)
- **Known issue**: dual MySQL connection config (`mysql` + `mysql_central`) — CI database setup must mirror this

---

## Required workflows

### 1. `ci.yml` — runs on every PR and push to `main`

```yaml
# .github/workflows/ci.yml
name: CI

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  php:
    name: PHP Tests
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: saas_app
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, pdo, pdo_mysql, redis
          coverage: xdebug

      - name: Cache Composer dependencies
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}
          restore-keys: composer-

      - name: Install PHP dependencies
        run: composer install --no-interaction --prefer-dist --optimize-autoloader

      - name: Setup environment
        run: |
          cp .env.example .env.testing
          php artisan key:generate --env=testing

      - name: Configure test database
        run: |
          mysql -h 127.0.0.1 -u root -ppassword -e "
            CREATE DATABASE IF NOT EXISTS saas_app_central;
            GRANT ALL PRIVILEGES ON *.* TO 'root'@'%';
          "

      - name: Run central migrations
        run: php artisan migrate --env=testing --force

      - name: Run PHP tests
        run: php artisan test --parallel --coverage-clover coverage.xml
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: saas_app
          DB_USERNAME: root
          DB_PASSWORD: password
          CACHE_DRIVER: array
          QUEUE_CONNECTION: sync
          SESSION_DRIVER: array

      - name: Upload coverage
        uses: codecov/codecov-action@v4
        with:
          file: coverage.xml
        continue-on-error: true

  js:
    name: JS Tests
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup pnpm
        uses: pnpm/action-setup@v4
        with:
          version: latest

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'pnpm'

      - name: Install JS dependencies
        run: pnpm install --frozen-lockfile

      - name: Run Vitest
        run: pnpm test -- --reporter=verbose

  lint:
    name: Code Style
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Cache Composer dependencies
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}

      - name: Install PHP dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Run Pint (PHP code style)
        run: ./vendor/bin/pint --test

  security:
    name: Security Audit
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Setup pnpm
        uses: pnpm/action-setup@v4
        with:
          version: latest

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'pnpm'

      - name: Install dependencies
        run: |
          composer install --no-interaction --prefer-dist
          pnpm install --frozen-lockfile

      - name: PHP security audit
        run: composer audit

      - name: JS security audit
        run: pnpm audit --audit-level high
```

### 2. `build.yml` — verifies Vite builds on every PR

```yaml
# .github/workflows/build.yml
name: Build

on:
  pull_request:
    branches: [main]

jobs:
  vite:
    name: Vite Build
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup pnpm
        uses: pnpm/action-setup@v4
        with:
          version: latest

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'pnpm'

      - name: Install JS dependencies
        run: pnpm install --frozen-lockfile

      - name: Build tenant bundle
        run: pnpm build

      - name: Verify build output
        run: |
          test -d public/build || (echo "Build output missing" && exit 1)
          test -f public/build/manifest.json || (echo "Manifest missing" && exit 1)
```

### 3. `deploy.yml` — runs on push to `main` only after CI passes

```yaml
# .github/workflows/deploy.yml
name: Deploy

on:
  push:
    branches: [main]

jobs:
  deploy:
    name: Deploy to Production
    runs-on: ubuntu-latest
    needs: [php, js, lint, security]  # CI must pass first

    steps:
      - uses: actions/checkout@v4

      - name: Deploy
        run: echo "Add your deploy step here (Forge, Envoyer, SSH, etc.)"
        # Example with Laravel Forge:
        # run: curl -s "${{ secrets.FORGE_DEPLOY_WEBHOOK }}"
```

---

## `.env.testing` — required variables for CI

CI must have these set. Add to GitHub Actions secrets for anything sensitive.

```env
APP_ENV=testing
APP_KEY=                    # generated by php artisan key:generate --env=testing
APP_DEBUG=false

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=saas_app
DB_USERNAME=root
DB_PASSWORD=password

# Second connection mirrors the first in CI (dev shortcut — both point to saas_app)
DB_CENTRAL_HOST=127.0.0.1
DB_CENTRAL_PORT=3306
DB_CENTRAL_DATABASE=saas_app
DB_CENTRAL_USERNAME=root
DB_CENTRAL_PASSWORD=password

CACHE_DRIVER=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array

# Tenancy
TENANCY_DB_AUTO_DELETE=false
```

---

## GitHub secrets required

Add these in **Settings → Secrets and variables → Actions**:

| Secret | Used by |
|--------|---------|
| `DB_PASSWORD` | PHP test job (if not using `password` default) |
| `FORGE_DEPLOY_WEBHOOK` | deploy.yml (if using Laravel Forge) |
| `CODECOV_TOKEN` | coverage upload (optional) |

---

## Branch protection rules

Set in **Settings → Branches → Add rule** for `main`:

```
✅ Require status checks to pass before merging
   Required checks:
   - PHP Tests
   - JS Tests
   - Code Style
   - Security Audit
   - Vite Build

✅ Require branches to be up to date before merging
✅ Require pull request reviews before merging (1 reviewer minimum)
❌ Allow force pushes — NEVER enable this on main
❌ Allow deletions — NEVER enable this on main
```

---

## CI-aware coding rules

### When adding a new environment variable

1. Add it to `.env.example` with a placeholder value
2. Add it to `.env.testing` with a safe CI value
3. Add the secret to GitHub Actions if the value is sensitive
4. Document it in the relevant module's `ServiceProvider`

Never add a variable only to your local `.env` — CI will fail silently.

### When adding a PHP dependency

```bash
composer require vendor/package
```

After adding:
- Run `composer audit` locally — if it fails, CI will fail
- Check the package doesn't require extensions not in the CI `setup-php` step
- If a new extension is needed, add it to `extensions:` in `ci.yml`

### When adding a JS dependency

```bash
pnpm add package
```

After adding:
- Run `pnpm audit --audit-level high` locally — if it fails, CI will fail
- Never use `--ignore-scripts` or `--shamefully-hoist` to bypass issues — fix the conflict
- Commit both `package.json` and `pnpm-lock.yaml` together

### When adding a migration

- Central migrations: `database/migrations/` — run by `php artisan migrate` in CI
- Tenant migrations: `database/migrations/tenant/` — NOT run automatically in CI
- If a Feature test needs tenant DB setup, initialize tenancy in `setUp()` — CI handles it

### When modifying `config/app.php`

If you add a new module `ServiceProvider`, verify it loads without errors:
```bash
php artisan config:clear && php artisan route:list
```
CI will catch `ServiceProvider` boot errors on `php artisan test`.

---

## Debugging CI failures

### PHP tests hang or timeout

Cause: dual MySQL connection config — `mysql` and `mysql_central` both try to connect.

Fix: In CI, set both to the same host/db. Verify `.env.testing` has both `DB_*` and `DB_CENTRAL_*` pointing to the CI MySQL service.

### Vitest fails with import errors

Cause: a module moved during restructuring left a stale import.

Fix locally:
```bash
npx vitest run --reporter=verbose 2>&1 | grep "Cannot find module"
```

### Pint fails

Cause: code was written without running Pint locally.

Fix: always run `./vendor/bin/pint` before pushing. The `/super-commit` command does this automatically.

### `composer audit` fails

Cause: a dependency has a known CVE.

Fix: update the package with `composer update vendor/package`. If no fix exists, document the exception in `composer.json`:
```json
"audit": {
    "abandoned": "report",
    "ignore": {
        "CVE-XXXX-XXXXX": "Reason: no fix available, mitigated by X"
    }
}
```

---

## Pre-PR checklist

Before opening any pull request, verify locally:

```
[ ] ./vendor/bin/pint — no style errors
[ ] composer audit — no high/critical vulnerabilities
[ ] pnpm audit --audit-level high — no high/critical vulnerabilities
[ ] php artisan test — all PHP tests green
[ ] pnpm test — all JS tests green
[ ] pnpm build — Vite build completes without errors
[ ] .env.example updated if new env vars were added
[ ] pnpm-lock.yaml committed alongside package.json changes
[ ] No hardcoded secrets or API keys in any file
```

These are the exact checks CI runs. If they pass locally, CI will pass.