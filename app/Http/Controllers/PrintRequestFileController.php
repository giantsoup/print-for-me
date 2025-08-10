<?php

namespace App\Http\Controllers;

use App\Models\PrintRequest;
use App\Models\PrintRequestFile;
use Illuminate\Support\Facades\Storage;

class PrintRequestFileController extends Controller
{
    public function download(PrintRequest $print_request, PrintRequestFile $file)
    {
        $this->authorize('download', $print_request);

        if ($file->print_request_id !== $print_request->id) {
            abort(404);
        }

        if (! Storage::disk($file->disk)->exists($file->path)) {
            abort(404);
        }

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }
}
