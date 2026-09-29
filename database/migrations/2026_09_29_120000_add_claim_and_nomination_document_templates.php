<?php

use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Get or create Category 1: Employee Personal Documents
        $personalCategory = DocumentCategory::query()->firstOrCreate(
            ['short_code' => 'personal'],
            [
                'category_name' => 'Employee Personal Documents',
                'status' => 1,
            ]
        );

        // 2. Ensure Education Allowance Claim Form document type
        $educationType = DocumentType::query()->firstOrCreate(
            ['document_code' => 'education_allowance_claim'],
            [
                'category_id' => $personalCategory->id,
                'document_name' => 'Education Allowance Claim Form',
                'requires_number' => false,
                'requires_expiry' => false,
                'editable_before_approval' => true,
                'requires_hr_approval' => true,
                'requires_reminder' => false,
                'record_source' => 'uploaded',
                'requires_attachments' => true,
            ]
        );

        // 3. Ensure Employee Nominee Declaration Form document type (nomination_form)
        $nomineeType = DocumentType::query()->firstOrCreate(
            ['document_code' => 'nomination_form'],
            [
                'category_id' => $personalCategory->id,
                'document_name' => 'Employee Nomination Form',
                'requires_number' => false,
                'requires_expiry' => false,
                'editable_before_approval' => true,
                'requires_hr_approval' => true,
                'requires_reminder' => true,
                'record_source' => 'uploaded',
                'requires_attachments' => true,
            ]
        );

        // 4. Ensure Dependent Air Ticket Claim Form document type
        $airTicketType = DocumentType::query()->firstOrCreate(
            ['document_code' => 'air_ticket_claim'],
            [
                'category_id' => $personalCategory->id,
                'document_name' => 'Dependent Air Ticket Claim Form',
                'requires_number' => false,
                'requires_expiry' => false,
                'editable_before_approval' => true,
                'requires_hr_approval' => true,
                'requires_reminder' => false,
                'record_source' => 'uploaded',
                'requires_attachments' => true,
            ]
        );

        // 5. Create / Update Education Allowance Template
        $educationHtml = <<<'HTML'
