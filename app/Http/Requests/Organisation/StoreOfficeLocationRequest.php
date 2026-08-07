<?php

namespace App\Http\Requests\Organisation;

use App\Models\Organisation;
use App\Models\SalesCrm\Country;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOfficeLocationRequest extends FormRequest
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
            'organisation_id' => ['required', 'integer', Rule::exists(Organisation::class, 'id')],
            'office_name' => ['required', 'string', 'max:255'],
            'country_id' => ['required', 'integer', Rule::exists(Country::class, 'id')],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function officeLocationPayload(): array
    {
        return $this->validated();
    }
}
