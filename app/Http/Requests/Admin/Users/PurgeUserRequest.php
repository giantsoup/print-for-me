<?php

namespace App\Http\Requests\Admin\Users;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class PurgeUserRequest extends UserActionRequest
{
    protected function ability(): string
    {
        return 'forceDelete';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $subject = $this->route('user');

        return [
            'confirm_email' => ['required', 'email:rfc', Rule::in([$subject?->email])],
            'confirm_purge' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm_email.required' => 'Type the user email to confirm the permanent purge.',
            'confirm_email.in' => 'Type the exact user email to confirm the permanent purge.',
            'confirm_purge.accepted' => 'Confirm that requests, files, and magic links will be removed.',
        ];
    }
}
