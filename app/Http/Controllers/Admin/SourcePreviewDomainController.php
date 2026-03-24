<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SourcePreviewFetchPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SourcePreviewDomains\AttemptSourcePreviewDomainRequest;
use App\Http\Requests\Admin\SourcePreviewDomains\UpdateSourcePreviewDomainRequest;
use App\Models\PrintRequest;
use App\Models\SourcePreviewDomain;
use App\Services\SourcePreviews\AttemptSourcePreview;
use App\Services\SourcePreviews\SourcePreviewDomainManager;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SourcePreviewDomainController extends Controller
{
    public function __construct(
        public SourcePreviewDomainManager $domains,
        public AttemptSourcePreview $attemptSourcePreview,
    ) {}

    public function index(): Response
    {
        $this->domains->syncDefaults();

        $definitions = $this->domains->definitions();
        $items = SourcePreviewDomain::query()
            ->orderByRaw('case when last_seen_at is null then 1 else 0 end')
            ->orderByDesc('last_seen_at')
            ->orderBy('label')
            ->get()
            ->map(function (SourcePreviewDomain $domain) use ($definitions): array {
                $definition = $definitions[$domain->domain] ?? null;

                return [
                    'id' => $domain->id,
                    'label' => $domain->label,
                    'domain' => $domain->domain,
                    'policy' => $domain->policy?->value ?? SourcePreviewFetchPolicy::Allow->value,
                    'recommended_policy' => $definition['policy']->value ?? SourcePreviewFetchPolicy::Allow->value,
                    'last_seen_url' => $domain->last_seen_url,
                    'last_seen_at' => $domain->last_seen_at?->toIso8601String(),
                    'last_attempted_at' => $domain->last_attempted_at?->toIso8601String(),
                    'last_attempt_status' => $domain->last_attempt_status,
                    'last_success_at' => $domain->last_success_at?->toIso8601String(),
                    'last_failure_at' => $domain->last_failure_at?->toIso8601String(),
                    'can_attempt' => filled($domain->last_seen_url) && filled($domain->last_seen_print_request_id),
                    'is_seeded' => $definition !== null,
                ];
            })
            ->values();

        return Inertia::render('admin/source-previews/Index', [
            'domains' => $items,
            'summary' => [
                'allowed' => $items->where('policy', SourcePreviewFetchPolicy::Allow->value)->count(),
                'blocked' => $items->where('policy', SourcePreviewFetchPolicy::Block->value)->count(),
                'tracked' => $items->count(),
            ],
        ]);
    }

    public function update(UpdateSourcePreviewDomainRequest $request, SourcePreviewDomain $sourcePreviewDomain): RedirectResponse
    {
        $sourcePreviewDomain->update($request->validated());

        return back()->with('status', "Preview policy updated for {$sourcePreviewDomain->label}.");
    }

    public function attempt(SourcePreviewDomain $sourcePreviewDomain): RedirectResponse
    {
        $printRequest = PrintRequest::withTrashed()->find($sourcePreviewDomain->last_seen_print_request_id);

        if (
            ! $printRequest
            || blank($printRequest->source_url)
            || $this->domains->extractDomain($printRequest->source_url) !== $sourcePreviewDomain->domain
        ) {
            return back()->with('status', "No recent request URL is available for {$sourcePreviewDomain->label}.");
        }

        $preview = $this->attemptSourcePreview->handle($printRequest, ignoreAutomaticPolicy: true);

        return back()->with(
            'status',
            $preview
                ? "Preview fetch succeeded for {$sourcePreviewDomain->label}."
                : "Preview fetch failed for {$sourcePreviewDomain->label}.",
        );
    }

    public function attemptUrl(AttemptSourcePreviewDomainRequest $request, SourcePreviewDomain $sourcePreviewDomain): RedirectResponse
    {
        $testUrl = (string) $request->validated('url');

        if ($this->domains->extractDomain($testUrl) !== $sourcePreviewDomain->domain) {
            return back()
                ->withInput()
                ->with('status', "Test URL must belong to {$sourcePreviewDomain->domain}.");
        }

        $preview = $this->attemptSourcePreview->handleUrl($testUrl, ignoreAutomaticPolicy: true);

        return back()
            ->withInput()
            ->with(
                'status',
                $preview
                    ? "Preview fetch succeeded for {$sourcePreviewDomain->label}."
                    : "Preview fetch failed for {$sourcePreviewDomain->label}.",
            );
    }
}
