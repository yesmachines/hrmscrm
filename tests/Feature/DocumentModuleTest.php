<?php

use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\EmployeeDocument;
use App\Models\Organisation;
use App\Services\LetterRequestService;
use Database\Seeders\DocumentSeeder;

test('authenticated users can manage document categories types and templates', function () {
    $admin = createHrmsLoginUser('admin');

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('document-categories.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('document-categories.store'), [
            'category_name' => 'Employment',
            'short_code' => 'EMP',
            'status' => 1,
            'parent_id' => null,
        ])
        ->assertRedirect();

    $category = DocumentCategory::query()->where('short_code', 'EMP')->first();
    expect($category)->not->toBeNull();

    $this->actingAs($admin)
        ->post(route('document-types.store'), [
            'category_id' => $category->id,
            'document_name' => 'Passport',
            'document_code' => 'passport',
            'editable_before_approval' => 0,
            'requires_hr_approval' => 1,
            'requires_reminder' => 1,
            'requires_number' => 1,
            'requires_expiry' => 1,
            'requires_issue_date' => 0,
            'requires_attachments' => 1,
            'requires_uid' => 0,
            'requires_total_experience' => 0,
            'record_source' => 'uploaded',
            'validity_days' => 365,
        ])
        ->assertRedirect();

    $type = DocumentType::query()->where('document_code', 'passport')->first();
    expect($type)->not->toBeNull()
        ->and($type->field_requires)->toBe([
            'ref_number' => true,
            'expiry_date' => true,
            'issue_date' => false,
            'attachments' => true,
            'uid' => false,
            'total_experience' => false,
        ])
        ->and($type->record_source)->toBe('uploaded')
        ->and($type->validity_days)->toBe(365);

    $this->actingAs($admin)
        ->post(route('document-types.store'), [
            'category_id' => $category->id,
            'document_name' => 'Unconfigured Document',
            'document_code' => 'unconfigured_document',
            'requires_number' => 0,
            'requires_expiry' => 0,
            'requires_issue_date' => 0,
            'requires_attachments' => 0,
            'requires_uid' => 0,
            'requires_total_experience' => 0,
        ])
        ->assertRedirect();

    $unconfiguredType = DocumentType::query()
        ->where('document_code', 'unconfigured_document')
        ->first();
    expect($unconfiguredType?->field_requires)->toBe([
        'ref_number' => false,
        'expiry_date' => false,
        'issue_date' => false,
        'attachments' => false,
        'uid' => false,
        'total_experience' => false,
    ]);

    $org = Organisation::query()->firstOrCreate(
        ['short_name' => 'YESM'],
        ['org_name' => 'Yes Machinery', 'status' => 1]
    );

    $this->actingAs($admin)
        ->post(route('document-templates.store'), [
            'document_type_id' => $type->id,
            'organisation_id' => $org->id,
            'template_name' => 'Passport Template',
            'template_code' => 'passport_tpl',
            'status' => 1,
        ])
        ->assertRedirect();

    $template = DocumentTemplate::query()->where('template_code', 'passport_tpl')->first();
    expect($template)->not->toBeNull()
        ->and($template->organisation_id)->toBe($org->id)
        ->and($template->organisation?->id)->toBe($org->id);

    $this->actingAs($admin)
        ->delete(route('document-templates.destroy', $template))
        ->assertRedirect(route('document-templates.index'));

    $this->actingAs($admin)
        ->delete(route('document-types.destroy', $type))
        ->assertRedirect(route('document-types.index'));

    $this->actingAs($admin)
        ->delete(route('document-categories.destroy', $category))
        ->assertRedirect(route('document-categories.index'));
});

test('document templates resolve organisation specific template first then fallback to global template for all organisations', function () {
    $category = DocumentCategory::query()->create([
        'category_name' => 'General Letters',
        'short_code' => 'GEN_TEST',
        'status' => 1,
    ]);

    $type = DocumentType::query()->create([
        'category_id' => $category->id,
        'document_name' => 'Salary Certificate Test',
        'document_code' => 'salary_cert_test',
    ]);

    $org1 = Organisation::query()->firstOrCreate(
        ['short_name' => 'ORG1_T'],
        ['org_name' => 'Organisation One', 'status' => 1]
    );

    $org2 = Organisation::query()->firstOrCreate(
        ['short_name' => 'ORG2_T'],
        ['org_name' => 'Organisation Two', 'status' => 1]
    );

    // 1. Create a global template (organisation_id = null)
    $globalTemplate = DocumentTemplate::query()->create([
        'document_type_id' => $type->id,
        'organisation_id' => null,
        'template_name' => 'General Salary Certificate',
        'template_code' => '<div>Global Template</div>',
        'status' => 1,
    ]);

    // For any organisation without specific template, it resolves to globalTemplate
    expect($type->resolveTemplate($org1->id)->id)->toBe($globalTemplate->id);
    expect($type->resolveTemplate($org2->id)->id)->toBe($globalTemplate->id);
    expect($type->resolveTemplate(null)->id)->toBe($globalTemplate->id);

    // 2. Now create an organisation-specific template for org1
    $org1Template = DocumentTemplate::query()->create([
        'document_type_id' => $type->id,
        'organisation_id' => $org1->id,
        'template_name' => 'Org 1 Salary Certificate',
        'template_code' => '<div>Org 1 Template</div>',
        'status' => 1,
    ]);

    // Org 1 resolves to its own template, while Org 2 continues using the global template
    expect($type->resolveTemplate($org1->id)->id)->toBe($org1Template->id);
    expect($type->resolveTemplate($org2->id)->id)->toBe($globalTemplate->id);
    expect($type->resolveTemplate(null)->id)->toBe($globalTemplate->id);
});

