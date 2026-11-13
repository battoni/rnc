@extends('mail.layout')

@section('title', __('mail.referral.subject', ['firm' => $referral->firmName, 'company' => $referral->company]))
@section('preheader', __('mail.referral.preheader', ['firm' => $referral->firmName, 'company' => $referral->company]))
@section('footer_reason', __('mail.referral.footer_reason', ['firm' => $referral->firmName]))

@section('body')
    <h1 style="margin:0 0 16px 0;font-size:22px;line-height:28px;font-weight:700;color:{{ $ink }};">
        {{ __('mail.referral.heading', ['firm' => $referral->firmName]) }}
    </h1>

    <p style="margin:0 0 8px 0;font-size:15px;line-height:22px;color:#3f3f46;">
        {{ __('mail.referral.hello', ['partner' => $referral->partnerName]) }}
    </p>

    <p style="margin:0 0 24px 0;font-size:15px;line-height:22px;color:#3f3f46;">
        {{ __('mail.referral.body', ['firm' => $referral->firmName, 'specialty' => $referral->specialty]) }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;border:1px solid #d4e4ef;border-radius:10px;background-color:#f3f8fb;">
        <tr>
            <td style="padding:20px 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="padding:0 0 4px 0;font-size:12px;line-height:16px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#52708a;">{{ __('mail.referral.company_label') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:0 0 16px 0;font-size:20px;line-height:28px;font-weight:700;color:{{ $ink }};">
                            {{ $referral->company }}@if ($referral->sector) <span style="font-size:14px;font-weight:400;color:#52708a;">· {{ __('mail.referral.sectors.'.$referral->sector) }}</span>@endif
                        </td>
                    </tr>
                    @if (count($referral->covers) > 0)
                        <tr>
                            <td style="padding:0 0 4px 0;font-size:12px;line-height:16px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#52708a;">{{ __('mail.referral.covers_label') }}</td>
                        </tr>
                        <tr>
                            <td style="padding:0 0 16px 0;font-size:15px;line-height:22px;color:#1f2f3d;">
                                @foreach ($referral->covers as $cover)
                                    • {{ $cover }}<br>
                                @endforeach
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:0 0 4px 0;font-size:12px;line-height:16px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#52708a;">{{ __('mail.referral.message_label', ['name' => $referral->referredBy]) }}</td>
                    </tr>
                    <tr>
                        <td style="font-size:15px;line-height:22px;color:#1f2f3d;font-style:italic;">“{!! nl2br(e($referral->message)) !!}”</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px 0;font-size:15px;line-height:22px;color:#3f3f46;">
        {{ __('mail.referral.through_firm', ['firm' => $referral->firmName]) }}
    </p>

    <p style="margin:0 0 24px 0;font-size:15px;line-height:22px;color:#3f3f46;">
        <strong>{{ $referral->referredBy }}</strong> · {{ $referral->firmName }}
        @if ($referral->firmPhone)<br>{{ __('mail.referral.phone_label') }}: {{ $referral->firmPhone }}@endif
        @if ($referral->firmMailbox)<br>{{ __('mail.referral.email_label') }}: <a href="mailto:{{ $referral->firmMailbox }}" style="color:{{ $ink }};">{{ $referral->firmMailbox }}</a>@endif
    </p>

    <p style="margin:0;font-size:15px;line-height:22px;color:#3f3f46;">
        {{ __('mail.meeting.sign_off') }}<br>
        <strong>{{ __('mail.meeting.team', ['firm' => $referral->firmName]) }}</strong>
    </p>

    @if ($referral->firmAddress)
        <p style="margin:16px 0 0 0;font-size:12px;line-height:18px;color:#71717a;">
            {{ $referral->firmName }} · {{ $referral->firmAddress }}
        </p>
    @endif
@endsection
