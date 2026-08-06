<?php

use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;

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
        ])
        ->assertRedirect();

    $type = DocumentType::query()->where('document_code', 'passport')->first();
    expect($type)->not->toBeNull()
        ->and($type->requires_number)->toBeTrue()
        ->and($type->record_source)->toBe('uploaded');

    $this->actingAs($admin)
        ->post(route('document-templates.store'), [
            'document_type_id' => $type->id,
            'template_name' => 'Passport Template',
            'template_code' => 'passport_tpl',
            'status' => 1,
        ])
        ->assertRedirect();

    $template = DocumentTemplate::query()->where('template_code', 'passport_tpl')->first();
    expect($template)->not->toBeNull();

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
