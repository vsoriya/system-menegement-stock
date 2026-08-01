<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfilePasswordRequest;
use App\Http\Requests\ProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lets a signed in user manage their own account.
 *
 * Every action writes to the authenticated user only, never to an id taken
 * from the request, so no role check is needed and one account can never be
 * edited through another's session.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Change the display name and the email used to sign in.
     */
    public function update(ProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->payload());

        return redirect()
            ->route('profile.edit')
            ->with('status', __('app.profile.updated_msg'));
    }

    public function updatePassword(ProfilePasswordRequest $request): RedirectResponse
    {
        // The User model casts password => hashed, so this is stored as a hash.
        $request->user()->forceFill([
            'password' => $request->newPassword(),
        ])->save();

        return redirect()
            ->route('profile.edit')
            ->with('status', __('app.profile.password_updated_msg'));
    }
}
