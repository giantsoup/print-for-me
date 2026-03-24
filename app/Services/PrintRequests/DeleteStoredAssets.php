<?php

namespace App\Services\PrintRequests;

use App\Models\PrintRequest;
use Illuminate\Support\Facades\Storage;

class DeleteStoredAssets
{
    public function handle(PrintRequest $printRequest): void
    {
        $printRequest->loadMissing([
            'files:id,print_request_id,disk,path',
            'completionPhotos:id,print_request_id,disk,path',
        ]);

        foreach ($printRequest->files as $file) {
            if ($file->disk && $file->path && Storage::disk($file->disk)->exists($file->path)) {
                Storage::disk($file->disk)->delete($file->path);
            }
        }

        foreach ($printRequest->completionPhotos as $photo) {
            if ($photo->disk && $photo->path && Storage::disk($photo->disk)->exists($photo->path)) {
                Storage::disk($photo->disk)->delete($photo->path);
            }
        }
    }
}
