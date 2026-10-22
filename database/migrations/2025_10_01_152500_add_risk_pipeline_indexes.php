<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $db = DB::connection('mongodb')->getDatabase();

        // Usage: most recent month per customer.
        $db->selectCollection('usage')->createIndex(
            ['customer_id' => 1, 'month' => -1],
            ['name' => 'usage_customer_month_desc']
        );

        // Payments: most recent date per customer. This key pattern already
        // exists as customer_id_1_date_-1 from an earlier migration and MongoDB
        // rejects a second index with a different name on the same pattern.
        $db->selectCollection('payments')->createIndex(
            ['customer_id' => 1, 'date' => -1],
            ['name' => 'customer_id_1_date_-1']
        );

        // Complaints: lookups for stats per customer.
        $db->selectCollection('complaints')->createIndex(
            ['customer_id' => 1, 'status' => 1],
            ['name' => 'customer_id_1_status_1']
        );

        // Subscriptions: current subscription selection per customer.
        $db->selectCollection('subscriptions')->createIndex(
            ['customer_id' => 1, 'status' => 1, 'tenure_months' => -1],
            ['name' => 'subscriptions_customer_status_tenure_desc']
        );

        // Churn: the unique customer_id index already exists as customer_id_1
        // (created by 2026_09_30_183003), so no new index is needed here.

        // Customers filtered by status + segment in the risk pipeline. The
        // status+district compound index already exists as
        // customer_status_1_district_1 from an earlier migration.
        $db->selectCollection('customers')->createIndex(
            ['customer_status' => 1, 'customer_segment' => 1],
            ['name' => 'customers_status_segment']
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $db = DB::connection('mongodb')->getDatabase();

        $db->selectCollection('usage')->dropIndex('usage_customer_month_desc');
        $db->selectCollection('subscriptions')->dropIndex('subscriptions_customer_status_tenure_desc');
        $db->selectCollection('customers')->dropIndex('customers_status_segment');
    }
};
