{{--
    A "bulletproof" button: a table cell with the colour and a link inside, so it is a button in
    Outlook too (which ignores padding on an <a>).
--}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;">
    <tr>
        <td align="center" style="border-radius:8px;background-color:{{ $ink }};">
            <a href="{{ $href }}" style="display:inline-block;padding:14px 24px;font-size:15px;line-height:20px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:8px;">{{ $label }}</a>
        </td>
    </tr>
</table>
