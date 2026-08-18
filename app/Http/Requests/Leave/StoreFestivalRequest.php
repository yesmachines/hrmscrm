<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class StoreFestivalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:festivals,name',
            'type' => 'required|in:festival,holiday',
            'start_date' => ['nullable', 'date', 'before_or_equal:end_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => 'boolean',
            'countries' => ['nullable', 'array'],
            'countries.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $name = $this->input('name');
        $shortcode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 3)).date('y').rand(10, 99);

        return [
            'name' => $name,
            'type' => $this->input('type', 'festival'),
            'shortcode' => $shortcode,
            'is_active' => $this->boolean('is_active'),
            'start_date' => $this->input('start_date'),
            'end_date' => $this->input('end_date'),
            'countries' => $this->input('countries', []),
        ];
    }
}
