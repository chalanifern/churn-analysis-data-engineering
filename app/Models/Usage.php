<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

/**
 * Monthly consumption snapshot for a single customer.
 *
 * @property string $usage_id
 * @property string $customer_id
 * @property string $month YYYY-MM
 * @property float $data_usage_gb
 * @property int $voice_minutes
 * @property int $sms_count
 * @property int $active_days
 */
final class Usage extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $table = 'usage';

    /** @var list<string> */
    protected $fillable = [
        'usage_id',
        'customer_id',
        'month',
        'data_usage_gb',
        'voice_minutes',
        'sms_count',
        'active_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_usage_gb' => 'float',
            'voice_minutes' => 'integer',
            'sms_count' => 'integer',
            'active_days' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
