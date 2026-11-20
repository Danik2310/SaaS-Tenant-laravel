<?php

declare(strict_types=1);

namespace App\Shared\Providers;

use App\Shared\Support\JwtCookie;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use PHPOpenSourceSaver\JWTAuth\JWT;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use PHPOpenSourceSaver\JWTAuth\Manager;
use PHPOpenSourceSaver\JWTAuth\Providers\JWT\Lcobucci;

/**
 * JWT guard driver.
 *
 * Extends Laravel's auth system with a `jwt` driver used by both the `admin`
 * and `web` guards. The signing secret is resolved per guard from
 * `jwt.guard_secrets.{guard}`, falling back to `jwt.secret` only outside
 * production. Configuration is validated before a guard is built.
 *
 * Flow (per guard resolution):
 *  1. Resolve the secret for the guard name.
 *  2. Build a token manager (algorithm, keys, blacklist, refresh-iat, claims).
 *  3. Build a JWT that locks the subject, with the guard's user provider.
 *  4. Apply the guard TTL and keep the guard's request reference refreshed.
 *
 * @see config/jwt.php
 * @see JwtCookie
 */
class JwtServiceProvider extends ServiceProvider
{
    /**
     * Reject weak or cross-context JWT configuration.
     *
     * Production fails closed: a shared tenant/admin secret would reduce the
     * isolation boundary to the guessable `prv` claim. Non-production warns so
     * a fresh local checkout can still boot before its secrets are generated.
     */
    public static function assertSecureConfiguration(
        string $guard,
        ?string $secret,
        string $algo,
        bool $production
    ): void {
        $allowed = (array) config('jwt.allowed_algorithms', ['HS256', 'HS384', 'HS512']);

        if (! in_array($algo, $allowed, true)) {
            throw new \RuntimeException("JWT algorithm [$algo] is not allowed.");
        }

        $missing = ! is_string($secret) || $secret === '';
        $weakHmac = str_starts_with($algo, 'HS')
            && (! is_string($secret) || strlen($secret) < 32);
        $sharedWithTenant = $guard === 'admin'
            && is_string($secret)
            && $secret === config('jwt.guard_secrets.web');

        if (! $missing && ! $weakHmac && ! $sharedWithTenant) {
            return;
        }

        $problems = [];

        if ($missing) {
            $problems[] = "the $guard secret is missing";
        }

        if ($weakHmac) {
            $problems[] = "the $guard secret must be at least 32 bytes for $algo";
        }

        if ($sharedWithTenant) {
            $problems[] = 'the admin secret must be distinct from the tenant secret';
        }

        $message = 'Insecure JWT configuration: '.implode('; ', $problems).'.';

        if ($production) {
            throw new \RuntimeException($message);
        }

        Log::warning($message);
    }

    public function boot(): void
    {
        $this->app['auth']->extend('jwt', function (Application $app, string $name, array $config) {
            $settings = $app->make('config');

            $secret = $settings->get("jwt.guard_secrets.$name") ?: $settings->get('jwt.secret');
            $algo = (string) $settings->get('jwt.algo');

            self::assertSecureConfiguration(
                $name,
                is_string($secret) ? $secret : null,
                $algo,
                $settings->get('app.env') === 'production'
            );

            $manager = (new Manager(
                new Lcobucci(
                    $secret,
                    $algo,
                    $settings->get('jwt.keys')
                ),
                $app['tymon.jwt.blacklist'],
                $app['tymon.jwt.payload.factory']
            ))
                ->setBlacklistEnabled((bool) $settings->get('jwt.blacklist_enabled'))
                ->setRefreshIat((bool) $settings->get('jwt.refresh_iat', false))
                ->setPersistentClaims($settings->get('jwt.persistent_claims'))
                ->setBlackListExceptionEnabled((bool) $settings->get('jwt.show_black_list_exception', 0));

            $jwt = (new JWT($manager, $app['tymon.jwt.parser']))
                ->lockSubject($settings->get('jwt.lock_subject'));

            $guard = new JWTGuard(
                $jwt,
                $app['auth']->createUserProvider($config['provider']),
                $app['request'],
                $app['events']
            );

            $guard->setTTL($config['ttl'] ?? $settings->get('jwt.ttl'));

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }
}
