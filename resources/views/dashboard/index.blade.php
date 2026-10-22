@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Executive Dashboard')

@section('content')
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach ([
            ['Total customers', number_format($overview['customers_total']), number_format($overview['customers_active']).' active', 'text-slate-900'],
            ['Churn rate', $overview['churn_rate'].'%', number_format($overview['customers_churned']).' churned', 'text-rose-600'],
            ['Active MRR', 'Rs '.number_format($overview['mrr'], 0), 'from active subscriptions', 'text-emerald-600'],
            ['High-risk customers', number_format($overview['risk_high']), $overview['risk_medium'].' medium, '.$overview['risk_low'].' low', 'text-amber-600'],
        ] as $kpi)
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <div class="text-xs uppercase tracking-wide text-slate-400">{{ $kpi[0] }}</div>
                <div class="mt-1 text-2xl font-bold {{ $kpi[3] }}">{{ $kpi[1] }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ $kpi[2] }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="font-semibold text-sm mb-1">Churned vs retained per month</h2>
            <p class="text-xs text-slate-400 mb-4">Synthetic cohort, final 12 months of the reporting window.</p>
            <div class="h-72">
                <canvas data-chart="churn-trend" data-rows="{{ json_encode($churnTrend) }}"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="font-semibold text-sm mb-1">Risk band distribution</h2>
            <p class="text-xs text-slate-400 mb-4">Retained subscribers scored by the prototype model.</p>
            <div class="h-72">
                <canvas data-chart="risk-dist" data-rows="{{ json_encode($riskDist) }}"></canvas>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        @foreach ([
            ['reasons', 'Churn reasons', 'Derived from the synthetic churn records.', '#ef4444'],
            ['districts', 'Churn by district', 'Public administrative geography only.', '#0ea5e9'],
        ] as $chart)
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="font-semibold text-sm mb-1">{{ $chart[1] }}</h2>
                <p class="text-xs text-slate-400 mb-4">{{ $chart[2] }}</p>
                <div class="h-64">
                    <canvas data-chart="bar-h"
                            data-series-label="Customers"
                            data-color="{{ $chart[3] }}"
                            data-rows="{{ json_encode(array_map(fn ($r) => ['label' => $r['_id'] ?? 'Unknown', 'count' => $r['count'] ?? 0], ${$chart[0]})) }}"></canvas>
                </div>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="font-semibold text-sm">Highest risk retained customers</h2>
                <p class="text-xs text-slate-400 mt-0.5">Top 10 by composite risk score.</p>
            </div>
            <a href="{{ route('risk.index') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">View all →</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-400 border-b border-slate-200">
                    <tr>
                        <th class="py-2 pr-3">Customer</th>
                        <th class="py-2 pr-3">District</th>
                        <th class="py-2 pr-3">Plan</th>
                        <th class="py-2 pr-3">Score</th>
                        <th class="py-2 pr-3">Level</th>
                        <th class="py-2">Triggered factors</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($topRisk as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="py-2 pr-3">
                                <a href="{{ route('customers.show', $row['customer_id']) }}" class="text-indigo-600 hover:underline">{{ $row['name'] }}</a>
                                <div class="text-[11px] text-slate-400">{{ $row['customer_id'] }}</div>
                            </td>
                            <td class="py-2 pr-3">{{ $row['district'] }}</td>
                            <td class="py-2 pr-3">{{ $row['plan_name'] }}</td>
                            <td class="py-2 pr-3 font-semibold">{{ $row['risk_score'] }}</td>
                            <td class="py-2 pr-3">@include('partials.risk-badge', ['level' => $row['risk_level']])</td>
                            <td class="py-2 text-xs text-slate-500">{{ $row['factor_labels'] ? implode(', ', $row['factor_labels']) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-slate-400">No scored customers.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
