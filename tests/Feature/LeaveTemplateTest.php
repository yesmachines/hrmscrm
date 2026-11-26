<?php

use App\Models\Festival;
use App\Models\LeaveRequest;
use App\Models\LeaveTemplate;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use App\Models\SalesCrm\User as SalesCrmUser;
use App\Services\LeavePdfGenerationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\LeaveTemplateSeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\WorkFromHomeSeeder;
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

test('leave template seeder refreshes non-Annual defaults and preserves Annual edits', function () {
    (new LeaveTypeSeeder)->run();
    (new WorkFromHomeSeeder)->run();

    $leaveTypeNames = config('params.leave_type.names');
    $seeder = new LeaveTemplateSeeder;
    $seeder->run();

    expect(LeaveTemplate::query()->count())->toBe(count($leaveTypeNames));

    foreach ($leaveTypeNames as $code => $name) {
        $leaveType = LeaveType::query()->where('code', $code)->firstOrFail();
        $template = LeaveTemplate::query()
            ->where('leave_type_id', $leaveType->id)
            ->whereNull('organisation_id')
            ->firstOrFail();

        expect($template->status)->toBe(1)
            ->and($template->template_code)->toContain('{{leave_type_choices}}')
            ->and($template->template_code)->toContain('class="leave-declaration"');

        if ($code !== 'ANNUAL') {
            expect($template->template_name)
                ->toBe("{$name} Form")
                ->and($template->template_code)
                ->toContain("font-family: 'Times New Roman', serif; font-size: 15px;")
                ->toContain('padding: 20px;')
                ->toContain('border: 1px solid #222; padding: 6px;')
                ->toContain('LEAVE REQUEST AND HANDOVER FORM')
                ->toContain('Approval of leave:')
                ->toContain('Declaration:')
                ->toContain('HANDOVER DETAILS')
                ->toContain('color: #16a34a;')
                ->toContain('color: red;')
                ->not->toContain('{{request_type}}')
                ->not->toContain('{{approved_by}}')
                ->not->toContain('{{handover_other_organisation}}');
        }
    }

    $annualType = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();
    $annualTemplate = LeaveTemplate::query()
        ->where('leave_type_id', $annualType->id)
        ->whereNull('organisation_id')
        ->firstOrFail();

    expect($annualTemplate->template_name)->toBe('Annual Leave and Handover Form 2026')
        ->and($annualTemplate->template_code)->toContain('{{employee_name}}')
        ->and($annualTemplate->template_code)->toContain('{{handover_description}}')
        ->and($annualTemplate->template_code)->toContain('class="leave-declaration"')
        ->and($annualTemplate->template_code)->toContain('min-height: 45px;')
        ->and($annualTemplate->template_code)->toContain('height: 67px;')
        ->and($annualTemplate->template_code)->toContain('{{ticket_checkbox}}')
        ->and($annualTemplate->template_code)->toContain('YORK ENGINEERING SOLUTIONS FZC')
        ->and($annualTemplate->template_code)->not->toContain('{{company_name}}')
        ->and($annualTemplate->template_code)->not->toContain('Paternity Leave')
        ->and($annualTemplate->template_code)->not->toContain('Other ______________________________');
    expect(substr_count($annualTemplate->template_code, '{{remarks}}'))->toBe(1);
    expect(substr_count($annualTemplate->template_code, '{{employee_signature}}'))->toBe(2);

    $maternityType = LeaveType::query()->where('code', 'MATERNITY')->firstOrFail();
    $maternityTemplate = LeaveTemplate::query()->where('leave_type_id', $maternityType->id)->firstOrFail();
    expect($maternityTemplate->template_code)
        ->toContain('<tr><td colspan="2" style="border: 1px solid #222; padding: 7px;"><strong>Expected Due Date:</strong> <span style="color: red;">{{due_date}}</span></td></tr>')
        ->not->toContain('{{child_birth_date}}')
        ->not->toContain('{{ticket_checkbox}}');

    $sickType = LeaveType::query()->where('code', 'SICK')->firstOrFail();
    $sickTemplate = LeaveTemplate::query()
        ->where('leave_type_id', $sickType->id)
        ->whereNull('organisation_id')
        ->firstOrFail();
    $sickTemplate->update([
        'template_name' => "{$leaveTypeNames['SICK']} Request and Handover Form",
        'template_code' => '<p>Customized Sick template</p>',
    ]);
    $seeder->run();

    expect($sickTemplate->fresh()->template_name)->toBe("{$leaveTypeNames['SICK']} Form")
        ->and($sickTemplate->fresh()->template_code)
        ->toContain("font-family: 'Times New Roman', serif; font-size: 15px;")
        ->not->toContain('<p>Customized Sick template</p>');

    $wfhType = LeaveType::query()->where('code', 'WFH')->firstOrFail();
    $wfhTemplate = LeaveTemplate::query()->where('leave_type_id', $wfhType->id)->firstOrFail();
    expect($wfhTemplate->template_code)
        ->not->toContain('{{due_date}}')
        ->not->toContain('{{family_relationship}}');

    $annualTemplate->update([
        'template_code' => '<p>Custom layout</p><strong>Reason for requested leave:</strong><br />Annual Leave<br />Paternity Leave<br />Other ______________________________</td>',
    ]);
    $seeder->run();

    expect($annualTemplate->fresh()->template_code)
        ->toContain('<p>Custom layout</p>')
        ->toContain('{{leave_type_choices}}')
        ->not->toContain('Paternity Leave');

    $annualTemplate->update(['template_code' => '<p>Customized Annual template</p>']);
    $seeder->run();

    expect(LeaveTemplate::query()->count())->toBe(count($leaveTypeNames))
        ->and($annualTemplate->fresh()->template_code)->toBe('<p>Customized Annual template</p>');
});

