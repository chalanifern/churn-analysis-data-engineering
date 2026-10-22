<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

final class StorePlanRequest extends PlanRequest
{
    protected function planIdRule(): Unique
    {
        // Create always requires the ID to be new; the route has no value yet.
        return Rule::unique('plans', 'plan_id');
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }
}
