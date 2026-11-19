<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvitationsController extends Controller
{
    public function show(User $user): View|RedirectResponse
    {
        if ($user->hasAcceptedInvitation()) {
            return redirect()
                ->route('login')
                ->with('status', __('Je account is al geactiveerd. Log in met je wachtwoord.'));
        }

        return view('invitations.show', ['user' => $user]);
    }

    public function update(AcceptInvitationRequest $request, User $user): RedirectResponse
    {
        $user->update(['password' => $request->validated('password')]);

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('entries.index');
    }
}
