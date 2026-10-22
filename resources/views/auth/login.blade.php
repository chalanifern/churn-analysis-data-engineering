<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-900 text-slate-200 min-h-screen flex items-center justify-center p-6">
<div class="w-full max-w-md">
    <div class="bg-white text-slate-800 rounded-2xl shadow-xl p-8">
        <div class="text-center mb-6">
            <div class="text-slate-900 font-bold text-xl">SLT Churn Analytics</div>
            <p class="text-sm text-slate-500 mt-1">Decision Analytics prototype</p>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       autocomplete="username" inputmode="email"
                       class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('email')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('password')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1"
                       class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                Remember me
            </label>

            <button type="submit"
                    class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 transition">
                Sign in
            </button>
        </form>

        <div class="mt-6 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-xs text-slate-500">
            <div class="font-medium text-slate-600 mb-1">Demo credentials</div>
            <div><code>admin@sltmobitel.test</code> / <code>password</code></div>
        </div>

        <p class="mt-4 text-center text-[11px] text-slate-400">
            Synthetic academic prototype. Not affiliated with or endorsed by SLT-MOBITEL.
        </p>
    </div>
</div>
</body>
</html>
