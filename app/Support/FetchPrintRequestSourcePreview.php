<?php

namespace App\Support;

use App\Models\PrintRequest;
use App\Services\SourcePreviews\AttemptSourcePreview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchPrintRequestSourcePreview implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(
        public int $printRequestId,
        public string $sourceUrl,
    ) {}

    public function handle(AttemptSourcePreview $attemptSourcePreview): void
    {
        $printRequest = PrintRequest::query()->find($this->printRequestId);

        if (! $printRequest || blank($printRequest->source_url) || $printRequest->source_url !== $this->sourceUrl) {
            return;
        }

        $attemptSourcePreview->handle($printRequest);
    }
}
