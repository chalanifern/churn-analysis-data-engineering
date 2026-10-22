<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ChurnRecord;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Usage;
use App\Models\User;
use App\Services\ReferentialIntegrityService;
use Tests\TestCase;

/**
 * CRUD coverage for the write side of the customer and plan catalogues.
 *
 * These tests run against the same MongoDB instance as the seeded demo data, so
 * every record they create is removed again in tearDown() and all IDs use a
 * dedicated prefix that cannot collide with generated customers or plans.
 */
final class CrudOperationsTest extends TestCase
{
    private const TEST_CUSTOMER_ID = 'C99991';

    private const TEST_PLAN_ID = 'P999';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::first() ?? User::create([
            'name' => 'Test Admin',
            'email' => 'test-admin@sltmobitel.test',
            'password' => 'password',
        ]));

        $this->purgeTestFixtures();
    }

    protected function tearDown(): void
    {
        $this->purgeTestFixtures();

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function validCustomerAttributes(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => self::TEST_CUSTOMER_ID,
            'name' => 'Test Customer',
            'age' => 34,
            'gender' => 'Male',
            'district' => 'Colombo',
            'customer_segment' => 'Individual',
            'customer_status' => 'Active',
            'registration_date' => '2024-01-15',
            'phone' => '0771234567',
            'email' => 'test.customer@sltmobitel.test',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPlanAttributes(array $overrides = []): array
    {
        return array_merge([
            'plan_id' => self::TEST_PLAN_ID,
            'plan_name' => 'Test Plan',
            'plan_type' => 'Prepaid',
            'monthly_price' => 1499.50,
            'data_gb' => 5.0,
            'voice_minutes' => 500,
            'sms_count' => 100,
            'status' => 'Active',
            'description' => 'Fixture plan for tests.',
        ], $overrides);
    }

    private function purgeTestFixtures(): void
    {
        foreach ([
            Complaint::class,
            Payment::class,
            Usage::class,
            Subscription::class,
            ChurnRecord::class,
        ] as $model) {
            $model::query()->where('customer_id', self::TEST_CUSTOMER_ID)->delete();
        }

        Customer::query()->where('customer_id', self::TEST_CUSTOMER_ID)->delete();
        Plan::query()->where('plan_id', self::TEST_PLAN_ID)->delete();
    }

    public function test_guest_cannot_reach_any_write_route(): void
    {
        auth()->logout();

        $this->post(route('customers.store'), $this->validCustomerAttributes())->assertRedirect(route('login'));
        $this->put(route('customers.update', self::TEST_CUSTOMER_ID), $this->validCustomerAttributes())->assertRedirect(route('login'));
        $this->delete(route('customers.destroy', self::TEST_CUSTOMER_ID), ['confirm_customer_id' => self::TEST_CUSTOMER_ID])->assertRedirect(route('login'));
        $this->post(route('plans.store'), $this->validPlanAttributes())->assertRedirect(route('login'));
        $this->get(route('customers.create'))->assertRedirect(route('login'));
        $this->get(route('plans.create'))->assertRedirect(route('login'));

        $this->assertSame(0, Customer::query()->where('customer_id', self::TEST_CUSTOMER_ID)->count());
    }

    public function test_customer_can_be_created_and_persisted(): void
    {
        $response = $this->post(route('customers.store'), $this->validCustomerAttributes());

        $response->assertRedirect(route('customers.show', self::TEST_CUSTOMER_ID));
        $response->assertSessionHas('status');

        $stored = Customer::query()->where('customer_id', self::TEST_CUSTOMER_ID)->first();

        $this->assertNotNull($stored);
        $this->assertSame('Test Customer', $stored->name);
        $this->assertSame(34, $stored->age);
        $this->assertSame('Individual', $stored->customer_segment);
    }

    public function test_create_form_renders_vocabulary_options(): void
    {
        $response = $this->get(route('customers.create'));

        $response->assertOk();
        $response->assertSee('New Customer');
        $response->assertSee('name="customer_segment"', false);
        $response->assertSee('Corporate');
    }

    public function test_customer_creation_rejects_values_outside_the_vocabulary(): void
    {
        $response = $this->post(route('customers.store'), $this->validCustomerAttributes([
            'customer_segment' => 'Government',
            'customer_status' => 'Retired',
            'gender' => 'Unknown',
        ]));

        $response->assertSessionHasErrors(['customer_segment', 'customer_status', 'gender']);

        $this->assertSame(0, Customer::query()->where('customer_id', self::TEST_CUSTOMER_ID)->count());
    }

    public function test_customer_creation_rejects_a_duplicate_id(): void
    {
        Customer::query()->create($this->validCustomerAttributes());

        $this->post(route('customers.store'), $this->validCustomerAttributes(['name' => 'Duplicate']))
            ->assertSessionHasErrors('customer_id');

        $this->assertSame(1, Customer::query()->where('customer_id', self::TEST_CUSTOMER_ID)->count());
    }

    public function test_customer_creation_rejects_a_malformed_id(): void
    {
        $this->post(route('customers.store'), $this->validCustomerAttributes(['customer_id' => 'NOTANID']))
            ->assertSessionHasErrors('customer_id');
    }

    public function test_customer_can_be_updated(): void
    {
        Customer::query()->create($this->validCustomerAttributes());

        $response = $this->put(route('customers.update', self::TEST_CUSTOMER_ID), $this->validCustomerAttributes([
            'name' => 'Renamed Customer',
            'customer_status' => 'Suspended',
            'phone' => '',
        ]));

        $response->assertRedirect(route('customers.show', self::TEST_CUSTOMER_ID));

        $stored = Customer::query()->where('customer_id', self::TEST_CUSTOMER_ID)->first();

        $this->assertSame('Renamed Customer', $stored->name);
        $this->assertSame('Suspended', $stored->customer_status);
        // A blank optional field must overwrite, not be skipped as a missing value.
        $this->assertNull($stored->phone);
    }

    public function test_customer_id_cannot_be_renamed(): void
    {
        Customer::query()->create($this->validCustomerAttributes());

        $this->put(route('customers.update', self::TEST_CUSTOMER_ID), $this->validCustomerAttributes([
            'customer_id' => 'C99992',
            'name' => 'Renamed',
        ]))->assertSessionHasErrors('customer_id');

        $this->assertSame(1, Customer::query()->where('customer_id', self::TEST_CUSTOMER_ID)->count());
        $this->assertSame(0, Customer::query()->where('customer_id', 'C99992')->count());
    }

    public function test_editing_an_unknown_customer_returns_not_found(): void
    {
        $this->get(route('customers.edit', 'ZZZ99999'))->assertNotFound();
        $this->put(route('customers.update', 'ZZZ99999'), $this->validCustomerAttributes(['customer_id' => 'ZZZ99999']))
            ->assertNotFound();
        $this->delete(route('customers.destroy', 'ZZZ99999'), ['confirm_customer_id' => 'ZZZ99999'])
            ->assertNotFound();
    }

    public function test_customer_deletion_requires_typing_the_identifier(): void
    {
        Customer::query()->create($this->validCustomerAttributes());

        $this->delete(route('customers.destroy', self::TEST_CUSTOMER_ID), ['confirm_customer_id' => 'wrong'])
            ->assertSessionHasErrors('confirm_customer_id');

        $this->assertSame(1, Customer::query()->where('customer_id', self::TEST_CUSTOMER_ID)->count());
    }

    public function test_customer_deletion_cascades_to_every_dependent_collection(): void
    {
        Customer::query()->create($this->validCustomerAttributes());
        Plan::query()->create($this->validPlanAttributes());

        Subscription::query()->create([
            'subscription_id' => 'S99991',
            'customer_id' => self::TEST_CUSTOMER_ID,
            'plan_id' => self::TEST_PLAN_ID,
            'connection_type' => 'Prepaid',
            'start_date' => '2024-01-15',
            'end_date' => null,
            'tenure_months' => 6,
            'monthly_charge' => 1499.50,
            'status' => 'Active',
        ]);

        Usage::query()->create([
            'usage_id' => 'U99991',
            'customer_id' => self::TEST_CUSTOMER_ID,
            'month' => '2024-03',
            'data_used_gb' => 3.2,
            'voice_minutes_used' => 120,
            'sms_used' => 10,
        ]);

        Payment::query()->create([
            'payment_id' => 'PAY99991',
            'customer_id' => self::TEST_CUSTOMER_ID,
            'date' => '2024-03-02',
            'amount' => 1499.50,
            'payment_type' => 'Online',
            'payment_status' => 'Paid',
        ]);

        Complaint::query()->create([
            'complaint_id' => 'CP99991',
            'customer_id' => self::TEST_CUSTOMER_ID,
            'complaint_date' => '2024-03-04',
            'category' => 'Network',
            'severity' => 'Medium',
            'status' => 'Open',
            'description' => 'Fixture complaint.',
        ]);

        ChurnRecord::query()->create([
            'customer_id' => self::TEST_CUSTOMER_ID,
            'churn_status' => 'Active',
            'churn_date' => null,
            'churn_reason' => 'N/A',
            'retention_action' => 'None',
            'churn_value' => null,
        ]);

        $counts = app(ReferentialIntegrityService::class)->customerDependentCounts(self::TEST_CUSTOMER_ID);

        $this->assertSame(1, $counts['subscriptions']);
        $this->assertSame(1, $counts['usage']);
        $this->assertSame(1, $counts['payments']);
        $this->assertSame(1, $counts['complaints']);
        $this->assertSame(1, $counts['churn_records']);

        $response = $this->delete(route('customers.destroy', self::TEST_CUSTOMER_ID), [
            'confirm_customer_id' => self::TEST_CUSTOMER_ID,
        ]);

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHas('status');

        $this->assertSame(0, Customer::query()->where('customer_id', self::TEST_CUSTOMER_ID)->count());
        $this->assertSame(0, Subscription::query()->where('customer_id', self::TEST_CUSTOMER_ID)->count());
        $this->assertSame(0, Usage::query()->where('customer_id', self::TEST_CUSTOMER_ID)->count());
        $this->assertSame(0, Payment::query()->where('customer_id', self::TEST_CUSTOMER_ID)->count());
        $this->assertSame(0, Complaint::query()->where('customer_id', self::TEST_CUSTOMER_ID)->count());
        $this->assertSame(0, ChurnRecord::query()->where('customer_id', self::TEST_CUSTOMER_ID)->count());

        // The plan is not owned by the customer, so it must survive.
        $this->assertSame(1, Plan::query()->where('plan_id', self::TEST_PLAN_ID)->count());
    }

    public function test_customer_detail_exposes_the_delete_confirmation_form(): void
    {
        Customer::query()->create($this->validCustomerAttributes());

        $response = $this->get(route('customers.show', self::TEST_CUSTOMER_ID));

        $response->assertOk();
        $response->assertSee('Delete this customer');
        $response->assertSee('name="confirm_customer_id"', false);
    }

    public function test_plan_crud_round_trip(): void
    {
        $this->get(route('plans.index'))->assertOk()->assertSee('Plan Catalogue');

        $this->post(route('plans.store'), $this->validPlanAttributes())
            ->assertRedirect(route('plans.index'));

        $stored = Plan::query()->where('plan_id', self::TEST_PLAN_ID)->first();

        $this->assertNotNull($stored);
        $this->assertSame('Test Plan', $stored->plan_name);
        $this->assertEqualsWithDelta(1499.50, (float) $stored->monthly_price, 0.001);

        $this->get(route('plans.show', self::TEST_PLAN_ID))->assertOk()->assertSee('Test Plan');

        $this->put(route('plans.update', self::TEST_PLAN_ID), $this->validPlanAttributes([
            'plan_name' => 'Renamed Plan',
            'status' => 'Discontinued',
        ]))->assertRedirect(route('plans.show', self::TEST_PLAN_ID));

        $this->assertSame('Renamed Plan', Plan::query()->where('plan_id', self::TEST_PLAN_ID)->first()->plan_name);
    }

    public function test_plan_creation_rejects_invalid_values(): void
    {
        $this->post(route('plans.store'), $this->validPlanAttributes([
            'plan_type' => 'Carrier Pigeon',
            'monthly_price' => -5,
            'voice_minutes' => 'not-a-number',
        ]))->assertSessionHasErrors(['plan_type', 'monthly_price', 'voice_minutes']);

        $this->assertSame(0, Plan::query()->where('plan_id', self::TEST_PLAN_ID)->count());
    }

    public function test_plan_id_cannot_be_renamed(): void
    {
        Plan::query()->create($this->validPlanAttributes());

        $this->put(route('plans.update', self::TEST_PLAN_ID), $this->validPlanAttributes(['plan_id' => 'P998']))
            ->assertSessionHasErrors('plan_id');

        $this->assertSame(0, Plan::query()->where('plan_id', 'P998')->count());
    }

    public function test_plan_deletion_is_blocked_while_subscriptions_exist(): void
    {
        Plan::query()->create($this->validPlanAttributes());
        Customer::query()->create($this->validCustomerAttributes());

        Subscription::query()->create([
            'subscription_id' => 'S99992',
            'customer_id' => self::TEST_CUSTOMER_ID,
            'plan_id' => self::TEST_PLAN_ID,
            'connection_type' => 'Prepaid',
            'start_date' => '2024-01-15',
            'end_date' => null,
            'tenure_months' => 6,
            'monthly_charge' => 1499.50,
            'status' => 'Active',
        ]);

        $this->delete(route('plans.destroy', self::TEST_PLAN_ID), ['confirm_plan_id' => self::TEST_PLAN_ID])
            ->assertSessionHasErrors('cascade_subscriptions');

        $this->assertSame(1, Plan::query()->where('plan_id', self::TEST_PLAN_ID)->count());
        $this->assertSame(1, Subscription::query()->where('plan_id', self::TEST_PLAN_ID)->count());
    }

    public function test_plan_deletion_removes_subscriptions_when_the_cascade_is_confirmed(): void
    {
        Plan::query()->create($this->validPlanAttributes());
        Customer::query()->create($this->validCustomerAttributes());

        Subscription::query()->create([
            'subscription_id' => 'S99993',
            'customer_id' => self::TEST_CUSTOMER_ID,
            'plan_id' => self::TEST_PLAN_ID,
            'connection_type' => 'Prepaid',
            'start_date' => '2024-01-15',
            'end_date' => null,
            'tenure_months' => 6,
            'monthly_charge' => 1499.50,
            'status' => 'Active',
        ]);

        $this->delete(route('plans.destroy', self::TEST_PLAN_ID), [
            'confirm_plan_id' => self::TEST_PLAN_ID,
            'cascade_subscriptions' => '1',
        ])->assertRedirect(route('plans.index'));

        $this->assertSame(0, Plan::query()->where('plan_id', self::TEST_PLAN_ID)->count());
        $this->assertSame(0, Subscription::query()->where('plan_id', self::TEST_PLAN_ID)->count());

        // The customer survives a plan cascade; only subscriptions are affected.
        $this->assertSame(1, Customer::query()->where('customer_id', self::TEST_CUSTOMER_ID)->count());
    }

    public function test_plan_deletion_requires_typing_the_identifier(): void
    {
        Plan::query()->create($this->validPlanAttributes());

        $this->delete(route('plans.destroy', self::TEST_PLAN_ID), ['confirm_plan_id' => 'nope'])
            ->assertSessionHasErrors('confirm_plan_id');

        $this->assertSame(1, Plan::query()->where('plan_id', self::TEST_PLAN_ID)->count());
    }

    public function test_customer_edit_form_renders_stored_values(): void
    {
        Customer::query()->create($this->validCustomerAttributes());

        $response = $this->get(route('customers.edit', self::TEST_CUSTOMER_ID));

        $response->assertOk();
        $response->assertSee('Edit Customer');
        $response->assertSee('value="'.self::TEST_CUSTOMER_ID.'"', false);
        $response->assertSee('value="0771234567"', false);
        $response->assertSee('name="_method" value="PUT"', false);
    }

    public function test_plan_forms_render_for_create_and_edit(): void
    {
        Plan::query()->create($this->validPlanAttributes());

        $create = $this->get(route('plans.create'));

        $create->assertOk();
        $create->assertSee('New Plan');
        $create->assertSee('name="monthly_price"', false);
        $create->assertSee('Postpaid');

        $edit = $this->get(route('plans.edit', self::TEST_PLAN_ID));

        $edit->assertOk();
        $edit->assertSee('Edit Plan');
        $edit->assertSee('value="'.self::TEST_PLAN_ID.'"', false);
        $edit->assertSee('value="Test Plan"', false);
    }

    public function test_unused_plan_can_be_deleted_without_the_cascade_flag(): void
    {
        Plan::query()->create($this->validPlanAttributes());

        $this->delete(route('plans.destroy', self::TEST_PLAN_ID), ['confirm_plan_id' => self::TEST_PLAN_ID])
            ->assertRedirect(route('plans.index'));

        $this->assertSame(0, Plan::query()->where('plan_id', self::TEST_PLAN_ID)->count());
    }
}
