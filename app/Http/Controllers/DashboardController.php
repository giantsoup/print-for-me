<?php

namespace App\Http\Controllers;

use App\Enums\PrintRequestStatus;
use App\Models\PrintRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = (bool) ($user->is_admin ?? false);

        $baseQuery = PrintRequest::query();

        if (! $isAdmin) {
            $baseQuery->where('user_id', $user->id);
        }

        $statusCounts = ['all' => (clone $baseQuery)->count()];

        foreach (PrintRequestStatus::all() as $status) {
            $statusCounts[$status] = (clone $baseQuery)->where('status', $status)->count();
        }

        $recentRequests = (clone $baseQuery)
            ->with(['user:id,name,email'])
            ->withCount('files')
            ->latest()
            ->limit($isAdmin ? 6 : 5)
            ->get([
                'id',
                'user_id',
                'status',
                'source_url',
                'instructions',
                'created_at',
                'accepted_at',
                'completed_at',
                'reverted_at',
            ]);

        $recentActivity = $recentRequests
            ->flatMap(function (PrintRequest $printRequest) use ($isAdmin) {
                $actor = $printRequest->user?->name ?? 'A friend';

                return collect([
                    [
                        'id' => "created-{$printRequest->id}",
                        'kind' => 'created',
                        'title' => $isAdmin ? 'New request submitted' : 'Request submitted',
                        'description' => $isAdmin
                            ? "{$actor} submitted request #{$printRequest->id}."
                            : "Request #{$printRequest->id} entered the queue.",
                        'at' => $printRequest->created_at?->toIso8601String(),
                        'request_id' => $printRequest->id,
                    ],
                    [
                        'id' => "accepted-{$printRequest->id}",
                        'kind' => 'accepted',
                        'title' => 'Request accepted',
                        'description' => "Request #{$printRequest->id} was accepted for production.",
                        'at' => $printRequest->accepted_at?->toIso8601String(),
                        'request_id' => $printRequest->id,
                    ],
                    [
                        'id' => "reverted-{$printRequest->id}",
                        'kind' => 'reverted',
                        'title' => 'Request returned to pending',
                        'description' => "Request #{$printRequest->id} was moved back to the queue.",
                        'at' => $printRequest->reverted_at?->toIso8601String(),
                        'request_id' => $printRequest->id,
                    ],
                    [
                        'id' => "completed-{$printRequest->id}",
                        'kind' => 'completed',
                        'title' => 'Print completed',
                        'description' => "Request #{$printRequest->id} is ready for pickup.",
                        'at' => $printRequest->completed_at?->toIso8601String(),
                        'request_id' => $printRequest->id,
                    ],
                ]);
            })
            ->filter(fn (array $event) => filled($event['at']))
            ->sortByDesc('at')
            ->take(6)
            ->values();

        return Inertia::render('Dashboard', [
            'isAdmin' => $isAdmin,
            'statusCounts' => $statusCounts,
            'recentRequests' => $recentRequests,
            'recentActivity' => $recentActivity,
        ]);
    }
}
