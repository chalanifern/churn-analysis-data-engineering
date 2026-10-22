<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

final class UpdateCustomerRequest extends CustomerRequest
{
    /**
     * customer_id is the business key that every dependent collection stores as
     * a plain string reference (subscriptions, usage, payments, complaints,
     * churn records). Renaming it would silently orphan all of them, so the ID
     * is immutable: saving with a different value is rejected rather than
     * silently rewriting references.
     *
     * The unique check ignores this record so leaving the field unchanged is
     * accepted.
     */
    protected function customerIdRule(): Unique
    {
        return Rule::unique('customers', 'customer_id')->ignore($this->route('customerId'), 'customer_id');
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'customer_id.immutable' => 'Customer ID cannot be changed because dependent records reference it.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $submitted = $this->input('customer_id');
            $current = $this->route('customerId');

            if (is_string($submitted) && is_string($current) && $submitted !== $current) {
                $validator->errors()->add('customer_id', 'Customer ID cannot be changed because dependent records reference it.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedCustomerAttributes(): array
    {
        // Force the ID back to the route value so a tampered payload cannot
        // rewrite the business key even if the rule above is bypassed.
        $attributes = parent::validatedCustomerAttributes();
        $attributes['customer_id'] = $this->route('customerId');

        return $attributes;
    }
}
