<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Churn Analytics') — {{ config('app.name') }}</title>

    {{-- Chart.js is bundled through npm; no CDN is used so the app works offline. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
<div class="min-h-screen flex">

    <aside class="hidden lg:flex lg:flex-col w-64 shrink-0 bg-slate-900 text-slate-300">
        <div class="px-6 py-5 border-b border-slate-800">
            <div class="text-white font-bold text-lg leading-tight">SLT Churn</div>
            <div class="text-[11px] uppercase tracking-wider text-slate-500 mt-0.5">Decision Analytics</div>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
            @foreach ([
                ['route' => 'dashboard', 'label' => 'Dashboard'],
                ['route' => 'customers.index', 'label' => 'Customers'],
                ['route' => 'plans.index', 'label' => 'Plans'],
                ['route' => 'churn.index', 'label' => 'Churn Analysis'],
                ['route' => 'risk.index', 'label' => 'Risk & Retention'],
            ] as $item)
                <a href="{{ route($item['route']) }}"
                   @class([
                       'flex items-center gap-3 px-3 py-2 rounded-lg transition',
                       'bg-slate-800 text-white' => request()->routeIs($item['route']),
                       'hover:bg-slate-800 hover:text-white' => ! request()->routeIs($item['route']),
                   ])>
                    <span class="w-1.5 h-1.5 rounded-full bg-current opacity-60" aria-hidden="true"></span>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="px-6 py-4 border-t border-slate-800 text-xs">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-slate-400 hover:text-white transition">
                    Sign out ({{ auth()->user()?->name }})
                </button>
            </form>
        </div>
    </aside>

    <div class="flex-1 min-w-0 flex flex-col">
        <header class="bg-white border-b border-slate-200 px-6 py-3 flex items-center justify-between">
            <h1 class="font-semibold text-slate-800">@yield('heading', 'Dashboard')</h1>
            <span class="text-xs text-slate-400">Reporting window ends {{ $prototypeAnchor ?? '' }}</span>
        </header>

        <main class="flex-1 p-6 space-y-6">
            @if (session('status'))
                <div role="status" class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800 space-y-1">
                    <p class="font-semibold">Please fix the following:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="px-6 py-4 text-xs text-slate-400 border-t border-slate-200 bg-white">
            {{ $prototypeDisclaimer ?? 'Synthetic academic prototype — not official SLT-MOBITEL data.' }}
        </footer>
    </div>
</div>

@stack('scripts')
</body>
</html>
