<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\User;
use App\Shared\Constants\OAuthProvider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards the start of a settings-initiated Google connect.
 *
 * A password must already exist: if Google is the only sign-in method, losing
 * the link would lock the user out. This mirrors UnlinkOAuthAccountRequest.
 */
class ConnectOAuthAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && tenant() !== null
            && $user->password !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(OAuthProvider::SUPPORTED)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['provider' => $this->route('provider')]);
    }

    protected function failedAuthorization(): void
    {
        if ($this->user() instanceof User && $this->user()->password === null) {
            throw new AuthorizationException('Set a password before connecting Google.');
        }

        throw new AuthorizationException;
    }
}
