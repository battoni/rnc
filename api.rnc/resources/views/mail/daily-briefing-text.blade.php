{{ __('mail.briefing.greeting', ['name' => $briefing->firstName]) }}

{{ __('mail.briefing.lede', ['day' => $briefing->day()]) }}

{{ __('mail.briefing.kpi.leads') }}: {{ $day['leads']['last_7_days'] }} · {{ __('mail.briefing.kpi.leads_sub', ['count' => $day['leads']['last_24_hours']]) }}
{{ __('mail.briefing.kpi.meetings') }}: {{ $day['meetings']['count'] }} · {{ __('mail.briefing.kpi.meetings_sub', ['count' => $day['meetings']['today']]) }}
@if ($day['referrals'] !== null)
{{ __('mail.briefing.kpi.referrals') }}: {{ $day['referrals']['count'] }} · {{ __('mail.briefing.kpi.referrals_sub', ['breached' => $day['referrals']['by_state']['estourado'], 'warning' => $day['referrals']['by_state']['atencao']]) }}
@endif
{{ __('mail.briefing.kpi.pipeline') }}: {{ $briefing->pipelineRange() }} · {{ __('mail.briefing.kpi.pipeline_sub', ['count' => $day['pipeline']['count']]) }}

{{ __('mail.briefing.actions_heading') }}
@forelse ($day['actions'] as $action)
{{ $loop->iteration }}. {{ $briefing->actionLine($action) }} {{ $briefing->url($action['to']) }}
@empty
{{ __('mail.briefing.no_actions') }}
@endforelse

{{ __('mail.briefing.meetings_heading') }}
@forelse ($day['meetings']['next'] as $meeting)
- {{ $briefing::when($meeting['starts_at']) }} · {{ $meeting['company'] }} · {{ $meeting['specialist'] }}
@empty
{{ __('mail.briefing.no_meetings') }}
@endforelse
@if ($day['referrals'] !== null)

{{ __('mail.briefing.referrals_heading', ['goal' => $day['referrals']['goal_hours']]) }}
@forelse ($day['referrals']['top'] as $referral)
- {{ $referral['company'] }} → {{ $referral['partner']['name'] }} · {{ __('mail.briefing.sla', ['hours' => $briefing::hours($referral['hours']), 'goal' => $day['referrals']['goal_hours']]) }}
@empty
{{ __('mail.briefing.no_referrals') }}
@endforelse
@if ($day['referrals']['more'] > 0)
{{ __('mail.briefing.more', ['count' => $day['referrals']['more']]) }}
@endif
@endif

{{ __('mail.briefing.cta') }}: {{ $briefing->link() }}
