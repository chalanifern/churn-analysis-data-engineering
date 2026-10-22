<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * "customers" is the anchor collection: it carries the dimensions that most of
 * the analytics pages group by (district, segment, status, registration date) and
 * is joined into by every other collection through `customer_id`.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('customers', function (Blueprint $collection): void {
            // Business key - must be unique and heavily looked up via $lookup.
            $collection->unique('customer_id');

            // Filter dimensions used by the management CRUD screens.
            $collection->index('name');
            $collection->index('district');
            $collection->index('customer_status');
            $collection->index('customer_segment');
            $collection->index('gender');

            // Compound index supporting the "active customers in a district" access
            // pattern used by the dashboard and the customer list filters.
            $collection->index(['customer_status' => 1, 'district' => 1]);
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('customers');
    }
};
