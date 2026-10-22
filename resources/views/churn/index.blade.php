@extends('layouts.app')

@section('title', 'Churn Analysis')
@section('heading', 'Churn Analysis')

@section('content')
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @foreach ([
            ['Total customers', number_format($summary['total_customers'])],
            ['Churned customers', number_format($summary['churned_customers'])],
            ['Churn rate', $summary['churn_rate'].'%'],
        ] as $kpi)
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <div class="text-xs uppercase tracking-wide text-slate-400">{{ $kpi[0] }}</div>
                <div class="mt-1 text-2xl font-bold text-slate-900">{{ $kpi[1] }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        @foreach ([
            ['reasons', 'Churn reasons', 'why customers left, from the synthetic churn records', '#ef4444'],
            ['bySegment', 'Churn by customer segment', 'Individual, Business, Government, Family', '#4f46e5'],
            ['byPlan', 'Churn by plan', 'plan_name from the joined plans collection', '#0ea5e9'],
            ['byDistrict', 'Churn by district', 'top districts by churned customer count', '#10b981'],
        ] as $chart)
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="font-semibold text-sm mb-1">{{ $chart[1] }}</h2>
                <p class="text-xs text-slate-400 mb-4">{{ $chart[2] }}</p>
                <div class="h-72">
                    <canvas data-chart="bar-h"
                            data-series-label="Churned customers"
                            data-color="{{ $chart[3] }}"
                            data-rows="{{ json_encode(array_values(array_map(
                                fn ($r) => ['label' => $r['plan_name'] ?? $r['_id'] ?? 'Unknown', 'count' => $r['count'] ?? 0],
                                ${$chart[0]}
                            ))) }}"></canvas>
                </div>
            </div>
        @endforeach
    </div>
@endsection
