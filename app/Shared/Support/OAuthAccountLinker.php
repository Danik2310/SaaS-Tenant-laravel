<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Models\OAuthAccount;
use App\Models\User;
use App\Shared\Constants\OAuthProvider;
use App\Shared\Contracts\ResourceEnforcementInterface;
use App\Shared\Exceptions\OAuthAccountRejectedException;
use App\Shared\Exceptions\PlanLimitExceededException;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Turns a verified Google identity into a tenant user, or refuses.
 *
 * Decision order (each step is a security boundary):
 *  1. Known identity (`provider` + `sub`) → sign in, no matching by email at
 *     all. This is the only path that can never be hijacked by an email change
 *     on either side.
 *  2. Unknown identity → match an existing local account by email **only when
 *     Google reports `email_verified`**. Linking an unverified address would let
 *     anyone who can create an unverified Google account for victim@example.com
 *     take over the victim's local account.
 *  3. No local account → create one without a password, already verified, and
 *     within the plan's user quota.
 *
 * Soft-deleted accounts are never resurrected: the tenant email index is
 * partial on purpose (`deleted_at IS NULL`), and the Eloquent global scope keeps
 * lookups inside that same window.
 *
 * No provider tokens (access/refresh) are stored — the app only needs identity.
 */
final class OAuthAccountLinker
{
    public function loginOrRegister(
        SocialiteUser $identity,
        ResourceEnforcementInterface $enforcement,
        string $provider = OAuthProvider::GOOGLE,
    ): User {
        $providerUserId = trim((string) $identity->getId());
        $email = Str::lower(trim((string) $identity->getEmail()));

        if ($providerUserId === '' || $email === '') {
            throw new OAuthAccountRejectedException(OAuthAccountRejectedException::MALFORMED);
        }

        $linked = OAuthAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($linked !== null) {
            $user = $linked->user;

            if (! $user instanceof User) {
                throw new OAuthAccountRejectedException(OAuthAccountRejectedException::DELETED);
            }

            if (! $user->is_active) {
                throw new OAuthAccountRejectedException(OAuthAccountRejectedException::INACTIVE);
            }

            return $user;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User) {
            if (! $user->is_active) {
                throw new OAuthAccountRejectedException(OAuthAccountRejectedException::INACTIVE);
            }

            if (! $this->emailIsVerified($identity)) {
                throw new OAuthAccountRejectedException(OAuthAccountRejectedException::UNVERIFIED_EMAIL);
            }

            $this->link($user, $provider, $providerUserId, $email, $identity->getAvatar());

            return $user;
        }

        if (! $this->emailIsVerified($identity)) {
            throw new OAuthAccountRejectedException(OAuthAccountRejectedException::UNVERIFIED_EMAIL);
        }

        $this->assertWithinUserQuota($enforcement);

        $user = new User;
        $user->forceFill([
            'name' => $this->displayName($identity, $email),
            // Null on purpose: passwordless account, see
            // 2026_11_14_000002_make_users_password_nullable.
            'password' => null,
            'email' => $email,
            'is_active' => true,
            // Google vouches for the address, so there is nothing to confirm and
            // no verification mail to send (the `Registered` event is
            // deliberately not fired: its listener only sends that mail).
            'email_verified_at' => now(),
        ])->save();

        $this->link($user, $provider, $providerUserId, $email, $identity->getAvatar());

        return $user;
    }

    private function link(User $user, string $provider, string $providerUserId, string $email, ?string $avatar): void
    {
        OAuthAccount::query()->firstOrCreate(
            [
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
            ],
            [
                'user_id' => $user->getKey(),
                'provider_email' => $email,
                'avatar_url' => $avatar,
            ],
        );
    }

    /**
     * Google reports `email_verified` in the raw payload; it is not part of
     * Socialite's normalised field set.
     */
    private function emailIsVerified(SocialiteUser $identity): bool
    {
        return (bool) ($identity->getRaw()['email_verified'] ?? false);
    }

    private function displayName(SocialiteUser $identity, string $email): string
    {
        $name = trim((string) ($identity->getName() ?? $identity->getNickname() ?? ''));

        return $name !== '' ? $name : Str::before($email, '@');
    }

    private function assertWithinUserQuota(ResourceEnforcementInterface $enforcement): void
    {
        $limit = $enforcement->maxUsers();

        if ($limit !== PHP_INT_MAX && User::query()->count() >= $limit) {
            throw new PlanLimitExceededException('users', $limit);
        }
    }
}
