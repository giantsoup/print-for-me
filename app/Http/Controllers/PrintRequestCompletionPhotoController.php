<?php

namespace App\Http\Controllers;

use App\Models\PrintRequest;
use App\Models\PrintRequestCompletionPhoto;
use Illuminate\Support\Facades\Storage;

class PrintRequestCompletionPhotoController extends Controller
{
    public function show(PrintRequest $print_request, PrintRequestCompletionPhoto $photo)
    {
        $this->authorize('view', $print_request);

        if ($photo->print_request_id !== $print_request->id) {
            abort(404);
        }

        if (! Storage::disk($photo->disk)->exists($photo->path)) {
            abort(404);
        }

        return Storage::disk($photo->disk)->response($photo->path, $photo->original_name, [
            'Content-Disposition' => 'inline; filename="'.$photo->original_name.'"',
            'Content-Type' => $photo->mime_type ?? 'image/webp',
        ]);
    }
}
