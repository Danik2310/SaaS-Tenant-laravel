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
 *  3. No local account → for a **registration** flow, create one without a
 *     password, already verified, and within the plan's user quota. A
 *     **login** flow never creates: an unknown identity is refused with
 *     `NO_ACCOUNT`, because the login button must not silently add a member to
 *     a workspace the visitor was never invited to.
 *  4. A deliberately unlinked identity is refused before any of the above:
 *     deleting the row would let verified-email matching silently recreate it.
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
        bool $createUnknown = true,
    ): User {
        $providerUserId = trim((string) $identity->getId());
        $email = Str::lower(trim((string) $identity->getEmail()));

        if ($providerUserId === '' || $email === '') {
            throw new OAuthAccountRejectedException(OAuthAccountRejectedException::MALFORMED);
        }

        if (OAuthAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->whereNotNull('unlinked_at')
            ->exists()) {
            throw new OAuthAccountRejectedException(OAuthAccountRejectedException::UNLINKED);
        }

        $linked = OAuthAccount::query()
            ->linked()
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

            $this->syncProviderMetadata($linked, $email, $identity->getAvatar());

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

        if (! $createUnknown) {
            // Login only signs in. The register screen is the single place that
            // may turn a Google identity into a new member, so an uninvited
            // visitor cannot join a workspace by pressing the login button.
            throw new OAuthAccountRejectedException(OAuthAccountRejectedException::NO_ACCOUNT);
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

    /**
     * Attach a verified provider identity to the user who started the connect
     * flow from their security settings.
     *
     * This is deliberately not `loginOrRegister()`: connect has an authenticated
     * target user, so it never matches by email and never creates a user. The
     * only identity that may be revived is a tombstoned row belonging to that
     * same user; a live row owned by somebody else is refused.
     */
    public function connect(User $user, SocialiteUser $identity, string $provider = OAuthProvider::GOOGLE): void
    {
        $providerUserId = trim((string) $identity->getId());
        $email = Str::lower(trim((string) $identity->getEmail()));

        if ($providerUserId === '' || $email === '') {
            throw new OAuthAccountRejectedException(OAuthAccountRejectedException::MALFORMED);
        }

        if (! $this->emailIsVerified($identity)) {
            throw new OAuthAccountRejectedException(OAuthAccountRejectedException::UNVERIFIED_EMAIL);
        }

        if (! $user->is_active) {
            throw new OAuthAccountRejectedException(OAuthAccountRejectedException::INACTIVE);
        }

        $existing = OAuthAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($existing instanceof OAuthAccount) {
            if ((int) $existing->user_id !== (int) $user->getKey()) {
                throw new OAuthAccountRejectedException(OAuthAccountRejectedException::ALREADY_LINKED);
            }

            // Explicit reconnect: only the owner can clear their own tombstone.
            $existing->update([
                'unlinked_at' => null,
                'provider_email' => $email,
                'avatar_url' => $identity->getAvatar(),
            ]);

            return;
        }

        $this->link($user, $provider, $providerUserId, $email, $identity->getAvatar());
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
     * Keep the cached provider email/avatar current for UI display.
     *
     * The link itself is keyed by provider_user_id, never by email, so this is
     * informational only and must not influence sign-in decisions.
     */
    private function syncProviderMetadata(OAuthAccount $account, string $email, ?string $avatar): void
    {
        $attributes = [];

        if ($account->provider_email !== $email) {
            $attributes['provider_email'] = $email;
        }

        if ($avatar !== null && $account->avatar_url !== $avatar) {
            $attributes['avatar_url'] = $avatar;
        }

        if ($attributes !== []) {
            $account->update($attributes);
        }
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
