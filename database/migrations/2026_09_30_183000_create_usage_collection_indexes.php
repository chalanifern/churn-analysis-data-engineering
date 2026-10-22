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
        Schema::connection('mongodb')->create('usage', function (Blueprint $collection): void {
            $collection->unique('usage_id');
            $collection->index('customer_id');
            $collection->index('month');

            // A customer has one usage document per month; the compound index both
            // enforces that rule and speeds up the per-customer usage trend chart.
            $collection->unique(['customer_id' => 1, 'month' => 1]);
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('usage');
    }
};
