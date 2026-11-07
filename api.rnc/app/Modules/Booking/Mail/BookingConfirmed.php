<?php

namespace App\Modules\Booking\Mail;

use App\Modules\Booking\DTOs\MeetingMailDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The visitor's confirmation (rnc-1 R17): the meeting, what the diagnostic found and the link to
 * the "Relatório de Impacto". Queued — the booking answers without waiting for the mailer — and it
 * carries values, not models (plan AM6).
 */
class BookingConfirmed extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly MeetingMailDTO $meeting) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.booking.subject', ['when' => $this->meeting->when()]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.booking-confirmed',
            text: 'mail.booking-confirmed-text',
            with: ['meeting' => $this->meeting],
        );
    }
}
