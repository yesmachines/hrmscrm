<?php

use App\Models\LeaveRequest;
use App\Models\LeaveTemplate;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use App\Services\LeavePdfGenerationService;
use Database\Seeders\AnnualLeaveTemplateSeeder;
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

test('the application layout exposes its csrf token to client requests', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('meta name="csrf-token" content="', false);
});

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

test('leave template preview receives the company logo url', function () {
    $admin = createHrmsLoginUser('admin');
    $leaveType = createLeaveTemplateTestType();
    $template = LeaveTemplate::query()->create([
        'leave_type_id' => $leaveType->id,
        'template_name' => 'Logo preview template',
        'template_code' => '<img src="{{company_logo_url}}">',
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('leave-templates.show', $template))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leave-templates/show')
            ->where('companyLogoUrl', asset('yes_machinery_logo.png'))
        );
});

test('leave template preview fills values and escapes handover tasks', function () {
    $admin = createHrmsLoginUser('admin');
    $employee = createLeaveTemplateTestEmployee();
    $leaveType = createLeaveTemplateTestType();

    LeaveTemplate::query()->create([
        'leave_type_id' => $leaveType->id,
        'template_name' => 'Preview template',
        'template_code' => '<h1>{{employee_name}}</h1><p>Date: {{issue_date}}</p><table><tbody>{{handover_description}}</tbody></table><p>{{travel_yes_checkbox}}|{{travel_no_checkbox}}|{{ticket_checkbox}}|{{no_ticket_checkbox}}</p>',
        'status' => 1,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('leave-requests.template-preview'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'values' => [
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-02',
                'handover_description' => '["Cover <inbox>", "Submit report"]',
                'traveling_outside_country' => true,
                'ticket_status' => 'no_ticket',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('template_name', 'Preview template');

    $html = $response->json('html');

    expect($html)
        ->toContain('<h1>'.$employee->user()->value('name').'</h1>')
        ->toContain('<p>Date: </p>')
        ->toContain('<td style="width: 8%; border: 1px solid #222; padding: 6px; text-align: center; vertical-align: top;">1</td>')
        ->toContain('Cover &lt;inbox&gt;')
        ->toContain('<td style="width: 8%; border: 1px solid #222; padding: 6px; text-align: center; vertical-align: top;">2</td>')
        ->toContain('<span style="color: red;">Submit report</span>')
        ->and(substr_count($html, 'height: 14px; border: 1px solid #222;'))->toBe(4)
        ->and(substr_count($html, '>&#10003;</span>'))->toBe(2)
        ->and($html)->toContain('font-family: &quot;DejaVu Sans&quot;, sans-serif;')
        ->and($html)->toContain('font-size: 12px;')
        ->and(substr_count($html, '>&nbsp;</span>'))->toBe(2)
        ->and($html)->not->toContain('&#974');
});

test('annual leave template seeder is idempotent and preserves existing edits', function () {
    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Annual Leave',
        'code' => 'ANNUAL',
        'is_paid' => true,
        'requires_approval' => true,
        'status' => 1,
    ]);

    $seeder = new AnnualLeaveTemplateSeeder;
    $seeder->run();

    $template = LeaveTemplate::query()
        ->where('leave_type_id', $leaveType->id)
        ->whereNull('organisation_id')
        ->firstOrFail();

    expect($template->status)->toBe(1)
        ->and($template->template_code)->toContain('{{employee_name}}')
        ->and($template->template_code)->toContain('{{handover_description}}')
        ->and($template->template_code)->toContain('{{ticket_checkbox}}')
        ->and($template->template_code)->toContain('YORK ENGINEERING SOLUTIONS FZC')
        ->and($template->template_code)->not->toContain('{{company_name}}')
        ->and($template->template_code)->not->toContain('{{leave_type}}')
        ->and($template->template_code)->toContain('&#10003;')
        ->and($template->template_code)->toContain('font-family: &quot;DejaVu Sans&quot;, sans-serif;')
        ->and($template->template_code)->toContain('font-size: 12px;')
        ->and($template->template_code)->not->toContain('&#974');
    expect(substr_count($template->template_code, '{{remarks}}'))->toBe(1);
    expect(substr_count($template->template_code, '{{employee_signature}}'))->toBe(2);

    $template->update(['template_code' => '<p>Customized template</p>']);
    $seeder->run();

    expect(LeaveTemplate::query()->count())->toBe(1)
        ->and($template->fresh()->template_code)->toBe('<p>Customized template</p>');
});

test('approved leave shows the appropriate notice when no PDF is available', function () {
    $admin = createHrmsLoginUser('admin');
    $employee = createLeaveTemplateTestEmployee();
    $leaveType = createLeaveTemplateTestType();
    $leaveRequest = LeaveRequest::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'request_type' => 'fullday',
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-01',
        'total_days' => 1,
        'status' => 'approved',
    ]);

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('leave-requests.show', $leaveRequest))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pdfNotice', 'No active leave template is configured for this leave type.'));

    LeaveTemplate::query()->create([
        'leave_type_id' => $leaveType->id,
        'template_name' => 'Approved leave',
        'template_code' => '<p>{{employee_name}}</p>',
        'status' => 1,
    ]);

    $this->get(route('leave-requests.show', $leaveRequest))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pdfNotice', 'This approved request does not have a generated PDF yet. Edit and save it to generate one.'));
});

test('approved leave generates a PDF that can be downloaded', function () {
    Storage::fake('public');

    $admin = createHrmsLoginUser('admin');
    $employee = createLeaveTemplateTestEmployee();
    $leaveType = createLeaveTemplateTestType();
    LeaveTemplate::query()->create([
        'leave_type_id' => $leaveType->id,
        'template_name' => 'Approved leave',
        'template_code' => '<h1>{{employee_name}}</h1><p>{{start_date}}</p><p>{{issue_date}}</p>',
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
    $leaveRequest->forceFill(['created_at' => '2026-10-15 09:00:00'])->save();

    $this->actingAs($admin)
        ->get(route('leave-requests.pdf.view', $leaveRequest))
        ->assertNotFound();

    expect(app(LeavePdfGenerationService::class)
        ->render(LeaveTemplate::query()->firstOrFail(), $employee, [], $leaveRequest))
        ->toContain('<p>15 Oct 2026</p>');

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

    $viewResponse = $this->actingAs($admin)
        ->get(route('leave-requests.pdf.view', $leaveRequest))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    expect($viewResponse->headers->get('content-disposition'))->toContain('inline');

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('leave-requests.show', $leaveRequest))
        ->assertInertia(fn (Assert $page) => $page
            ->where('generatedPdfUrl', route('leave-requests.pdf', $leaveRequest))
            ->where('viewPdfUrl', route('leave-requests.pdf.view', $leaveRequest)));
});
