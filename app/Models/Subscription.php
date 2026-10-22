<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

/**
 * A customer's binding to a plan. A customer holds at most one subscription per
 * billing cycle; churned customers keep a "Churned" document for history.
 *
 * @property string $subscription_id
 * @property string $customer_id
 * @property string $plan_id
 * @property string $connection_type
 * @property string $activation_date
 * @property int $tenure_months
 * @property float $monthly_charge
 * @property string $status
 */
final class Subscription extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $table = 'subscriptions';

    /** @var list<string> */
    protected $fillable = [
        'subscription_id',
        'customer_id',
        'plan_id',
        'connection_type',
        'activation_date',
        'tenure_months',
        'monthly_charge',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenure_months' => 'integer',
            'monthly_charge' => 'float',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'plan_id');
    }
}
