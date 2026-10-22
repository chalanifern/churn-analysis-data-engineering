<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('subscriptions', function (Blueprint $collection): void {
            $collection->unique('subscription_id');

            // Foreign key target for $lookup from the churn / analytics pipelines.
            $collection->index('customer_id');

            $collection->index('plan_id');
            $collection->index('connection_type');
            $collection->index('status');

            // Serves "active subscriptions grouped by plan / connection type".
            $collection->index(['status' => 1, 'plan_id' => 1]);
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('subscriptions');
    }
};
