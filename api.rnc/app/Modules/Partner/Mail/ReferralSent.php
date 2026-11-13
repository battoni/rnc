<?php

namespace App\Modules\Partner\Mail;

use App\Modules\Partner\DTOs\ReferralMailDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The partner's notice of a referral (rnc-1 R22, D7): which company Franco recommends, why, the
 * team's message and how to reach Franco — never the lead's own contact, the partner talks to the
 * lead only through Franco. Queued, carrying values, not models (plan AM6). A reply goes to the
 * firm's mailbox.
 */
class ReferralSent extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ReferralMailDTO $referral) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.referral.subject', ['firm' => $this->referral->firmName, 'company' => $this->referral->company]),
            replyTo: $this->referral->firmMailbox ? [new Address($this->referral->firmMailbox, $this->referral->firmName)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.referral-sent',
            text: 'mail.referral-sent-text',
            with: ['referral' => $this->referral],
        );
    }
}
