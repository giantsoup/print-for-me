<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class CompletePrintRequestRequest extends FormRequest
{
    public const int MAX_PHOTOS = 5;

    public const int MAX_PHOTO_KILOBYTES = 12 * 1024;

    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin ?? false);
    }

    public function rules(): array
    {
        return [
            'photos' => ['sometimes', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => [
                'file',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'])->max(self::MAX_PHOTO_KILOBYTES),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'photos.array' => 'Completion photos must be uploaded as a list of images.',
            'photos.max' => 'You may upload up to '.self::MAX_PHOTOS.' completion photos.',
            'photos.*.file' => 'Each completion photo must be a valid upload.',
            'photos.*.types' => 'Upload JPG, PNG, WebP, HEIC, or HEIF photos.',
            'photos.*.max' => 'Each completion photo must be 12 MB or smaller.',
        ];
    }
}
