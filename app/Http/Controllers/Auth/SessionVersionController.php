<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SessionVersionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Increment per-user session version to invalidate all active sessions.
        // Do not update the session 'sv' here; we want the next request to detect mismatch and log out.
        $user->forceFill([
            'session_version' => (int) ($user->session_version ?? 1) + 1,
        ])->save();

        return back()->with('status', 'All devices will be logged out. Please sign in again.');
    }
}
