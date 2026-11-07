@extends('mail.layout')

@section('title', __('mail.verification.subject'))
@section('preheader', __('mail.verification.preheader', ['minutes' => $ttlMinutes]))

@section('body')
    <h1 style="margin:0 0 16px 0;font-size:20px;line-height:26px;font-weight:600;color:{{ $ink }};">
        {{ __('mail.verification.heading') }}
    </h1>

    <p style="margin:0 0 24px 0;font-size:15px;line-height:22px;color:#3f3f46;">
        {{ __('mail.verification.body') }}
    </p>

    @include('mail.partials._code', ['code' => $code])

    <p style="margin:0;font-size:13px;line-height:20px;color:#71717a;">
        {{ __('mail.verification.expires', ['minutes' => $ttlMinutes]) }}
        {{ __('mail.verification.ignore') }}
    </p>
@endsection
