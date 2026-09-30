<?php

use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Organisation;

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
            'requires_number' => 1,
            'requires_expiry' => 1,
            'editable_before_approval' => 0,
            'requires_hr_approval' => 1,
            'requires_reminder' => 1,
            'record_source' => 'uploaded',
            'requires_attachments' => 1,
            'validity_days' => 365,
        ])
        ->assertRedirect();

    $type = DocumentType::query()->where('document_code', 'passport')->first();
    expect($type)->not->toBeNull()
        ->and($type->requires_number)->toBeTrue()
        ->and($type->record_source)->toBe('uploaded')
        ->and($type->validity_days)->toBe(365);

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
