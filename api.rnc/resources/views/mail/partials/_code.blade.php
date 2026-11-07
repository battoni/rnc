{{--
    A one-time code, big enough to read on a phone and to copy without zooming.

    Monospace and letter-spaced because the reader is transcribing it into another window:
    the failure mode of this block is a 0 read as an O, and the typeface is what prevents it.
--}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px 0;">
    <tr>
        <td style="background-color:#f4f4f5;border-left:4px solid {{ $accent }};border-radius:8px;padding:16px 20px;">
            <div style="font-size:12px;line-height:16px;letter-spacing:0.08em;text-transform:uppercase;color:#71717a;">{{ __('mail.common.code_label') }}</div>
            <div style="font-family:'SFMono-Regular',Menlo,Consolas,'Courier New',monospace;font-size:32px;line-height:40px;font-weight:700;letter-spacing:0.12em;color:{{ $ink }};">{{ $code }}</div>
        </td>
    </tr>
</table>
