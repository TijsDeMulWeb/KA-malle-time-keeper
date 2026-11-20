<?php

namespace App\Actions;

use App\Mail\InvitationMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendInvitation
{
    public function execute(User $user, User $inviter): bool
    {
        try {
            Mail::to($user)->send(new InvitationMail($user, $inviter));
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return false;
        }

        $user->update(['invited_at' => now()]);

        return true;
    }
}
