@extends('layouts.app')

@section('title', $plan->plan_name)
@section('heading', $plan->plan_name)

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-2">
            <a href="{{ route('plans.edit', $plan->plan_id) }}"
               class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50 transition">Edit</a>
            <a href="{{ route('plans.index') }}"
               class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50 transition">Back to list</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="font-semibold text-sm mb-4">Plan details</h2>
            <dl class="space-y-2 text-sm">
                @foreach ([
                    'Plan ID' => $plan->plan_id,
                    'Plan name' => $plan->plan_name,
                    'Type' => $plan->plan_type,
                    'Status' => $plan->status,
                ] as $label => $value)
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-400 text-xs">{{ $label }}</dt>
                        <dd class="text-right font-medium">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="font-semibold text-sm mb-4">Allowances</h2>
            <dl class="space-y-2 text-sm">
                @foreach ([
                    'Monthly price' => 'Rs '.number_format((float) $plan->monthly_price, 2),
                    'Data' => rtrim(rtrim(number_format((float) $plan->data_gb, 1), '0'), '.').' GB',
                    'Voice minutes' => number_format((int) $plan->voice_minutes),
                    'SMS' => number_format((int) $plan->sms_count),
                ] as $label => $value)
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-400 text-xs">{{ $label }}</dt>
                        <dd class="text-right font-medium">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="font-semibold text-sm mb-4">Usage of this plan</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-400 text-xs">Subscriptions</dt>
                    <dd class="text-right font-medium">{{ number_format($subscriptionCount) }}</dd>
                </div>
            </dl>
            <p class="text-xs text-slate-400 mt-3">
                {{ $subscriptionCount === 0
                    ? 'No customer is currently subscribed to this plan.'
                    : 'This plan appears in the churn by-plan breakdown on the Churn Analysis page.' }}
            </p>
        </div>
    </div>

    @if ($plan->description)
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="font-semibold text-sm mb-2">Description</h2>
            <p class="text-sm text-slate-600">{{ $plan->description }}</p>
        </div>
    @endif

    <section class="bg-white rounded-xl border border-rose-200 p-6">
        <h2 class="font-semibold text-sm text-rose-800 mb-1">Delete this plan</h2>
        <p class="text-xs text-slate-500 mb-4">
            @if ($subscriptionCount > 0)
                {{ number_format($subscriptionCount) }} subscription(s) reference this plan.
                Deletion requires confirming the cascade, which removes those subscriptions too.
            @else
                No subscriptions reference this plan, so it can be removed safely.
            @endif
        </p>

        <form method="POST" action="{{ route('plans.destroy', $plan->plan_id) }}"
              class="flex flex-wrap items-end gap-3">
            @csrf
            @method('DELETE')

            <div>
                <label for="confirm_plan_id" class="block text-xs font-medium text-slate-600 mb-1">
                    Type <span class="font-mono font-semibold">{{ $plan->plan_id }}</span> to confirm
                </label>
                <input id="confirm_plan_id" name="confirm_plan_id" autocomplete="off"
                       class="rounded-lg border-slate-300 text-sm shadow-sm font-mono"
                       @error('confirm_plan_id') border-rose-400 bg-rose-50 @enderror>
                @error('confirm_plan_id')
                    <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                @enderror
            </div>

            @if ($subscriptionCount > 0)
                <label class="flex items-center gap-2 text-sm text-slate-700 pb-2">
                    <input type="checkbox" name="cascade_subscriptions" value="1"
                           class="rounded border-slate-300 text-rose-600 focus:ring-rose-400">
                    Also delete {{ number_format($subscriptionCount) }} subscription(s)
                </label>
            @endif

            <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700 transition">
                Delete plan permanently
            </button>
        </form>
    </section>
@endsection
