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
        $this->replaceAnnualLeaveTemplateMarkup([
            [
                '<span style="display: inline-block; width: 14px; height: 14px; border: 1px solid #222; text-align: center; vertical-align: middle; line-height: 12px; font-size: 10px;">x</span>',
                '<span style="display: inline-block; width: 14px; height: 14px; border: 1px solid #222; text-align: center; vertical-align: middle; line-height: 12px; font-size: 10px;">&#10003;</span>',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->replaceAnnualLeaveTemplateMarkup([
            [
                '<span style="display: inline-block; width: 14px; height: 14px; border: 1px solid #222; text-align: center; vertical-align: middle; line-height: 12px; font-size: 10px;">&#10003;</span>',
                '<span style="display: inline-block; width: 14px; height: 14px; border: 1px solid #222; text-align: center; vertical-align: middle; line-height: 12px; font-size: 10px;">x</span>',
            ],
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $replacements
     */
    private function replaceAnnualLeaveTemplateMarkup(array $replacements): void
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
