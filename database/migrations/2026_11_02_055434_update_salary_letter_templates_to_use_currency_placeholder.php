<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceTemplateText('salary_certificate', 'Amount (AED)', 'Amount ({{currency}})');
        $this->replaceTemplateText(
            'salary_transfer_letter',
            'AED {{gross_salary}}',
            '{{currency}} {{gross_salary}}',
        );
    }

    public function down(): void
    {
        $this->replaceTemplateText('salary_certificate', 'Amount ({{currency}})', 'Amount (AED)');
        $this->replaceTemplateText(
            'salary_transfer_letter',
            '{{currency}} {{gross_salary}}',
            'AED {{gross_salary}}',
        );
    }

    private function replaceTemplateText(string $documentCode, string $search, string $replace): void
    {
        $documentTypeId = DB::table('document_types')
            ->where('document_code', $documentCode)
            ->value('id');

        if ($documentTypeId === null) {
            return;
        }

        $templates = DB::table('document_templates')
            ->where('document_type_id', $documentTypeId)
            ->get(['id', 'template_code']);

        foreach ($templates as $template) {
            $updatedCode = str_replace($search, $replace, $template->template_code);

            if ($updatedCode !== $template->template_code) {
                DB::table('document_templates')
                    ->where('id', $template->id)
                    ->update(['template_code' => $updatedCode]);
            }
        }
    }
};
