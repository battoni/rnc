{{ __('mail.beta_signup.heading') }}

{{ __('mail.beta_signup.body') }}

{{ __('mail.beta_signup.company_label') }}: {{ $signup->company }} · {{ $signup->city }}/{{ $signup->uf }}
{{ __('mail.beta_signup.contact_label') }}: {{ $signup->name }} · {{ $signup->role }}
{{ __('mail.beta_signup.email_label') }}: {{ $signup->email }}
{{ __('mail.beta_signup.phone_label') }}: {{ $signup->phone }}
{{ __('mail.beta_signup.band_label') }}: {{ __('mail.beta_signup.bands.'.$signup->clientsBand) }}
@if ($signup->message)

{{ __('mail.beta_signup.message_label') }}:
"{{ $signup->message }}"
@endif

{{ __('mail.beta_signup.cta') }}: {{ $signup->leadUrl() }}
