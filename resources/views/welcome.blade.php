<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
</head>
<body style="font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0">
    <div style="text-align:center">
        <h1 style="font-size:2rem;margin:0">{{ config('app.name') }}</h1>
        <p style="opacity:.7">Loading…</p>
    </div>
</body>
</html>
