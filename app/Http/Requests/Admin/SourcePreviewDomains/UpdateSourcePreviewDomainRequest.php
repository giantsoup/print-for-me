<?php

namespace App\Http\Requests\Admin\SourcePreviewDomains;

use App\Enums\SourcePreviewFetchPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSourcePreviewDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'policy' => ['required', Rule::enum(SourcePreviewFetchPolicy::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'policy.required' => 'Choose whether previews should be allowed or blocked for this domain.',
        ];
    }
}
