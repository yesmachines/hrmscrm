<?php

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class LocalApiEmployeeSeeder extends Seeder
{
    private const EMAIL = 'api.employee@example.test';

    private const PASSWORD = 'LocalApi123!';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('The local API employee seeder can only run in the local environment.');
        }

        $user = User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Local API Test Employee',
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
            ],
        );

        $employee = Employee::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'emp_num' => 'EMP-LOCAL-API-001',
                'employee_code' => 'LOCAL-API-001',
                'designation' => 'Test Employee',
                'division' => 'Testing',
                'status' => 1,
                'has_report' => false,
            ],
        );

        EmployeeProfile::query()->updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'gender' => 'M',
                'dob_personal' => '1990-01-15',
                'marital_status' => 'single',
                'nationality' => 'Test',
                'religion' => 'Other',
                'personal_email' => self::EMAIL,
            ],
        );

        $this->command?->info('Local API test employee and HRMS profile created.');
        $this->command?->line('Email: '.self::EMAIL);
        $this->command?->line('Password: '.self::PASSWORD);
    }
}