test('document seeder includes all valid local templates with configurable currency placeholders', function () {
    $this->seed(DocumentSeeder::class);

    $expectedTemplates = [
        'air_ticket_claim' => 'Dependent Air Ticket Claim Form Template',
        'education_allowance_claim' => 'Education Allowance Claim Form Template',
        'noc' => 'Standard NOC Template',
        'nomination_form' => 'Employee Nominee Declaration Form Template',
        'pay_slip' => 'Standard Pay Slip Template',
        'salary_certificate' => 'Standard Salary Certificate',
        'salary_transfer_letter' => 'Standard Salary Transfer Letter',
    ];

    foreach ($expectedTemplates as $documentCode => $templateName) {
        $documentType = DocumentType::query()
            ->where('document_code', $documentCode)
            ->firstOrFail();

        expect(DocumentTemplate::query()
            ->where('document_type_id', $documentType->id)
            ->where('template_name', $templateName)
            ->exists())->toBeTrue();
    }

    foreach (['education_allowance_claim', 'noc', 'salary_certificate', 'salary_transfer_letter'] as $documentCode) {
        $template = DocumentTemplate::query()
            ->whereHas('documentType', fn ($query) => $query->where('document_code', $documentCode))
            ->firstOrFail();

        expect($template->template_code)
            ->toContain('{{currency}}')
            ->not->toContain('AED');
    }

    foreach (['noc', 'salary_certificate', 'salary_transfer_letter'] as $documentCode) {
        $template = DocumentTemplate::query()
            ->whereHas('documentType', fn ($query) => $query->where('document_code', $documentCode))
            ->firstOrFail();

        expect($template->template_code)
            ->toContain('<div class="letter-header">')
            ->toContain('<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">')
            ->toContain('class="letter-footer"')
            ->toContain('width: 75px; height: auto;')
            ->toContain('margin-top: 90px; text-align: center; font-size: 9px; line-height: 1.15;')
            ->toContain("font-family: 'DejaVu Sans', sans-serif;")
            ->toContain('height: 6px; background-color: #7AC142;');
    }

    $paySlipTemplate = DocumentTemplate::query()
        ->whereHas('documentType', fn ($query) => $query->where('document_code', 'pay_slip'))
        ->firstOrFail();
    expect($paySlipTemplate->template_code)
        ->toContain('src="{{company_logo_url}}"')
        ->not->toContain('Logo Approximation');
});

test('letter template renderer uses submitted currency or defaults to AED', function () {
    $this->seed(DocumentSeeder::class);

    $documentType = DocumentType::query()
        ->where('document_code', 'noc')
        ->firstOrFail();
    $template = DocumentTemplate::query()->create([
        'document_type_id' => $documentType->id,
        'template_name' => 'Currency rendering test',
        'template_code' => '{{currency}} {{gross_salary}}',
        'status' => 1,
    ]);

    $document = EmployeeDocument::query()->create([
        'employee_id' => 999999,
        'document_type_id' => $documentType->id,
        'document_title' => 'Currency rendering test',
        'document_template_id' => $template->id,
        'status' => 'submitted',
    ]);
    $document->requestDetails()->create([
        'field_name' => 'Currency',
        'field_key' => 'currency',
        'field_value' => 'USD',
    ]);

    $service = new LetterRequestService;
    expect($service->renderDocumentHtml($document->fresh()))
        ->toContain('USD 0.00');

    $documentWithoutCurrency = EmployeeDocument::query()->create([
        'employee_id' => 999998,
        'document_type_id' => $documentType->id,
        'document_title' => 'Currency fallback test',
        'document_template_id' => $template->id,
        'status' => 'submitted',
    ]);

    expect($service->renderDocumentHtml($documentWithoutCurrency))
        ->toContain('AED 0.00');
});

test('NOC renderer embeds the repository company logo asset', function () {
    $this->seed(DocumentSeeder::class);

    $nocType = DocumentType::query()
        ->where('document_code', 'noc')
        ->firstOrFail();
    $template = DocumentTemplate::query()
        ->where('document_type_id', $nocType->id)
        ->where('template_name', 'Standard NOC Template')
        ->firstOrFail();
    $document = EmployeeDocument::query()->create([
        'employee_id' => 999997,
        'document_type_id' => $nocType->id,
        'document_title' => 'NOC Logo Test',
        'document_template_id' => $template->id,
        'status' => 'submitted',
    ]);

    $renderedHtml = (new LetterRequestService)->renderDocumentHtml($document);

    expect($renderedHtml)
        ->toContain('src="data:image/png;base64,')
        ->not->toContain('{{company_logo_url}}')
        ->not->toContain('>YES</div>');

    expect($template->template_code)
        ->toContain('<div class="letter-header">')
        ->toContain('<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">')
        ->toContain('class="letter-footer"')
        ->toContain('width: 75px; height: auto;')
        ->toContain('margin-top: 90px; text-align: center; font-size: 9px; line-height: 1.15;')
        ->toContain('height: 6px; background-color: #7AC142;')
        ->not->toContain('display: flex');
});

test('document template preview resolves the logo URL without changing the saved HTML', function () {
    $admin = createHrmsLoginUser('admin');
    $this->seed(DocumentSeeder::class);

    $template = DocumentTemplate::query()
        ->whereHas('documentType', fn ($query) => $query->where('document_code', 'noc'))
        ->where('template_name', 'Standard NOC Template')
        ->firstOrFail();

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('document-templates.show', $template))
        ->assertInertia(fn ($page) => $page
            ->component('document-templates/show')
            ->where('companyLogoUrl', asset('yes_machinery_logo.png'))
            ->where('template.template_code', $template->template_code));
});
