<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()?->can('manage profile', 'admin');
    }

    public function rules(): array
    {
        return [
            'password' => 'required|string',
        ];
    }
}
