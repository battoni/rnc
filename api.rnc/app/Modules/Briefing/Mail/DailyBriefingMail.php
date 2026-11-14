<?php

namespace App\Modules\Briefing\Mail;

use App\Modules\Briefing\DTOs\BriefingMailDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Reinaldo's briefing by e-mail at 07:00 on business days (rnc-3 R27, D13; the demo's "chega também
 * por e-mail às 7h"): the four figures, the day's actions, the next meetings and the referrals by
 * SLA, with a link to `/gestao`. Queued (AM6; sync locally), carrying values, never models; in
 * pt-BR under the firm's own brand (rnc-4 R12, `MailBrand`): from the platform's address under the
 * firm's name, replies to the firm's mailbox, every link on the firm's own host.
 */
class DailyBriefingMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly BriefingMailDTO $briefing) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->briefing->brand->sender(),
            replyTo: $this->briefing->brand->replyTo(),
            subject: __('mail.briefing.subject', ['day' => $this->briefing->day()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.daily-briefing',
            text: 'mail.daily-briefing-text',
            with: ['briefing' => $this->briefing, 'day' => $this->briefing->briefing, 'mailBrand' => $this->briefing->brand],
        );
    }
}
