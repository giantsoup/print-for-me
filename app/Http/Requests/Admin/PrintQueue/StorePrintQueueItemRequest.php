<?php

namespace App\Http\Requests\Admin\PrintQueue;

use Illuminate\Contracts\Validation\ValidationRule;

class StorePrintQueueItemRequest extends UpdatePrintQueueItemRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [...parent::rules(),
            'add_to_top' => ['sometimes', 'boolean'],
            'plates' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'plates.*.name' => ['required', 'string', 'max:120'],
            'plates.*.estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'plates.*.filament' => ['nullable', 'string', 'max:160'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [...parent::messages(),
            'add_to_top.boolean' => 'Choose whether to add this item to the top.',
            'plates.required' => 'Add at least one plate.',
            'plates.array' => 'Add plates as a list.',
            'plates.list' => 'Add plates as a list.',
            'plates.min' => 'Add at least one plate.',
            'plates.max' => 'Add up to 50 plates.',
            'plates.*.name.required' => 'Name every plate.',
            'plates.*.name.string' => 'Enter a plate name as text.',
            'plates.*.name.max' => 'Keep plate names to 120 characters.',
            'plates.*.estimated_minutes.integer' => 'Estimated time must be whole minutes.',
            'plates.*.filament.string' => 'Enter filament details as text.',
            'plates.*.filament.max' => 'Keep filament details to 160 characters.',
            'plates.*.estimated_minutes.min' => 'Estimated time must be between 1 minute and 7 days.',
            'plates.*.estimated_minutes.max' => 'Estimated time must be between 1 minute and 7 days.',
        ];
    }
}
