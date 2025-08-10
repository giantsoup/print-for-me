<?php

namespace App\Http\Requests;

use App\Models\PrintRequest;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePrintRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'source_url' => ['nullable', 'url', 'max:2048'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'files' => ['sometimes', 'array'],
            'files.*' => ['file', 'max:51200', 'mimes:stl,3mf,obj,f3d,f3z,step,stp,iges,igs'],
            'remove_file_ids' => ['sometimes', 'array'],
            'remove_file_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var PrintRequest|null $printRequest */
            $printRequest = $this->route('print_request') ?? $this->route('printRequest');

            $existingCount = $printRequest?->files()->count() ?? 0;
            $existingSize = $printRequest?->files()->sum('size_bytes') ?? 0;

            $files = $this->file('files', []);
            $newCount = is_array($files) ? count($files) : 0;
            $newSize = 0;
            foreach ($files as $f) {
                $newSize += $f->getSize();
            }

            $removeIds = collect($this->input('remove_file_ids', []))->filter()->all();
            if (!empty($removeIds) && $printRequest) {
                $removing = $printRequest->files()->whereIn('id', $removeIds)->get();
                $existingCount -= $removing->count();
                $existingSize -= $removing->sum('size_bytes');
            }

            // Enforce file count max 10 considering existing + new
            if (($existingCount + $newCount) > 10) {
                $validator->errors()->add('files', 'You may not attach more than 10 files to a request.');
            }

            // Aggregate size: existing + new must be <= 50 MB
            if (($existingSize + $newSize) > 50 * 1024 * 1024) {
                $validator->errors()->add('files', 'Total upload size exceeds 50 MB.');
            }

            // At least one source overall after updates
            $hasFilesAfter = ($existingCount + $newCount) > 0;
            $candidateUrl = $this->input('source_url', $printRequest?->source_url);
            $hasUrlAfter = filled($candidateUrl);

            if (! $hasFilesAfter && ! $hasUrlAfter) {
                $validator->errors()->add('source_url', 'At least one source is required: provide a source URL or upload at least one file.');
            }
        });
    }
}
