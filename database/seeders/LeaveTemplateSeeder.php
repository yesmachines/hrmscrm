<?php

namespace Database\Seeders;

use App\Models\LeaveTemplate;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;
use RuntimeException;

class LeaveTemplateSeeder extends Seeder
{
    private const ANNUAL_TEMPLATE_NAME = 'Annual Leave and Handover Form 2026';

    private const LEAVE_TYPE_CHOICES_PLACEHOLDER = '{{leave_type_choices}}';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $leaveTypeNames = config('params.leave_type.names');

        if (! is_array($leaveTypeNames) || $leaveTypeNames === []) {
            throw new RuntimeException('Cannot seed leave templates because no leave type names are configured.');
        }

        $leaveTypes = LeaveType::query()
            ->whereIn('code', array_keys($leaveTypeNames))
            ->get()
            ->keyBy('code');
        $missingCodes = array_diff(array_keys($leaveTypeNames), $leaveTypes->keys()->all());

        if ($missingCodes !== []) {
            throw new RuntimeException(sprintf(
                'Cannot seed leave templates because these leave types do not exist: %s.',
                implode(', ', $missingCodes),
            ));
        }

        foreach ($leaveTypeNames as $code => $name) {
            if (! is_string($code) || ! is_string($name) || $name === '') {
                throw new RuntimeException('Cannot seed leave templates because the configured leave type names are invalid.');
            }

            $leaveType = $leaveTypes->get($code);
            if (! $leaveType instanceof LeaveType) {
                throw new RuntimeException("Cannot seed a template because leave type [{$code}] does not exist.");
            }

            $templateName = $code === 'ANNUAL'
                ? self::ANNUAL_TEMPLATE_NAME
                : "{$name} Form";
            $templateCode = $code === 'ANNUAL'
                ? $this->annualTemplateCode()
                : $this->leaveTemplateCode($code);

            $templateAttributes = [
                'leave_type_id' => $leaveType->id,
                'organisation_id' => null,
                'template_name' => $templateName,
            ];
            $template = LeaveTemplate::withTrashed()->firstOrNew($templateAttributes);

            if (! $template->exists && $code !== 'ANNUAL') {
                $legacyTemplate = LeaveTemplate::withTrashed()->firstOrNew([
                    'leave_type_id' => $leaveType->id,
                    'organisation_id' => null,
                    'template_name' => "{$name} Request and Handover Form",
                ]);

                if ($legacyTemplate->exists) {
                    $legacyTemplate->template_name = $templateName;

                    if (! $legacyTemplate->save()) {
                        throw new RuntimeException("Could not rename the legacy leave template for type [{$code}].");
                    }

                    $template = $legacyTemplate;
                }
            }

            if (! $template->exists) {
                $template = LeaveTemplate::withTrashed()->firstOrCreate(
                    $templateAttributes,
                    [
                        'template_code' => $templateCode,
                        'status' => 1,
                    ],
                );
            }

            if ($code !== 'ANNUAL' && $template->template_code !== $templateCode) {
                if (! $template->update(['template_code' => $templateCode])) {
                    throw new RuntimeException("Could not update the default leave template for type [{$code}].");
                }
            }

            if ($template->trashed() && ! $template->restore()) {
                throw new RuntimeException("Could not restore the leave template for type [{$code}].");
            }

            if ($code === 'ANNUAL') {
                $this->replaceLegacyLeaveTypeChoices($template);
                $this->applyAnnualPageFit($template);
            }
        }
    }

    private function annualTemplateCode(): string
    {
        $declarationBlock = $this->declarationBlock();

        return <<<HTML
<div style="font-family: 'Times New Roman', serif; font-size: 15px; line-height: 1.5; color: #333; max-width: 800px; margin: auto; padding: 20px;">
  <table style="width: 100%; border-collapse: collapse; margin-bottom: 14px;">
    <tr>
      <td style="width: 25%; vertical-align: bottom; padding: 0;">
        <img src="{{company_logo_url}}" alt="YES Machinery" style="width: 75px; height: auto; display: block;" />
      </td>
      <td style="width: 75%; vertical-align: bottom; text-align: right; border-bottom: 2px solid #7AC142; padding: 0 0 5px;">
        <h3 style="margin: 0; font-family: Arial, sans-serif; font-size: 16px; color: #333; font-weight: bold;">YORK ENGINEERING SOLUTIONS FZC</h3>
      </td>
    </tr>
  </table>

  <h2 style="margin: 0 0 12px; text-align: center; font-family: Arial, sans-serif; font-size: 17px; font-weight: bold; text-decoration: underline;">LEAVE REQUEST AND HANDOVER FORM</h2>

  <table style="width: 100%; border-collapse: collapse; margin-bottom: 9px;">
    <tr>
      <td style="width: 50%; border: 1px solid #222; padding: 6px;"><strong>NAME:</strong> <span style="color: #16a34a; font-weight: bold;">{{employee_name}}</span></td>
      <td style="width: 50%; border: 1px solid #222; padding: 6px;"><strong>DEPARTMENT:</strong> <span style="color: #16a34a;">{{department}}</span></td>
    </tr>
    <tr>
      <td colspan="2" style="border: 1px solid #222; padding: 7px; line-height: 1.5;">
        <strong>Reason for requested leave:</strong><br />
        {{leave_type_choices}}
      </td>
    </tr>
    <tr>
      <td colspan="2" style="border: 1px solid #222; padding: 8px;">
        <strong>Dates Requested:</strong>&nbsp; From: <span style="color: red;">{{start_date}}</span>&nbsp;&nbsp; To: <span style="color: red;">{{end_date}}</span><br />
        <strong>No. of days requested:</strong> <span style="color: red;">{{total_days}}</span>
      </td>
    </tr>
  </table>

  <table style="width: 100%; border-collapse: collapse; margin-bottom: 9px;">
    <tr>
      <td style="width: 50%; border: 1px solid #222; padding: 7px; vertical-align: top;">
        <strong>Employee Signature:</strong><br />
        <div style="min-height: 45px;"><span style="color: #16a34a;">{{employee_signature}}</span></div>
        <strong>Date:</strong> <span style="color: #16a34a;">{{issue_date}}</span>
      </td>
      <td style="width: 50%; border: 1px solid #222; padding: 7px; vertical-align: top;">
        <strong>Employee Code:</strong> <span style="color: #16a34a;">{{employee_code}}</span><br /><br />
        <strong>Local Contact Number:</strong> <span style="color: red;">{{local_contact_no}}</span>
      </td>
    </tr>
    <tr>
      <td colspan="2" style="border: 1px solid #222; padding: 7px;">
        <strong>Travelling Outside Country:</strong>&nbsp;
        <span style="color: red;">{{travel_yes_checkbox}}</span> Yes&nbsp;&nbsp;
        <span style="color: red;">{{travel_no_checkbox}}</span> No
      </td>
    </tr>
    <tr>
      <td style="width: 50%; border: 1px solid #222; padding: 7px;"><strong>Destination of Vacation:</strong> <span style="color: red;">{{destination}}</span></td>
      <td style="width: 50%; border: 1px solid #222; padding: 7px;"><strong>Contact No:</strong> <span style="color: red;">{{travel_contact_no}}</span></td>
    </tr>
    <tr>
      <td colspan="2" style="border: 1px solid #222; padding: 7px;">
        <strong>Ticket:</strong>&nbsp;
        <span style="color: red;">{{ticket_checkbox}}</span> Ticket&nbsp;&nbsp;
        <span style="color: red;">{{no_ticket_checkbox}}</span> No Ticket
        <span style="font-size: 11px;">(*To be marked by Boss)</span>
      </td>
    </tr>
  </table>

  <table style="width: 100%; border-collapse: collapse; margin-bottom: 9px;">
    <tr><td colspan="3" style="border: 1px solid #222; padding: 6px;"><strong>Approval of leave:</strong></td></tr>
    <tr>
      <td style="width: 33.33%; height: 67px; border: 1px solid #222; padding: 6px;"><strong>Department Head</strong></td>
      <td style="width: 33.33%; height: 67px; border: 1px solid #222; padding: 6px;"><strong>Finance Manager</strong></td>
      <td style="width: 33.33%; height: 67px; border: 1px solid #222; padding: 6px;"><strong>Director</strong></td>
    </tr>
    <tr><td colspan="3" style="height: 40px; border: 1px solid #222; padding: 6px;"><strong>Notes / Comments:</strong></td></tr>
  </table>

  {$declarationBlock}

  <div style="page-break-before: always;"></div>

  <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
    <tr>
      <td style="width: 25%; vertical-align: bottom; padding: 0;">
        <img src="{{company_logo_url}}" alt="YES Machinery" style="width: 75px; height: auto; display: block;" />
      </td>
      <td style="width: 75%; vertical-align: bottom; text-align: right; border-bottom: 2px solid #7AC142; padding: 0 0 5px;">
        <h3 style="margin: 0; font-family: Arial, sans-serif; font-size: 16px; color: #333; font-weight: bold;">YORK ENGINEERING SOLUTIONS FZC</h3>
      </td>
    </tr>
  </table>

  <h2 style="margin: 0 0 12px; font-family: Arial, sans-serif; font-size: 14px; text-decoration: underline;">HANDOVER DETAILS</h2>
  <p style="margin: 0 0 8px;"><strong>Duties Handed Over to:</strong> <span style="color: red;">{{handover_person}}</span> ______________________________</p>
  <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
    <thead>
      <tr>
        <th style="width: 8%; border: 1px solid #222; padding: 6px; text-align: left;">Sl No</th>
        <th style="border: 1px solid #222; padding: 6px; text-align: left;">Task / Responsibility</th>
      </tr>
    </thead>
    <tbody>{{handover_description}}</tbody>
  </table>
  <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
    <tr><td style="height: 90px; border: 1px solid #222; padding: 8px; vertical-align: top;"><strong>Other Remarks:</strong><br /><br /><span style="color: red;">{{remarks}}</span></td></tr>
  </table>
  <table style="width: 100%; border-collapse: collapse;">
    <tr>
      <td style="width: 50%; border: 1px solid #222; padding: 8px; vertical-align: top; height: 100px;">
        <strong>Signature of Person Handing Over:</strong><br />
        <div style="min-height: 55px;"><span style="color: red;">{{handover_giver_signature}}</span></div>
      </td>
      <td style="width: 50%; border: 1px solid #222; padding: 8px; vertical-align: top; height: 100px;">
        <strong>Signature of Person Receiving:</strong><br />
        <div style="min-height: 35px;">1. <span style="color: red;">{{handover_receiver_signature}}</span></div>
        <div>2. ____________________</div>
        <div>3. ____________________</div>
      </td>
    </tr>
  </table>
</div>
HTML;
    }

    private function leaveTemplateCode(string $code): string
    {
        $typeSpecificRows = $this->typeSpecificRows($code);
        $declarationBlock = $this->declarationBlock();

        return <<<HTML
<div style="font-family: 'Times New Roman', serif; font-size: 15px; line-height: 1.5; color: #333; max-width: 800px; margin: auto; padding: 20px;">
  <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
    <tr>
      <td style="width: 25%; vertical-align: bottom; padding: 0;">
        <img src="{{company_logo_url}}" alt="YES Machinery" style="width: 75px; height: auto; display: block;" />
      </td>
      <td style="width: 75%; vertical-align: bottom; text-align: right; border-bottom: 2px solid #7AC142; padding: 0 0 5px;">
        <h3 style="margin: 0; font-family: Arial, sans-serif; font-size: 16px; color: #333; font-weight: bold;">YORK ENGINEERING SOLUTIONS FZC</h3>
      </td>
    </tr>
  </table>

  <h2 style="margin: 0 0 10px; text-align: center; font-family: Arial, sans-serif; font-size: 17px; font-weight: bold; text-decoration: underline;">LEAVE REQUEST AND HANDOVER FORM</h2>

  <table style="width: 100%; border-collapse: collapse; margin-bottom: 8px;">
    <tr>
      <td style="width: 50%; border: 1px solid #222; padding: 6px;"><strong>NAME:</strong> <span style="color: #16a34a; font-weight: bold;">{{employee_name}}</span></td>
      <td style="width: 50%; border: 1px solid #222; padding: 6px;"><strong>DEPARTMENT:</strong> <span style="color: #16a34a;">{{department}}</span></td>
    </tr>
    <tr>
      <td colspan="2" style="border: 1px solid #222; padding: 7px; line-height: 1.4;">
        <strong>Reason for requested leave:</strong><br />
        {{leave_type_choices}}
      </td>
    </tr>
    <tr>
      <td colspan="2" style="border: 1px solid #222; padding: 8px;">
        <strong>Dates Requested:</strong>&nbsp; From: <span style="color: red;">{{start_date}}</span>&nbsp;&nbsp; To: <span style="color: red;">{{end_date}}</span><br />
        <strong>No. of days requested:</strong> <span style="color: red;">{{total_days}}</span>
      </td>
    </tr>
  </table>

  <table style="width: 100%; border-collapse: collapse; margin-bottom: 8px;">
    <tr>
      <td style="width: 50%; border: 1px solid #222; padding: 7px; vertical-align: top;">
        <strong>Employee Signature:</strong><br />
        <div style="min-height: 40px;"><span style="color: #16a34a;">{{employee_signature}}</span></div>
        <strong>Date:</strong> <span style="color: #16a34a;">{{issue_date}}</span>
      </td>
      <td style="width: 50%; border: 1px solid #222; padding: 7px; vertical-align: top;">
        <strong>Employee Code:</strong> <span style="color: #16a34a;">{{employee_code}}</span>
      </td>
    </tr>
    {$typeSpecificRows}
  </table>

  <table style="width: 100%; border-collapse: collapse; margin-bottom: 8px;">
    <tr><td colspan="3" style="border: 1px solid #222; padding: 6px;"><strong>Approval of leave:</strong></td></tr>
    <tr>
      <td style="width: 33.33%; height: 65px; border: 1px solid #222; padding: 6px;"><strong>Department Head</strong></td>
      <td style="width: 33.33%; height: 65px; border: 1px solid #222; padding: 6px;"><strong>Finance Manager</strong></td>
      <td style="width: 33.33%; height: 65px; border: 1px solid #222; padding: 6px;"><strong>Director</strong></td>
    </tr>
    <tr><td colspan="3" style="height: 38px; border: 1px solid #222; padding: 6px;"><strong>Notes / Comments:</strong></td></tr>
  </table>

  {$declarationBlock}

  <div style="page-break-before: always;"></div>

  <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
    <tr>
      <td style="width: 25%; vertical-align: bottom; padding: 0;">
        <img src="{{company_logo_url}}" alt="YES Machinery" style="width: 75px; height: auto; display: block;" />
      </td>
      <td style="width: 75%; vertical-align: bottom; text-align: right; border-bottom: 2px solid #7AC142; padding: 0 0 5px;">
        <h3 style="margin: 0; font-family: Arial, sans-serif; font-size: 16px; color: #333; font-weight: bold;">YORK ENGINEERING SOLUTIONS FZC</h3>
      </td>
    </tr>
  </table>

  <h2 style="margin: 0 0 12px; font-family: Arial, sans-serif; font-size: 14px; text-decoration: underline;">HANDOVER DETAILS</h2>
  <p style="margin: 0 0 8px;"><strong>Duties Handed Over to:</strong> <span style="color: red;">{{handover_person}}</span> ______________________________</p>
  <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
    <thead>
      <tr>
        <th style="width: 8%; border: 1px solid #222; padding: 6px; text-align: left;">Sl No</th>
        <th style="border: 1px solid #222; padding: 6px; text-align: left;">Task / Responsibility</th>
      </tr>
    </thead>
    <tbody>{{handover_description}}</tbody>
  </table>
  <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
    <tr><td style="height: 90px; border: 1px solid #222; padding: 8px; vertical-align: top;"><strong>Other Remarks:</strong><br /><br /><span style="color: red;">{{remarks}}</span></td></tr>
  </table>
  <table style="width: 100%; border-collapse: collapse;">
    <tr>
      <td style="width: 50%; border: 1px solid #222; padding: 8px; vertical-align: top; height: 100px;">
        <strong>Signature of Person Handing Over:</strong><br />
        <div style="min-height: 55px;"><span style="color: red;">{{handover_giver_signature}}</span></div>
      </td>
      <td style="width: 50%; border: 1px solid #222; padding: 8px; vertical-align: top; height: 100px;">
        <strong>Signature of Person Receiving:</strong><br />
        <div style="min-height: 35px;">1. <span style="color: red;">{{handover_receiver_signature}}</span></div>
        <div>2. ____________________</div>
        <div>3. ____________________</div>
      </td>
    </tr>
  </table>
</div>
HTML;
    }

    private function declarationBlock(): string
    {
        return <<<'HTML'
<table class="leave-declaration" style="width: 100%; border-collapse: collapse; page-break-inside: avoid; break-inside: avoid; margin: 0;">
  <tbody>
    <tr style="page-break-inside: avoid; break-inside: avoid;">
      <td style="border: 0; padding: 0;">
        <p style="margin: 4px 0 0; font-weight: bold; text-decoration: underline;">Declaration:</p>
        <p style="margin: 0; font-size: 13px; line-height: 1.35;">
          I hereby declare that I will remain available for any emergency queries as required by the company and can be reached through any designated contact method. I will also promptly inform the company in the event of any personal emergency that may affect my availability.
          <strong>Signature:</strong>
        </p>
        <div style="margin: 0; padding: 0; line-height: 1;"><span style="color: #16a34a;">{{employee_signature}}</span></div>
        <div style="margin: 0; padding: 0; line-height: 1;">______________________________</div>
      </td>
    </tr>
  </tbody>
</table>
HTML;
    }

    private function applyAnnualPageFit(LeaveTemplate $template): void
    {
        if (str_contains($template->template_code, 'class="leave-declaration"')) {
            $templateCode = $this->restoreCompactedAnnualPageSpacing($template->template_code);

            if ($templateCode !== $template->template_code && ! $template->update(['template_code' => $templateCode])) {
                throw new RuntimeException('Could not restore the Annual Leave page spacing.');
            }

            return;
        }

        $legacyDeclarationPattern = '~'
            .'<p style="margin: 4px 0 0; font-weight: bold; text-decoration: underline;">Declaration:</p>\s*'
            .'<p style="margin: 0; font-size: 13px; line-height: 1\.3;">\s*'
            .'I hereby declare that I will remain available for any emergency queries as required by the company and can be reached through any designated contact method\. I will also promptly inform the company in the event of any personal emergency that may affect my availability\.\s*'
            .'<strong>Signature:</strong>\s*</p>\s*'
            .'<div style="page-break-inside: avoid; margin: 0; padding: 0;">\s*'
            .'<div style="margin: 0; padding: 0; line-height: 1;"><span style="color: #16a34a;">\{\{employee_signature\}\}</span></div>\s*'
            .'<div style="margin: 0; padding: 0; line-height: 1;">______________________________</div>\s*'
            .'</div>~s';
        $replacementCount = 0;
        $templateCode = preg_replace(
            $legacyDeclarationPattern,
            $this->declarationBlock(),
            $template->template_code,
            -1,
            $replacementCount,
        );

        if ($templateCode === null) {
            throw new RuntimeException('Could not update the Annual Leave declaration block.');
        }

        if ($replacementCount === 0) {
            return;
        }

        if ($replacementCount !== 1) {
            throw new RuntimeException('Could not uniquely update the Annual Leave declaration block.');
        }

        $templateCode = $this->adjustLegacyAnnualPageSpacing($templateCode);

        if (! $template->update(['template_code' => $templateCode])) {
            throw new RuntimeException('Could not save the Annual Leave page-fit layout.');
        }
    }

    private function restoreCompactedAnnualPageSpacing(string $templateCode): string
    {
        $pageBreak = '<div style="page-break-before: always;"></div>';
        $pageBreakPosition = strpos($templateCode, $pageBreak);

        if ($pageBreakPosition === false) {
            return $templateCode;
        }

        $firstPage = substr($templateCode, 0, $pageBreakPosition);
        $compactDateBreak = '{{end_date}}</span><br />'."\n".'        <strong>No. of days requested:';

        if (
            ! str_contains($firstPage, self::LEAVE_TYPE_CHOICES_PLACEHOLDER)
            || ! str_contains($firstPage, 'class="leave-declaration"')
            || ! str_contains($firstPage, 'margin: 0 0 10px; text-align: center;')
            || ! str_contains($firstPage, 'line-height: 1.4;')
            || ! str_contains($firstPage, 'min-height: 40px;')
            || ! str_contains($firstPage, $compactDateBreak)
            || substr_count($firstPage, 'margin-bottom: 8px;') !== 3
            || substr_count($firstPage, 'margin-bottom: 12px;') !== 1
            || substr_count($firstPage, 'height: 65px;') !== 3
            || substr_count($firstPage, 'height: 38px;') !== 1
        ) {
            return $templateCode;
        }

        $headerTableStart = strpos($firstPage, '<table');
        $headerTableEnd = $headerTableStart === false
            ? false
            : strpos($firstPage, '>', $headerTableStart);

        if ($headerTableStart === false || $headerTableEnd === false) {
            return $templateCode;
        }

        $headerTableLength = $headerTableEnd - $headerTableStart + 1;
        $headerTable = substr($firstPage, $headerTableStart, $headerTableLength);
        $restoredHeaderTable = $this->replaceFirstOccurrence($headerTable, 'margin-bottom: 8px;', 'margin-bottom: 14px;');
        $restoredHeaderTable = $this->replaceFirstOccurrence($restoredHeaderTable, 'margin-bottom: 12px;', 'margin-bottom: 14px;');

        if ($restoredHeaderTable === $headerTable) {
            return $templateCode;
        }

        $firstPage = substr_replace($firstPage, $restoredHeaderTable, $headerTableStart, $headerTableLength);
        $firstPage = $this->replaceFirstOccurrence($firstPage, 'margin: 0 0 10px; text-align: center;', 'margin: 0 0 12px; text-align: center;');
        $firstPage = $this->replaceFirstOccurrence($firstPage, 'line-height: 1.4;', 'line-height: 1.5;');
        $firstPage = $this->replaceFirstOccurrence($firstPage, 'min-height: 40px;', 'min-height: 45px;');

        for ($index = 0; $index < 3; $index++) {
            $firstPage = $this->replaceFirstOccurrence($firstPage, 'margin-bottom: 8px;', 'margin-bottom: 9px;');
        }

        $firstPage = $this->replaceFirstOccurrence($firstPage, 'margin-bottom: 12px;', 'margin-bottom: 9px;');

        for ($index = 0; $index < 3; $index++) {
            $firstPage = $this->replaceFirstOccurrence($firstPage, 'height: 65px;', 'height: 67px;');
        }

        $firstPage = $this->replaceFirstOccurrence($firstPage, 'height: 38px;', 'height: 40px;');
        $firstPage = $this->replaceFirstOccurrence(
            $firstPage,
            '<p style="margin: 0; font-size: 13px; line-height: 1.3;">',
            '<p style="margin: 0; font-size: 13px; line-height: 1.35;">',
        );

        return $firstPage.substr($templateCode, $pageBreakPosition);
    }

    private function adjustLegacyAnnualPageSpacing(string $templateCode): string
    {
        $pageBreak = '<div style="page-break-before: always;"></div>';
        $pageBreakPosition = strpos($templateCode, $pageBreak);

        if ($pageBreakPosition === false) {
            return $templateCode;
        }

        $firstPage = substr($templateCode, 0, $pageBreakPosition);
        $firstPage = $this->replaceFirstOccurrence($firstPage, 'margin-bottom: 20px;', 'margin-bottom: 14px;');
        $firstPage = $this->replaceFirstOccurrence($firstPage, 'margin: 0 0 16px; text-align: center;', 'margin: 0 0 12px; text-align: center;');
        $firstPage = $this->replaceFirstOccurrence($firstPage, 'line-height: 1.7;', 'line-height: 1.5;');
        $firstPage = $this->replaceFirstOccurrence($firstPage, '<br /><br />', '<br />');
        $firstPage = $this->replaceFirstOccurrence($firstPage, 'min-height: 55px;', 'min-height: 45px;');

        for ($index = 0; $index < 3; $index++) {
            $firstPage = $this->replaceFirstOccurrence($firstPage, 'margin-bottom: 12px;', 'margin-bottom: 9px;');
            $firstPage = $this->replaceFirstOccurrence($firstPage, 'height: 75px;', 'height: 67px;');
        }

        $firstPage = $this->replaceFirstOccurrence($firstPage, 'height: 45px;', 'height: 40px;');

        return $firstPage.substr($templateCode, $pageBreakPosition);
    }

    private function replaceFirstOccurrence(string $subject, string $search, string $replacement): string
    {
        $position = strpos($subject, $search);

        return $position === false
            ? $subject
            : substr_replace($subject, $replacement, $position, strlen($search));
    }

    private function typeSpecificRows(string $code): string
    {
        return match ($code) {
            'MATERNITY' => '<tr><td colspan="2" style="border: 1px solid #222; padding: 7px;"><strong>Expected Due Date:</strong> <span style="color: red;">{{due_date}}</span></td></tr>',
            'PARENTAL' => '<tr><td colspan="2" style="border: 1px solid #222; padding: 7px;"><strong>Child\'s Date of Birth:</strong> <span style="color: red;">{{child_birth_date}}</span></td></tr>',
            'COMPASSIONATE' => '<tr><td colspan="2" style="border: 1px solid #222; padding: 7px;"><strong>Family Relationship:</strong> <span style="color: red;">{{family_relationship}}</span></td></tr>',
            'FESTIVAL' => '<tr><td colspan="2" style="border: 1px solid #222; padding: 7px;"><strong>Festival:</strong> <span style="color: red;">{{festival_name}}</span></td></tr>',
            default => '',
        };
    }

    private function replaceLegacyLeaveTypeChoices(LeaveTemplate $template): void
    {
        if (
            str_contains($template->template_code, self::LEAVE_TYPE_CHOICES_PLACEHOLDER)
            || ! str_contains($template->template_code, 'Paternity Leave')
            || ! str_contains($template->template_code, 'Other ______________________________')
        ) {
            return;
        }

        $replacementCount = 0;
        $templateCode = preg_replace_callback(
            '/(<strong>Reason for requested leave:<\/strong><br \/>).*?(<\/td>)/s',
            fn (array $matches): string => $matches[1].self::LEAVE_TYPE_CHOICES_PLACEHOLDER.$matches[2],
            $template->template_code,
            1,
            $replacementCount,
        );

        if (! is_string($templateCode) || $replacementCount !== 1) {
            throw new RuntimeException('Could not update the legacy Annual Leave reason list.');
        }

        if (! $template->update(['template_code' => $templateCode])) {
            throw new RuntimeException('Could not save the updated Annual Leave reason list.');
        }
    }
}
