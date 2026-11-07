{{--
    The plain-text alternative, and not a formality.

    A message with no text part is scored as more likely to be spam, and some readers ask for
    text on purpose. It is also the only version a screen reader never has to fight.
--}}
{{ __('mail.verification.heading') }}

{{ __('mail.verification.body') }}

{{ __('mail.common.code_label') }}: {{ $code }}

{{ __('mail.verification.expires', ['minutes' => $ttlMinutes]) }}
{{ __('mail.verification.ignore') }}

{{ $frontendUrl }}
