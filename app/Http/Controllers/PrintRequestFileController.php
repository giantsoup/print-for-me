<?php

namespace App\Http\Controllers;

use App\Models\PrintRequest;
use App\Models\PrintRequestFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PrintRequestFileController extends Controller
{
    public function download(PrintRequest $print_request, PrintRequestFile $file)
    {
        $this->authorizeOwnerOrAdmin($print_request);

        if ($file->print_request_id !== $print_request->id) {
            abort(404);
        }

        if (! Storage::disk($file->disk)->exists($file->path)) {
            abort(404);
        }

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    private function authorizeOwnerOrAdmin(PrintRequest $printRequest): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }
        if ($user->id !== $printRequest->user_id && !($user->is_admin ?? false)) {
            abort(403);
        }
    }
}
