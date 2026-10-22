<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Models\Plan;
use App\Services\ReferentialIntegrityService;
use App\Support\ChurnVocabulary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PlanController extends Controller
{
    public function __construct(private readonly ReferentialIntegrityService $integrity) {}

    public function index(Request $request): View
    {
        $query = Plan::query();

        if ($type = $request->query('type')) {
            if (in_array($type, ChurnVocabulary::PLAN_TYPES, true)) {
                $query->where('plan_type', $type);
            }
        }

        if ($status = $request->query('status')) {
            if (in_array($status, ChurnVocabulary::RECORD_STATUSES, true)) {
                $query->where('status', $status);
            }
        }

        $plans = $query->orderBy('plan_id')->paginate(20)->withQueryString();

        return view('plans.index', compact('plans'));
    }

    public function create(): View
    {
        return view('plans.create', [
            'plan' => new Plan([
                'plan_type' => ChurnVocabulary::PLAN_TYPES[0],
                'status' => ChurnVocabulary::RECORD_STATUSES[0],
            ]),
        ]);
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        $plan = Plan::create($request->validatedPlanAttributes());

        return redirect()
            ->route('plans.index')
            ->with('status', sprintf('Plan %s was created.', $plan->plan_id));
    }

    public function show(string $planId): View
    {
        $plan = Plan::query()->where('plan_id', $planId)->first();

        abort_if($plan === null, 404);

        $subscriptionCount = $this->integrity->planDependentCounts($planId)['subscriptions'];

        return view('plans.show', compact('plan', 'subscriptionCount'));
    }

    public function edit(string $planId): View
    {
        $plan = Plan::query()->where('plan_id', $planId)->first();

        abort_if($plan === null, 404);

        return view('plans.edit', compact('plan'));
    }

    public function update(UpdatePlanRequest $request, string $planId): RedirectResponse
    {
        $plan = Plan::query()->where('plan_id', $planId)->first();

        abort_if($plan === null, 404);

        $plan->fill($request->validatedPlanAttributes())->save();

        return redirect()
            ->route('plans.show', $plan->plan_id)
            ->with('status', sprintf('Plan %s was updated.', $plan->plan_id));
    }

    /**
     * Deleting a plan that subscriptions still reference would break the churn
     * by-plan breakdown, so it is blocked unless the operator explicitly
     * confirms the cascade.
     */
    public function destroy(Request $request, string $planId): RedirectResponse
    {
        $plan = Plan::query()->where('plan_id', $planId)->first();

        abort_if($plan === null, 404);

        $subscriptionCount = $this->integrity->planDependentCounts($planId)['subscriptions'];

        if ($request->input('confirm_plan_id') !== $planId) {
            return back()->withErrors([
                'confirm_plan_id' => 'Type the plan ID exactly to confirm deletion.',
            ]);
        }

        $cascade = $request->boolean('cascade_subscriptions');

        if ($subscriptionCount > 0 && ! $cascade) {
            return back()->withErrors([
                'cascade_subscriptions' => sprintf(
                    'This plan is referenced by %d subscription(s). Confirm the cascade to delete them too.',
                    $subscriptionCount,
                ),
            ]);
        }

        $removed = $this->integrity->deletePlan($planId, $cascade);

        return redirect()
            ->route('plans.index')
            ->with('status', sprintf(
                'Deleted plan %s%s.',
                $planId,
                $removed['subscriptions'] > 0
                    ? sprintf(' and %d subscription(s)', $removed['subscriptions'])
                    : '',
            ));
    }
}
