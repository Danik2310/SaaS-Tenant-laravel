<?php

namespace App\Shared\Support;

use InvalidArgumentException;

/**
 * Signs and verifies the short-lived, one-time handoff token used to enter a
 * tenant in god mode. The token is HMAC'd with the shared application key, so
 * only the same application can produce and validate it (central and tenant
 * run on the same codebase and share APP_KEY).
 */
class ImpersonationToken
{
    /**
     * Produce a signed token string for the given payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function sign(array $payload, int $ttlSeconds): string
    {
        $payload['iat'] = time();
        $payload['exp'] = time() + $ttlSeconds;

        $encoded = self::base64UrlEncode(json_encode($payload));

        return $encoded.'.'.self::signature($encoded);
    }

    /**
     * Validate a token and return its decoded payload, or null when invalid.
     *
     * @return array<string, mixed>|null
     */
    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$encoded, $signature] = $parts;

        if (! hash_equals(self::signature($encoded), $signature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($encoded), true);

        if (! is_array($payload)) {
            return null;
        }

        $exp = (int) ($payload['exp'] ?? 0);

        if ($exp > 0 && time() > $exp) {
            return null;
        }

        return $payload;
    }

    protected static function signature(string $encoded): string
    {
        $key = config('app.key');

        if (! $key || ! str_starts_with($key, 'base64:')) {
            throw new InvalidArgumentException('Impersonation token requires an application key.');
        }

        return hash_hmac('sha256', $encoded, base64_decode(substr($key, 7)));
    }

    protected static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected static function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'));
    }
}
