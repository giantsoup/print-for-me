<?php

namespace App\Services\AdminUsers;

use App\Models\MagicLoginToken;
use App\Models\PrintRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeUser
{
    public function __construct(
        private readonly AdminUserEventLogger $logger,
    ) {}

    public function __invoke(User $actor, User $subject): void
    {
        $fileRecords = PrintRequest::withTrashed()
            ->where('user_id', $subject->id)
            ->with(['files'])
            ->get()
            ->flatMap(fn (PrintRequest $printRequest) => $printRequest->files);

        foreach ($fileRecords as $file) {
            if ($file->disk && $file->path && Storage::disk($file->disk)->exists($file->path)) {
                Storage::disk($file->disk)->delete($file->path);
            }
        }

        DB::transaction(function () use ($actor, $subject): void {
            $this->logger->log($actor, $subject, 'user_purged');

            PrintRequest::withTrashed()
                ->where('user_id', $subject->id)
                ->get()
                ->each(function (PrintRequest $printRequest): void {
                    $printRequest->files()->delete();
                    $printRequest->forceDelete();
                });

            MagicLoginToken::query()->where('email', $subject->email)->delete();

            $subject->forceDelete();
        });
    }
}
