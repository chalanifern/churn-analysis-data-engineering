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
        Schema::connection('mongodb')->create('complaints', function (Blueprint $collection): void {
            $collection->unique('complaint_id');
            $collection->index('customer_id');
            $collection->index('complaint_date');
            $collection->index('category');
            $collection->index('severity');
            $collection->index('status');

            // Complaint counts per customer power the "multiple complaints" risk
            // factor and the complaint statistics on the profile page.
            $collection->index(['customer_id' => 1, 'status' => 1]);
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('complaints');
    }
};
