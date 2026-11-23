<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->replaceTemplateMarkup([
            [
                '<p style="margin: 10px 0 2px; font-weight: bold; text-decoration: underline;">Declaration:</p>',
                '<p style="margin: 4px 0 0; font-weight: bold; text-decoration: underline;">Declaration:</p>',
            ],
            [
                '<p style="margin: 0 0 2px; font-size: 13px;">',
                '<p style="margin: 0; font-size: 13px; line-height: 1.3;">',
            ],
            [
                '<div style="margin-bottom: 2px; line-height: 1;"><span style="color: #16a34a;">{{employee_signature}}</span></div>',
                '<div style="page-break-inside: avoid; margin: 0; padding: 0;"><div style="margin: 0; padding: 0; line-height: 1;"><span style="color: #16a34a;">{{employee_signature}}</span></div>',
            ],
            [
                '<p style="margin: 0 0 10px;">______________________________</p>',
                '<div style="margin: 0; padding: 0; line-height: 1;">______________________________</div></div>',
            ],
        ], [
            '<div style="margin-bottom: 2px; line-height: 1;"><span style="color: #16a34a;">{{employee_signature}}</span></div>',
            '<p style="margin: 0 0 10px;">______________________________</p>',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->replaceTemplateMarkup([
            [
                '<p style="margin: 4px 0 0; font-weight: bold; text-decoration: underline;">Declaration:</p>',
                '<p style="margin: 10px 0 2px; font-weight: bold; text-decoration: underline;">Declaration:</p>',
            ],
            [
                '<p style="margin: 0; font-size: 13px; line-height: 1.3;">',
                '<p style="margin: 0 0 2px; font-size: 13px;">',
            ],
            [
                '<div style="page-break-inside: avoid; margin: 0; padding: 0;"><div style="margin: 0; padding: 0; line-height: 1;"><span style="color: #16a34a;">{{employee_signature}}</span></div>',
                '<div style="margin-bottom: 2px; line-height: 1;"><span style="color: #16a34a;">{{employee_signature}}</span></div>',
            ],
            [
                '<div style="margin: 0; padding: 0; line-height: 1;">______________________________</div></div>',
                '<p style="margin: 0 0 10px;">______________________________</p>',
            ],
        ], [
            '<div style="page-break-inside: avoid; margin: 0; padding: 0;"><div style="margin: 0; padding: 0; line-height: 1;"><span style="color: #16a34a;">{{employee_signature}}</span></div>',
            '<div style="margin: 0; padding: 0; line-height: 1;">______________________________</div></div>',
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $replacements
     * @param  list<string>  $requiredMarkup
     */
    private function replaceTemplateMarkup(array $replacements, array $requiredMarkup): void
    {
        $leaveTypeId = DB::table('leave_types')
            ->where('code', 'ANNUAL')
            ->value('id');

        if ($leaveTypeId === null) {
            return;
        }

        $templates = DB::table('leave_templates')
            ->where('leave_type_id', $leaveTypeId)
            ->whereNull('organisation_id')
            ->where('template_name', 'Annual Leave and Handover Form 2026')
            ->get(['id', 'template_code']);

        foreach ($templates as $template) {
            $templateCode = (string) $template->template_code;
            $hasRequiredMarkup = true;

            foreach ($requiredMarkup as $markup) {
                if (! str_contains($templateCode, $markup)) {
                    $hasRequiredMarkup = false;

                    break;
                }
            }

            if (! $hasRequiredMarkup) {
                continue;
            }

            foreach ($replacements as [$search, $replace]) {
                $templateCode = str_replace($search, $replace, $templateCode);
            }

            if ($templateCode !== $template->template_code) {
                DB::table('leave_templates')
                    ->where('id', $template->id)
                    ->update(['template_code' => $templateCode]);
            }
        }
    }
};