test('leave template seeder groups compacted legacy Annual declaration markup', function () {
    (new LeaveTypeSeeder)->run();
    (new WorkFromHomeSeeder)->run();

    $seeder = new LeaveTemplateSeeder;
    $seeder->run();

    $annualType = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();
    $annualTemplate = LeaveTemplate::query()
        ->where('leave_type_id', $annualType->id)
        ->whereNull('organisation_id')
        ->firstOrFail();
    $legacyDeclarationBlock = <<<'HTML'
<p style="margin: 4px 0 0; font-weight: bold; text-decoration: underline;">Declaration:</p>
<p style="margin: 0; font-size: 13px; line-height: 1.3;">
  I hereby declare that I will remain available for any emergency queries as required by the company and can be reached through any designated contact method. I will also promptly inform the company in the event of any personal emergency that may affect my availability.
  <strong>Signature:</strong>
</p>
<div style="page-break-inside: avoid; margin: 0; padding: 0;"><div style="margin: 0; padding: 0; line-height: 1;"><span style="color: #16a34a;">{{employee_signature}}</span></div><div style="margin: 0; padding: 0; line-height: 1;">______________________________</div></div>
HTML;
    $templateCode = preg_replace(
        '~<table class="leave-declaration"[^>]*>.*?</table>~s',
        $legacyDeclarationBlock,
        $annualTemplate->template_code,
        1,
        $replacementCount,
    );

    expect($replacementCount)->toBe(1);

    if ($templateCode === null) {
        throw new RuntimeException('Could not create the compacted legacy Annual declaration fixture.');
    }

    $annualTemplate->update(['template_code' => $templateCode]);
    $seeder->run();

    expect($annualTemplate->fresh()->template_code)
        ->toContain('class="leave-declaration"')
        ->toContain('break-inside: avoid;');
});

test('leave template seeder restores spacing on a compacted Annual template without replacing edits', function () {
    (new LeaveTypeSeeder)->run();
    (new WorkFromHomeSeeder)->run();

    $seeder = new LeaveTemplateSeeder;
    $seeder->run();

    $annualType = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();
    $annualTemplate = LeaveTemplate::query()
        ->where('leave_type_id', $annualType->id)
        ->whereNull('organisation_id')
        ->firstOrFail();
    $replaceFirstOccurrence = static function (string $subject, string $search, string $replacement): string {
        $position = strpos($subject, $search);

        return $position === false
            ? $subject
            : substr_replace($subject, $replacement, $position, strlen($search));
    };
    $templateCode = str_replace(
        'LEAVE REQUEST AND HANDOVER FORM',
        'LEAVE REQUEST AND HANDOVER FORM - Customized',
        $annualTemplate->template_code,
    );
    $templateCode = $replaceFirstOccurrence($templateCode, 'margin-bottom: 14px;', 'margin-bottom: 8px;');
    $templateCode = $replaceFirstOccurrence($templateCode, 'margin: 0 0 12px; text-align: center;', 'margin: 0 0 10px; text-align: center;');
    $templateCode = str_replace(
        '<td colspan="2" style="border: 1px solid #222; padding: 7px; line-height: 1.5;">',
        '<td colspan="2" style="border: 1px solid #222; padding: 7px; line-height: 1.4;">',
        $templateCode,
    );
    $templateCode = $replaceFirstOccurrence($templateCode, 'min-height: 45px;', 'min-height: 40px;');
    $templateCode = $replaceFirstOccurrence($templateCode, 'margin-bottom: 9px;', 'margin-bottom: 8px;');
    $templateCode = $replaceFirstOccurrence($templateCode, 'margin-bottom: 9px;', 'margin-bottom: 8px;');
    $templateCode = $replaceFirstOccurrence($templateCode, 'margin-bottom: 9px;', 'margin-bottom: 12px;');
    $templateCode = str_replace('height: 67px;', 'height: 65px;', $templateCode);
    $templateCode = str_replace(
        '<tr><td colspan="3" style="height: 40px;',
        '<tr><td colspan="3" style="height: 38px;',
        $templateCode,
    );
    $templateCode = $replaceFirstOccurrence(
        $templateCode,
        '<p style="margin: 0; font-size: 13px; line-height: 1.35;">',
        '<p style="margin: 0; font-size: 13px; line-height: 1.3;">',
    );
    $annualTemplate->update(['template_code' => $templateCode]);

    $seeder->run();

    expect($annualTemplate->fresh()->template_code)
        ->toContain('LEAVE REQUEST AND HANDOVER FORM - Customized')
        ->toContain('margin-bottom: 14px;')
        ->toContain('margin: 0 0 12px; text-align: center;')
        ->toContain('line-height: 1.5;')
        ->toContain('{{end_date}}</span><br />')
        ->toContain('min-height: 45px;')
        ->toContain('height: 67px;')
        ->toContain('height: 40px;')
        ->toContain('<p style="margin: 0; font-size: 13px; line-height: 1.35;">');
});

