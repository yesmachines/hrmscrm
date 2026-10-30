<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApproveLetterRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'salary_amount' => ['nullable', 'string', 'max:50'],
            'bank_iban' => ['nullable', 'string', 'max:100'],
            'to_address' => ['nullable', 'string', 'max:500'],
            'destination_country' => ['nullable', 'string', 'max:100'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
