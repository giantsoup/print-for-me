<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrintRequest;
use App\Services\SourcePreviews\AttemptSourcePreview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PrintRequestSourcePreviewController extends Controller
{
    public function __invoke(Request $request, PrintRequest $print_request, AttemptSourcePreview $attemptSourcePreview): RedirectResponse
    {
        if (blank($print_request->source_url)) {
            return back()->with('status', 'This request does not have a source URL to refetch.');
        }

        $preview = $attemptSourcePreview->handle($print_request, ignoreAutomaticPolicy: true);

        return back()->with(
            'status',
            $preview
                ? 'Request content refreshed successfully.'
                : 'Request content refresh failed. The source link is still saved on the request.',
        );
    }
}
