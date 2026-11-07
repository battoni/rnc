@extends('mail.layout')

@section('title', __('mail.booking.subject', ['when' => $meeting->when()]))
@section('preheader', __('mail.booking.preheader', ['specialist' => $meeting->specialist]))
@section('footer_reason', __('mail.meeting.footer_reason'))

@section('body')
    <h1 style="margin:0 0 16px 0;font-size:22px;line-height:28px;font-weight:700;color:{{ $ink }};">
        {{ __('mail.booking.heading') }}
    </h1>

    <p style="margin:0 0 8px 0;font-size:15px;line-height:22px;color:#3f3f46;">
        {{ $meeting->firstName ? __('mail.meeting.hello', ['name' => $meeting->firstName]) : __('mail.meeting.hello_anonymous') }}
    </p>

    <p style="margin:0 0 24px 0;font-size:15px;line-height:22px;color:#3f3f46;">
        {{ __('mail.booking.body') }}
    </p>

    @include('mail.partials._meeting')

    <p style="margin:0 0 16px 0;font-size:15px;line-height:22px;color:#3f3f46;">
        {{ __('mail.meeting.found', [
            'company' => $meeting->company,
            'opportunities' => trans_choice('mail.meeting.opportunity', $meeting->opportunities),
            'risks' => trans_choice('mail.meeting.risk', $meeting->risks),
        ]) }}
    </p>

    <p style="margin:0 0 16px 0;font-size:15px;line-height:22px;color:#3f3f46;">
        {{ __('mail.booking.report_intro') }}
    </p>

    @include('mail.partials._button', ['href' => $meeting->reportUrl, 'label' => __('mail.booking.report_action')])

    <p style="margin:0 0 16px 0;font-size:13px;line-height:20px;color:#71717a;">
        {{ $meeting->firmPhone ? __('mail.meeting.reschedule', ['phone' => $meeting->firmPhone]) : __('mail.meeting.reschedule_no_phone') }}
    </p>

    <p style="margin:0;font-size:15px;line-height:22px;color:#3f3f46;">
        {{ __('mail.meeting.sign_off') }}<br>
        <strong>{{ __('mail.meeting.team', ['firm' => $meeting->firmName]) }}</strong>
    </p>

    @if ($meeting->firmAddress)
        <p style="margin:16px 0 0 0;font-size:12px;line-height:18px;color:#71717a;">
            {{ $meeting->firmName }} · {{ $meeting->firmAddress }}@if ($meeting->firmPhone) · {{ $meeting->firmPhone }}@endif
        </p>
    @endif
@endsection