<div style="font-family: Arial, sans-serif; font-size: 13px; line-height: 1.5; color: #111; max-width: 800px; margin: auto; padding: 25px;">
    <div style="text-align: center; margin-bottom: 25px;">
        <h2 style="margin: 0; font-size: 16px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">YES MACHINERY EDUCATION ALLOWANCE CLAIM FORM</h2>
        <p style="margin: 3px 0 0; font-size: 13px; font-weight: bold;">(After 5years’ of Service)</p>
    </div>

    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 10px 0; font-size: 14px; font-weight: bold;">Employee Information</h3>
        <table style="width: 100%; border: none; font-size: 13px;">
            <tr>
                <td style="padding: 4px 0; width: 22%;">• Employee Name:</td>
                <td style="padding: 4px 0; border-bottom: 1px solid #333; width: 78%; font-weight: bold;">{{employee_name}}</td>
            </tr>
            <tr>
                <td style="padding: 4px 0;">• Department:</td>
                <td style="padding: 4px 0; border-bottom: 1px solid #333;">{{department}}</td>
            </tr>
            <tr>
                <td style="padding: 4px 0;">• Designation:</td>
                <td style="padding: 4px 0; border-bottom: 1px solid #333;">{{designation}}</td>
            </tr>
            <tr>
                <td style="padding: 4px 0;">• Date of Joining:</td>
                <td style="padding: 4px 0; border-bottom: 1px solid #333;">{{joining_date}}</td>
            </tr>
        </table>
    </div>

    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 10px 0; font-size: 14px; font-weight: bold;">Child Information</h3>
        
        <div style="margin-bottom: 12px;">
            <p style="margin: 0 0 4px 0; font-weight: bold; font-size: 13px;">Child 1</p>
            <table style="width: 100%; border: none; font-size: 13px;">
                <tr><td style="padding: 3px 0; width: 24%;">• Name:</td><td style="padding: 3px 0; border-bottom: 1px solid #555; width: 76%;">&nbsp;</td></tr>
                <tr><td style="padding: 3px 0;">• Date of Birth:</td><td style="padding: 3px 0; border-bottom: 1px solid #555;">&nbsp;</td></tr>
                <tr><td style="padding: 3px 0;">• School/College Name:</td><td style="padding: 3px 0; border-bottom: 1px solid #555;">&nbsp;</td></tr>
            </table>
        </div>

        <div style="margin-bottom: 12px;">
            <p style="margin: 0 0 4px 0; font-weight: bold; font-size: 13px;">Child 2</p>
            <table style="width: 100%; border: none; font-size: 13px;">
                <tr><td style="padding: 3px 0; width: 24%;">• Name:</td><td style="padding: 3px 0; border-bottom: 1px solid #555; width: 76%;">&nbsp;</td></tr>
                <tr><td style="padding: 3px 0;">• Date of Birth:</td><td style="padding: 3px 0; border-bottom: 1px solid #555;">&nbsp;</td></tr>
                <tr><td style="padding: 3px 0;">• School/College Name:</td><td style="padding: 3px 0; border-bottom: 1px solid #555;">&nbsp;</td></tr>
            </table>
        </div>

        <div style="margin-bottom: 12px;">
            <p style="margin: 0 0 4px 0; font-weight: bold; font-size: 13px;">Child 3</p>
            <table style="width: 100%; border: none; font-size: 13px;">
                <tr><td style="padding: 3px 0; width: 24%;">• Name:</td><td style="padding: 3px 0; border-bottom: 1px solid #555; width: 76%;">&nbsp;</td></tr>
                <tr><td style="padding: 3px 0;">• Date of Birth:</td><td style="padding: 3px 0; border-bottom: 1px solid #555;">&nbsp;</td></tr>
                <tr><td style="padding: 3px 0;">• School/College Name:</td><td style="padding: 3px 0; border-bottom: 1px solid #555;">&nbsp;</td></tr>
            </table>
        </div>
    </div>

    <div style="margin-bottom: 25px;">
        <h3 style="margin: 0 0 10px 0; font-size: 14px; font-weight: bold;">Claim Details</h3>
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 12px;">
            <thead>
                <tr style="background-color: #f8fafc;">
                    <th style="border: 1px solid #000; padding: 6px 8px; font-weight: bold; width: 25%;">Child Name</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; font-weight: bold; width: 20%;">Academic Year</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; font-weight: bold; width: 25%;">School/College</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; font-weight: bold; width: 15%;">Amount Paid</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; font-weight: bold; width: 15%;">Amount Claimed</th>
                </tr>
            </thead>
            <tbody>
                <tr><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td></tr>
                <tr><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td></tr>
                <tr><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td></tr>
                <tr><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 14px 8px;">&nbsp;</td></tr>
            </tbody>
        </table>
        <div style="margin-top: 15px; font-size: 13px;">
            <strong>Total Amount Claimed: AED</strong> <span style="display: inline-block; width: 220px; border-bottom: 1px solid #000;">&nbsp;</span>
        </div>
    </div>

    <div style="page-break-before: always; margin-top: 25px;"></div>

    <div style="margin-bottom: 25px;">
        <h3 style="margin: 0 0 10px 0; font-size: 14px; font-weight: bold;">Supporting Documents Attached</h3>
        <p style="margin: 6px 0; font-size: 13px;">☐ &nbsp; School Fee Receipt(s)</p>
        <p style="margin: 6px 0; font-size: 13px;">☐ &nbsp; Admission/Enrollment Confirmation</p>
        <p style="margin: 6px 0; font-size: 13px;">☐ &nbsp; Child's Passport/ ID Copy</p>
        <p style="margin: 6px 0; font-size: 13px;">☐ &nbsp; UAE Visa Copy(Children Studying in UAE)</p>
        <p style="margin: 6px 0; font-size: 13px;">☐ &nbsp; Any Other Supporting Documents</p>
    </div>

    <div style="margin-bottom: 25px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
        <h3 style="margin: 0 0 8px 0; font-size: 14px; font-weight: bold;">Employee Declaration</h3>
        <p style="margin: 0 0 15px 0; font-size: 12px; line-height: 1.5; text-align: justify;">
            I hereby certify that the information provided in this claim form is true and correct. I understand that the company reserves the right to verify the submitted documents and reject any claim that does not comply with the Education Allowance Policy.
        </p>
        <table style="width: 100%; border: none; font-size: 13px;">
            <tr>
                <td style="width: 50%; padding: 4px 0;">Employee Signature: <span style="display: inline-block; width: 170px; border-bottom: 1px solid #000;">&nbsp;</span></td>
                <td style="width: 50%; padding: 4px 0;">Date: <span style="display: inline-block; width: 170px; border-bottom: 1px solid #000;">&nbsp;</span></td>
            </tr>
        </table>
    </div>

    <div style="margin-bottom: 25px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
        <h3 style="margin: 0 0 8px 0; font-size: 14px; font-weight: bold;">HR Verification</h3>
        <p style="margin: 6px 0; font-size: 13px;">
            ☐ Eligibility Confirmed &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            ☐ Supporting Documents Verified
        </p>
        <p style="margin: 6px 0; font-size: 13px;">
            ☐ Claim Approved &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            ☐ Claim Rejected
        </p>
        <p style="margin: 10px 0 15px 0; font-size: 13px;">Remarks: <span style="display: inline-block; width: 480px; border-bottom: 1px solid #000;">&nbsp;</span></p>
        <table style="width: 100%; border: none; font-size: 13px;">
            <tr>
                <td style="width: 38%; padding: 4px 0;">HR Representative: <span style="display: inline-block; width: 110px; border-bottom: 1px solid #000;">&nbsp;</span></td>
                <td style="width: 32%; padding: 4px 0;">Signature: <span style="display: inline-block; width: 100px; border-bottom: 1px solid #000;">&nbsp;</span></td>
                <td style="width: 30%; padding: 4px 0;">Date: <span style="display: inline-block; width: 100px; border-bottom: 1px solid #000;">&nbsp;</span></td>
            </tr>
        </table>
    </div>

    <div style="margin-bottom: 25px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
        <h3 style="margin: 0 0 8px 0; font-size: 14px; font-weight: bold;">Management Approval</h3>
        <p style="margin: 4px 0 12px 0; font-size: 13px;">Approved Amount: AED <span style="display: inline-block; width: 220px; border-bottom: 1px solid #000;">&nbsp;</span></p>
        <table style="width: 100%; border: none; font-size: 13px;">
            <tr>
                <td style="width: 40%; padding: 4px 0;">Approver Name: <span style="display: inline-block; width: 130px; border-bottom: 1px solid #000;">&nbsp;</span></td>
                <td style="width: 32%; padding: 4px 0;">Signature: <span style="display: inline-block; width: 110px; border-bottom: 1px solid #000;">&nbsp;</span></td>
                <td style="width: 28%; padding: 4px 0;">Date: <span style="display: inline-block; width: 110px; border-bottom: 1px solid #000;">&nbsp;</span></td>
            </tr>
        </table>
    </div>

    <div style="font-size: 11px; font-style: italic; line-height: 1.4; color: #475569; border-top: 1px solid #e2e8f0; padding-top: 10px;">
        Education Allowance claims are subject to verification of the submitted documents. Reimbursement will be made for the actual tuition fees paid, up to the maximum education allowance entitlement as per the Company's policy, whichever is lower.
    </div>
