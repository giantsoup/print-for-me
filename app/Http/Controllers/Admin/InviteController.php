<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MagicLoginToken;
use App\Models\User;
use App\Notifications\MagicLoginLinkNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;

class InviteController extends Controller
{
    public function create(Request $request)
    {
        return Inertia::render('admin/Invite');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc'],
        ]);

        $email = strtolower($validated['email']);

        $user = User::firstOrNew(['email' => $email]);
        if (! $user->exists) {
            $user->name = strstr($email, '@', true) ?: $email;
            $user->password = Str::random(40); // Will be hashed by cast
        }
        $user->whitelisted_at = now();
        $user->save();

        // Generate token
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);

        MagicLoginToken::create([
            'email' => $email,
            'token_hash' => $hash,
            'expires_at' => now()->addMinutes(10),
            'ip' => (string) $request->ip(),
            'user_agent' => 'admin-invite',
        ]);

        // Build signed URL with parameters
        $loginUrl = URL::temporarySignedRoute(
            'magic.login',
            now()->addMinutes(10),
            ['email' => $email, 'token' => $raw]
        );

        // Notify user via email (logged locally)
        $user->notify(new MagicLoginLinkNotification($loginUrl));

        if ($request->wantsJson()) {
            return response()->json(['status' => 'invited', 'email' => $email]);
        }

        return back()->with('status', 'Invitation sent to '.$email);
    }
}
