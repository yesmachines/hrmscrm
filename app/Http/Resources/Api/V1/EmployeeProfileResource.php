<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EmployeeProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmployeeProfile
 */
class EmployeeProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'gender' => $this->gender,
            'dob_personal' => $this->dob_personal?->format('Y-m-d'),
            'marital_status' => $this->marital_status,
            'nationality' => $this->nationality,
            'religion' => $this->religion,
            'blood_group' => $this->blood_group,
            'personal_email' => $this->personal_email,
            'personal_mobile' => $this->personal_mobile,
            'address_uae' => $this->address_uae,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_relation' => $this->emergency_relation,
            'emergency_mobile' => $this->emergency_mobile,
            'home_country' => $this->home_country,
            'home_country_name' => $this->home_country_name,
            'address_home' => $this->address_home,
            'home_mobile' => $this->home_mobile,
            'home_emergency_name' => $this->home_emergency_name,
            'home_emergency_relation' => $this->home_emergency_relation,
            'home_emergency_mobile' => $this->home_emergency_mobile,
            'visa_type' => $this->visa_type,
            'visa_from' => $this->visa_from,
            'dob_passport' => $this->dob_passport?->format('Y-m-d'),
            'total_experience' => $this->total_experience,
            'highest_education' => $this->highest_education,
        ];
    }
}
