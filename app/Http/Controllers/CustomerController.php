<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\Analytics\CustomerAnalytics;
use App\Services\ReferentialIntegrityService;
use App\Support\ChurnVocabulary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerAnalytics $analytics,
        private readonly ReferentialIntegrityService $integrity,
    ) {}

    public function index(Request $request): View
    {
        $query = Customer::query();

        if ($segment = $request->query('segment')) {
            // Reject unknown vocabulary values rather than silently returning an
            // empty table, which would look like a data problem to the user.
            if (in_array($segment, ChurnVocabulary::CUSTOMER_SEGMENTS, true)) {
                $query->where('customer_segment', $segment);
            }
        }

        if ($status = $request->query('status')) {
            if (in_array($status, ChurnVocabulary::CUSTOMER_STATUSES, true)) {
                $query->where('customer_status', $status);
            }
        }

        if ($district = $request->query('district')) {
            $query->where('district', $district);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('customer_id')->paginate(20)->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function show(string $customerId): View
    {
        $profile = $this->analytics->findById($customerId);

        abort_if($profile === null, 404);

        $usage = $this->analytics->usageHistory($customerId, 18);
        $payments = $this->analytics->paymentHistory($customerId, 12);
        $complaints = $this->analytics->complaints($customerId, 20);
        $dependentCounts = $this->integrity->customerDependentCounts($customerId);

        return view('customers.show', compact('profile', 'usage', 'payments', 'complaints', 'dependentCounts'));
    }

    public function create(): View
    {
        return view('customers.create', [
            'customer' => new Customer([
                'customer_status' => ChurnVocabulary::CUSTOMER_STATUSES[0],
                'gender' => ChurnVocabulary::GENDERS[0],
                'customer_segment' => ChurnVocabulary::CUSTOMER_SEGMENTS[0],
                'district' => ChurnVocabulary::DISTRICTS[0],
                'registration_date' => now()->toDateString(),
            ]),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->validatedCustomerAttributes());

        return redirect()
            ->route('customers.show', $customer->customer_id)
            ->with('status', sprintf('Customer %s was created.', $customer->customer_id));
    }

    public function edit(string $customerId): View
    {
        $customer = Customer::query()->where('customer_id', $customerId)->first();

        abort_if($customer === null, 404);

        return view('customers.edit', compact('customer'));
    }

    public function update(UpdateCustomerRequest $request, string $customerId): RedirectResponse
    {
        $customer = Customer::query()->where('customer_id', $customerId)->first();

        abort_if($customer === null, 404);

        $customer->fill($request->validatedCustomerAttributes())->save();

        return redirect()
            ->route('customers.show', $customer->customer_id)
            ->with('status', sprintf('Customer %s was updated.', $customer->customer_id));
    }

    public function destroy(Request $request, string $customerId): RedirectResponse
    {
        $customer = Customer::query()->where('customer_id', $customerId)->first();

        abort_if($customer === null, 404);

        // Typing the customer ID is the confirmation step. A DELETE triggered
        // by a stray link should not silently remove a customer plus several
        // thousand dependent documents.
        if ($request->input('confirm_customer_id') !== $customerId) {
            return back()->withErrors([
                'confirm_customer_id' => 'Type the customer ID exactly to confirm deletion.',
            ]);
        }

        $removed = $this->integrity->deleteCustomer($customer);

        $totalRemoved = array_sum($removed);

        return redirect()
            ->route('customers.index')
            ->with('status', sprintf(
                'Deleted customer %s and %d related document(s).',
                $customerId,
                $totalRemoved - 1,
            ));
    }
}
