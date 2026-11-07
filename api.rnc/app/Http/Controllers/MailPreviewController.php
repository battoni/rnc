<?php

namespace App\Http\Controllers;

use App\Mail\EmailVerificationCodeMail;
use Illuminate\Http\Response;
use Illuminate\Mail\Mailable;

/**
 * Renders the e-mails in a browser, so they can be looked at without being sent.
 *
 * An e-mail is the one screen in a product nobody opens while building it: it is written,
 * sent to a log, and seen for the first time by the person it was written for. This is the
 * cheapest possible fix — a URL per message, and an index that lists them.
 *
 * LOCAL ONLY. Registered behind `app()->isLocal()` in `routes/web.php`, because these
 * routes render with sample data and no authentication, and the sample data is the whole
 * point. Nothing here should ever answer in production.
 */
class MailPreviewController extends Controller
{
    /**
     * Every message the API can send, with the sample arguments to render it.
     *
     * A new Mailable adds a line here. That is deliberate: a preview needs example data,
     * and only the person writing the message knows what a realistic example looks like.
     *
     * @return array<string, array{label: string, make: callable(): Mailable}>
     */
    private function messages(): array
    {
        return [
            'verification-code' => [
                'label' => 'Registration — verification code',
                'make' => fn (): Mailable => new EmailVerificationCodeMail('482913'),
            ],
        ];
    }

    public function index(): Response
    {
        $rows = '';
        foreach ($this->messages() as $key => $message) {
            $rows .= sprintf(
                '<li><a href="/mail-preview/%s">%s</a> <code>%s</code> '
                .'<a href="/mail-preview/%s?format=text">text</a></li>',
                $key,
                e($message['label']),
                e($key),
                $key,
            );
        }

        return response(
            '<!doctype html><meta charset="utf-8"><title>Mail preview</title>'
            .'<body style="font-family:system-ui;margin:40px;line-height:1.6">'
            .'<h1>Mail preview</h1>'
            .'<p>Rendered with sample data. Local only.</p>'
            ."<ul>{$rows}</ul></body>"
        );
    }

    public function show(string $message): Response
    {
        $messages = $this->messages();

        abort_unless(isset($messages[$message]), 404);

        $mailable = $messages[$message]['make']();

        // The text alternative is half the message and never gets looked at. One query string
        // away is close enough that it might actually be read.
        //
        // Rendered from the `Content` the Mailable declares, because `Mailable` exposes no
        // renderText(): `render()` gives the HTML part and nothing gives the other one.
        if (request()->query('format') === 'text') {
            // `content()` is the Mailable convention, not part of the base class: a message built
            // the older way (`build()`) has no declared text part to show.
            abort_unless(method_exists($mailable, 'content'), 404, 'This message declares no content.');

            $content = $mailable->content();

            abort_unless($content->text, 404, 'This message has no text alternative.');

            $text = view($content->text, array_merge($mailable->buildViewData(), $content->with))->render();

            return response($text, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return response($mailable->render());
    }
}
