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
        Schema::connection('mongodb')->create('payments', function (Blueprint $collection): void {
            $collection->unique('payment_id');
            $collection->index('customer_id');
            $collection->index('date');
            $collection->index('payment_status');
            $collection->index('payment_method');
            $collection->index('payment_type');

            // "Most recent transaction per customer" is the access pattern behind
            // average monthly spending and the days-since-last-recharge factor.
            $collection->index(['customer_id' => 1, 'date' => -1]);
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('payments');
    }
};
