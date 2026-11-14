<?php

namespace App\Modules\Lead\Mail;

use App\Modules\Lead\DTOs\BetaSignupMailDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The platform team's "inscrição no beta" (rnc-4 R29): a firm signed up on the root site. Sent to
 * every active member of the platform firm's team beside the bell, under the platform firm's brand,
 * with the signup's answers and a link to the lead. Queued, carrying values.
 */
class BetaSignupMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly BetaSignupMailDTO $signup) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: $this->signup->brand->sender(), replyTo: $this->signup->brand->replyTo(), subject: __('mail.beta_signup.subject', ['company' => $this->signup->company]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.beta-signup',
            text: 'mail.beta-signup-text',
            with: ['signup' => $this->signup, 'mailBrand' => $this->signup->brand],
        );
    }
}
