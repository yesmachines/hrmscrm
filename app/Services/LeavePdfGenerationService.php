<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\LeaveRequestGeneratedFile;
use App\Models\LeaveTemplate;
use App\Models\LeaveType;
use App\Models\SalesCrm\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LeavePdfGenerationService
{
    public function resolveTemplate(LeaveType $leaveType, ?int $organisationId): ?LeaveTemplate
    {
        return $leaveType->resolveTemplate($organisationId);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function render(
        LeaveTemplate $template,
        Employee $employee,
        array $values = [],
        ?LeaveRequest $leaveRequest = null,
    ): string {
        $employee->loadMissing(['user', 'department', 'organisation']);
        $profile = $employee->profile();

        if ($leaveRequest !== null) {
            $leaveRequest->loadMissing(['leaveType', 'details', 'approvals.approver']);
            $values = array_merge(
                $leaveRequest->details->pluck('field_value', 'field_key')->all(),
                [
                    'remarks' => $leaveRequest->remarks,
                    'start_date' => $leaveRequest->start_date?->format('Y-m-d'),
                    'end_date' => $leaveRequest->end_date?->format('Y-m-d'),
                    'total_days' => $leaveRequest->total_days,
                    'request_type' => $leaveRequest->request_type,
                ],
                $values,
            );
        }

        $handoverEmployee = isset($values['handover_person_id'])
            ? Employee::query()->with('user')->find($values['handover_person_id'])
            : null;
        $latestApproval = $leaveRequest?->approvals->sortByDesc('id')->first();
        $requestSignaturePath = $values['signature_path'] ?? null;
        $employeeSignature = $this->signatureMarkup($profile?->signature_path ?? $requestSignaturePath);
        $handoverGiverSignature = $this->signatureMarkup($values['handover_giver_signature_path'] ?? null);
        $handoverReceiverSignature = $this->signatureMarkup($values['handover_receiver_signature_path'] ?? null);
        $requestType = match ($values['request_type'] ?? null) {
            'firsthalf' => 'First half',
            'secondhalf' => 'Second half',
            'fullday' => 'Full day',
            default => (string) ($values['request_type'] ?? ''),
        };
        $ticketStatus = match ($values['ticket_status'] ?? null) {
            'ticket' => 'Ticket',
            'no_ticket' => 'No Ticket',
            default => (string) ($values['ticket_status'] ?? ''),
        };

        $replacements = [
            '{{company_logo_url}}' => $this->companyLogoDataUri(),
            '{{company_name}}' => $this->escape($employee->organisation?->org_name ?? config('app.name', 'HRMS')),
            '{{employee_name}}' => $this->escape($employee->user?->name ?? "Employee #{$employee->id}"),
            '{{employee_code}}' => $this->escape($employee->employee_code ?? $employee->emp_num),
            '{{employee_phone}}' => $this->escape($employee->phone ?? ''),
            '{{designation}}' => $this->escape($employee->designation ?? ''),
            '{{department}}' => $this->escape($employee->department?->name ?? ''),
            '{{leave_type}}' => $this->escape($leaveRequest?->leaveType?->leave_name ?? (string) ($values['leave_type_name'] ?? '')),
            '{{leave_type_name}}' => $this->escape($leaveRequest?->leaveType?->leave_name ?? (string) ($values['leave_type_name'] ?? '')),
            '{{start_date}}' => $this->escape($this->formatDate($values['start_date'] ?? null)),
            '{{end_date}}' => $this->escape($this->formatDate($values['end_date'] ?? null)),
            '{{total_days}}' => $this->escape((string) ($values['total_days'] ?? '')),
            '{{request_type}}' => $this->escape($requestType),
            '{{remarks}}' => $this->escape((string) ($values['remarks'] ?? '')),
            '{{local_contact_no}}' => $this->escape((string) ($values['local_contact_no'] ?? '')),
            '{{traveling_outside_country}}' => $this->escape($this->yesNo($values['traveling_outside_country'] ?? null)),
            '{{destination}}' => $this->escape((string) ($values['destination'] ?? '')),
            '{{travel_contact_no}}' => $this->escape((string) ($values['travel_contact_no'] ?? '')),
            '{{ticket_status}}' => $this->escape($ticketStatus),
            '{{handover_person}}' => $this->escape($handoverEmployee?->user?->name ?? ''),
            '{{handover_description}}' => $this->formatHandoverDescription($values['handover_description'] ?? null),
            '{{employee_signature}}' => $employeeSignature,
            '{{signature}}' => $employeeSignature,
            '{{handover_giver_signature}}' => $handoverGiverSignature,
            '{{handover_receiver_signature}}' => $handoverReceiverSignature,
            '{{approved_by}}' => $this->escape($latestApproval?->approver?->name ?? ''),
            '{{approved_date}}' => $this->escape($this->formatDate($latestApproval?->approved_date)),
            '{{issue_date}}' => now()->format('d M Y'),
        ];

        return strtr($template->template_code, $replacements);
    }

    public function generateIfAvailable(
        LeaveRequest $leaveRequest,
        ?int $actorId,
    ): ?LeaveRequestGeneratedFile {
        if ($leaveRequest->status !== 'approved') {
            return null;
        }

        $leaveRequest->load(['employee', 'leaveType', 'details', 'approvals.approver']);
        $employee = $leaveRequest->employee;
        $leaveType = $leaveRequest->leaveType;

        if ($employee === null || $leaveType === null) {
            throw new RuntimeException('Cannot generate a leave PDF without an employee and leave type.');
        }

        $template = $this->resolveTemplate($leaveType, $employee->organisation_id);
        if ($template === null || blank($template->template_code)) {
            return null;
        }

        $version = $leaveRequest->generatedFiles()->count() + 1;
        $generatedAt = now();
        $filePath = sprintf(
            'leave-generated/%d/v%d-%s.pdf',
            $leaveRequest->id,
            $version,
            Str::uuid(),
        );
        $html = $this->render($template, $employee, [], $leaveRequest);
        $pdfHtml = '<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; }
        p, div, span, table { max-width: 100% !important; }
        p { margin-right: 0 !important; padding-right: 0 !important; width: auto !important; }
    </style>
</head>
<body>'.$html.'</body>
</html>';
        $pdf = Pdf::loadHTML($pdfHtml)->setPaper('a4', 'portrait');

        if (! Storage::disk('public')->put($filePath, $pdf->output())) {
            throw new RuntimeException('Failed to store the generated leave PDF.');
        }

        try {
            return $leaveRequest->generatedFiles()->create([
                'leave_template_id' => $template->id,
                'version_no' => $version.'.0',
                'file_path' => $filePath,
                'generated_by' => $actorId,
                'generated_at' => $generatedAt,
                'change_notes' => 'Generated upon leave approval.',
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($filePath);

            throw $exception;
        }
    }

    private function companyLogoDataUri(): string
    {
        $logoPath = public_path('yes_machinery_logo.png');

        if (! is_file($logoPath)) {
            return '';
        }

        $logoContent = file_get_contents($logoPath);

        return $logoContent === false ? '' : 'data:image/png;base64,'.base64_encode($logoContent);
    }

    private function signatureMarkup(?string $path): string
    {
        if (blank($path) || str_contains($path, '..') || ! Storage::disk('public')->exists($path)) {
            return '';
        }

        $mimeType = Storage::disk('public')->mimeType($path);
        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return '';
        }

        return '<img src="data:'.$mimeType.';base64,'.base64_encode(Storage::disk('public')->get($path))
            .'" alt="Signature" style="max-width:180px;max-height:70px;">';
    }

    private function formatHandoverDescription(mixed $description): string
    {
        if (! is_string($description) || trim($description) === '') {
            return '';
        }

        $tasks = json_decode($description, true);
        if (is_array($tasks) && array_is_list($tasks)) {
            $items = array_filter($tasks, fn (mixed $task): bool => is_string($task) && trim($task) !== '');
            if ($items === []) {
                return '';
            }

            return '<ul>'.implode('', array_map(
                fn (string $task): string => '<li>'.$this->escape($task).'</li>',
                $items,
            )).'</ul>';
        }

        return nl2br($this->escape($description));
    }

    private function formatDate(mixed $date): string
    {
        if (blank($date)) {
            return '';
        }

        return Carbon::parse($date)->format('d M Y');
    }

    private function yesNo(mixed $value): string
    {
        if (in_array($value, [true, 1, '1', 'Yes', 'yes'], true)) {
            return 'Yes';
        }

        if (in_array($value, [false, 0, '0', 'No', 'no'], true)) {
            return 'No';
        }

        return '';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