test('leave template rendering fills type-specific values and external handover names', function () {
    $employee = createLeaveTemplateTestEmployee();
    $leaveType = LeaveType::query()->create([
        'leave_name' => 'Maternity Leave',
        'code' => 'MATERNITY',
        'status' => 1,
        'requires_approval' => true,
    ]);
    $festival = Festival::query()->create([
        'name' => 'National Day',
        'shortcode' => 'NATIONAL-DAY',
    ]);
    $template = LeaveTemplate::query()->create([
        'leave_type_id' => $leaveType->id,
        'template_name' => 'Specific fields template',
        'template_code' => '{{leave_type_choices}}|{{due_date}}|{{child_birth_date}}|{{family_relationship}}|{{festival_name}}|{{handover_person}}',
        'status' => 1,
    ]);

    $html = app(LeavePdfGenerationService::class)->render($template, $employee, [
        'due_date' => '2026-12-02',
        'child_birth_date' => '2026-11-01',
        'family_relationship' => 'mother',
        'festival_id' => $festival->id,
        'handover_other_name' => 'External Contact',
        'handover_other_organisation' => 'External Company',
        'handover_other_designation' => 'External Role',
        'handover_other_contact_number' => '+971501234567',
    ]);

    expect($html)
        ->toContain('Maternity Leave')
        ->toContain('>&#10003;</span>')
        ->toContain('02 Dec 2026')
        ->toContain('01 Nov 2026')
        ->toContain('Mother')
        ->toContain('National Day')
        ->toContain('External Contact')
        ->not->toContain('External Company')
        ->not->toContain('External Role')
        ->not->toContain('+971501234567');
});

test('annual leave PDF keeps the declaration on page one and handover on page two', function () {
    (new LeaveTypeSeeder)->run();
    (new WorkFromHomeSeeder)->run();
    (new LeaveTemplateSeeder)->run();

    $leaveType = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();
    $template = LeaveTemplate::query()
        ->where('leave_type_id', $leaveType->id)
        ->whereNull('organisation_id')
        ->firstOrFail();
    $employee = createLeaveTemplateTestEmployee();

    Storage::fake('public');

    $logoContent = file_get_contents(public_path('yes_machinery_logo.png'));
    if (! is_string($logoContent)) {
        throw new RuntimeException('Could not read the logo image fixture.');
    }

    Storage::disk('public')->put('signatures/template-test.png', $logoContent);

    $html = app(LeavePdfGenerationService::class)->render($template, $employee, [
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-15',
        'total_days' => 15,
        'local_contact_no' => '+971501234567',
        'traveling_outside_country' => true,
        'destination' => 'United Kingdom',
        'travel_contact_no' => '+971501234567',
        'ticket_status' => 'ticket',
        'signature_path' => 'signatures/template-test.png',
    ]);
    $pdfHtml = '<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page { margin: 0.6cm 1.2cm; }
        body { font-family: "DejaVu Sans", sans-serif; }
        p, div, span, table { max-width: 100% !important; }
        p { margin-right: 0 !important; padding-right: 0 !important; width: auto !important; }
    </style>
</head>
<body>'.$html.'</body>
</html>';
    $pdf = Pdf::loadHTML($pdfHtml)->setPaper('a4', 'portrait');
    $pdf->render();

    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBe(2);
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
