<?php

namespace App\Http\Requests\Admin;

use App\Models\AdminUser;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): string
    {
        $this->ensureIsNotRateLimited();

        $token = Auth::guard('admin')->attempt(
            $this->only('email', 'password') + ['is_active' => true]
        );

        if (! $token) {
            RateLimiter::hit($this->throttleKey());

            $blocked = AdminUser::withoutTrashed()
                ->where('email', $this->string('email'))
                ->where('is_active', false)
                ->exists();

            throw ValidationException::withMessages([
                'email' => $blocked
                    ? 'Your account has been blocked. Please contact the system administrator for assistance.'
                    : trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $token;
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
