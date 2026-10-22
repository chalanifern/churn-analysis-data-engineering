@extends('layouts.app')

@section('title', 'Plans')
@section('heading', 'Plan Catalogue')

@section('content')
    <form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Plan type</label>
            <select name="type" class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
                <option value="">All</option>
                @foreach (\App\Support\ChurnVocabulary::PLAN_TYPES as $t)
                    <option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
            <select name="status" class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
                <option value="">All</option>
                @foreach (\App\Support\ChurnVocabulary::RECORD_STATUSES as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-wrap gap-2 justify-between">
            <div class="flex gap-2">
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Apply</button>
                <a href="{{ route('plans.index') }}" class="rounded-lg px-4 py-2 text-sm text-slate-600 border border-slate-300 hover:bg-slate-50">Reset</a>
            </div>
            <a href="{{ route('plans.create') }}"
               class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 transition">
                + New plan
            </a>
        </div>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-xs text-slate-400 mb-3">{{ number_format($plans->total()) }} synthetic plan records.</p>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-400 border-b border-slate-200">
                    <tr>
                        <th class="py-2 pr-3">Plan</th>
                        <th class="py-2 pr-3">Type</th>
                        <th class="py-2 pr-3">Monthly</th>
                        <th class="py-2 pr-3">Data</th>
                        <th class="py-2 pr-3">Voice</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($plans as $plan)
                        <tr class="hover:bg-slate-50">
                            <td class="py-2 pr-3">
                                <div class="font-medium">{{ $plan->plan_name }}</div>
                                <div class="text-[11px] text-slate-400 font-mono">{{ $plan->plan_id }}</div>
                            </td>
                            <td class="py-2 pr-3">{{ $plan->plan_type }}</td>
                            <td class="py-2 pr-3">Rs {{ number_format((float) $plan->monthly_price, 2) }}</td>
                            <td class="py-2 pr-3">{{ rtrim(rtrim(number_format((float) $plan->data_gb, 1), '0'), '.') }} GB</td>
                            <td class="py-2 pr-3 text-slate-500">{{ number_format((int) $plan->voice_minutes) }} min</td>
                            <td class="py-2 pr-3">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-[11px] ring-1 ring-inset',
                                    'bg-emerald-50 text-emerald-700 ring-emerald-200' => $plan->status === 'Active',
                                    'bg-slate-100 text-slate-600 ring-slate-200' => $plan->status !== 'Active',
                                ])>{{ $plan->status }}</span>
                            </td>
                            <td class="py-2 text-right whitespace-nowrap">
                                <a href="{{ route('plans.show', $plan->plan_id) }}"
                                   class="text-indigo-600 hover:underline text-xs font-medium">View</a>
                                <span class="text-slate-300 mx-1">|</span>
                                <a href="{{ route('plans.edit', $plan->plan_id) }}"
                                   class="text-slate-600 hover:underline text-xs font-medium">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-slate-400">No plans match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $plans->links() }}</div>
    </div>
@endsection
