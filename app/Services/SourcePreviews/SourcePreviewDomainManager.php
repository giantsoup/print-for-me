<?php

namespace App\Services\SourcePreviews;

use App\Enums\SourcePreviewFetchPolicy;
use App\Models\PrintRequest;
use App\Models\SourcePreviewDomain;

class SourcePreviewDomainManager
{
    /**
     * @return array<string, array{label: string, policy: SourcePreviewFetchPolicy}>
     */
    public function definitions(): array
    {
        return [
            'makerworld.com' => [
                'label' => 'MakerWorld',
                'policy' => SourcePreviewFetchPolicy::Block,
            ],
            'printables.com' => [
                'label' => 'Printables',
                'policy' => SourcePreviewFetchPolicy::Allow,
            ],
            'thingiverse.com' => [
                'label' => 'Thingiverse',
                'policy' => SourcePreviewFetchPolicy::Allow,
            ],
            'thangs.com' => [
                'label' => 'Thangs',
                'policy' => SourcePreviewFetchPolicy::Allow,
            ],
            'cults3d.com' => [
                'label' => 'Cults3D',
                'policy' => SourcePreviewFetchPolicy::Allow,
            ],
            'myminifactory.com' => [
                'label' => 'MyMiniFactory',
                'policy' => SourcePreviewFetchPolicy::Allow,
            ],
            'pinshape.com' => [
                'label' => 'Pinshape',
                'policy' => SourcePreviewFetchPolicy::Allow,
            ],
        ];
    }

    public function syncDefaults(): void
    {
        foreach ($this->definitions() as $domain => $definition) {
            $record = SourcePreviewDomain::query()->firstOrNew(['domain' => $domain]);

            if (! $record->exists) {
                $record->policy = $definition['policy'];
            }

            if (blank($record->label)) {
                $record->label = $definition['label'];
            }

            $record->save();
        }
    }

    public function extractDomain(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./i', '', strtolower($host)) ?: strtolower($host);
    }

    public function definitionForDomain(string $domain): ?array
    {
        return $this->definitions()[$domain] ?? null;
    }

    public function defaultPolicyForDomain(string $domain): SourcePreviewFetchPolicy
    {
        return $this->definitionForDomain($domain)['policy'] ?? SourcePreviewFetchPolicy::Allow;
    }

    public function defaultLabelForDomain(string $domain): string
    {
        return $this->definitionForDomain($domain)['label'] ?? $domain;
    }

    public function isAutomaticFetchAllowed(?string $url): bool
    {
        $domain = $this->extractDomain($url);

        if ($domain === null) {
            return false;
        }

        $record = SourcePreviewDomain::query()->where('domain', $domain)->first();
        $policy = $record?->policy ?? $this->defaultPolicyForDomain($domain);

        return $policy === SourcePreviewFetchPolicy::Allow;
    }

    public function registerSeenPrintRequest(PrintRequest $printRequest): ?SourcePreviewDomain
    {
        $domain = $this->extractDomain($printRequest->source_url);

        if ($domain === null) {
            return null;
        }

        $record = SourcePreviewDomain::query()->firstOrNew(['domain' => $domain]);

        if (! $record->exists) {
            $record->policy = $this->defaultPolicyForDomain($domain);
        }

        $record->label = $record->label ?: $this->defaultLabelForDomain($domain);
        $record->last_seen_print_request_id = $printRequest->id;
        $record->last_seen_url = $printRequest->source_url;
        $record->last_seen_at = now();
        $record->save();

        return $record;
    }

    public function recordAttempt(PrintRequest $printRequest, ?array $preview): ?SourcePreviewDomain
    {
        $record = $this->registerSeenPrintRequest($printRequest);

        if (! $record) {
            return null;
        }

        $record->last_attempted_at = now();
        $record->last_attempt_status = $preview ? 'success' : 'failure';

        if ($preview) {
            $record->last_success_at = now();
        } else {
            $record->last_failure_at = now();
        }

        $record->save();

        return $record;
    }
}
