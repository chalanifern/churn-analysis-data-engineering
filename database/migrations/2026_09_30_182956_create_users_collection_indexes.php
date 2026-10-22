<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * MongoDB does not have a fixed relational schema, so "migrations" in this
 * project are used to create the collections together with the indexes that the
 * search, filter and aggregation pipelines depend on.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('users', function (Blueprint $collection): void {
            $collection->unique('email');
            $collection->index('name');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('users');
    }
};
