@extends('mail.layout')

@section('title', __('mail.beta_signup.subject', ['company' => $signup->company]))
@section('preheader', __('mail.beta_signup.preheader', ['name' => $signup->name, 'company' => $signup->company, 'city' => $signup->city, 'uf' => $signup->uf]))
@section('footer_reason', __('mail.beta_signup.footer_reason', ['firm' => $signup->brand->name]))

@section('body')
    <h1 style="margin:0 0 16px 0;font-size:22px;line-height:28px;font-weight:700;color:{{ $ink }};">
        {{ __('mail.beta_signup.heading') }}
    </h1>

    <p style="margin:0 0 24px 0;font-size:15px;line-height:22px;color:#3f3f46;">
        {{ __('mail.beta_signup.body') }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;border:1px solid #d4e4ef;border-radius:10px;background-color:#f3f8fb;">
        <tr>
            <td style="padding:20px 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="padding:0 0 4px 0;font-size:12px;line-height:16px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#52708a;">{{ __('mail.beta_signup.company_label') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:0 0 16px 0;font-size:20px;line-height:28px;font-weight:700;color:{{ $ink }};">
                            {{ $signup->company }} <span style="font-size:14px;font-weight:400;color:#52708a;">· {{ $signup->city }}/{{ $signup->uf }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 0 4px 0;font-size:12px;line-height:16px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#52708a;">{{ __('mail.beta_signup.contact_label') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:0 0 16px 0;font-size:15px;line-height:22px;color:#1f2f3d;">
                            <strong>{{ $signup->name }}</strong> · {{ $signup->role }}<br>
                            {{ __('mail.beta_signup.email_label') }}: <a href="mailto:{{ $signup->email }}" style="color:{{ $ink }};">{{ $signup->email }}</a><br>
                            {{ __('mail.beta_signup.phone_label') }}: {{ $signup->phone }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 0 4px 0;font-size:12px;line-height:16px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#52708a;">{{ __('mail.beta_signup.band_label') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:0 0 {{ $signup->message ? '16px' : '0' }} 0;font-size:15px;line-height:22px;color:#1f2f3d;">{{ __('mail.beta_signup.bands.'.$signup->clientsBand) }}</td>
                    </tr>
                    @if ($signup->message)
                        <tr>
                            <td style="padding:0 0 4px 0;font-size:12px;line-height:16px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#52708a;">{{ __('mail.beta_signup.message_label') }}</td>
                        </tr>
                        <tr>
                            <td style="font-size:15px;line-height:22px;color:#1f2f3d;font-style:italic;">“{!! nl2br(e($signup->message)) !!}”</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @include('mail.partials._button', ['href' => $signup->leadUrl(), 'label' => __('mail.beta_signup.cta')])
@endsection
