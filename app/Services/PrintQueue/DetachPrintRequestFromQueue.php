<?php

namespace App\Services\PrintQueue;

use App\Models\PrintRequest;
use Illuminate\Support\Facades\DB;

class DetachPrintRequestFromQueue
{
    public function __construct(private ReorderPrintQueue $ordering) {}

    public function handle(PrintRequest $request): void
    {
        DB::transaction(function () use ($request): void {
            $items = $request->queueItems()->lockForUpdate()->get();
            $deleted = false;

            foreach ($items as $item) {
                if ($item->completed_at !== null) {
                    $item->delete();
                    $deleted = true;

                    continue;
                }

                $generatedTitles = array_filter([
                    'Request #'.$request->id,
                    data_get($request->source_preview, 'title'),
                    $request->source_url,
                ]);

                $item->update([
                    'print_request_id' => null,
                    'for_whom' => null,
                    'notes' => null,
                    'title' => in_array($item->title, $generatedTitles, true) ? 'Untitled print' : $item->title,
                ]);
            }

            if ($deleted) {
                $this->ordering->renumber();
            }
        });
    }
}
