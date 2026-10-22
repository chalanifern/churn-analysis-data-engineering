@extends('layouts.app')

@section('title', $mode === 'create' ? 'New Plan' : 'Edit Plan')
@section('heading', $mode === 'create' ? 'New Plan' : 'Edit ' . $plan->plan_id)

@section('content')
    <form method="POST"
          action="{{ $mode === 'create' ? route('plans.store') : route('plans.update', $plan->plan_id) }}"
          class="max-w-3xl space-y-6">
        @csrf
        @if ($mode === 'edit')
            @method('PUT')
        @endif

        <section class="bg-white rounded-xl border border-slate-200 p-6 space-y-5">
            <h2 class="font-semibold text-slate-800">Plan details</h2>

            @if ($mode === 'edit')
                <div class="space-y-1">
                    <label for="plan_id" class="block text-sm font-medium text-slate-700">Plan ID</label>
                    <input id="plan_id" value="{{ old('plan_id', $plan->plan_id) }}" readonly
                           class="w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-500 cursor-not-allowed font-mono">
                    <p class="text-xs text-slate-400">
                        Plan IDs are referenced by subscriptions, so they cannot be renamed.
                    </p>
                    {{-- The read-only field above is not submitted; hidden input keeps the value. --}}
                    <input type="hidden" name="plan_id" value="{{ old('plan_id', $plan->plan_id) }}">
                </div>
            @else
                <div class="space-y-1">
                    <label for="plan_id" class="block text-sm font-medium text-slate-700">
                        Plan ID <span class="text-rose-600" aria-hidden="true">*</span>
                    </label>
                    <input id="plan_id" name="plan_id" type="text" required
                           value="{{ old('plan_id') }}" placeholder="e.g. P013"
                           @class([
                               'w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:outline-none font-mono',
                               'border-rose-400 bg-rose-50' => $errors->has('plan_id'),
                               'border-slate-300 bg-white focus:border-indigo-500 focus:ring-indigo-200' => ! $errors->has('plan_id'),
                           ])>
                    @error('plan_id')
                        <p class="text-xs text-rose-700">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div class="space-y-1">
                <label for="plan_name" class="block text-sm font-medium text-slate-700">
                    Plan name <span class="text-rose-600" aria-hidden="true">*</span>
                </label>
                <input id="plan_name" name="plan_name" type="text" required
                       value="{{ old('plan_name', $plan->plan_name) }}"
                       @class([
                           'w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:outline-none',
                           'border-rose-400 bg-rose-50' => $errors->has('plan_name'),
                           'border-slate-300 bg-white focus:border-indigo-500 focus:ring-indigo-200' => ! $errors->has('plan_name'),
                       ])>
                @error('plan_name')
                    <p class="text-xs text-rose-700">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                @foreach ([
                    ['plan_type', 'Plan type', \App\Support\ChurnVocabulary::PLAN_TYPES],
                    ['status', 'Status', \App\Support\ChurnVocabulary::RECORD_STATUSES],
                ] as [$name, $label, $options])
                    <div class="space-y-1">
                        <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">
                            {{ $label }} <span class="text-rose-600" aria-hidden="true">*</span>
                        </label>
                        <select id="{{ $name }}" name="{{ $name }}" required
                                @class([
                                    'w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:outline-none',
                                    'border-rose-400 bg-rose-50' => $errors->has($name),
                                    'border-slate-300 bg-white focus:border-indigo-500 focus:ring-indigo-200' => ! $errors->has($name),
                                ])>
                            <option value="">Select…</option>
                            @foreach ($options as $option)
                                <option value="{{ $option }}" @selected(old($name, $plan->{$name} ?? '') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        @error($name)
                            <p class="text-xs text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>
        </section>

        <section class="bg-white rounded-xl border border-slate-200 p-6 space-y-5">
            <h2 class="font-semibold text-slate-800">Allowances</h2>

            <div class="grid gap-5 sm:grid-cols-2">
                @foreach ([
                    ['monthly_price', 'Monthly price (Rs)', 'number', '0.01', 'step="0.01" min="0"'],
                    ['data_gb', 'Data allowance (GB)', 'number', '0.1', 'step="0.1" min="0"'],
                    ['voice_minutes', 'Voice minutes', 'number', '1', 'step="1" min="0"'],
                    ['sms_count', 'SMS count', 'number', '1', 'step="1" min="0"'],
                ] as [$name, $label, $type, $step, $extra])
                    <div class="space-y-1">
                        <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">
                            {{ $label }} <span class="text-rose-600" aria-hidden="true">*</span>
                        </label>
                        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" step="{{ $step }}" required
                               value="{{ old($name, $plan->{$name} ?? '') }}" {{ $extra }}
                               @class([
                                   'w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:outline-none',
                                   'border-rose-400 bg-rose-50' => $errors->has($name),
                                   'border-slate-300 bg-white focus:border-indigo-500 focus:ring-indigo-200' => ! $errors->has($name),
                               ])>
                        @error($name)
                            <p class="text-xs text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>

            <div class="space-y-1">
                <label for="description" class="block text-sm font-medium text-slate-700">Description (optional)</label>
                <textarea id="description" name="description" rows="3"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none">{{ old('description', $plan->description ?? '') }}</textarea>
                @error('description')
                    <p class="text-xs text-rose-700">{{ $message }}</p>
                @enderror
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition">
                {{ $mode === 'create' ? 'Create plan' : 'Save changes' }}
            </button>
            <a href="{{ $mode === 'create' ? route('plans.index') : route('plans.show', $plan->plan_id) }}"
               class="text-sm text-slate-500 hover:text-slate-800 transition">Cancel</a>
        </div>
    </form>
@endsection
