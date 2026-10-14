<?php

declare(strict_types=1);

namespace App\Shared\Providers;

use App\Shared\Support\JwtCookie;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use PHPOpenSourceSaver\JWTAuth\JWT;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use PHPOpenSourceSaver\JWTAuth\Manager;
use PHPOpenSourceSaver\JWTAuth\Providers\JWT\Lcobucci;

/**
 * JWT guard driver.
 *
 * Extends Laravel's auth system with a `jwt` driver used by the `admin` guard.
 * The signing secret is resolved per guard from `jwt.guard_secrets.{guard}`,
 * falling back to the shared `jwt.secret`.
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
    public function boot(): void
    {
        $this->app['auth']->extend('jwt', function (Application $app, string $name, array $config) {
            $settings = $app->make('config');

            $secret = $settings->get("jwt.guard_secrets.$name") ?: $settings->get('jwt.secret');

            $manager = (new Manager(
                new Lcobucci(
                    $secret,
                    $settings->get('jwt.algo'),
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
