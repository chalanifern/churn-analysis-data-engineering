<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Analytics\MongoAggregator;
use Tests\TestCase;

final class CustomerBrowsingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::first() ?? User::create([
            'name' => 'Test Admin',
            'email' => 'test-admin@sltmobitel.test',
            'password' => 'password',
        ]));
    }

    private function aggregator(): MongoAggregator
    {
        return app(MongoAggregator::class);
    }

    public function test_customer_index_lists_records_and_paginates(): void
    {
        $response = $this->get(route('customers.index'));

        $response->assertOk();
        $response->assertSee('Customer Records');

        $response->assertViewHas('customers', function ($paginator): bool {
            return $paginator->perPage() === 20;
        });
    }

    public function test_customer_index_filters_by_status_and_segment(): void
    {
        $response = $this->get(route('customers.index', ['status' => 'Churned', 'segment' => 'Individual']));

        $response->assertOk();

        $response->assertViewHas('customers', function ($paginator): bool {
            foreach ($paginator->items() as $customer) {
                if ($customer->customer_status !== 'Churned' || $customer->customer_segment !== 'Individual') {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_customer_detail_shows_aggregated_profile(): void
    {
        $customerId = $this->aggregator()
            ->aggregateFirst('customers', [['$project' => ['_id' => 0, 'customer_id' => 1]]])['customer_id'] ?? null;

        if ($customerId === null) {
            $this->markTestSkipped('No synthetic customers seeded.');
        }

        $response = $this->get(route('customers.show', $customerId));

        $response->assertOk();
        $response->assertSee('Customer details');
        $response->assertSee('Subscription');
        $response->assertSee('Monthly usage history');
        $response->assertSee('data-chart="usage-history"', false);
    }

    public function test_unknown_customer_returns_not_found(): void
    {
        $this->get(route('customers.show', 'ZZZ99999'))->assertNotFound();
    }

    public function test_churn_analysis_page_renders_all_breakdowns(): void
    {
        $response = $this->get(route('churn.index'));

        $response->assertOk();
        $response->assertSee('Churn Analysis');
        $response->assertSee('Churn by customer segment');
        $response->assertSee('Churn by plan');
        $response->assertSee('Churn by district');
        $response->assertSee('Churn reasons');
    }

    public function test_risk_page_renders_filters_and_factor_definitions(): void
    {
        $response = $this->get(route('risk.index', ['level' => 'High']));

        $response->assertOk();
        $response->assertSee('Risk &amp; Retention', false);
        $response->assertSee('Risk factor definitions');
        $response->assertSee('Long Recharge Gap');
        $response->assertSee('Weight: +20');
    }
}
