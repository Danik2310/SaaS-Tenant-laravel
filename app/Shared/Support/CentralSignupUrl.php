<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * The central "create a workspace" page for a link rendered on a tenant host.
 *
 * The tenant login page offers "Sign up" to visitors who do not have a
 * workspace. That page is the **central** `/register` (plan → workspace →
 * payment), not the tenant's own `/register` (which joins the existing
 * workspace). `route('register.tenant')` is only trustworthy when the URL root
 * is forced back to a central domain, otherwise a link rendered on
 * `acme.sasapp` resolves against that host and lands on the tenant route that
 * shares the same URI.
 */
final class CentralSignupUrl
{
    public static function for(): string
    {
        $root = ForcedUrlRoot::centralRoot();
        $scheme = parse_url($root, PHP_URL_SCHEME) ?: ForcedUrlRoot::scheme();

        return ForcedUrlRoot::run($root, $scheme, static fn (): string => route('register.tenant'));
    }
}
