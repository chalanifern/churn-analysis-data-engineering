<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

/**
 * A single billing or recharge transaction.
 *
 * Prepaid customers produce recharge documents (recharge_amount /
 * recharge_frequency / days_since_last_recharge) while postpaid customers
 * produce monthly bill payments. Both live in the same collection and are
 * distinguished by `payment_type`.
 *
 * @property string $payment_id
 * @property string $customer_id
 * @property string $date
 * @property float $amount
 * @property string $payment_method
 * @property string $payment_status
 * @property int $days_overdue
 * @property string $payment_type
 * @property float|null $recharge_amount
 * @property string|null $recharge_frequency
 * @property int|null $days_since_last_recharge
 */
final class Payment extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $table = 'payments';

    /** @var list<string> */
    protected $fillable = [
        'payment_id',
        'customer_id',
        'date',
        'amount',
        'payment_method',
        'payment_status',
        'days_overdue',
        'payment_type',
        'recharge_amount',
        'recharge_frequency',
        'days_since_last_recharge',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'days_overdue' => 'integer',
            'recharge_amount' => 'float',
            'days_since_last_recharge' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * Prepaid recharges also describe how long the customer waited before topping
     * up, which feeds the "long recharge gap" risk factor.
     */
    public function isRecharge(): bool
    {
        return $this->payment_type === 'Recharge';
    }
}
