<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

class InvitationMail extends Mailable
{
    public function __construct(
        public User $user,
        public User $inviter,
    ) {}

    public function envelope(): Envelope
    {
        $appName = config('app.name');

        return new Envelope(
            subject: "Je bent uitgenodigd voor {$appName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invitation',
            with: [
                'url' => $this->invitationUrl(),
                'expiresInDays' => config('time-keeper.invitation_expires_in_days'),
            ],
        );
    }

    private function invitationUrl(): string
    {
        return URL::temporarySignedRoute(
            'invitations.show',
            now()->addDays(config('time-keeper.invitation_expires_in_days')),
            ['user' => $this->user],
        );
    }
}
