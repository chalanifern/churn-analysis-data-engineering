<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

/**
 * Churn outcome record for a customer. Every customer in the dataset has exactly
 * one document here: "Active" when retained, "Churned" when lost.
 *
 * @property string $customer_id
 * @property string $churn_status
 * @property string|null $churn_date
 * @property string $churn_reason
 * @property string $retention_action
 * @property string|null $churn_value
 */
final class ChurnRecord extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $table = 'churn_records';

    /** @var list<string> */
    protected $fillable = [
        'customer_id',
        'churn_status',
        'churn_date',
        'churn_reason',
        'retention_action',
        'churn_value',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function isChurned(): bool
    {
        return $this->churn_status === 'Churned';
    }
}
