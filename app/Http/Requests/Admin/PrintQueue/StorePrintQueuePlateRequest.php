<?php

namespace App\Http\Requests\Admin\PrintQueue;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePrintQueuePlateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'filament' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Name every plate.',
            'name.string' => 'Enter a plate name as text.',
            'name.max' => 'Keep plate names to 120 characters.',
            'estimated_minutes.integer' => 'Estimated time must be whole minutes.',
            'estimated_minutes.min' => 'Estimated time must be between 1 minute and 7 days.',
            'estimated_minutes.max' => 'Estimated time must be between 1 minute and 7 days.',
            'filament.string' => 'Enter filament details as text.',
            'filament.max' => 'Keep filament details to 160 characters.',
            'notes.string' => 'Enter notes as text.',
            'notes.max' => 'Keep notes to 2000 characters.',
        ];
    }
}
