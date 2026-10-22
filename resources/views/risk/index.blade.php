@extends('layouts.app')

@section('title', 'Risk & Retention')
@section('heading', 'Risk & Retention')

@section('content')
    <form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
        <div class="lg:col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
            <input name="search" value="{{ $filters['search'] }}" placeholder="Customer ID or name"
                   class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Risk level</label>
            <select name="level" class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
                <option value="">All</option>
                @foreach (['Low', 'Medium', 'High'] as $l)
                    <option value="{{ $l }}" @selected($filters['risk_level'] === $l)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Connection</label>
            <select name="connection" class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
                <option value="">All</option>
                @foreach (\App\Support\ChurnVocabulary::CONNECTION_TYPES as $c)
                    <option value="{{ $c }}" @selected($filters['connection_type'] === $c)>{{ $c }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Segment</label>
            <select name="segment" class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
                <option value="">All</option>
                @foreach (\App\Support\ChurnVocabulary::CUSTOMER_SEGMENTS as $s)
                    <option value="{{ $s }}" @selected($filters['customer_segment'] === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Gender</label>
            <select name="gender" class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
                <option value="">All</option>
                @foreach (\App\Support\ChurnVocabulary::GENDERS as $g)
                    <option value="{{ $g }}" @selected($filters['gender'] === $g)>{{ $g }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Sort</label>
            <select name="sort" class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
                @foreach (['risk_score_desc', 'risk_score_asc', 'tenure_asc', 'tenure_desc', 'recharge_desc'] as $s)
                    <option value="{{ $s }}" @selected(($filters['sort'] ?? 'risk_score_desc') === $s)>{{ str_replace('_', ' ', $s) }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2 lg:col-span-6 flex gap-2">
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Apply</button>
            <a href="{{ route('risk.index') }}" class="rounded-lg px-4 py-2 text-sm text-slate-600 border border-slate-300 hover:bg-slate-50">Reset</a>
        </div>
    </form>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @foreach ([
            ['High', $result['summary']['high'], 'text-rose-600'],
            ['Medium', $result['summary']['medium'], 'text-amber-600'],
            ['Low', $result['summary']['low'], 'text-emerald-600'],
        ] as $b)
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <div class="text-xs uppercase tracking-wide text-slate-400">{{ $b[0] }} risk</div>
                <div class="mt-1 text-2xl font-bold {{ $b[2] }}">{{ number_format($b[1]) }}</div>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <p class="text-xs text-slate-400">{{ number_format($result['total']) }} retained subscribers match filters.</p>
            <div class="text-xs text-slate-400">Avg score {{ number_format($result['summary']['average_score'], 1) }}</div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-400 border-b border-slate-200">
                    <tr>
                        <th class="py-2 pr-3">Customer</th>
                        <th class="py-2 pr-3">District</th>
                        <th class="py-2 pr-3">Segment</th>
                        <th class="py-2 pr-3">Plan</th>
                        <th class="py-2 pr-3">Conn.</th>
                        <th class="py-2 pr-3">Tenure</th>
                        <th class="py-2 pr-3">Score</th>
                        <th class="py-2 pr-3">Level</th>
                        <th class="py-2">Factors</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($result['rows'] as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="py-2 pr-3">
                                <a href="{{ route('customers.show', $row['customer_id']) }}" class="text-indigo-600 hover:underline">{{ $row['name'] }}</a>
                                <div class="text-[11px] text-slate-400">{{ $row['customer_id'] }}</div>
                            </td>
                            <td class="py-2 pr-3">{{ $row['district'] }}</td>
                            <td class="py-2 pr-3">{{ $row['customer_segment'] }}</td>
                            <td class="py-2 pr-3">{{ $row['plan_name'] }}</td>
                            <td class="py-2 pr-3">{{ $row['connection_type'] }}</td>
                            <td class="py-2 pr-3">{{ $row['tenure_months'] }} mo</td>
                            <td class="py-2 pr-3 font-semibold">{{ $row['risk_score'] }}</td>
                            <td class="py-2 pr-3">@include('partials.risk-badge', ['level' => $row['risk_level']])</td>
                            <td class="py-2 text-xs text-slate-500">{{ $row['factor_labels'] ? implode(', ', $row['factor_labels']) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-6 text-center text-slate-400">No customers match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $paginator ?? null }}</div>
        @if (! isset($paginator))
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                <div class="text-slate-400">
                    Page {{ $result['current_page'] }} of {{ $result['last_page'] }} · Per page {{ $result['per_page'] }}
                </div>
                <div class="flex gap-2">
                    @php($q = request()->query())
                    @if ($result['current_page'] > 1)
                        <a href="{{ route('risk.index', [...$q, 'page' => $result['current_page'] - 1]) }}"
                           class="rounded-lg px-3 py-1.5 border border-slate-300 hover:bg-slate-50">Prev</a>
                    @endif
                    @if ($result['current_page'] < $result['last_page'])
                        <a href="{{ route('risk.index', [...$q, 'page' => $result['current_page'] + 1]) }}"
                           class="rounded-lg px-3 py-1.5 border border-slate-300 hover:bg-slate-50">Next</a>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="font-semibold text-sm mb-3">Risk factor definitions</h2>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            @foreach ($factors as $factor)
                <div>
                    <dt class="font-medium">{{ $factor['label'] }}</dt>
                    <dd class="text-slate-500 mt-0.5">{{ $factor['description'] }}</dd>
                    <dd class="text-xs text-slate-400 mt-0.5">Weight: +{{ $factor['weight'] }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
@endsection
