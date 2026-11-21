<?php

use App\Models\LeaveRequest;
use App\Models\LeaveTemplate;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function createLeaveTemplateTestType(): LeaveType
{
    return LeaveType::query()->create([
        'leave_name' => 'Template Test Leave',
        'code' => fake()->unique()->bothify('TPL-#####'),
        'status' => 1,
        'requires_approval' => true,
    ]);
}

function createLeaveTemplateTestEmployee(): Employee
{
    return Employee::query()->create([
        'user_id' => SalesCrmUser::factory()->create()->id,
        'emp_num' => fake()->unique()->bothify('TPL-EMP-#####'),
        'employee_code' => fake()->unique()->bothify('TPL-EMP-#####'),
        'designation' => 'Engineer',
        'division' => 'Technology',
        'status' => 1,
        'has_report' => false,
    ]);
}

test('finance can manage leave templates without an extra role restriction', function () {
    $financeUser = createHrmsLoginUser('finance');
    $leaveType = createLeaveTemplateTestType();
    $payload = [
        'leave_type_id' => $leaveType->id,
        'organisation_id' => '',
        'template_name' => 'Annual leave request',
        'template_code' => '<h1>{{employee_name}}</h1>',
        'status' => 1,
    ];

    $this->actingAs($financeUser)
        ->withoutVite()
        ->get(route('leave-templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('leave-templates/index'));

    $this->actingAs($financeUser)
        ->withoutVite()
        ->get(route('leave-templates.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('leave-templates/create'));

    $this->actingAs($financeUser)
        ->post(route('leave-templates.store'), $payload)
        ->assertRedirect();

    $this->assertDatabaseHas('leave_templates', [
        'leave_type_id' => $leaveType->id,
        'template_name' => 'Annual leave request',
        'status' => 1,
    ]);

    $this->actingAs($financeUser)
        ->from(route('leave-templates.create'))
        ->post(route('leave-templates.store'), $payload)
        ->assertSessionHasErrors('leave_type_id');
});

test('leave template preview fills values and escapes handover tasks', function () {
    $admin = createHrmsLoginUser('admin');
    $employee = createLeaveTemplateTestEmployee();
    $leaveType = createLeaveTemplateTestType();

    LeaveTemplate::query()->create([
        'leave_type_id' => $leaveType->id,
        'template_name' => 'Preview template',
        'template_code' => '<h1>{{employee_name}}</h1>{{handover_description}}',
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->postJson(route('leave-requests.template-preview'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'values' => [
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-02',
                'handover_description' => '["Cover <inbox>"]',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('template_name', 'Preview template')
        ->assertJsonPath(
            'html',
            '<h1>'.$employee->user()->value('name').'</h1><ul><li>Cover &lt;inbox&gt;</li></ul>',
        );
});

test('approved leave generates a PDF that can be downloaded', function () {
    Storage::fake('public');

    $admin = createHrmsLoginUser('admin');
    $employee = createLeaveTemplateTestEmployee();
    $leaveType = createLeaveTemplateTestType();
    LeaveTemplate::query()->create([
        'leave_type_id' => $leaveType->id,
        'template_name' => 'Approved leave',
        'template_code' => '<h1>{{employee_name}}</h1><p>{{start_date}}</p>',
        'status' => 1,
    ]);
    $leaveRequest = LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'request_type' => 'fullday',
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-01',
        'total_days' => 1,
        'status' => 'applied',
    ]);

    $this->actingAs($admin)
        ->put(route('leave-requests.update', $leaveRequest), ['status' => 'approved'])
        ->assertRedirect();

    $generatedFile = $leaveRequest->generatedFiles()->firstOrFail();
    expect($leaveRequest->fresh()->status)->toBe('approved');
    Storage::disk('public')->assertExists($generatedFile->file_path);
    expect(Storage::disk('public')->get($generatedFile->file_path))->toStartWith('%PDF');

    $this->actingAs($admin)
        ->get(route('leave-requests.pdf', $leaveRequest))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
