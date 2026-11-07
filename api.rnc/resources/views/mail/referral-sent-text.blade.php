{{ __('mail.referral.heading', ['firm' => $referral->firmName]) }}

{{ __('mail.referral.hello', ['partner' => $referral->partnerName]) }}

{{ __('mail.referral.body', ['firm' => $referral->firmName, 'specialty' => $referral->specialty]) }}

{{ __('mail.referral.company_label') }}: {{ $referral->company }}@if ($referral->sector) · {{ __('mail.referral.sectors.'.$referral->sector) }}@endif

@if (count($referral->covers) > 0)
{{ __('mail.referral.covers_label') }}:
@foreach ($referral->covers as $cover)
- {{ $cover }}
@endforeach

@endif
{{ __('mail.referral.message_label', ['name' => $referral->referredBy]) }}:
"{{ $referral->message }}"

{{ __('mail.referral.through_firm', ['firm' => $referral->firmName]) }}

{{ $referral->referredBy }} · {{ $referral->firmName }}
@if ($referral->firmPhone)
{{ __('mail.referral.phone_label') }}: {{ $referral->firmPhone }}
@endif
@if ($referral->firmMailbox)
{{ __('mail.referral.email_label') }}: {{ $referral->firmMailbox }}
@endif

{{ __('mail.meeting.sign_off') }}
{{ __('mail.meeting.team', ['firm' => $referral->firmName]) }}
