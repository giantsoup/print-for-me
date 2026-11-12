<?php

namespace App\Http\Requests\Admin\PrintQueue;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class MovePrintQueuePlateRequest extends MovePrintQueueItemRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['direction' => ['required', Rule::in(['up', 'down'])]];
    }
}
