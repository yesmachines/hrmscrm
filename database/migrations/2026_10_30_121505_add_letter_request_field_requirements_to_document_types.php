<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FIELD_REQUIREMENTS = [
        'noc' => [
            'to_address' => true,
            'visa_designation' => true,
            'purpose' => true,
        ],
        'salary_certificate' => [
            'salary_amount' => true,
            'currency' => true,
            'purpose' => true,
        ],
        'salary_transfer_letter' => [
            'salary_amount' => true,
            'currency' => true,
            'bank_iban' => true,
            'to_address' => true,
            'purpose' => true,
        ],
    ];

    public function up(): void
    {
        foreach (self::FIELD_REQUIREMENTS as $documentCode => $requiredFields) {
            $this->updateFieldRequirements($documentCode, $requiredFields);
        }
    }

    public function down(): void
    {
        foreach (self::FIELD_REQUIREMENTS as $documentCode => $requiredFields) {
            $this->updateFieldRequirements($documentCode, array_fill_keys(array_keys($requiredFields), false), true);
        }
    }

    /**
     * @param  array<string, bool>  $fieldUpdates
     */
    private function updateFieldRequirements(string $documentCode, array $fieldUpdates, bool $remove = false): void
    {
        $documentType = DB::table('document_types')
            ->where('document_code', $documentCode)
            ->first(['id', 'field_requires']);

        if ($documentType === null) {
            return;
        }

        $fieldRequires = json_decode($documentType->field_requires ?? '{}', true) ?: [];

        if ($remove) {
            $fieldRequires = array_diff_key($fieldRequires, $fieldUpdates);
        } else {
            $fieldRequires = array_merge($fieldRequires, $fieldUpdates);
        }

        DB::table('document_types')
            ->where('id', $documentType->id)
            ->update([
                'field_requires' => $fieldRequires === [] ? null : json_encode($fieldRequires, JSON_THROW_ON_ERROR),
            ]);
    }
};
