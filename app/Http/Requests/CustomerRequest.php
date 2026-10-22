<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\ChurnVocabulary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation shared by customer create and update.
 *
 * The vocabulary constants are used as Rule::in() sources so a value that is not
 * part of the controlled set can never reach the database, whichever form
 * submitted it.
 */
abstract class CustomerRequest extends FormRequest
{
    abstract protected function customerIdRule(): Unique;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                'string',
                'regex:/^C\d{5,8}$/',
                $this->customerIdRule(),
            ],
            'name' => ['required', 'string', 'max:120'],
            'age' => ['required', 'integer', 'between:18,100'],
            'gender' => ['required', Rule::in(ChurnVocabulary::GENDERS)],
            'district' => ['required', Rule::in(ChurnVocabulary::DISTRICTS)],
            'customer_segment' => ['required', Rule::in(ChurnVocabulary::CUSTOMER_SEGMENTS)],
            'customer_status' => ['required', Rule::in(ChurnVocabulary::CUSTOMER_STATUSES)],
            'registration_date' => ['required', 'date_format:Y-m-d'],
            'phone' => ['nullable', 'string', 'regex:/^0\d{9}$/'],
            'email' => ['nullable', 'email', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.regex' => 'Customer ID must look like C00001 (letter C followed by digits).',
            'phone.regex' => 'Phone must be 10 digits starting with 0.',
            'age.between' => 'Age must be between 18 and 100.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer ID',
            'customer_segment' => 'segment',
            'customer_status' => 'status',
            'registration_date' => 'registration date',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedCustomerAttributes(): array
    {
        $attributes = $this->validated();
        $attributes['age'] = (int) $attributes['age'];

        // Blank optional fields should overwrite the old value rather than store
        // an empty string, so they are normalised to null.
        foreach (['phone', 'email'] as $optional) {
            if (($attributes[$optional] ?? null) === '') {
                $attributes[$optional] = null;
            }
        }

        return $attributes;
    }
}
