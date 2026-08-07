<?php

namespace App\Http\Resources\Api\V1;

use App\Models\SalesCrm\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'emp_num' => $this->emp_num,
            'employee_code' => $this->employee_code,
            'phone' => $this->phone,
            'designation' => $this->designation,
            'division' => $this->division,
            'employment_status' => $this->employment_status,
            'joining_date' => $this->joining_date?->format('Y-m-d'),
            'status' => $this->status,
            'department_id' => $this->department_id,
            'organisation_id' => $this->organisation_id,
            'office_location_id' => $this->office_location_id,
            'image_url' => $this->image_url,
        ];
    }
}
