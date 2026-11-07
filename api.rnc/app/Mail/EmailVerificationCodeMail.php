<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The six digits that prove somebody reads the address they typed.
 *
 * Queueable but not queued: the bootstrap ships no worker, so `Mail::send` runs inline and
 * a product that turns a queue on gets the deferral by switching the call to `queue`,
 * without this class changing.
 */
class EmailVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.verification.subject'));
    }

    /**
     * HTML and text, both.
     *
     * A message with no text part scores worse with spam filters and leaves the readers who
     * ask for text with the HTML source. The two views say the same thing; neither is a
     * degraded copy of the other.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.verification-code',
            text: 'mail.verification-code-text',
            with: [
                'code' => $this->code,
                'ttlMinutes' => (int) config('registration.code_ttl_minutes'),
            ],
        );
    }
}
