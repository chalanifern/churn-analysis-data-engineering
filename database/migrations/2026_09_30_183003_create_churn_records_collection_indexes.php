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
        Schema::connection('mongodb')->create('churn_records', function (Blueprint $collection): void {
            $collection->unique('customer_id');
            $collection->index('churn_status');
            $collection->index('churn_reason');
            $collection->index('churn_date');
            $collection->index('retention_action');

            // Supports the "churned customers per month" trend line and the
            // churned-in-a-district rate calculation.
            $collection->index(['churn_status' => 1, 'churn_date' => -1]);
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('churn_records');
    }
};