</div>
HTML;

        DocumentTemplate::query()->updateOrCreate(
            [
                'document_type_id' => $educationType->id,
                'template_name' => 'Education Allowance Claim Form Template',
            ],
            [
                'template_code' => $educationHtml,
                'status' => 1,
            ]
        );

        // 6. Create / Update Employee Nominee Declaration Template
        $nomineeHtml = <<<'HTML'
<div style="font-family: Arial, sans-serif; font-size: 13px; line-height: 1.5; color: #111; max-width: 800px; margin: auto; padding: 25px;">
    <table style="width: 100%; border: none; margin-bottom: 20px;">
        <tr>
            <td style="vertical-align: middle;">
                <h2 style="margin: 0; font-size: 20px; font-weight: bold; color: #1e293b;">Employee Nominee Declaration Form</h2>
            </td>
            <td style="width: 110px; text-align: right; vertical-align: top;">
                <div style="background-color: #7AC142; color: white; padding: 12px 6px; width: 95px; text-align: center; display: inline-block;">
                    <div style="font-size: 26px; font-weight: 900; line-height: 1; font-family: 'Arial Black', sans-serif;">YES</div>
                    <div style="font-size: 9px; font-weight: bold; letter-spacing: 0.5px; margin-top: 4px;">MACHINERY</div>
                </div>
            </td>
        </tr>
    </table>
    <div style="height: 3px; background-color: #7AC142; width: 100%; margin-bottom: 20px;"></div>

    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 8px 0; font-size: 14px; font-weight: bold;">1. Employee Information</h3>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
            <tr><td style="border: 1px solid #333; padding: 5px 8px; width: 35%; font-weight: bold;">Employee Name</td><td style="border: 1px solid #333; padding: 5px 8px; width: 65%;">{{employee_name}}</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Employee ID</td><td style="border: 1px solid #333; padding: 5px 8px;">{{employee_code}}</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Department</td><td style="border: 1px solid #333; padding: 5px 8px;">{{department}}</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Job Title</td><td style="border: 1px solid #333; padding: 5px 8px;">{{designation}}</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Nationality</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Date of Birth</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Emirates ID</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Mobile Number</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Email Address</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Residential Address</td><td style="border: 1px solid #333; padding: 25px 8px;">&nbsp;</td></tr>
        </table>
    </div>

    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 8px 0; font-size: 14px; font-weight: bold;">2. Emergency Contact Details in UAE</h3>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
            <tr><td style="border: 1px solid #333; padding: 5px 8px; width: 35%; font-weight: bold;">Contact Person Name</td><td style="border: 1px solid #333; padding: 5px 8px; width: 65%;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Relationship with Employee</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Contact Number</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Alternate Contact Number</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Nationality</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Email Address</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
        </table>
    </div>

    <div style="page-break-before: always; margin-top: 25px;"></div>

    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 8px 0; font-size: 14px; font-weight: bold;">3. Emergency Contact Details in Native Country</h3>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
            <tr><td style="border: 1px solid #333; padding: 5px 8px; width: 35%; font-weight: bold;">Contact Person Name</td><td style="border: 1px solid #333; padding: 5px 8px; width: 65%;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Relationship with Employee</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Contact Number</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Alternate Contact Number</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Nationality</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Email Address</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Residential Address</td><td style="border: 1px solid #333; padding: 25px 8px;">&nbsp;</td></tr>
        </table>
    </div>

    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 8px 0; font-size: 14px; font-weight: bold;">4. Nominee Details (For Gratuity, Final Settlement, )</h3>
        
        <p style="margin: 4px 0; font-weight: bold; font-size: 13px;">Nominee Details 1</p>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 12px;">
            <tr><td style="border: 1px solid #333; padding: 5px 8px; width: 35%; font-weight: bold;">Nominee Name</td><td style="border: 1px solid #333; padding: 5px 8px; width: 65%;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Relationship with Employee</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Date of Birth</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Nationality</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Address</td><td style="border: 1px solid #333; padding: 15px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Percentage of Benefit</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
        </table>

        <p style="margin: 4px 0; font-weight: bold; font-size: 13px;">Nominee Details 2</p>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 12px;">
            <tr><td style="border: 1px solid #333; padding: 5px 8px; width: 35%; font-weight: bold;">Nominee Name</td><td style="border: 1px solid #333; padding: 5px 8px; width: 65%;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Relationship with Employee</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Date of Birth</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Nationality</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Address</td><td style="border: 1px solid #333; padding: 15px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Percentage of Benefit</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
        </table>

        <p style="margin: 4px 0; font-weight: bold; font-size: 13px;">Nominee Details 3</p>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 12px;">
            <tr><td style="border: 1px solid #333; padding: 5px 8px; width: 35%; font-weight: bold;">Nominee Name</td><td style="border: 1px solid #333; padding: 5px 8px; width: 65%;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Relationship with Employee</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Date of Birth</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Nationality</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Address</td><td style="border: 1px solid #333; padding: 15px 8px;">&nbsp;</td></tr>
            <tr><td style="border: 1px solid #333; padding: 5px 8px; font-weight: bold;">Percentage of Benefit</td><td style="border: 1px solid #333; padding: 5px 8px;">&nbsp;</td></tr>
        </table>
    </div>

    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 8px 0; font-size: 14px; font-weight: bold;">5. Benefits Covered</h3>
        <p style="margin: 0 0 6px 0; font-size: 12px;">The nominee shall be entitled to receive the following benefits in case of the employee’s death:</p>
        <p style="margin: 4px 0 4px 15px; font-size: 12px;">• End of Service Gratuity</p>
        <p style="margin: 4px 0 4px 15px; font-size: 12px;">• Pending Salary / Final Settlement/ Leave Encashment</p>
    </div>

    <div style="margin-bottom: 20px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
        <h3 style="margin: 0 0 8px 0; font-size: 14px; font-weight: bold;">6. Declaration by Employee</h3>
        <p style="margin: 0 0 15px 0; font-size: 12px; line-height: 1.5; text-align: justify;">
            I hereby declare that the above information is true and correct to the best of my knowledge. I authorize the company to release my dues and benefits to the nominated person(s) listed above in the event of my demise.
        </p>
        <p style="margin: 6px 0; font-size: 13px;">• Employee Signature: <span style="display: inline-block; width: 280px; border-bottom: 1px solid #000;">&nbsp;</span></p>
        <p style="margin: 6px 0; font-size: 13px;">• Employee Name: <span style="display: inline-block; width: 300px; border-bottom: 1px solid #000;">&nbsp;</span></p>
        <p style="margin: 6px 0; font-size: 13px;">• Date: <span style="display: inline-block; width: 220px; border-bottom: 1px solid #000;">&nbsp;</span></p>
    </div>
