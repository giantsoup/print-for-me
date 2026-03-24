<?php

namespace App\Services\SourcePreviews;

use App\Models\PrintRequest;
use App\Support\SourcePreviewFetcher;

class AttemptSourcePreview
{
    public function __construct(
        public SourcePreviewFetcher $fetcher,
        public SourcePreviewDomainManager $domains,
    ) {}

    public function handle(PrintRequest $printRequest, bool $ignoreAutomaticPolicy = false): ?array
    {
        $sourceUrl = $printRequest->source_url;

        if (blank($sourceUrl)) {
            return null;
        }

        $this->domains->registerSeenPrintRequest($printRequest);

        if (! $ignoreAutomaticPolicy && ! $this->domains->isAutomaticFetchAllowed($sourceUrl)) {
            $this->markRequestAsUnavailable($printRequest);

            return null;
        }

        $preview = $this->fetcher->fetch($sourceUrl);

        $printRequest->forceFill([
            'source_preview' => $preview,
            'source_preview_fetched_at' => $preview ? now() : null,
            'source_preview_failed_at' => $preview ? null : now(),
        ])->save();

        $this->domains->recordAttempt($printRequest, $preview);

        return $preview;
    }

    public function handleUrl(string $sourceUrl, bool $ignoreAutomaticPolicy = false): ?array
    {
        if (blank($sourceUrl)) {
            return null;
        }

        if (! $ignoreAutomaticPolicy && ! $this->domains->isAutomaticFetchAllowed($sourceUrl)) {
            $this->domains->recordAttemptForUrl($sourceUrl, null);

            return null;
        }

        $preview = $this->fetcher->fetch($sourceUrl);

        $this->domains->recordAttemptForUrl($sourceUrl, $preview);

        return $preview;
    }

    private function markRequestAsUnavailable(PrintRequest $printRequest): void
    {
        $printRequest->forceFill([
            'source_preview' => null,
            'source_preview_fetched_at' => null,
            'source_preview_failed_at' => now(),
        ])->save();
    }
}
