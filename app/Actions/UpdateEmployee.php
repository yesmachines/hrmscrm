<?php

namespace App\Actions;

use App\Models\EmployeeProfile;
use App\Models\SalesCrm\Employee;
use App\Support\SalesCrmRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UpdateEmployee
{
    /**
     * @param  array<string, mixed>  $crmUser
     * @param  array<string, mixed>  $crmEmployee
     * @param  array<string, mixed>  $profile
     * @param  list<string>  $roles
     *
     * @throws Throwable
     */
    public function handle(
        Employee $employee,
        array $crmUser,
        array $crmEmployee,
        array $profile,
        array $roles = [],
    ): Employee {
        $sales = DB::connection('salescrm');
        $hrms = DB::connection();
        $previousSignaturePath = EmployeeProfile::query()
            ->where('employee_id', $employee->id)
            ->value('signature_path');
        $newSignaturePath = $profile['signature_path'] ?? null;

        $sales->beginTransaction();
        $hrms->beginTransaction();

        try {
            $employee->loadMissing('user');

            if ($employee->user !== null && $crmUser !== []) {
                $employee->user->update($crmUser);
            }

            if (isset($crmEmployee['image_url']) && $employee->image_url && Storage::disk('public')->exists($employee->image_url)) {
                Storage::disk('public')->delete($employee->image_url);
            }

            $employee->update($crmEmployee);

            if ($roles !== [] && $employee->user_id) {
                SalesCrmRoles::syncRoles((int) $employee->user_id, $roles);
            }

            EmployeeProfile::query()->updateOrCreate(
                ['employee_id' => $employee->id],
                $profile,
            );

            $sales->commit();
            $hrms->commit();

            if (
                $newSignaturePath !== null
                && $previousSignaturePath !== null
                && $newSignaturePath !== $previousSignaturePath
            ) {
                Storage::disk('public')->delete($previousSignaturePath);
            }

            return $employee->fresh(['user']) ?? $employee;
        } catch (Throwable $exception) {
            $sales->rollBack();
            $hrms->rollBack();

            if ($newSignaturePath !== null && $newSignaturePath !== $previousSignaturePath) {
                Storage::disk('public')->delete($newSignaturePath);
            }

            throw $exception;
        }
    }
}
