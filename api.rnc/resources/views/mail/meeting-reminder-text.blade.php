{{ __('mail.reminder.heading') }}

{{ $meeting->firstName ? __('mail.meeting.hello', ['name' => $meeting->firstName]) : __('mail.meeting.hello_anonymous') }}

{{ __('mail.reminder.body') }}

{{ __('mail.meeting.when_label') }}: {{ ucfirst($meeting->when()) }}
{{ __('mail.meeting.with_label') }}: {{ $meeting->specialist }}@if ($meeting->area) · {{ $meeting->area }}@endif

{{ __('mail.meeting.length_label') }}: {{ __('mail.meeting.length', ['minutes' => $meeting->minutes]) }}

{{ __('mail.reminder.bring') }}

{{ __('mail.reminder.report_action') }}: {{ $meeting->reportUrl }}

{{ $meeting->firmPhone ? __('mail.meeting.reschedule', ['phone' => $meeting->firmPhone]) : __('mail.meeting.reschedule_no_phone') }}

{{ __('mail.meeting.sign_off') }}
{{ __('mail.meeting.team', ['firm' => $meeting->firmName]) }}
