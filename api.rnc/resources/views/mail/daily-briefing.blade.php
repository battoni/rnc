@extends('mail.layout')

@section('title', __('mail.briefing.subject', ['day' => $briefing->day()]))
{{-- A firm without the network has no referrals (rnc-4 R19): the figure, the line and the list go. --}}
@section('preheader', $day['referrals'] === null
    ? __('mail.briefing.preheader_no_referrals', ['leads' => $day['leads']['last_7_days'], 'meetings' => $day['meetings']['count']])
    : __('mail.briefing.preheader', ['leads' => $day['leads']['last_7_days'], 'meetings' => $day['meetings']['count'], 'referrals' => $day['referrals']['count']]))
@section('footer_reason', __('mail.briefing.footer_reason', ['firm' => $briefing->firmName]))

@section('body')
    <p style="margin:0 0 4px 0;font-size:12px;line-height:16px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#52708a;">
        {{ __('mail.briefing.heading') }}
    </p>
    <h1 style="margin:0 0 8px 0;font-size:22px;line-height:28px;font-weight:700;color:{{ $ink }};">
        {{ __('mail.briefing.greeting', ['name' => $briefing->firstName]) }}
    </h1>
    <p style="margin:0 0 24px 0;font-size:15px;line-height:22px;color:#3f3f46;">
        {{ __('mail.briefing.lede', ['day' => $briefing->day()]) }}
    </p>

    {{-- The figures (four, three without the network), two by two: a table survives every client a grid would not. --}}
    @php
        $kpis = [
            [__('mail.briefing.kpi.leads'), number_format($day['leads']['last_7_days'], 0, ',', '.'), __('mail.briefing.kpi.leads_sub', ['count' => $day['leads']['last_24_hours']])],
            [__('mail.briefing.kpi.meetings'), number_format($day['meetings']['count'], 0, ',', '.'), __('mail.briefing.kpi.meetings_sub', ['count' => $day['meetings']['today']])],
            ...($day['referrals'] === null ? [] : [[__('mail.briefing.kpi.referrals'), number_format($day['referrals']['count'], 0, ',', '.'), __('mail.briefing.kpi.referrals_sub', ['breached' => $day['referrals']['by_state']['estourado'], 'warning' => $day['referrals']['by_state']['atencao']])]]),
            [__('mail.briefing.kpi.pipeline'), $briefing->pipelineRange(), __('mail.briefing.kpi.pipeline_sub', ['count' => $day['pipeline']['count']])],
        ];
    @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;">
        @foreach (array_chunk($kpis, 2) as $row)
            <tr>
                @foreach ($row as [$label, $value, $sub])
                    <td width="50%" valign="top" style="padding:0 {{ $loop->first ? '6px' : '0' }} 12px {{ $loop->first ? '0' : '6px' }};">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #d4e4ef;border-radius:10px;background-color:#f3f8fb;">
                            <tr>
                                <td style="padding:14px 16px;">
                                    <p style="margin:0;font-size:12px;line-height:16px;font-weight:600;color:#52708a;">{{ $label }}</p>
                                    <p style="margin:0;padding:4px 0;font-size:20px;line-height:26px;font-weight:700;color:{{ $ink }};">{{ $value }}</p>
                                    <p style="margin:0;font-size:12px;line-height:16px;color:#52708a;">{{ $sub }}</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <h2 style="margin:0 0 8px 0;font-size:17px;line-height:24px;font-weight:700;color:{{ $ink }};">{{ __('mail.briefing.actions_heading') }}</h2>
    @if (count($day['actions']) > 0)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;">
            @foreach ($day['actions'] as $action)
                <tr>
                    <td valign="top" style="padding:0 8px 10px 0;font-size:15px;line-height:22px;font-weight:700;color:{{ $ink }};">{{ $loop->iteration }}.</td>
                    <td style="padding:0 0 10px 0;font-size:15px;line-height:22px;color:#3f3f46;">
                        {{ $briefing->actionLine($action) }}
                        <a href="{{ $briefing->url($action['to']) }}" style="color:{{ $ink }};font-weight:600;">{{ __('mail.briefing.go.'.$action['kind']) }}</a>
                    </td>
                </tr>
            @endforeach
        </table>
    @else
        <p style="margin:0 0 24px 0;font-size:15px;line-height:22px;color:#3f3f46;">{{ __('mail.briefing.no_actions') }}</p>
    @endif

    <h2 style="margin:0 0 8px 0;font-size:17px;line-height:24px;font-weight:700;color:{{ $ink }};">{{ __('mail.briefing.meetings_heading') }}</h2>
    <p style="margin:0 0 24px 0;font-size:15px;line-height:22px;color:#3f3f46;">
        @forelse ($day['meetings']['next'] as $meeting)
            <strong>{{ $briefing::when($meeting['starts_at']) }}</strong> · {{ $meeting['company'] }} <span style="color:#71717a;">· {{ $meeting['specialist'] }}</span><br>
        @empty
            {{ __('mail.briefing.no_meetings') }}
        @endforelse
    </p>

    @if ($day['referrals'] !== null)
    <h2 style="margin:0 0 8px 0;font-size:17px;line-height:24px;font-weight:700;color:{{ $ink }};">{{ __('mail.briefing.referrals_heading', ['goal' => $day['referrals']['goal_hours']]) }}</h2>
    <p style="margin:0 0 24px 0;font-size:15px;line-height:22px;color:#3f3f46;">
        @forelse ($day['referrals']['top'] as $referral)
            <strong>{{ $referral['company'] }}</strong> → {{ $referral['partner']['name'] }} · <span style="color:{{ $referral['state'] === 'estourado' ? '#b42318' : ($referral['state'] === 'atencao' ? '#8a5a00' : '#2f6b3a') }};">{{ __('mail.briefing.sla', ['hours' => $briefing::hours($referral['hours']), 'goal' => $day['referrals']['goal_hours']]) }}</span><br>
        @empty
            {{ __('mail.briefing.no_referrals') }}
        @endforelse
        @if ($day['referrals']['more'] > 0)
            <span style="color:#71717a;">{{ __('mail.briefing.more', ['count' => $day['referrals']['more']]) }}</span>
        @endif
    </p>
    @endif

    @include('mail.partials._button', ['href' => $briefing->link(), 'label' => __('mail.briefing.cta')])

    <p style="margin:0;font-size:13px;line-height:20px;color:#71717a;">
        <a href="{{ $briefing->link() }}" style="color:{{ $ink }};">{{ $briefing->link() }}</a>
    </p>
@endsection
