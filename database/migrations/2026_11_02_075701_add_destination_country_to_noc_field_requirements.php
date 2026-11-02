<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateNocFieldRequirement(true);
    }

    public function down(): void
    {
        $this->updateNocFieldRequirement(false);
    }

    private function updateNocFieldRequirement(bool $add): void
    {
        $documentType = DB::table('document_types')
            ->where('document_code', 'noc')
            ->first(['id', 'field_requires']);

        if ($documentType === null) {
            return;
        }

        $fieldRequires = json_decode($documentType->field_requires ?? '{}', true) ?: [];

        if ($add) {
            $fieldRequires['destination_country'] = true;
        } else {
            unset($fieldRequires['destination_country']);
        }

        DB::table('document_types')
            ->where('id', $documentType->id)
            ->update([
                'field_requires' => $fieldRequires === [] ? null : json_encode($fieldRequires, JSON_THROW_ON_ERROR),
            ]);
    }
};
