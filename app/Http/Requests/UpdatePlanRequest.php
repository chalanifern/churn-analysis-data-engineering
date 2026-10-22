<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

final class UpdatePlanRequest extends PlanRequest
{
    /**
     * Like the customer ID, plan_id is a business key that subscriptions store
     * as a plain string reference. It is therefore immutable so an edit cannot
     * orphan existing subscriptions.
     */
    protected function planIdRule(): Unique
    {
        return Rule::unique('plans', 'plan_id')->ignore($this->route('planId'), 'plan_id');
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $submitted = $this->input('plan_id');
            $current = $this->route('planId');

            if (is_string($submitted) && is_string($current) && $submitted !== $current) {
                $validator->errors()->add('plan_id', 'Plan ID cannot be changed because subscriptions reference it.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPlanAttributes(): array
    {
        $attributes = parent::validatedPlanAttributes();
        $attributes['plan_id'] = $this->route('planId');

        return $attributes;
    }
}
