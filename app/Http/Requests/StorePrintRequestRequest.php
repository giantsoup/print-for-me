<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StorePrintRequestRequest extends FormRequest
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
            'files' => ['sometimes', 'array', 'max:10'],
            'files.*' => ['file', 'max:51200', 'mimes:stl,3mf,obj,f3d,f3z,step,stp,iges,igs'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $files = $this->file('files', []);
            $hasFiles = is_array($files) && count($files) > 0;
            $hasUrl = filled($this->input('source_url'));

            if (! $hasFiles && ! $hasUrl) {
                $validator->errors()->add('files', 'At least one source is required: provide a source URL or upload at least one file.');
            }

            // Aggregate size validation (50 MB total)
            $totalBytes = 0;
            foreach ($files as $f) {
                $totalBytes += $f->getSize();
            }
            if ($totalBytes > 50 * 1024 * 1024) {
                $validator->errors()->add('files', 'Total upload size exceeds 50 MB.');
            }
        });
    }
}
