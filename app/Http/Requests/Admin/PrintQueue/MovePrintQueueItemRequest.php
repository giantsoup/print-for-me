<?php

namespace App\Http\Requests\Admin\PrintQueue;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovePrintQueueItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['direction' => ['required', Rule::in(['up', 'down', 'top'])]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['direction.required' => 'Choose a move direction.', 'direction.in' => 'Choose an available move direction.'];
    }
}
