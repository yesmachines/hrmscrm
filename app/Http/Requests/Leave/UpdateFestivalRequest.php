<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFestivalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:festivals,name,'.$this->route('festival')->id,
            'type' => 'required|in:festival,holiday',
            'start_date' => ['nullable', 'date', 'before_or_equal:end_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => 'boolean',
            'countries' => ['nullable', 'array'],
            'countries.*' => ['integer'],
        ];
    }

    public function payload(): array
    {
        return [
            'name' => $this->input('name'),
            'type' => $this->input('type', 'festival'),
            'start_date' => $this->input('start_date'),
            'end_date' => $this->input('end_date'),
            'is_active' => $this->boolean('is_active'),
            'countries' => $this->input('countries', []),
        ];
    }
}
