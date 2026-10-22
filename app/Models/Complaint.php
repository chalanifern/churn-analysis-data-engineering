<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

/**
 * A service complaint raised by a customer.
 *
 * @property string $complaint_id
 * @property string $customer_id
 * @property string $complaint_date
 * @property string $category
 * @property string $description
 * @property string $severity
 * @property string $status
 * @property int|null $resolution_days
 */
final class Complaint extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $table = 'complaints';

    /** @var list<string> */
    protected $fillable = [
        'complaint_id',
        'customer_id',
        'complaint_date',
        'category',
        'description',
        'severity',
        'status',
        'resolution_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolution_days' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function isResolved(): bool
    {
        return $this->status === 'Resolved';
    }
}
