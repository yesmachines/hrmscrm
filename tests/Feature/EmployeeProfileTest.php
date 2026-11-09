<?php

use App\Models\EmployeeProfile;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use App\Support\SalesCrmRoles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Never mass-delete Sales CRM tables. Only touch rows created inside each test.
 */
beforeEach(function () {
    $database = (string) config('database.connections.salescrm.database');

    expect($database)->toEndWith('_testing');

    if (! Schema::connection('salescrm')->hasTable('roles')) {
        return;
    }

    $sales = DB::connection('salescrm');

    foreach (['admin', 'hr', 'salesmanager'] as $role) {
        if (! $sales->table('roles')->where('name', $role)->exists()) {
            $sales->table('roles')->insert([
                'name' => $role,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    $this->departmentId = (int) $sales->table('departments')->max('id') + 1;
    $sales->table('departments')->insert([
        'id' => $this->departmentId,
        'name' => 'Employee Test Department',
        'code' => 'EMP-TEST-DEPT',
        'status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

afterEach(function () {
    // Clean only the emails/emp_nums this suite creates — never truncate tables.
    $sales = DB::connection('salescrm');
    $testEmails = [
        'test.employee@example.com',
        'optional.division@example.com',
        'old.employee@example.com',
        'delete.me@example.com',
        'upload.employee@example.com',
        'missing.department@example.com',
    ];
    $testEmpNums = ['T-9001', 'T-9002', 'T-9003', 'T-9999', 'T-9010', 'T-9011'];

    $userIds = $sales->table('users')->whereIn('email', $testEmails)->pluck('id');
    $employeeIds = $sales->table('employees')->whereIn('emp_num', $testEmpNums)->pluck('id');

    if ($employeeIds->isNotEmpty()) {
        EmployeeProfile::query()->whereIn('employee_id', $employeeIds)->delete();
        $sales->table('employees')->whereIn('id', $employeeIds)->delete();
    }

    if ($userIds->isNotEmpty()) {
        SalesCrmRoles::revokeAllRolesForUsers($userIds->all());
        $sales->table('users')->whereIn('id', $userIds)->delete();
    }

    $sales->table('departments')->where('code', 'EMP-TEST-DEPT')->delete();
});

test('guests cannot access employees', function () {
    $this->get(route('employees.index'))->assertRedirect(route('login'));
});

test('authenticated users can view the employees index', function () {
    $user = createHrmsLoginUser('admin');

    $this->actingAs($user)
        ->withoutVite()
        ->get(route('employees.index'))
        ->assertOk();
});

test('authenticated users can create an employee across salescrm and hrms', function () {
    $admin = createHrmsLoginUser('admin');

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('employees.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/create')
            ->where('genderOptions', [
                ['value' => 'M', 'label' => 'Male'],
                ['value' => 'F', 'label' => 'Female'],
            ])
            ->where('religionOptions', collect(config('params.leave_type.religion_options'))
                ->map(fn (string $religion) => ['value' => $religion, 'label' => $religion])
                ->all()));

    $this->actingAs($admin)
        ->post(route('employees.store'), [
            'name' => 'Test Employee',
            'email' => 'test.employee@example.com',
            'password' => 'Password123!',
            'roles' => 'salesmanager',
            'emp_num' => 'T-9001',
            'designation' => 'Engineer',
            'department_id' => $this->departmentId,
            'division' => 'ops',
            'employment_status' => 'fulltime',
            'status' => 1,
            'has_report' => 1,
            'nationality' => 'Indian',
            'personal_email' => 'personal@example.com',
            'gender' => 'M',
            'religion' => 'Islam',
        ])
        ->assertRedirect();

    $crmUser = SalesCrmUser::query()->where('email', 'test.employee@example.com')->first();
    expect($crmUser)->not->toBeNull();

    $employee = Employee::query()->where('emp_num', 'T-9001')->first();
    expect($employee)->not->toBeNull()
        ->and($employee->user_id)->toBe($crmUser->id)
        ->and($employee->designation)->toBe('Engineer');

    $this->assertDatabaseHas('employee_profiles', [
        'employee_id' => $employee->id,
        'nationality' => 'Indian',
        'personal_email' => 'personal@example.com',
        'religion' => 'Islam',
    ]);

    $this->actingAs($admin)
        ->get(route('employees.edit', $employee))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/edit')
            ->where('employee.email', 'test.employee@example.com')
            ->where('employee.profile.personal_email', 'personal@example.com')
            ->where('genderOptions.0.value', 'M')
            ->where('religionOptions.2.value', 'Islam'));

    $this->actingAs($admin)
        ->get(route('employees.show', $employee))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/show')
            ->where('employee.email', 'test.employee@example.com')
            ->where('employee.profile.personal_email', 'personal@example.com'));

    $roleId = DB::connection('salescrm')->table('roles')->where('name', 'salesmanager')->value('id');
    expect(
        DB::connection('salescrm')
            ->table('model_has_roles')
            ->where('model_id', $crmUser->id)
            ->where('role_id', $roleId)
            ->exists()
    )->toBeTrue();
});

test('employee division is optional on create and edit', function () {
    $admin = createHrmsLoginUser('admin');

    $this->actingAs($admin)
        ->post(route('employees.store'), [
            'name' => 'Optional Division Employee',
            'email' => 'optional.division@example.com',
            'password' => 'Password123!',
            'roles' => 'salesmanager',
            'emp_num' => 'T-9010',
            'designation' => 'Engineer',
            'department_id' => $this->departmentId,
        ])
        ->assertRedirect();

    $employee = Employee::query()->where('emp_num', 'T-9010')->firstOrFail();

    expect($employee->division)->toBe('');

    $this->actingAs($admin)
        ->put(route('employees.update', $employee), [
            'name' => 'Optional Division Employee',
            'email' => 'optional.division@example.com',
            'emp_num' => 'T-9010',
            'designation' => 'Engineer',
            'department_id' => $this->departmentId,
            'division' => '',
        ])
        ->assertRedirect(route('employees.show', $employee));

    expect($employee->fresh()->division)->toBe('');
});

test('department is required when creating and editing an employee', function () {
    $admin = createHrmsLoginUser('admin');

    $this->actingAs($admin)
        ->from(route('employees.create'))
        ->post(route('employees.store'), [
            'name' => 'Missing Department Employee',
            'email' => 'missing.department@example.com',
            'password' => 'Password123!',
            'roles' => 'salesmanager',
            'emp_num' => 'T-9011',
            'designation' => 'Engineer',
        ])
        ->assertSessionHasErrors(['department_id']);

    $user = SalesCrmUser::query()->create([
        'name' => 'Missing Department Employee',
        'email' => 'missing.department@example.com',
        'password' => 'Password123!',
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $user->id,
        'emp_num' => 'T-9011',
        'designation' => 'Engineer',
        'division' => '',
        'status' => 1,
        'has_report' => false,
    ]);

    $this->actingAs($admin)
        ->from(route('employees.edit', $employee))
        ->put(route('employees.update', $employee), [
            'name' => 'Missing Department Employee',
            'email' => 'missing.department@example.com',
            'emp_num' => 'T-9011',
            'designation' => 'Engineer',
            'department_id' => '',
        ])
        ->assertSessionHasErrors(['department_id']);
});

test('authenticated users can update an employee', function () {
    $admin = createHrmsLoginUser('admin');

    $crmUser = SalesCrmUser::query()->create([
        'name' => 'Old Name',
        'email' => 'old.employee@example.com',
        'password' => 'Password123!',
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $crmUser->id,
        'emp_num' => 'T-9002',
        'designation' => 'Analyst',
        'division' => 'sd',
        'status' => 1,
        'has_report' => true,
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $employee->id,
        'nationality' => 'Indian',
    ]);

    $this->actingAs($admin)
        ->put(route('employees.update', $employee), [
            'name' => 'New Name',
            'email' => 'old.employee@example.com',
            'emp_num' => 'T-9002',
            'designation' => 'Senior Analyst',
            'department_id' => $this->departmentId,
            'division' => 'sd',
            'status' => 1,
            'has_report' => 1,
            'nationality' => 'Emirati',
        ])
        ->assertRedirect(route('employees.show', $employee));

    expect($employee->fresh()->designation)->toBe('Senior Analyst')
        ->and($crmUser->fresh()->name)->toBe('New Name');

    $this->assertDatabaseHas('employee_profiles', [
        'employee_id' => $employee->id,
        'nationality' => 'Emirati',
    ]);
});

test('authenticated users can delete an employee from both databases', function () {
    $admin = createHrmsLoginUser('admin');

    $crmUser = SalesCrmUser::query()->create([
        'name' => 'Delete Me',
        'email' => 'delete.me@example.com',
        'password' => 'Password123!',
        'email_verified_at' => now(),
    ]);

    $employee = Employee::query()->create([
        'user_id' => $crmUser->id,
        'emp_num' => 'T-9003',
        'designation' => 'Temp',
        'division' => 'ops',
        'status' => 1,
        'has_report' => true,
    ]);

    EmployeeProfile::query()->create([
        'employee_id' => $employee->id,
        'nationality' => 'Indian',
    ]);

    $employeeId = $employee->id;
    $userId = $crmUser->id;

    $this->actingAs($admin)
        ->delete(route('employees.destroy', $employee))
        ->assertRedirect(route('employees.index'));

    expect(Employee::query()->find($employeeId))->toBeNull()
        ->and(SalesCrmUser::query()->find($userId))->toBeNull();

    $this->assertDatabaseMissing('employee_profiles', [
        'employee_id' => $employeeId,
    ]);
});

test('authenticated users can create an employee with image upload, highest education, and visa from', function () {
    Storage::fake('public');

    $admin = createHrmsLoginUser('admin');

    $fakeImage = UploadedFile::fake()->image('avatar.jpg', 200, 200);

    $this->actingAs($admin)
        ->post(route('employees.store'), [
            'name' => 'Upload Employee',
            'email' => 'upload.employee@example.com',
            'password' => 'Password123!',
            'roles' => 'salesmanager',
            'emp_num' => 'T-9999',
            'designation' => 'Tech Lead',
            'department_id' => $this->departmentId,
            'division' => 'sd',
            'employment_status' => 'fulltime',
            'status' => 1,
            'image_url' => $fakeImage,
            'highest_education' => 'Bachelors Degree',
            'visa_from' => 'Yes Machinery',
        ])
        ->assertRedirect();

    $employee = Employee::query()->where('emp_num', 'T-9999')->first();
    expect($employee)->not->toBeNull()
        ->and($employee->image_url)->not->toBeNull();

    Storage::disk('public')->assertExists($employee->image_url);

    $profile = EmployeeProfile::query()->where('employee_id', $employee->id)->first();
    expect($profile)->not->toBeNull()
        ->and($profile->highest_education)->toBe('Bachelors Degree')
        ->and($profile->visa_from)->toBe('Yes Machinery');
});
