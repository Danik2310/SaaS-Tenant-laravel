<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\Tenants\Support\WorkspaceAddress;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Guest workspace lookup from the marketing site.
 *
 * The value is deliberately not rewritten here: {@see WorkspaceAddress}
 * owns the "what did they mean" rules, and the controller needs the whole
 * ordered candidate list rather than one normalised string. Validation only
 * rejects input that could never be an address.
 */
class FindWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'workspace' => ['required', 'string', 'max:255'],
        ];
    }
}
