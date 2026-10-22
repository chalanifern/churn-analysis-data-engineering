<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\ChurnVocabulary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation shared by plan create and update.
 */
abstract class PlanRequest extends FormRequest
{
    abstract protected function planIdRule(): Unique;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'string', 'max:40', $this->planIdRule()],
            'plan_name' => ['required', 'string', 'max:80'],
            'plan_type' => ['required', Rule::in(ChurnVocabulary::PLAN_TYPES)],
            'monthly_price' => ['required', 'numeric', 'between:0,100000'],
            'data_gb' => ['required', 'numeric', 'between:0,1000'],
            'voice_minutes' => ['required', 'integer', 'between:0,100000'],
            'sms_count' => ['required', 'integer', 'between:0,10000'],
            'status' => ['required', Rule::in(ChurnVocabulary::RECORD_STATUSES)],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monthly_price.between' => 'Monthly price must be between 0 and 100000.',
            'data_gb.between' => 'Data allowance must be between 0 and 1000 GB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'plan_id' => 'plan ID',
            'plan_name' => 'plan name',
            'plan_type' => 'plan type',
            'monthly_price' => 'monthly price',
            'data_gb' => 'data allowance',
            'voice_minutes' => 'voice minutes',
            'sms_count' => 'SMS count',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPlanAttributes(): array
    {
        $attributes = $this->validated();

        $attributes['monthly_price'] = (float) $attributes['monthly_price'];
        $attributes['data_gb'] = (float) $attributes['data_gb'];
        $attributes['voice_minutes'] = (int) $attributes['voice_minutes'];
        $attributes['sms_count'] = (int) $attributes['sms_count'];

        if (($attributes['description'] ?? null) === '') {
            $attributes['description'] = null;
        }

        return $attributes;
    }
}
