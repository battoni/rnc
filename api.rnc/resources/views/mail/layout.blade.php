{{--
    Shared e-mail chrome.

    Tables and inline styles ON PURPOSE, and it is not a stylistic choice: an e-mail client is
    not a browser. Outlook renders through Word, Gmail strips <style> from the <head>, and
    flex and grid are unavailable in enough clients that using them means the message arrives
    as an unstyled column of text. A table with inline styles is the one layout that survives
    everywhere, and it is why this file looks like 2004.

    Every message extends this. A message that needs different chrome is a second layout, not
    an exception inside this one.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Stops Apple Mail resizing the message on a narrow screen. --}}
    <meta name="x-apple-disable-message-reformatting">
    <title>@yield('title')</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;-webkit-text-size-adjust:100%;">
    {{--
        The preheader: the line the inbox shows next to the subject. Hidden in the message
        itself. Without it the client picks the first words it finds, which is usually the
        greeting — the least informative sentence in the e-mail.
    --}}
    <div style="display:none;max-height:0;overflow:hidden;font-size:1px;line-height:1px;color:#f4f4f5;">@yield('preheader')</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f5;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border-radius:12px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:{{ $ink }};">
                    <tr>
                        {{--
                            The band takes the LOGO FILE's own dark, not the theme's, so the
                            square tile blends into it instead of leaving a seam. See
                            `config/rnc.php`.
                        --}}
                        <td style="background-color:{{ $ink }};padding:20px 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding-right:12px;line-height:0;">
                                        {{--
                                            Embedded, not linked: `embed()` attaches the file and
                                            returns a cid: reference, so the mark survives without
                                            a public URL that has to still answer in five years.
                                            `$message` exists while the mailer renders; the preview
                                            route renders without it and falls back to the URL.
                                        --}}
                                        <img
                                            src="{{ isset($message) ? $message->embed($brandLogo) : url(config('rnc.brand.logo')) }}"
                                            width="40" height="40" alt="{{ $brandName }}"
                                            style="display:block;width:40px;height:40px;border:0;border-radius:6px;">
                                    </td>
                                    {{--
                                        The name stays as TEXT beside the mark. Most clients block
                                        images by default, and a header that is only an image is a
                                        blank band to the reader who has not clicked "show images".
                                    --}}
                                    <td style="font-size:19px;line-height:26px;font-weight:700;letter-spacing:-0.01em;color:{{ $accent }};">
                                        {{ $brandName }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px;">
                            @yield('body')
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#f4f4f5;padding:20px 32px;font-size:12px;line-height:18px;color:#71717a;">
                            {{ __('mail.common.footer_reason') }}<br>
                            <a href="{{ $frontendUrl }}" style="color:{{ $ink }};text-decoration:underline;">{{ $frontendUrl }}</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
