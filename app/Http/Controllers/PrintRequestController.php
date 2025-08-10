<?php

namespace App\Http\Controllers;

use App\Enums\PrintRequestStatus;
use App\Http\Requests\StorePrintRequestRequest;
use App\Http\Requests\UpdatePrintRequestRequest;
use App\Models\PrintRequest;
use App\Models\PrintRequestFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrintRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = PrintRequest::query()->with('files')->latest();

        if (!($user->is_admin ?? false)) {
            $query->where('user_id', $user->id);
        }

        $data = $query->paginate(20);

        // Always respond with JSON for now (UI will be added later)
        return response()->json($data);
    }

    public function show(Request $request, PrintRequest $print_request)
    {
        $this->authorizeOwnerOrAdmin($print_request);

        $print_request->load('files');

        // Always respond with JSON for now (UI will be added later)
        return response()->json($print_request);
    }

    public function store(StorePrintRequestRequest $request)
    {
        $user = $request->user();

        $printRequest = new PrintRequest();
        $printRequest->user_id = $user->id;
        $printRequest->status = PrintRequestStatus::PENDING;
        $printRequest->source_url = $request->input('source_url');
        $printRequest->instructions = $request->input('instructions');
        $printRequest->save();

        $this->attachFiles($printRequest, $request->file('files', []));

        if ($request->wantsJson()) {
            return response()->json($printRequest->load('files'), 201);
        }

        return redirect()->route('print-requests.show', $printRequest)->with('status', 'Print request created.');
    }

    public function update(UpdatePrintRequestRequest $request, PrintRequest $print_request)
    {
        $this->authorizeOwnerOrAdmin($print_request);
        $this->ensureEditableByUser($print_request, $request->user());

        $print_request->fill([
            'source_url' => $request->input('source_url', $print_request->source_url),
            'instructions' => $request->input('instructions', $print_request->instructions),
        ]);
        $print_request->save();

        // Remove selected files
        $removeIds = collect($request->input('remove_file_ids', []))->filter()->all();
        if (!empty($removeIds)) {
            $files = $print_request->files()->whereIn('id', $removeIds)->get();
            foreach ($files as $file) {
                if ($file->disk && $file->path) {
                    Storage::disk($file->disk)->delete($file->path);
                }
                $file->delete();
            }
        }

        // Attach new files
        $this->attachFiles($print_request, $request->file('files', []));

        if ($request->wantsJson()) {
            return response()->json($print_request->load('files'));
        }

        return redirect()->route('print-requests.show', $print_request)->with('status', 'Print request updated.');
    }

    public function destroy(Request $request, PrintRequest $print_request)
    {
        $this->authorizeOwnerOrAdmin($print_request);
        $this->ensureEditableByUser($print_request, $request->user());

        $print_request->delete();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'deleted']);
        }

        return redirect()->route('print-requests.index')->with('status', 'Print request deleted.');
    }

    private function attachFiles(PrintRequest $printRequest, array $files): void
    {
        foreach ($files as $file) {
            if (!$file) {
                continue;
            }
            // Compute SHA-256 before moving the file
            $sha256 = hash_file('sha256', $file->getRealPath());

            // Skip duplicates within same request
            if ($printRequest->files()->where('sha256', $sha256)->exists()) {
                continue;
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
            $dir = 'prints/' . now()->format('Y') . '/' . now()->format('m');
            $filename = (string) Str::uuid() . '.' . $ext;

            // Store on private local disk
            $storedPath = $file->storeAs($dir, $filename, 'local');

            $printRequest->files()->create([
                'disk' => 'local',
                'path' => $storedPath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'sha256' => $sha256,
            ]);
        }
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

    private function ensureEditableByUser(PrintRequest $printRequest, $user): void
    {
        if ($user && ($user->is_admin ?? false)) {
            return; // admin can edit any status
        }
        if ($printRequest->status !== PrintRequestStatus::PENDING) {
            abort(403, 'Only admins can modify non-pending requests.');
        }
    }
}
