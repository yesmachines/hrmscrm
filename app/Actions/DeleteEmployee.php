<?php

namespace App\Actions;

use App\Models\EmployeeProfile;
use App\Models\SalesCrm\Employee;
use App\Support\SalesCrmRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeleteEmployee
{
    /**
     * @throws Throwable
     */
    public function handle(Employee $employee): void
    {
        $sales = DB::connection('salescrm');
        $hrms = DB::connection();
        $signaturePath = EmployeeProfile::query()
            ->where('employee_id', $employee->id)
            ->value('signature_path');

        $sales->beginTransaction();
        $hrms->beginTransaction();

        try {
            if ($employee->image_url && Storage::disk('public')->exists($employee->image_url)) {
                Storage::disk('public')->delete($employee->image_url);
            }

            EmployeeProfile::query()
                ->where('employee_id', $employee->id)
                ->delete();

            $user = $employee->user;
            $userId = $employee->user_id;

            $employee->delete();

            if ($userId) {
                SalesCrmRoles::revokeAllRoles((int) $userId);
            }

            $user?->delete();

            $sales->commit();
            $hrms->commit();

            if ($signaturePath !== null) {
                Storage::disk('public')->delete($signaturePath);
            }
        } catch (Throwable $exception) {
            $sales->rollBack();
            $hrms->rollBack();

            throw $exception;
        }
    }
}
