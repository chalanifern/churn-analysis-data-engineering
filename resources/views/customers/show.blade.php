@extends('layouts.app')

@section('title', $profile['customer']['name'])
@section('heading', $profile['customer']['name'])

@section('content')
    @php
        $c = $profile['customer'];
        $sub = $profile['subscription'];
        $churn = $profile['churn'];
        $usage = $profile['usage_stats'];
        $compl = $profile['complaint_stats'];
        $risk = $risk ?? null;
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-2">
            <a href="{{ route('customers.edit', $c['customer_id']) }}"
               class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50 transition">Edit</a>
            <a href="{{ route('customers.index') }}"
               class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50 transition">Back to list</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="font-semibold text-sm mb-4">Customer details</h2>
            <dl class="space-y-2 text-sm">
                @foreach ([
                    'Customer ID' => $c['customer_id'],
                    'Age' => $c['age'],
                    'Gender' => $c['gender'],
                    'District' => $c['district'],
                    'Segment' => $c['customer_segment'],
                    'Status' => $c['customer_status'],
                    'Registered' => $c['registration_date'],
                    'Phone' => $c['phone'],
                    'Email' => $c['email'],
                ] as $label => $value)
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-400 text-xs">{{ $label }}</dt>
                        <dd class="text-right font-medium">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="font-semibold text-sm mb-4">Subscription</h2>
            @if (! empty($sub))
                <dl class="space-y-2 text-sm">
                    @foreach ([
                        'Plan' => $sub['plan_id'] ?? '—',
                        'Connection' => $sub['connection_type'] ?? '—',
                        'Monthly charge' => 'Rs '.number_format((float) ($sub['monthly_charge'] ?? 0), 2),
                        'Tenure (months)' => $sub['tenure_months'] ?? '—',
                        'Activated' => $sub['activation_date'] ?? '—',
                        'Status' => $sub['status'] ?? '—',
                    ] as $label => $value)
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-400 text-xs">{{ $label }}</dt>
                            <dd class="text-right font-medium">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="text-sm text-slate-400">No subscription record.</p>
            @endif

            @if (! empty($churn['churn_reason']) && ($churn['churn_reason'] ?? '') !== 'Unknown')
                <div class="mt-4 rounded-lg bg-rose-50 border border-rose-200 p-3 text-sm">
                    <div class="text-xs uppercase tracking-wide text-rose-500">Churn reason</div>
                    <div class="font-medium text-rose-800">{{ $churn['churn_reason'] }}</div>
                    <div class="text-xs text-rose-600 mt-0.5">
                        {{ $churn['churn_date'] ?? '' }} · {{ $churn['retention_action'] ?? '' }}
                    </div>
                </div>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="font-semibold text-sm mb-4">Behaviour summary</h2>
            <dl class="space-y-2 text-sm">
                @foreach ([
                    'Avg data (GB)' => number_format((float) ($usage['avg_data'] ?? 0), 2),
                    'Avg voice (min)' => number_format((float) ($usage['avg_voice'] ?? 0), 0),
                    'Avg SMS' => number_format((float) ($usage['avg_sms'] ?? 0), 0),
                    'Months observed' => $usage['months'] ?? 0,
                    'Complaints' => $compl['total'] ?? 0,
                    'Unresolved' => $compl['unresolved'] ?? 0,
                    'Last payment' => $profile['last_payment']['date'] ?? '—',
                    'Last amount' => 'Rs '.number_format((float) ($profile['last_payment']['amount'] ?? 0), 2),
                ] as $label => $value)
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-400 text-xs">{{ $label }}</dt>
                        <dd class="text-right font-medium">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="font-semibold text-sm mb-1">Monthly usage history</h2>
        <p class="text-xs text-slate-400 mb-4">Aggregated from the usage collection, most recent {{ count($usage) }} months.</p>
        <div class="h-72">
            <canvas data-chart="usage-history" data-rows="{{ json_encode($usage) }}"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="font-semibold text-sm mb-4">Recent payments</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-400 border-b border-slate-200">
                        <tr><th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Type</th><th class="py-2 pr-3">Amount</th><th class="py-2">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($payments as $p)
                            <tr>
                                <td class="py-2 pr-3">{{ $p['date'] ?? '' }}</td>
                                <td class="py-2 pr-3">{{ $p['payment_type'] ?? '' }}</td>
                                <td class="py-2 pr-3">Rs {{ number_format((float) ($p['amount'] ?? 0), 2) }}</td>
                                <td class="py-2">{{ $p['payment_status'] ?? '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-slate-400">No payments recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="font-semibold text-sm mb-4">Complaints</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-400 border-b border-slate-200">
                        <tr><th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Category</th><th class="py-2 pr-3">Severity</th><th class="py-2">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($complaints as $cp)
                            <tr>
                                <td class="py-2 pr-3">{{ $cp['complaint_date'] ?? '' }}</td>
                                <td class="py-2 pr-3">{{ $cp['category'] ?? '' }}</td>
                                <td class="py-2 pr-3">{{ $cp['severity'] ?? '' }}</td>
                                <td class="py-2">{{ $cp['status'] ?? '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-slate-400">No complaints recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <section class="bg-white rounded-xl border border-rose-200 p-6">
        <h2 class="font-semibold text-sm text-rose-800 mb-1">Delete this customer</h2>
        <p class="text-xs text-slate-500 mb-4">
            Deleting a customer also removes every record that references them, so the
            analytics stay consistent:
        </p>

        <dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5 text-sm">
            @foreach ($dependentCounts as $collection => $count)
                <div class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2">
                    <dt class="text-[11px] uppercase text-slate-400">{{ $collection }}</dt>
                    <dd class="font-semibold text-slate-700">{{ number_format($count) }}</dd>
                </div>
            @endforeach
            <div class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2">
                <dt class="text-[11px] uppercase text-slate-400">total</dt>
                <dd class="font-semibold text-slate-700">{{ number_format(array_sum($dependentCounts)) }}</dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('customers.destroy', $c['customer_id']) }}"
              class="flex flex-wrap items-end gap-3">
            @csrf
            @method('DELETE')

            <div>
                <label for="confirm_customer_id" class="block text-xs font-medium text-slate-600 mb-1">
                    Type <span class="font-mono font-semibold">{{ $c['customer_id'] }}</span> to confirm
                </label>
                <input id="confirm_customer_id" name="confirm_customer_id" autocomplete="off"
                       class="rounded-lg border-slate-300 text-sm shadow-sm font-mono"
                       @error('confirm_customer_id') border-rose-400 bg-rose-50 @enderror>
                @error('confirm_customer_id')
                    <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                @enderror
            </div>

            <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700 transition">
                Delete customer permanently
            </button>
        </form>
    </section>
@endsection
