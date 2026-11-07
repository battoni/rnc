<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $news->title }}</title>

    {{--
        This page exists for the crawler, not for the reader.

        WhatsApp, Telegram, Slack and the rest fetch a URL and read the head. They do NOT run
        JavaScript, so a single-page app cannot answer them: every route of it returns the same
        index.html with the same picture. Served from here, the tags are already true when they
        arrive.

        What is public is the PREVIEW — headline, standfirst, cover. The article itself still
        needs a session, and the redirect below hands the person to the app to get one.
    --}}
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
    <meta property="og:url" content="{{ $shareUrl }}">
    <meta property="og:title" content="{{ $news->title }}">
    @if ($news->subtitle)
        <meta property="og:description" content="{{ $news->subtitle }}">
    @endif
    @if ($coverUrl)
        <meta property="og:image" content="{{ $coverUrl }}">
        <meta property="og:image:width" content="1600">
        <meta property="og:image:height" content="900">
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    <meta name="twitter:title" content="{{ $news->title }}">
    @if ($news->subtitle)
        <meta name="twitter:description" content="{{ $news->subtitle }}">
    @endif

    {{-- A person gets sent on; a crawler ignores this and keeps the tags above. --}}
    <meta http-equiv="refresh" content="0; url={{ $appUrl }}">
    <link rel="canonical" href="{{ $appUrl }}">

    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #efe9df;
            color: #5c5145;
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            text-align: center;
            padding: 24px;
        }
        a { color: #a8552b; }
    </style>
</head>
<body>
    {{-- Shown only if the redirect does not happen: a dead end is worse than a link. --}}
    <p>
        Opening in {{ config('app.name') }}…<br>
        <a href="{{ $appUrl }}">Continue</a>
    </p>

    <script>
        window.location.replace({!! json_encode($appUrl) !!});
    </script>
</body>
</html>
