<?php

namespace App\Http\Controllers;

use App\Enums\PrintRequestStatus;
use App\Http\Requests\Admin\CompletePrintRequestRequest;
use App\Http\Requests\StorePrintRequestRequest;
use App\Http\Requests\UpdatePrintRequestRequest;
use App\Models\PrintRequest;
use App\Notifications\NewPrintRequestNotification;
use App\Notifications\PendingRequestCanceledByUserNotification;
use App\Services\PrintRequests\DeleteStoredAssets;
use App\Services\SourcePreviews\SourcePreviewDomainManager;
use App\Support\FetchPrintRequestSourcePreview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PrintRequestController extends Controller
{
    public function __construct(
        public SourcePreviewDomainManager $sourcePreviewDomains,
        public DeleteStoredAssets $deleteStoredAssets,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = (bool) ($user->is_admin ?? false);
        $status = (string) $request->query('status', '');
        $baseQuery = PrintRequest::query();

        if (! $isAdmin) {
            $baseQuery->where('user_id', $user->id);
        }

        $statusCounts = ['all' => (clone $baseQuery)->count()];

        foreach (PrintRequestStatus::all() as $value) {
            $statusCounts[$value] = (clone $baseQuery)->where('status', $value)->count();
        }

        $query = (clone $baseQuery)
            ->with(['files', 'user:id,name,email'])
            ->withCount('files')
            ->latest();

        if (in_array($status, PrintRequestStatus::all(), true)) {
            $query->where('status', $status);
        }

        $data = $query->paginate(20)
            ->through(function (PrintRequest $printRequest) use ($isAdmin) {
                return [
                    ...$printRequest->toArray(),
                    'availableStatusActions' => $printRequest->availableStatusActions($isAdmin),
                ];
            })
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return Inertia::render('prints/Index', [
            'items' => $data,
            'isAdmin' => $isAdmin,
            'filters' => [
                'status' => $status,
            ],
            'statuses' => PrintRequestStatus::all(),
            'statusCounts' => $statusCounts,
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('prints/Create', [
            'constraints' => [
                'maxFiles' => 10,
                'maxTotalBytes' => 50 * 1024 * 1024,
                'allowedExtensions' => ['stl', '3mf', 'obj', 'f3d', 'f3z', 'step', 'stp', 'iges', 'igs'],
            ],
        ]);
    }

    public function show(Request $request, PrintRequest $print_request)
    {
        $this->authorize('view', $print_request);

        $print_request->load(['files', 'completionPhotos', 'user:id,name,email']);
        $completionPhotos = $print_request->completionPhotos
            ->map(fn ($photo) => [
                'id' => $photo->id,
                'original_name' => $photo->original_name,
                'mime_type' => $photo->mime_type,
                'size_bytes' => $photo->size_bytes,
                'width' => $photo->width,
                'height' => $photo->height,
            ])
            ->values();
        $print_request->unsetRelation('completionPhotos');

        $timeline = collect([
            [
                'key' => 'created',
                'label' => 'Submitted',
                'description' => 'Your request entered the print queue.',
                'at' => $print_request->created_at?->toIso8601String(),
            ],
            [
                'key' => 'accepted',
                'label' => 'Accepted',
                'description' => 'The print has been approved for production.',
                'at' => $print_request->accepted_at?->toIso8601String(),
            ],
            [
                'key' => 'reverted',
                'label' => 'Returned to queue',
                'description' => 'The request was sent back to pending for another pass.',
                'at' => $print_request->reverted_at?->toIso8601String(),
            ],
            [
                'key' => 'completed',
                'label' => 'Completed',
                'description' => 'The print is finished and ready.',
                'at' => $print_request->completed_at?->toIso8601String(),
            ],
        ])->filter(fn (array $item) => filled($item['at']))->values();

        if ($request->wantsJson()) {
            return response()->json($print_request);
        }

        $user = $request->user();

        return Inertia::render('prints/Show', [
            'printRequest' => $print_request,
            'completionPhotos' => $completionPhotos,
            'sourcePreviewPolicy' => $print_request->source_url
                ? ($this->sourcePreviewDomains->isAutomaticFetchAllowed($print_request->source_url) ? 'allow' : 'block')
                : null,
            'can' => [
                'update' => $user ? $user->can('update', $print_request) : false,
                'delete' => $user ? $user->can('delete', $print_request) : false,
                'restore' => $user ? $user->can('restore', $print_request) : false,
                'isAdmin' => (bool) ($user->is_admin ?? false),
            ],
            'availableStatusActions' => $print_request->availableStatusActions((bool) ($user?->is_admin ?? false)),
            'timeline' => $timeline,
            'constraints' => [
                'maxFiles' => 10,
                'maxTotalBytes' => 50 * 1024 * 1024,
                'allowedExtensions' => ['stl', '3mf', 'obj', 'f3d', 'f3z', 'step', 'stp', 'iges', 'igs'],
            ],
            'completionPhotoConstraints' => [
                'maxFiles' => CompletePrintRequestRequest::MAX_PHOTOS,
            ],
        ]);
    }

    public function store(StorePrintRequestRequest $request)
    {
        $user = $request->user();
        $sourceUrl = $this->normalizedSourceUrl($request->input('source_url'));

        $printRequest = new PrintRequest;
        $printRequest->user_id = $user->id;
        $printRequest->status = PrintRequestStatus::PENDING;
        $printRequest->source_url = $sourceUrl;
        $printRequest->instructions = $request->input('instructions');
        $printRequest->save();

        $this->attachFiles($printRequest, $request->file('files', []));
        $this->dispatchSourcePreviewRefresh($printRequest, $sourceUrl);

        // Notify admin of new print request (queued mail)
        $adminEmail = (string) config('prints.admin_email');
        if ($adminEmail) {
            Notification::route('mail', $adminEmail)->notify(new NewPrintRequestNotification($printRequest));
        }

        if ($request->wantsJson()) {
            return response()->json($printRequest->load('files'), 201);
        }

        return redirect()->route('print-requests.show', $printRequest)->with('status', 'Print request created.');
    }

    public function update(UpdatePrintRequestRequest $request, PrintRequest $print_request)
    {
        $this->authorize('update', $print_request);

        $sourceUrl = $this->normalizedSourceUrl($request->input('source_url', $print_request->source_url));
        $sourceChanged = $sourceUrl !== $print_request->source_url;

        $print_request->fill([
            'source_url' => $sourceUrl,
            'instructions' => $request->input('instructions', $print_request->instructions),
        ]);

        if ($sourceChanged) {
            $print_request->forceFill([
                'source_preview' => null,
                'source_preview_fetched_at' => null,
                'source_preview_failed_at' => null,
            ]);
        }

        $print_request->save();

        // Remove selected files
        $removeIds = collect($request->input('remove_file_ids', []))->filter()->all();
        if (! empty($removeIds)) {
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
        $this->dispatchSourcePreviewRefresh($print_request, $sourceUrl, $sourceChanged);

        if ($request->wantsJson()) {
            return response()->json($print_request->load('files'));
        }

        return redirect()->route('print-requests.show', $print_request)->with('status', 'Print request updated.');
    }

    public function destroy(Request $request, PrintRequest $print_request)
    {
        $this->authorize('delete', $print_request);

        $user = $request->user();
        $isAdmin = (bool) ($user->is_admin ?? false);
        $wasPending = $print_request->status === PrintRequestStatus::PENDING;

        $print_request->delete();

        // Notify admin only when a user (non-admin) cancels their pending request
        if (! $isAdmin && $wasPending) {
            $adminEmail = (string) config('prints.admin_email');
            if ($adminEmail) {
                Notification::route('mail', $adminEmail)->notify(new PendingRequestCanceledByUserNotification($print_request));
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'deleted']);
        }

        return redirect()->route('print-requests.index')->with('status', 'Print request deleted.');
    }

    public function restore(Request $request, PrintRequest $print_request)
    {
        $this->authorize('restore', $print_request);

        $print_request->restore();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'restored']);
        }

        return back()->with('status', 'Print request restored.');
    }

    public function forceDestroy(Request $request, $id)
    {
        // Find including soft-deleted rows
        $print_request = PrintRequest::withTrashed()->findOrFail($id);

        // Authorize per policy
        $this->authorize('forceDelete', $print_request);

        $this->deleteStoredAssets->handle($print_request);

        $print_request->files()->delete();
        $print_request->completionPhotos()->delete();

        $print_request->forceDelete();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'force-deleted']);
        }

        return redirect()->route('print-requests.index')->with('status', 'Print request permanently deleted.');
    }

    private function attachFiles(PrintRequest $printRequest, array $files): void
    {
        foreach ($files as $file) {
            if (! $file) {
                continue;
            }
            // Compute SHA-256 before moving the file
            $sha256 = hash_file('sha256', $file->getRealPath());

            // Skip duplicates within same request
            if ($printRequest->files()->where('sha256', $sha256)->exists()) {
                continue;
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
            $dir = 'prints/'.now()->format('Y').'/'.now()->format('m');
            $filename = (string) Str::uuid().'.'.$ext;

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
        if (! $user) {
            abort(401);
        }
        if ($user->id !== $printRequest->user_id && ! ($user->is_admin ?? false)) {
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

    private function dispatchSourcePreviewRefresh(PrintRequest $printRequest, ?string $sourceUrl, bool $sourceChanged = false): void
    {
        if (blank($sourceUrl)) {
            return;
        }

        $this->sourcePreviewDomains->registerSeenPrintRequest($printRequest);

        if (! $sourceChanged && filled($printRequest->source_preview_failed_at)) {
            return;
        }

        if (! $sourceChanged && filled($printRequest->source_preview_fetched_at) && filled($printRequest->source_preview)) {
            return;
        }

        if (! $this->sourcePreviewDomains->isAutomaticFetchAllowed($sourceUrl)) {
            $printRequest->forceFill([
                'source_preview' => null,
                'source_preview_fetched_at' => null,
                'source_preview_failed_at' => now(),
            ])->save();

            return;
        }

        FetchPrintRequestSourcePreview::dispatch($printRequest->id, $sourceUrl);
    }

    private function normalizedSourceUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
