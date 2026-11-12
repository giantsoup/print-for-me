<?php

namespace App\Services\AdminUsers;

use App\Models\MagicLoginToken;
use App\Models\PrintRequest;
use App\Models\User;
use App\Services\PrintQueue\DetachPrintRequestFromQueue;
use App\Services\PrintRequests\DeleteStoredAssets;
use Illuminate\Support\Facades\DB;

class PurgeUser
{
    public function __construct(
        private readonly AdminUserEventLogger $logger,
        private readonly DeleteStoredAssets $deleteStoredAssets,
        private readonly DetachPrintRequestFromQueue $detachPrintRequestFromQueue,
    ) {}

    public function __invoke(User $actor, User $subject): void
    {
        $printRequests = PrintRequest::withTrashed()
            ->where('user_id', $subject->id)
            ->with(['files', 'completionPhotos'])
            ->get();

        foreach ($printRequests as $printRequest) {
            $this->deleteStoredAssets->handle($printRequest);
        }

        DB::transaction(function () use ($actor, $subject): void {
            $this->logger->log($actor, $subject, 'user_purged');

            PrintRequest::withTrashed()
                ->where('user_id', $subject->id)
                ->get()
                ->each(function (PrintRequest $printRequest): void {
                    $this->detachPrintRequestFromQueue->handle($printRequest);
                    $printRequest->files()->delete();
                    $printRequest->completionPhotos()->delete();
                    $printRequest->forceDelete();
                });

            MagicLoginToken::query()->where('email', $subject->email)->delete();

            $subject->forceDelete();
        });
    }
}
