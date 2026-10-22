<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ChurnRecord;
use App\Models\Customer;
use App\Models\User;
use App\Services\Analytics\ChurnRiskService;
use App\Services\Analytics\DashboardAnalytics;
use App\Services\Analytics\MongoAggregator;
use Tests\TestCase;

final class DashboardPageTest extends TestCase
{
    private function actingAsAdmin(): User
    {
        $user = User::first() ?? User::create([
            'name' => 'Test Admin',
            'email' => 'test-admin@sltmobitel.test',
            'password' => 'password',
        ]);

        $this->actingAs($user);

        return $user;
    }

    public function test_dashboard_renders_with_synthetic_disclaimer(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Executive Dashboard');
        $response->assertSee('Synthetic');
        $response->assertSee('data-chart="churn-trend"', false);
        $response->assertSee('data-chart="risk-dist"', false);
    }

    public function test_dashboard_shows_a_real_customer_total_from_mongodb(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('dashboard'));

        $count = app(MongoAggregator::class)->count('customers');

        if ($count > 0) {
            $response->assertSee(number_format($count));
        }
    }

    public function test_risk_table_orders_by_score_and_paginates(): void
    {
        $this->actingAsAdmin();

        $risk = app(ChurnRiskService::class);
        $page = $risk->paginatedRiskTable([], 10, 1);

        $this->assertGreaterThanOrEqual(0, $page['total']);
        $this->assertSame(10, $page['per_page']);
        $this->assertSame(1, $page['current_page']);

        $scores = array_column($page['rows'], 'risk_score');
        $sorted = $scores;
        rsort($sorted);

        $this->assertSame($sorted, $scores, 'Default ordering must be risk score descending.');
    }

    public function test_risk_filters_narrow_the_scored_population(): void
    {
        $this->actingAsAdmin();

        $risk = app(ChurnRiskService::class);
        $all = $risk->paginatedRiskTable([], 5, 1);
        $high = $risk->paginatedRiskTable(['risk_level' => 'High'], 5, 1);

        $this->assertLessThanOrEqual($all['total'], $high['total']);

        foreach ($high['rows'] as $row) {
            $this->assertSame('High', $row['risk_level']);
        }
    }

    public function test_risk_band_counts_match_the_visible_summary(): void
    {
        $this->actingAsAdmin();

        $risk = app(ChurnRiskService::class);
        $bands = $risk->bandCounts();
        $summary = $risk->paginatedRiskTable([], 5, 1)['summary'];

        $this->assertSame($bands['High'] ?? 0, $summary['high']);
        $this->assertSame($bands['Medium'] ?? 0, $summary['medium']);
        $this->assertSame($bands['Low'] ?? 0, $summary['low']);
    }

    public function test_churn_trend_reports_cohort_retention_for_every_month(): void
    {
        $trend = app(DashboardAnalytics::class)->churnTrend(12);

        $this->assertCount(12, $trend);

        $previousBase = 0;
        $highestRetained = null;

        foreach ($trend as $row) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $row['month']);
            $this->assertGreaterThan($previousBase - 1, $row['total'], 'The monthly customer base must not shrink.');
            $this->assertGreaterThan(0, $row['retained'], 'Retained subscribers must not collapse to zero.');
            $this->assertLessThanOrEqual($row['total'], $row['retained'] + $row['churned']);

            $previousBase = $row['total'];
            $highestRetained = max($highestRetained ?? 0, $row['retained']);
        }

        $final = $trend[count($trend) - 1];

        $this->assertLessThan(
            $highestRetained,
            $final['retained'],
            'Retained subscribers should be lower at the end of the window than at their peak.',
        );
    }

    public function test_the_final_trend_month_agrees_with_the_stored_outcomes(): void
    {
        $trend = app(DashboardAnalytics::class)->churnTrend(12);
        $final = $trend[count($trend) - 1];

        $stillActive = ChurnRecord::raw(
            static fn ($collection): int => $collection->countDocuments(['churn_status' => 'Active']),
        );

        $churnedInFinalMonth = ChurnRecord::raw(
            static fn ($collection): int => $collection->countDocuments([
                'churn_status' => 'Churned',
                'churn_date' => [
                    '$gte' => $final['month'].'-01',
                    '$lte' => $final['month'].'-31',
                ],
            ]),
        );

        $this->assertSame(Customer::query()->count(), $final['total'], 'Every customer must be in the base by the last month.');
        $this->assertSame($stillActive, $final['retained'], 'Nobody still active may be counted as churned.');
        $this->assertSame($churnedInFinalMonth, $final['churned']);
        $this->assertEqualsWithDelta(
            round($final['churned'] / $final['total'] * 100, 2),
            $final['rate'],
            0.001,
        );
    }
}
