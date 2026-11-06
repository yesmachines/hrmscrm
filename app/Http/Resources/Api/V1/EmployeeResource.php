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
        $roles = $this->user?->roleNames() ?? [];

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'roles' => $roles[0] ?? null,
            'organisation_id' => $this->organisation_id,
            'emp_num' => $this->emp_num,
            'employee_code' => $this->employee_code,
            'phone' => $this->phone,
            'designation' => $this->designation,
            'designation_id' => $this->designation_id,
            'employment_status' => $this->employment_status,
            'office_location_id' => $this->office_location_id,
            'joining_date' => $this->joining_date?->format('Y-m-d'),
            'resignation_date' => $this->resignation_date?->format('Y-m-d'),
            'division' => $this->division,
            'division_name' => $this->division_name,
            'image_url' => $this->image_url
                ? (str_starts_with($this->image_url, 'http://') || str_starts_with($this->image_url, 'https://')
                    ? $this->image_url
                    : asset('storage/'.ltrim($this->image_url, '/')))
                : null,
            'image_path' => $this->image_url,
            'status' => $this->status,
            'has_report' => $this->has_report,
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ] : null),
            'organisation' => $this->whenLoaded('organisation', fn () => $this->organisation ? [
                'id' => $this->organisation->id,
                'name' => $this->organisation->org_name,
                'short_name' => $this->organisation->short_name,
            ] : null),
            'office_location' => $this->whenLoaded('officeLocation', fn () => $this->officeLocation ? [
                'id' => $this->officeLocation->id,
                'name' => $this->officeLocation->office_name,
                'city' => $this->officeLocation->city,
            ] : null),
            'profile' => $this->whenLoaded(
                'profileDetails',
                fn () => $this->profileDetails ? new EmployeeProfileResource($this->profileDetails) : null,
            ),
        ];
    }
}
