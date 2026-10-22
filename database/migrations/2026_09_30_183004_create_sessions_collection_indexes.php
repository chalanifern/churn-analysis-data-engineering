<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * The session handler writes its documents here (SESSION_DRIVER=database with
 * SESSION_CONNECTION=mongodb). The TTL index lets MongoDB expire old sessions
 * itself, so no relational "sessions" table is required.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('sessions', function (Blueprint $collection): void {
            $collection->expire('expires_at', 0);
            $collection->index('last_activity');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('sessions');
    }
};
