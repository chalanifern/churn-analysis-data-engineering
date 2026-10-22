<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

final class StoreCustomerRequest extends CustomerRequest
{
    /**
     * customer_id is the unique business key, so it must not already exist.
     */
    protected function customerIdRule(): Unique
    {
        return Rule::unique('customers', 'customer_id');
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }
}
