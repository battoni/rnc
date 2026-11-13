{{--
    The meeting, as a card: when, with whom, how long. A table with inline styles, like the
    layout (see RULES.md) — the one shape every client renders.
--}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;border:1px solid #d4e4ef;border-radius:10px;background-color:#f3f8fb;">
    <tr>
        <td style="padding:20px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="padding:0 0 12px 0;font-size:12px;line-height:16px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#52708a;">{{ __('mail.meeting.when_label') }}</td>
                </tr>
                <tr>
                    <td style="padding:0 0 16px 0;font-size:20px;line-height:28px;font-weight:700;color:{{ $ink }};">{{ ucfirst($meeting->when()) }}</td>
                </tr>
                <tr>
                    <td style="padding:0 0 4px 0;font-size:12px;line-height:16px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#52708a;">{{ __('mail.meeting.with_label') }}</td>
                </tr>
                <tr>
                    <td style="padding:0 0 16px 0;font-size:15px;line-height:22px;color:#1f2f3d;">
                        <strong>{{ $meeting->specialist }}</strong>@if ($meeting->area) · {{ $meeting->area }}@endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 0 4px 0;font-size:12px;line-height:16px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#52708a;">{{ __('mail.meeting.length_label') }}</td>
                </tr>
                <tr>
                    <td style="font-size:15px;line-height:22px;color:#1f2f3d;">{{ __('mail.meeting.length', ['minutes' => $meeting->minutes]) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
