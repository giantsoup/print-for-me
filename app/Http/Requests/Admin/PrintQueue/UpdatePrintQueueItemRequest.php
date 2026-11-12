<?php

namespace App\Http\Requests\Admin\PrintQueue;

use App\Enums\PrintRequestStatus;
use App\Models\PrintQueueItem;
use App\Models\PrintRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePrintQueueItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $requestRules = ['nullable', 'integer'];
        if ($this->requestLinkChanged()) {
            $requestRules[] = Rule::exists('print_requests', 'id')->whereNull('deleted_at');
        }

        return [
            'title' => ['required', 'string', 'max:160'],
            'for_whom' => ['nullable', 'string', 'max:120'],
            'needed_by_date' => ['nullable', 'date_format:Y-m-d'],
            'filament' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'print_request_id' => $requestRules,
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->requestLinkChanged() || $validator->errors()->has('print_request_id') || ! $this->filled('print_request_id')) {
                return;
            }
            $request = PrintRequest::query()->find($this->integer('print_request_id'));
            if ($request?->status === PrintRequestStatus::COMPLETE) {
                $validator->errors()->add('print_request_id', 'Completed requests cannot be queued.');
            }
            $item = $this->route('print_queue_item');
            $linked = PrintQueueItem::query()->active()->where('print_request_id', $request?->id)
                ->when($item instanceof PrintQueueItem, fn (Builder $query): Builder => $query->whereKeyNot($item->id))->exists();
            if ($linked) {
                $validator->errors()->add('print_request_id', 'This request is already in the queue.');
            }
        }];
    }

    private function requestLinkChanged(): bool
    {
        $item = $this->route('print_queue_item');
        $requestId = $this->filled('print_request_id') ? $this->integer('print_request_id') : null;

        return ! $item instanceof PrintQueueItem || $requestId !== $item->print_request_id;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Give this queue item a title.',
            'title.string' => 'Enter a title as text.',
            'title.max' => 'Keep the title to 160 characters.',
            'for_whom.string' => 'Enter a name as text.',
            'for_whom.max' => 'Keep the name to 120 characters.',
            'needed_by_date.date_format' => 'Enter the needed-by date as MM/DD/YYYY.',
            'filament.string' => 'Enter filament details as text.',
            'filament.max' => 'Keep filament details to 160 characters.',
            'notes.string' => 'Enter notes as text.',
            'notes.max' => 'Keep notes to 5000 characters.',
            'print_request_id.integer' => 'Choose a request from the list.',
            'print_request_id.exists' => 'Choose a request from the list.',
        ];
    }
}
