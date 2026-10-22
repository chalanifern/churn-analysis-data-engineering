<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasMany;

/**
 * Root document of the churn data model. Every other analytical collection
 * references a customer through its string "customer_id" business key.
 *
 * @property string $customer_id
 * @property string $name
 * @property int $age
 * @property string $gender
 * @property string $district
 * @property string $customer_segment
 * @property string $registration_date
 * @property string $customer_status
 * @property string|null $phone
 * @property string|null $email
 */
final class Customer extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $table = 'customers';

    /** @var list<string> */
    protected $fillable = [
        'customer_id',
        'name',
        'age',
        'gender',
        'district',
        'customer_segment',
        'registration_date',
        'customer_status',
        'phone',
        'email',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'age' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'customer_id', 'customer_id');
    }

    public function usage(): HasMany
    {
        return $this->hasMany(Usage::class, 'customer_id', 'customer_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'customer_id', 'customer_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'customer_id', 'customer_id');
    }

    public function churnRecord(): HasMany
    {
        return $this->hasMany(ChurnRecord::class, 'customer_id', 'customer_id');
    }
}
