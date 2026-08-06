<?php

namespace App\Actions;

use App\Models\EmployeeProfile;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User;
use App\Support\SalesCrmRoles;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateEmployee
{
    /**
     * Create an employee the same way Sales CRM does (cm_users + cm_employees),
     * then populate HRMS employee_profiles linked by employee_id.
     *
     * @param  array<string, mixed>  $crmUser
     * @param  array<string, mixed>  $crmEmployee
     * @param  array<string, mixed>  $profile
     * @param  list<string>  $roles  Spatie role names in Sales CRM
     *
     * @throws Throwable
     */
    public function handle(
        array $crmUser,
        array $crmEmployee,
        array $profile,
        array $roles = [],
    ): Employee {
        $sales = DB::connection('salescrm');
        $hrms = DB::connection();

        $sales->beginTransaction();
        $hrms->beginTransaction();

        try {
            $user = User::query()->create([
                'name' => $crmUser['name'],
                'email' => $crmUser['email'],
                'password' => $crmUser['password'],
                'email_verified_at' => now(),
            ]);

            SalesCrmRoles::assignRoles($user->id, $roles);

            $employee = Employee::query()->create([
                'user_id' => $user->id,
                'emp_num' => $crmEmployee['emp_num'],
                'phone' => $crmEmployee['phone'] ?? null,
                'designation' => $crmEmployee['designation'],
                'division' => $crmEmployee['division'],
                'status' => (int) ($crmEmployee['status'] ?? 1),
                'image_url' => $crmEmployee['image_url'] ?? null,
                'organisation_id' => $crmEmployee['organisation_id'] ?? null,
                'employee_code' => $crmEmployee['employee_code'] ?? null,
                'designation_id' => $crmEmployee['designation_id'] ?? null,
                'employment_status' => $crmEmployee['employment_status'] ?? null,
                'office_location_id' => $crmEmployee['office_location_id'] ?? null,
                'joining_date' => $crmEmployee['joining_date'] ?? null,
                'resignation_date' => $crmEmployee['resignation_date'] ?? null,
                'has_report' => (bool) ($crmEmployee['has_report'] ?? true),
                'department_id' => $crmEmployee['department_id'] ?? null,
            ]);

            EmployeeProfile::query()->create([
                'employee_id' => $employee->id,
                'gender' => $profile['gender'] ?? null,
                'dob_personal' => $profile['dob_personal'] ?? null,
                'marital_status' => $profile['marital_status'] ?? null,
                'nationality' => $profile['nationality'] ?? null,
                'religion' => $profile['religion'] ?? null,
                'blood_group' => $profile['blood_group'] ?? null,
                'personal_email' => $profile['personal_email'] ?? null,
                'personal_mobile' => $profile['personal_mobile'] ?? null,
                'address_uae' => $profile['address_uae'] ?? null,
                'emergency_contact_name' => $profile['emergency_contact_name'] ?? null,
                'emergency_relation' => $profile['emergency_relation'] ?? null,
                'emergency_mobile' => $profile['emergency_mobile'] ?? null,
                'home_country' => $profile['home_country'] ?? null,
                'address_home' => $profile['address_home'] ?? null,
                'home_mobile' => $profile['home_mobile'] ?? null,
                'home_emergency_name' => $profile['home_emergency_name'] ?? null,
                'home_emergency_relation' => $profile['home_emergency_relation'] ?? null,
                'home_emergency_mobile' => $profile['home_emergency_mobile'] ?? null,
                'visa_type' => $profile['visa_type'] ?? null,
                'visa_from' => $profile['visa_from'] ?? null,
                'dob_passport' => $profile['dob_passport'] ?? null,
                'total_experience' => $profile['total_experience'] ?? null,
                'highest_education' => $profile['highest_education'] ?? null,
            ]);

            $sales->commit();
            $hrms->commit();

            return $employee->load('user');
        } catch (Throwable $exception) {
            $sales->rollBack();
            $hrms->rollBack();

            throw $exception;
        }
    }
}
