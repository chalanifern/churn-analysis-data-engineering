<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasMany;

/**
 * Mobile package catalogue used by subscriptions. Purely reference data.
 *
 * @property string $plan_id
 * @property string $plan_name
 * @property string $plan_type
 * @property float $monthly_price
 * @property float $data_gb
 * @property int $voice_minutes
 * @property int $sms_count
 * @property string $status
 */
final class Plan extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $table = 'plans';

    /** @var list<string> */
    protected $fillable = [
        'plan_id',
        'plan_name',
        'plan_type',
        'monthly_price',
        'data_gb',
        'voice_minutes',
        'sms_count',
        'status',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_price' => 'float',
            'data_gb' => 'float',
            'voice_minutes' => 'integer',
            'sms_count' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id', 'plan_id');
    }
}