</div>
HTML;

        DocumentTemplate::query()->updateOrCreate(
            [
                'document_type_id' => $nomineeType->id,
                'template_name' => 'Employee Nominee Declaration Form Template',
            ],
            [
                'template_code' => $nomineeHtml,
                'status' => 1,
            ]
        );

        // 7. Create / Update Dependent Air Ticket Claim Template
        $airTicketHtml = <<<'HTML'
<div style="font-family: Arial, sans-serif; font-size: 13px; line-height: 1.5; color: #111; max-width: 800px; margin: auto; padding: 25px;">
    <div style="text-align: center; margin-bottom: 25px;">
        <h3 style="margin: 0; font-size: 16px; font-weight: bold;">York Engineering Solutions FZC</h3>
        <h2 style="margin: 4px 0; font-size: 18px; font-weight: bold; text-transform: uppercase;">Dependent Air Ticket Claim Form</h2>
        <p style="margin: 0; font-size: 13px; font-style: italic; color: #475569;">Years of Service Benefit</p>
    </div>

    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 10px 0; font-size: 14px; font-weight: bold;">Employee Information</h3>
        <table style="width: 100%; border: none; font-size: 13px;">
            <tr><td style="padding: 4px 0; width: 26%;">• Employee Name:</td><td style="padding: 4px 0; border-bottom: 1px solid #333; width: 74%; font-weight: bold;">{{employee_name}}</td></tr>
            <tr><td style="padding: 4px 0;">• Department:</td><td style="padding: 4px 0; border-bottom: 1px solid #333;">{{department}}</td></tr>
            <tr><td style="padding: 4px 0;">• Date of Joining:</td><td style="padding: 4px 0; border-bottom: 1px solid #333;">{{joining_date}}</td></tr>
            <tr><td style="padding: 4px 0;">• Completed Years of Service:</td><td style="padding: 4px 0; border-bottom: 1px solid #333;">&nbsp;</td></tr>
        </table>
    </div>

    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 10px 0; font-size: 14px; font-weight: bold;">Dependent Information</h3>
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 12px;">
            <thead>
                <tr style="background-color: #f8fafc;">
                    <th style="border: 1px solid #000; padding: 6px 8px; width: 10%; text-align: center;">Sl. No</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; width: 32%;">Dependent Name</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; width: 22%;">Relationship</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; width: 18%;">Passport No.</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; width: 18%;">Nationality</th>
                </tr>
            </thead>
            <tbody>
                <tr><td style="border: 1px solid #000; padding: 12px 8px; text-align: center;">1</td><td style="border: 1px solid #000; padding: 12px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 12px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 12px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 12px 8px;">&nbsp;</td></tr>
                <tr><td style="border: 1px solid #000; padding: 12px 8px; text-align: center;">2</td><td style="border: 1px solid #000; padding: 12px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 12px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 12px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 12px 8px;">&nbsp;</td></tr>
                <tr><td style="border: 1px solid #000; padding: 12px 8px; text-align: center;">3</td><td style="border: 1px solid #000; padding: 12px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 12px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 12px 8px;">&nbsp;</td><td style="border: 1px solid #000; padding: 12px 8px;">&nbsp;</td></tr>
            </tbody>
        </table>
    </div>

    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 10px 0; font-size: 14px; font-weight: bold;">Travel Details</h3>
        <table style="width: 100%; border: none; font-size: 13px;">
            <tr><td style="padding: 4px 0; width: 22%;">• Travel Sector:</td><td style="padding: 4px 0; border-bottom: 1px solid #333; width: 78%;">&nbsp;</td></tr>
            <tr><td style="padding: 4px 0;">• Travel Date:</td><td style="padding: 4px 0; border-bottom: 1px solid #333;">&nbsp;</td></tr>
        </table>
    </div>

    <div style="margin-bottom: 20px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
        <h3 style="margin: 0 0 8px 0; font-size: 14px; font-weight: bold;">Declaration by Employee</h3>
        <p style="margin: 0 0 6px 0; font-size: 12px;">I hereby confirm that:</p>
        <p style="margin: 4px 0 4px 15px; font-size: 12px;">• I have completed above mentioned years of continuous service with the company.</p>
        <p style="margin: 4px 0 4px 15px; font-size: 12px;">• The submitted documents are genuine and valid.</p>
        <p style="margin: 4px 0 12px 15px; font-size: 12px;">• I understand that incomplete documentation may delay processing.</p>
        <table style="width: 100%; border: none; font-size: 13px;">
            <tr>
                <td style="width: 50%; padding: 4px 0;">Employee Signature: <span style="display: inline-block; width: 170px; border-bottom: 1px solid #000;">&nbsp;</span></td>
                <td style="width: 50%; padding: 4px 0;">Date: <span style="display: inline-block; width: 170px; border-bottom: 1px solid #000;">&nbsp;</span></td>
            </tr>
        </table>
    </div>

    <div style="margin-bottom: 20px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
        <h3 style="margin: 0 0 8px 0; font-size: 14px; font-weight: bold;">HR Verification</h3>
        <p style="margin: 6px 0; font-size: 13px;">• Eligibility Verified: &nbsp;&nbsp; ☐ Yes &nbsp;&nbsp;&nbsp;&nbsp; ☐ No</p>
        <p style="margin: 6px 0; font-size: 13px;">• Years of Service Confirmed: <span style="display: inline-block; width: 280px; border-bottom: 1px solid #000;">&nbsp;</span></p>
        <p style="margin: 6px 0; font-size: 13px;">• Documents Verified: &nbsp;&nbsp; ☐ Yes &nbsp;&nbsp;&nbsp;&nbsp; ☐ No</p>
        <p style="margin: 6px 0; font-size: 13px;">• Year of Issue: <span style="display: inline-block; width: 280px; border-bottom: 1px solid #000;">&nbsp;</span></p>
    </div>

    <div style="margin-bottom: 20px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
        <h3 style="margin: 0 0 8px 0; font-size: 14px; font-weight: bold;">Final Approval</h3>
        <p style="margin: 4px 0 8px 0; font-size: 13px;">• Management Approval</p>
        <table style="width: 100%; border: none; font-size: 13px;">
            <tr>
                <td style="width: 50%; padding: 4px 0;">Signature: <span style="display: inline-block; width: 200px; border-bottom: 1px solid #000;">&nbsp;</span></td>
                <td style="width: 50%; padding: 4px 0;">Date: <span style="display: inline-block; width: 170px; border-bottom: 1px solid #000;">&nbsp;</span></td>
            </tr>
        </table>
    </div>

    <div style="font-size: 11px; font-style: italic; color: #475569; border-top: 1px solid #e2e8f0; padding-top: 8px;">
        *Documents to be attached: Copy of Dependent Passport & Visa, Boarding Pass copy
    </div>
</div>
HTML;

        DocumentTemplate::query()->updateOrCreate(
            [
                'document_type_id' => $airTicketType->id,
                'template_name' => 'Dependent Air Ticket Claim Form Template',
            ],
            [
                'template_code' => $airTicketHtml,
                'status' => 1,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $typeIds = DocumentType::query()
            ->whereIn('document_code', ['education_allowance_claim', 'air_ticket_claim'])
            ->pluck('id');

        DocumentTemplate::query()->whereIn('document_type_id', $typeIds)->delete();
        DocumentType::query()->whereIn('id', $typeIds)->delete();
    }
};
