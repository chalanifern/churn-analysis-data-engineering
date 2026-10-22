<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChurnRecord;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Usage;

/**
 * Write-side integrity helpers.
 *
 * The analytics model stores references as plain string business keys rather
 * than MongoDB ObjectIds, so Eloquent relationship cascades cannot do this for
 * us. These helpers make every dependent delete explicit.
 *
 * Deletes are issued in dependency order (leaves first, then the owner) rather
 * than inside a transaction, because multi-document transactions require a
 * replica set and this project runs against standalone mongod. Ordering the
 * writes this way means an interruption part-way through leaves the owner
 * record still present with fewer dependents, which is visible and fixable,
 * rather than leaving the risk pipeline with $lookup inputs that resolve to
 * nothing.
 */
final class ReferentialIntegrityService
{
    /**
     * Dependent collections, in the order they should be removed.
     *
     * @var list<class-string>
     */
    private const DEPENDENT_MODELS = [
        ChurnRecord::class,
        Complaint::class,
        Payment::class,
        Usage::class,
        Subscription::class,
    ];

    /**
     * @return array<string, int>
     */
    public function customerDependentCounts(string $customerId): array
    {
        $counts = [];

        foreach (self::DEPENDENT_MODELS as $model) {
            /** @var class-string $model */
            $counts[$this->collectionLabel($model)] = $model::query()
                ->where('customer_id', $customerId)
                ->count();
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public function planDependentCounts(string $planId): array
    {
        return [
            'subscriptions' => Subscription::query()->where('plan_id', $planId)->count(),
        ];
    }

    /**
     * Delete a customer together with every record that references it.
     *
     * @return array<string, int>
     */
    public function deleteCustomer(Customer $customer): array
    {
        $removed = ['customers' => 0];

        foreach (self::DEPENDENT_MODELS as $model) {
            /** @var class-string $model */
            $removed[$this->collectionLabel($model)] = $model::query()
                ->where('customer_id', $customer->customer_id)
                ->delete();
        }

        // The owner record goes last, so the collections above can never be
        // deleted without their parent being removed too.
        $removed['customers'] = (int) Customer::query()
            ->where('customer_id', $customer->customer_id)
            ->delete();

        return $removed;
    }

    /**
     * Delete a plan, optionally taking its subscriptions with it.
     *
     * @return array<string, int>
     */
    public function deletePlan(string $planId, bool $cascadeSubscriptions): array
    {
        $removed = ['subscriptions' => 0, 'plans' => 0];

        if ($cascadeSubscriptions) {
            $removed['subscriptions'] = (int) Subscription::query()
                ->where('plan_id', $planId)
                ->delete();
        }

        $removed['plans'] = (int) Plan::query()
            ->where('plan_id', $planId)
            ->delete();

        return $removed;
    }

    /**
     * Human-readable collection name for reporting.
     *
     * @param  class-string  $model
     */
    private function collectionLabel(string $model): string
    {
        return (new $model)->getTable();
    }
}
