<?php

declare(strict_types=1);

namespace App\Tenants\Support;

use Illuminate\Support\Str;

/**
 * The verified Google identity behind an in-progress workspace signup.
 *
 * Google runs first: the visitor picks their account before seeing the plan or
 * the workspace form, and the callback parks the verified name/address in the
 * central session. The signup form then only asks for what Google does not
 * collect (plan, company, workspace name/address, terms) and the request layer
 * forces this address on submit, so a tampered payload cannot register a
 * workspace for a different mailbox.
 *
 * Both ends live on the central host (the form and the OAuth callback), so the
 * session cookie is enough — no signed cross-host handoff is needed here. The
 * short TTL keeps an abandoned flow from silently applying to a much later
 * submission in the same browser.
 */
final class PendingOAuthSignup
{
    private const KEY = 'oauth.signup';

    /** How long an unused Google identity stays attached to the browser. */
    public const TTL_MINUTES = 30;

    public static function remember(string $name, string $email): void
    {
        session()->put(self::KEY, [
            'name' => trim($name),
            'email' => Str::lower(trim($email)),
            // Stamp the deadline here, not on read: a refreshed or re-rendered
            // form must not extend the window indefinitely.
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->getTimestamp(),
        ]);
    }

    /**
     * @return array{name: string, email: string}|null
     */
    public static function identity(): ?array
    {
        $stored = session()->get(self::KEY);

        if (! is_array($stored)) {
            return null;
        }

        $expiresAt = $stored['expires_at'] ?? null;
        $email = Str::lower(trim((string) ($stored['email'] ?? '')));

        if (! is_int($expiresAt) || $expiresAt <= now()->getTimestamp() || $email === '') {
            self::forget();

            return null;
        }

        return [
            'name' => trim((string) ($stored['name'] ?? '')),
            'email' => $email,
        ];
    }

    public static function forget(): void
    {
        session()->forget(self::KEY);
    }
}
