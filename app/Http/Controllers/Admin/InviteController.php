<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminUsers\ReinviteUser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class InviteController extends Controller
{
    public function create(Request $request)
    {
        return Inertia::render('admin/Invite');
    }

    public function store(Request $request, ReinviteUser $reinviteUser)
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc'],
        ]);

        $email = strtolower($validated['email']);

        $user = User::withTrashed()->firstOrNew(['email' => $email]);
        if (! $user->exists) {
            $user->name = strstr($email, '@', true) ?: $email;
            $user->password = Str::random(40); // Will be hashed by cast
        } elseif ($user->trashed()) {
            $user->restore();
        }

        $user->forceFill([
            'whitelisted_at' => $user->whitelisted_at ?? now(),
            'access_revoked_at' => null,
            'access_revoked_by' => null,
        ]);
        $user->save();

        $reinviteUser($request->user(), $user, $request->ip(), 'admin-invite');

        if ($request->wantsJson()) {
            return response()->json(['status' => 'invited', 'email' => $email]);
        }

        return back()->with('status', 'Invitation sent to '.$email);
    }
}
