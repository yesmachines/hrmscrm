<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->json('field_requires')->nullable()->after('validity_days');
        });

        $extraFieldsByCode = [
            'visa' => ['uid' => true],
            'education' => ['issue_date' => true],
            'experience' => [
                'issue_date' => true,
                'total_experience' => true,
            ],
        ];

        DB::table('document_types')
            ->select(['id', 'document_code', 'requires_number', 'requires_expiry', 'requires_attachments'])
            ->orderBy('id')
            ->get()
            ->each(function (object $documentType) use ($extraFieldsByCode): void {
                $fieldRequires = [];

                if ($documentType->requires_number) {
                    $fieldRequires['ref_number'] = true;
                }

                if ($documentType->requires_expiry) {
                    $fieldRequires['expiry_date'] = true;
                }

                if ($documentType->requires_attachments) {
                    $fieldRequires['attachments'] = true;
                }

                $fieldRequires = array_merge(
                    $fieldRequires,
                    $extraFieldsByCode[$documentType->document_code] ?? [],
                );

                DB::table('document_types')
                    ->where('id', $documentType->id)
                    ->update([
                        'field_requires' => $fieldRequires === []
                            ? null
                            : json_encode((object) $fieldRequires),
                    ]);
            });

        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn([
                'requires_number',
                'requires_expiry',
                'requires_attachments',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->boolean('requires_number')->default(false)->after('document_code');
            $table->boolean('requires_expiry')->default(false)->after('requires_number');
            $table->boolean('requires_attachments')->default(false)->after('record_source');
        });

        DB::table('document_types')
            ->select(['id', 'field_requires'])
            ->orderBy('id')
            ->get()
            ->each(function (object $documentType): void {
                $fieldRequires = json_decode($documentType->field_requires ?? '{}', true) ?: [];

                DB::table('document_types')
                    ->where('id', $documentType->id)
                    ->update([
                        'requires_number' => (bool) ($fieldRequires['ref_number'] ?? false),
                        'requires_expiry' => (bool) ($fieldRequires['expiry_date'] ?? false),
                        'requires_attachments' => (bool) ($fieldRequires['attachments'] ?? false),
                    ]);
            });

        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn('field_requires');
        });
    }
};
