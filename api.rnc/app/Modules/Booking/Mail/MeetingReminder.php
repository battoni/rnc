<?php

namespace App\Modules\Booking\Mail;

use App\Modules\Booking\DTOs\MeetingMailDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The reminder sent within 24 hours of the meeting (rnc-1 R17), once (`appointments:remind`). */
class MeetingReminder extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly MeetingMailDTO $meeting) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.reminder.subject', ['when' => $this->meeting->when()]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.meeting-reminder',
            text: 'mail.meeting-reminder-text',
            with: ['meeting' => $this->meeting],
        );
    }
}
