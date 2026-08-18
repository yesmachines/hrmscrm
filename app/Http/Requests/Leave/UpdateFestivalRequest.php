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
        $isFestival = $this->input('type', 'festival') === 'festival';

        return [
            'name' => ['required', 'string', 'max:255', 'unique:festivals,name,'.$this->route('festival')->id],
            'type' => ['required', 'in:festival,holiday'],
            'start_date' => $isFestival
                ? ['required', 'date']
                : ['required', 'date', 'before_or_equal:end_date'],
            'end_date' => $isFestival
                ? ['nullable', 'date']
                : ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
            'countries' => ['nullable', 'array'],
            'countries.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $type = $this->input('type', 'festival');
        $startDate = $this->input('start_date');
        $endDate = $type === 'festival' ? $startDate : $this->input('end_date');

        return [
            'name' => $this->input('name'),
            'type' => $type,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_active' => $this->boolean('is_active'),
            'countries' => $this->input('countries', []),
        ];
    }
}
