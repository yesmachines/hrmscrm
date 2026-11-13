<?php

namespace App\Services;

use App\Models\Reward;
use App\Models\RewardCategory;
use App\Models\SalesCrm\Employee;
use App\Support\SalesCrmRoles;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RewardBulkImportService
{
    /**
     * Column headers expected in the bulk import CSV.
     */
    public const HEADERS = [
        'employee_identifier',
        'category',
        'amount',
        'currency',
        'award_date',
        'status',
        'description',
        'comments',
    ];

    /**
     * Generate the sample CSV template string.
     */
    public function generateTemplateCsv(): string
    {
        $handle = fopen('php://temp', 'r+');

        // Header row
        fputcsv($handle, self::HEADERS);

        // Sample rows
        fputcsv($handle, [
            'EMP001',
            'Excellence in Customer Service',
            '1000.00',
            'AED',
            now()->format('Y-m-d'),
            'approved',
            'Consistently highest customer satisfaction ratings for Q3.',
            'Batch import for Q3 Annual Awards.',
        ]);

        fputcsv($handle, [
            'sarah@company.com',
            'Employee of the Month',
            '1500.00',
            'AED',
            now()->subDays(5)->format('Y-m-d'),
            'paid',
            'Successfully led the migration project on schedule.',
            'Processed with November payroll.',
        ]);

        fputcsv($handle, [
            'EMP002',
            'Innovation Award',
            '750.00',
            'AED',
            now()->format('Y-m-d'),
            'pending',
            'Designed automated internal reporting tool.',
            'Requires departmental review.',
        ]);

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv ?: '';
    }

    /**
     * Generate sample Excel spreadsheet (.xls / SpreadsheetML) template.
     */
    public function generateTemplateExcel(): string
    {
        $rows = [
            [
                'EMP001',
                'Excellence in Customer Service',
                '1000.00',
                'AED',
                now()->format('Y-m-d'),
                'approved',
                'Consistently highest customer satisfaction ratings for Q3.',
                'Batch import for Q3 Annual Awards.',
            ],
            [
                'sarah@company.com',
                'Employee of the Month',
                '1500.00',
                'AED',
                now()->subDays(5)->format('Y-m-d'),
                'paid',
                'Successfully led the migration project on schedule.',
                'Processed with November payroll.',
            ],
            [
                'EMP002',
                'Innovation Award',
                '750.00',
                'AED',
                now()->format('Y-m-d'),
                'pending',
                'Designed automated internal reporting tool.',
                'Requires departmental review.',
            ],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>'."\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'."\n";
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"'."\n";
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"'."\n";
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'."\n";
        $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">'."\n";

        // Styling
        $xml .= ' <Styles>'."\n";
        $xml .= '  <Style ss:ID="HeaderStyle">'."\n";
        $xml .= '   <Font ss:Bold="1" ss:Color="#FFFFFF" />'."\n";
        $xml .= '   <Interior ss:Color="#10B981" ss:Pattern="Solid" />'."\n";
        $xml .= '   <Alignment ss:Horizontal="Center" ss:Vertical="Center" />'."\n";
        $xml .= '  </Style>'."\n";
        $xml .= '  <Style ss:ID="Default">'."\n";
        $xml .= '   <Alignment ss:Vertical="Center" />'."\n";
        $xml .= '  </Style>'."\n";
        $xml .= ' </Styles>'."\n";

        $xml .= ' <Worksheet ss:Name="Reward_Import_Template">'."\n";
        $xml .= '  <Table>'."\n";

        // Column widths
        $xml .= '   <Column ss:Width="130"/>'."\n";
        $xml .= '   <Column ss:Width="180"/>'."\n";
        $xml .= '   <Column ss:Width="80"/>'."\n";
        $xml .= '   <Column ss:Width="70"/>'."\n";
        $xml .= '   <Column ss:Width="100"/>'."\n";
        $xml .= '   <Column ss:Width="90"/>'."\n";
        $xml .= '   <Column ss:Width="280"/>'."\n";
        $xml .= '   <Column ss:Width="200"/>'."\n";

        // Headers
        $xml .= '   <Row ss:Height="26">'."\n";
        foreach (self::HEADERS as $header) {
            $safeHeader = htmlspecialchars($header, ENT_XML1, 'UTF-8');
            $xml .= '    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">'.$safeHeader.'</Data></Cell>'."\n";
        }
        $xml .= '   </Row>'."\n";

        // Sample Data Rows
        foreach ($rows as $row) {
            $xml .= '   <Row ss:Height="20">'."\n";
            foreach ($row as $idx => $val) {
                $type = ($idx === 2) ? 'Number' : 'String';
                $safeVal = htmlspecialchars((string) $val, ENT_XML1, 'UTF-8');
                $xml .= '    <Cell><Data ss:Type="'.$type.'">'.$safeVal.'</Data></Cell>'."\n";
            }
            $xml .= '   </Row>'."\n";
        }

        $xml .= '  </Table>'."\n";
        $xml .= ' </Worksheet>'."\n";
        $xml .= '</Workbook>';

        return $xml;
    }

    /**
     * Parse and import rewards from a CSV file.
     *
     * @return array{
     *     total_rows: int,
     *     imported_count: int,
     *     failed_count: int,
     *     success_records: array,
     *     errors: array<int, array{row: int, error: string, data: array}>
     * }
     */
    public function importCsv(UploadedFile|string $file, Authenticatable $uploader): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new \InvalidArgumentException('Uploaded CSV file cannot be read.');
        }

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new \InvalidArgumentException('Failed to open CSV file stream.');
        }

        // Detect BOM and strip it
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Read header row
        $rawHeaders = fgetcsv($handle);
        if (! $rawHeaders) {
            fclose($handle);
            throw new \InvalidArgumentException('CSV file is empty or corrupted.');
        }

        $headers = array_map(function ($h) {
            return strtolower(trim((string) $h));
        }, $rawHeaders);

        // Validate essential headers
        $requiredHeaders = ['employee_identifier', 'category', 'amount', 'description'];
        $missing = array_diff($requiredHeaders, $headers);
        if (! empty($missing)) {
            fclose($handle);
            throw new \InvalidArgumentException('Missing required CSV column headers: '.implode(', ', $missing));
        }

        $headerMap = array_flip($headers);

        $isFinanceOrAdmin = SalesCrmRoles::userHasAnyRole((int) $uploader->id, ['finance', 'admin']);

        // Cache categories for performance
        $categories = RewardCategory::query()->get();

        $rowNumber = 1; // 1 is header
        $importedCount = 0;
        $failedCount = 0;
        $successRecords = [];
        $errors = [];

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                // Skip completely empty lines
                if (empty(array_filter($row, fn ($val) => trim((string) $val) !== ''))) {
                    continue;
                }

                $rowData = [];
                foreach ($headerMap as $column => $idx) {
                    $rowData[$column] = isset($row[$idx]) ? trim((string) $row[$idx]) : '';
                }

                $validationResult = $this->validateAndResolveRow($rowData, $categories, $isFinanceOrAdmin, $rowNumber);

                if (! $validationResult['valid']) {
                    $failedCount++;
                    $errors[] = [
                        'row' => $rowNumber,
                        'error' => $validationResult['error'],
                        'data' => $rowData,
                    ];

                    continue;
                }

                /** @var array $parsed */
                $parsed = $validationResult['data'];

                // Generate unique claim_no
                $claimNo = 'REW-'.str_pad((string) (Reward::max('id') + 1 + $importedCount), 5, '0', STR_PAD_LEFT);

                /** @var Reward $reward */
                $reward = Reward::create([
                    'claim_no' => $claimNo,
                    'submitted_by' => $parsed['employee_id'],
                    'category_id' => $parsed['category_id'],
                    'amount' => $parsed['amount'],
                    'currency' => $parsed['currency'],
                    'status' => $parsed['status'],
                    'submitted_date' => $parsed['submitted_date'],
                    'description' => $parsed['description'],
                ]);

                // Create initial audit status entry
                $auditComment = ! empty($parsed['comments'])
                    ? $parsed['comments']
                    : "Bulk imported with status '{$parsed['status']}' by {$uploader->name}";

                $reward->approvalStatuses()->create([
                    'done_by' => $uploader->id,
                    'done_on' => now(),
                    'comments' => $auditComment,
                    'status' => $parsed['status'],
                ]);

                $importedCount++;
                $successRecords[] = [
                    'id' => $reward->id,
                    'claim_no' => $reward->claim_no,
                    'employee_id' => $parsed['employee_id'],
                    'employee_name' => $parsed['employee_name'],
                    'amount' => $parsed['amount'],
                    'currency' => $parsed['currency'],
                    'status' => $parsed['status'],
                ];
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            throw $e;
        }

        fclose($handle);

        return [
            'total_rows' => $importedCount + $failedCount,
            'imported_count' => $importedCount,
            'failed_count' => $failedCount,
            'success_records' => $successRecords,
            'errors' => $errors,
        ];
    }

    /**
     * Validate a single row and resolve relations.
     *
     * @param  array<string, string>  $row
     * @param  Collection<int, RewardCategory>  $categories
     * @return array{valid: bool, error?: string, data?: array}
     */
    protected function validateAndResolveRow(
        array $row,
        $categories,
        bool $isFinanceOrAdmin,
        int $rowNumber
    ): array {
        $identifier = $row['employee_identifier'] ?? '';
        if (empty($identifier)) {
            return ['valid' => false, 'error' => "Row {$rowNumber}: Employee identifier is missing."];
        }

        // Find employee by emp_num or user email
        $employee = Employee::query()
            ->with('user:id,name,email')
            ->where(function ($q) use ($identifier) {
                $q->where('emp_num', $identifier)
                    ->orWhereHas('user', function ($uq) use ($identifier) {
                        $uq->where('email', $identifier);
                    });
            })
            ->first();

        if (! $employee) {
            return ['valid' => false, 'error' => "Row {$rowNumber}: Employee '{$identifier}' not found in the system."];
        }

        // Category resolution (by ID or reward_name or short_code)
        $categoryInput = $row['category'] ?? '';
        if (empty($categoryInput)) {
            return ['valid' => false, 'error' => "Row {$rowNumber}: Reward category is missing."];
        }

        $matchedCategory = $categories->first(function ($cat) use ($categoryInput) {
            return (string) $cat->id === $categoryInput
                || strcasecmp(trim($cat->reward_name), $categoryInput) === 0
                || strcasecmp(trim($cat->short_code ?? ''), $categoryInput) === 0;
        });

        if (! $matchedCategory) {
            return ['valid' => false, 'error' => "Row {$rowNumber}: Reward category '{$categoryInput}' does not match any existing category."];
        }

        // Amount validation
        $amountStr = $row['amount'] ?? '';
        if (! is_numeric($amountStr) || (float) $amountStr <= 0) {
            return ['valid' => false, 'error' => "Row {$rowNumber}: Reward amount must be a positive number."];
        }
        $amount = round((float) $amountStr, 2);

        // Currency (defaults to AED)
        $currency = ! empty($row['currency']) ? strtoupper(substr(trim($row['currency']), 0, 3)) : 'AED';

        // Award date
        $awardDateInput = $row['award_date'] ?? '';
        if (! empty($awardDateInput)) {
            try {
                $submittedDate = Carbon::parse($awardDateInput)->toDateString();
            } catch (\Exception $e) {
                return ['valid' => false, 'error' => "Row {$rowNumber}: Invalid date format '{$awardDateInput}'. Use YYYY-MM-DD."];
            }
        } else {
            $submittedDate = now()->toDateString();
        }

        // Status (pending, approved, paid - default approved for HR bulk entry)
        $status = ! empty($row['status']) ? strtolower(trim($row['status'])) : 'approved';
        if (! in_array($status, ['pending', 'approved', 'paid'], true)) {
            return ['valid' => false, 'error' => "Row {$rowNumber}: Status '{$status}' is invalid. Allowed: pending, approved, paid."];
        }

        if ($status === 'paid' && ! $isFinanceOrAdmin) {
            return ['valid' => false, 'error' => "Row {$rowNumber}: Only Finance or Admin role users can import rewards directly with status 'paid'."];
        }

        // Description
        $description = $row['description'] ?? '';
        if (empty($description)) {
            return ['valid' => false, 'error' => "Row {$rowNumber}: Description / reason for the reward is required."];
        }

        return [
            'valid' => true,
            'data' => [
                'employee_id' => $employee->id,
                'employee_name' => $employee->user ? $employee->user->name : "Employee #{$employee->emp_num}",
                'category_id' => $matchedCategory->id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $status,
                'submitted_date' => $submittedDate,
                'description' => $description,
                'comments' => $row['comments'] ?? null,
            ],
        ];
    }
}
