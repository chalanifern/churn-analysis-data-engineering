@extends('layouts.app')

@section('title', 'Customers')
@section('heading', 'Customer Records')

@section('content')
    <form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
        <div class="lg:col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
            <input name="search" value="{{ request('search') }}" placeholder="Customer ID or name"
                   class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
            <select name="status" class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
                <option value="">All</option>
                @foreach (\App\Support\ChurnVocabulary::CUSTOMER_STATUSES as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Segment</label>
            <select name="segment" class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
                <option value="">All</option>
                @foreach (\App\Support\ChurnVocabulary::CUSTOMER_SEGMENTS as $s)
                    <option value="{{ $s }}" @selected(request('segment') === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">District</label>
            <input name="district" value="{{ request('district') }}" placeholder="e.g. Colombo"
                   class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
        </div>
        <div class="sm:col-span-2 lg:col-span-5 flex flex-wrap items-center justify-between gap-2">
            <div class="flex gap-2">
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Apply</button>
                <a href="{{ route('customers.index') }}" class="rounded-lg px-4 py-2 text-sm text-slate-600 border border-slate-300 hover:bg-slate-50">Reset</a>
            </div>
            <a href="{{ route('customers.create') }}"
               class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 transition">
                + New customer
            </a>
        </div>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-xs text-slate-400 mb-3">{{ number_format($customers->total()) }} synthetic customer records.</p>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-400 border-b border-slate-200">
                    <tr>
                        <th class="py-2 pr-3">Customer</th>
                        <th class="py-2 pr-3">District</th>
                        <th class="py-2 pr-3">Segment</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2 pr-3">Registered</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($customers as $customer)
                        <tr class="hover:bg-slate-50">
                            <td class="py-2 pr-3">
                                <div class="font-medium">{{ $customer->name }}</div>
                                <div class="text-[11px] text-slate-400">{{ $customer->customer_id }}</div>
                            </td>
                            <td class="py-2 pr-3">{{ $customer->district }}</td>
                            <td class="py-2 pr-3">{{ $customer->customer_segment }}</td>
                            <td class="py-2 pr-3">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-[11px] ring-1 ring-inset',
                                    'bg-emerald-50 text-emerald-700 ring-emerald-200' => $customer->customer_status === 'Active',
                                    'bg-rose-50 text-rose-700 ring-rose-200' => $customer->customer_status === 'Churned',
                                    'bg-slate-100 text-slate-600 ring-slate-200' => $customer->customer_status !== 'Active' && $customer->customer_status !== 'Churned',
                                ])>{{ $customer->customer_status }}</span>
                            </td>
                            <td class="py-2 pr-3 text-slate-500">{{ $customer->registration_date }}</td>
                            <td class="py-2 text-right whitespace-nowrap">
                                <a href="{{ route('customers.show', $customer->customer_id) }}"
                                   class="text-indigo-600 hover:underline text-xs font-medium">View</a>
                                <span class="text-slate-300 mx-1">|</span>
                                <a href="{{ route('customers.edit', $customer->customer_id) }}"
                                   class="text-slate-600 hover:underline text-xs font-medium">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-slate-400">No customers match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $customers->links() }}</div>
    </div>
@endsection
