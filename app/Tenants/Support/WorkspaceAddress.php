<?php

declare(strict_types=1);

namespace App\Tenants\Support;

use App\Models\Domain;

/**
 * Turns whatever a visitor types into ordered workspace-domain candidates.
 *
 * The central finder accepts a bare slug (`acme`), a full workspace host
 * (`acme.sasapp`), a pasted link (`https://acme.sasapp/login?next=/x`) or a
 * custom domain (`inventory.acme.com`). Only the host part is meaningful. The
 * controller stops at the first candidate that exists in {@see Domain}, so the
 * order here is the order a person is likely to have meant it.
 */
final class WorkspaceAddress
{
    /**
     * Ordered, de-duplicated domain candidates for a raw input.
     *
     * @return list<string>
     */
    public static function candidates(string $input): array
    {
        $host = self::normalize($input);

        if ($host === '') {
            return [];
        }

        $candidates = [$host];

        // A visitor who bookmarked `www.acme.com` should still land on the
        // address that is actually registered for the tenant.
        if (str_starts_with($host, 'www.')) {
            $candidates[] = substr($host, 4);
        }

        // A single label can only be a workspace slug, never a public domain.
        if (! str_contains($host, '.')) {
            $candidates[] = $host.'.'.self::suffix();
        }

        return array_values(array_unique(array_filter(
            $candidates,
            static fn (string $candidate): bool => $candidate !== '',
        )));
    }

    /**
     * Reduce a typed address to a lowercase host, or an empty string when the
     * input cannot be an address at all.
     */
    public static function normalize(string $input): string
    {
        $value = trim($input);

        if ($value === '') {
            return '';
        }

        if (preg_match('~^[a-z][a-z0-9+.\-]*://~i', $value) === 1) {
            // A typed or pasted URL: let the parser find the host. This also
            // drops credentials, a port, a path, the query and the fragment.
            $value = (string) (parse_url($value, PHP_URL_HOST) ?? '');
        } elseif (str_starts_with($value, '//')) {
            $value = (string) (parse_url('http:'.$value, PHP_URL_HOST) ?? '');
        } else {
            // No scheme: drop a path/query/fragment and any `user:pass@` part.
            $value = (string) preg_replace('~[/?#].*$~s', '', $value);

            $at = strrpos($value, '@');

            if ($at !== false) {
                $value = substr($value, $at + 1);
            }

            // `acme.sasapp:8000` in development — the port is not part of the
            // workspace address.
            $value = (string) preg_replace('~:\d+$~', '', $value);
        }

        // A trailing dot is the legal FQDN form (`acme.sasapp.`).
        $value = strtolower(rtrim(trim($value), '.'));

        // Anything with whitespace or a path separator left over is not a host.
        if ($value === '' || preg_match('~[\s/]~', $value) === 1) {
            return '';
        }

        return $value;
    }

    /**
     * Central hosts are the marketing site and the admin panel, never a
     * workspace, so they are rejected before the database lookup.
     */
    public static function isCentral(string $domain): bool
    {
        return in_array($domain, (array) config('tenancy.central_domains', []), true);
    }

    /** The workspace suffix without hardcoding `sasapp` into the frontend. */
    public static function suffix(): string
    {
        return strtolower(trim((string) config('tenancy.tenant_domain_suffix', 'sasapp')));
    }
}
