<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrintRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('needed_by_date')) {
            return;
        }

        $value = $this->input('needed_by_date');

        if (! is_string($value)) {
            return;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            $this->merge(['needed_by_date' => null]);

            return;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
            $this->merge(['needed_by_date' => $trimmed]);

            return;
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $trimmed) !== 1) {
            $this->merge(['needed_by_date' => $trimmed]);

            return;
        }

        [$month, $day, $year] = array_map('intval', explode('/', $trimmed));

        $this->merge([
            'needed_by_date' => checkdate($month, $day, $year)
                ? sprintf('%04d-%02d-%02d', $year, $month, $day)
                : $trimmed,
        ]);
    }

    public function rules(): array
    {
        return [
            'source_url' => ['nullable', 'url', 'max:2048'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'needed_by_date' => ['nullable', 'date_format:Y-m-d'],
            'files' => ['sometimes', 'array', 'max:10'],
            'files.*' => ['file', 'max:51200', 'mimes:stl,3mf,obj,f3d,f3z,step,stp,iges,igs'],
        ];
    }

    public function messages(): array
    {
        return [
            'needed_by_date.date_format' => 'Enter the needed-by date as MM/DD/YYYY.',
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
