{{ __('mail.booking.heading') }}

{{ $meeting->firstName ? __('mail.meeting.hello', ['name' => $meeting->firstName]) : __('mail.meeting.hello_anonymous') }}

{{ __('mail.booking.body') }}

{{ __('mail.meeting.when_label') }}: {{ ucfirst($meeting->when()) }}
{{ __('mail.meeting.with_label') }}: {{ $meeting->specialist }}@if ($meeting->area) · {{ $meeting->area }}@endif

{{ __('mail.meeting.length_label') }}: {{ __('mail.meeting.length', ['minutes' => $meeting->minutes]) }}

{{ __('mail.meeting.found', ['company' => $meeting->company, 'opportunities' => trans_choice('mail.meeting.opportunity', $meeting->opportunities), 'risks' => trans_choice('mail.meeting.risk', $meeting->risks)]) }}

{{ __('mail.booking.report_intro') }}
{{ __('mail.booking.report_action') }}: {{ $meeting->reportUrl }}

{{ $meeting->firmPhone ? __('mail.meeting.reschedule', ['phone' => $meeting->firmPhone]) : __('mail.meeting.reschedule_no_phone') }}

{{ __('mail.meeting.sign_off') }}
{{ __('mail.meeting.team', ['firm' => $meeting->firmName]) }}
