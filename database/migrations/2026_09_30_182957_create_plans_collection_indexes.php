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
        Schema::connection('mongodb')->create('plans', function (Blueprint $collection): void {
            $collection->unique('plan_id');
            $collection->index('plan_type');
            $collection->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('plans');
    }
};
