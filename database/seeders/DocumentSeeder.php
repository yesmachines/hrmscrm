<?php

namespace Database\Seeders;

use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'category_name' => 'Employee Personal Documents',
                'short_code' => 'personal',
                'status' => 1,
                'types' => [
                    [
                        'document_name' => 'Passport',
                        'document_code' => 'passport',
                        'requires_number' => true,
                        'requires_expiry' => true,
                        'editable_before_approval' => true,
                        'requires_hr_approval' => true,
                        'requires_reminder' => true,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                    [
                        'document_name' => 'Emirates ID',
                        'document_code' => 'emirates_id',
                        'requires_number' => true,
                        'requires_expiry' => true,
                        'editable_before_approval' => true,
                        'requires_hr_approval' => true,
                        'requires_reminder' => true,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                    [
                        'document_name' => 'Visa',
                        'document_code' => 'visa',
                        'requires_number' => true,
                        'requires_expiry' => true,
                        'editable_before_approval' => true,
                        'requires_hr_approval' => true,
                        'requires_reminder' => true,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                    [
                        'document_name' => 'Insurance',
                        'document_code' => 'insurance',
                        'requires_number' => true,
                        'requires_expiry' => true,
                        'editable_before_approval' => false,
                        'requires_hr_approval' => true,
                        'requires_reminder' => true,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                    [
                        'document_name' => 'Driving Licence',
                        'document_code' => 'driving_licence',
                        'requires_number' => true,
                        'requires_expiry' => true,
                        'editable_before_approval' => true,
                        'requires_hr_approval' => true,
                        'requires_reminder' => true,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                    [
                        'document_name' => 'Education Documents',
                        'document_code' => 'education',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => true,
                        'requires_hr_approval' => true,
                        'requires_reminder' => false,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                    [
                        'document_name' => 'Experience Certificate',
                        'document_code' => 'experience',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => true,
                        'requires_hr_approval' => true,
                        'requires_reminder' => false,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                    [
                        'document_name' => 'Dependent Air Ticket Claim Form',
                        'document_code' => 'air_ticket_claim',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => true,
                        'requires_hr_approval' => true,
                        'requires_reminder' => false,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                        'templates' => [
                            [
                                'template_name' => 'Dependent Air Ticket Claim Form Template',
                                'template_code' => '<div style="font-family: Arial, sans-serif; font-size: 13px; line-height: 1.5; color: #111; max-width: 800px; margin: auto; padding: 25px;">
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
</div>',
                                'status' => 1,
                            ],
                        ],
                    ],
                    [
                        'document_name' => 'Education Allowance Claim Form',
                        'document_code' => 'education_allowance_claim',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => true,
                        'requires_hr_approval' => true,
                        'requires_reminder' => false,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                        'templates' => [
                            [
                                'template_name' => 'Education Allowance Claim Form Template',
                                'template_code' => '<div style="font-family: Arial, sans-serif; font-size: 13px; line-height: 1.5; color: #111; max-width: 800px; margin: auto; padding: 25px;">
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
            <strong>Total Amount Claimed: {{currency}}</strong> <span style="display: inline-block; width: 220px; border-bottom: 1px solid #000;">&nbsp;</span>
        </div>
    </div>

    <div style="page-break-before: always; margin-top: 25px;"></div>

    <div style="margin-bottom: 25px;">
        <h3 style="margin: 0 0 10px 0; font-size: 14px; font-weight: bold;">Supporting Documents Attached</h3>
        <p style="margin: 6px 0; font-size: 13px;">☐ &nbsp; School Fee Receipt(s)</p>
        <p style="margin: 6px 0; font-size: 13px;">☐ &nbsp; Admission/Enrollment Confirmation</p>
        <p style="margin: 6px 0; font-size: 13px;">☐ &nbsp; Child\'s Passport/ ID Copy</p>
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
        <p style="margin: 4px 0 12px 0; font-size: 13px;">Approved Amount: {{currency}} <span style="display: inline-block; width: 220px; border-bottom: 1px solid #000;">&nbsp;</span></p>
        <table style="width: 100%; border: none; font-size: 13px;">
            <tr>
                <td style="width: 40%; padding: 4px 0;">Approver Name: <span style="display: inline-block; width: 130px; border-bottom: 1px solid #000;">&nbsp;</span></td>
                <td style="width: 32%; padding: 4px 0;">Signature: <span style="display: inline-block; width: 110px; border-bottom: 1px solid #000;">&nbsp;</span></td>
                <td style="width: 28%; padding: 4px 0;">Date: <span style="display: inline-block; width: 110px; border-bottom: 1px solid #000;">&nbsp;</span></td>
            </tr>
        </table>
    </div>

    <div style="font-size: 11px; font-style: italic; line-height: 1.4; color: #475569; border-top: 1px solid #e2e8f0; padding-top: 10px;">
        Education Allowance claims are subject to verification of the submitted documents. Reimbursement will be made for the actual tuition fees paid, up to the maximum education allowance entitlement as per the Company\'s policy, whichever is lower.
    </div>
</div>',
                                'status' => 1,
                            ],
                        ],
                    ],
                    [
                        'document_name' => 'Employee Nomination Form',
                        'document_code' => 'nomination_form',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => true,
                        'requires_hr_approval' => true,
                        'requires_reminder' => true,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                        'templates' => [
                            [
                                'template_name' => 'Employee Nominee Declaration Form Template',
                                'template_code' => '<div style="font-family: Arial, sans-serif; font-size: 13px; line-height: 1.5; color: #111; max-width: 800px; margin: auto; padding: 25px;">
    <table style="width: 100%; border: none; margin-bottom: 20px;">
        <tr>
            <td style="vertical-align: middle;">
                <h2 style="margin: 0; font-size: 20px; font-weight: bold; color: #1e293b;">Employee Nominee Declaration Form</h2>
            </td>
            <td style="width: 110px; text-align: right; vertical-align: top;">
                <div style="background-color: #7AC142; color: white; padding: 12px 6px; width: 95px; text-align: center; display: inline-block;">
                    <div style="font-size: 26px; font-weight: 900; line-height: 1; font-family: \'Arial Black\', sans-serif;">YES</div>
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
</div>',
                                'status' => 1,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'category_name' => 'Employment Documents',
                'short_code' => 'employment',
                'status' => 1,
                'types' => [
                    [
                        'document_name' => 'Offer Letter',
                        'document_code' => 'offer_letter',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => false,
                        'requires_hr_approval' => false,
                        'requires_reminder' => false,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                    [
                        'document_name' => 'Employment Contracts',
                        'document_code' => 'employment_contracts',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => false,
                        'requires_hr_approval' => false,
                        'requires_reminder' => false,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                    [
                        'document_name' => 'Confirmation Letter',
                        'document_code' => 'confirmation_letter',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => false,
                        'requires_hr_approval' => false,
                        'requires_reminder' => false,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                    [
                        'document_name' => 'Labour Card',
                        'document_code' => 'labour_card',
                        'requires_number' => true,
                        'requires_expiry' => true,
                        'editable_before_approval' => false,
                        'requires_hr_approval' => true,
                        'requires_reminder' => true,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                ],
            ],
            [
                'category_name' => 'Performance Docs',
                'short_code' => 'performance',
                'status' => 1,
                'types' => [
                    [
                        'document_name' => 'Appraisal Letter',
                        'document_code' => 'appraisal_letter',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => false,
                        'requires_hr_approval' => false,
                        'requires_reminder' => false,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                    [
                        'document_name' => 'Appreciation Letter',
                        'document_code' => 'appreciation_letter',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => false,
                        'requires_hr_approval' => false,
                        'requires_reminder' => false,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                ],
            ],
            [
                'category_name' => 'Disciplinary',
                'short_code' => 'disciplinary',
                'status' => 1,
                'types' => [
                    [
                        'document_name' => 'Warning Letter',
                        'document_code' => 'warning_letter',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => false,
                        'requires_hr_approval' => false,
                        'requires_reminder' => false,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                    [
                        'document_name' => 'Termination Letter',
                        'document_code' => 'termination_letter',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => false,
                        'requires_hr_approval' => false,
                        'requires_reminder' => false,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                    [
                        'document_name' => 'Memo',
                        'document_code' => 'memo',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => false,
                        'requires_hr_approval' => false,
                        'requires_reminder' => false,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                ],
            ],
            [
                'category_name' => 'HR Documents',
                'short_code' => 'hr_docs',
                'status' => 1,
                'types' => [
                    [
                        'document_name' => 'HR Policy',
                        'document_code' => 'hr_policy',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => false,
                        'requires_hr_approval' => false,
                        'requires_reminder' => false,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                    [
                        'document_name' => 'Leave Policy',
                        'document_code' => 'leave_policy',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => false,
                        'requires_hr_approval' => false,
                        'requires_reminder' => false,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                    [
                        'document_name' => 'WFH Policy',
                        'document_code' => 'wfh_policy',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => false,
                        'requires_hr_approval' => false,
                        'requires_reminder' => false,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
                    ],
                ],
            ],
            [
                'category_name' => 'Letter Requests',
                'short_code' => 'letter_requests',
                'status' => 1,
                'types' => [
                    [
                        'document_name' => 'NOC',
                        'document_code' => 'noc',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => true,
                        'requires_hr_approval' => true,
                        'requires_reminder' => false,
                        'record_source' => 'generated',
                        'requires_attachments' => false,
                        'field_requires' => [
                            'to_address' => true,
                            'visa_designation' => true,
                            'destination_country' => true,
                            'purpose' => true,
                        ],
                        'templates' => [
                            [
                                'template_name' => 'Standard NOC Template',
                                'template_code' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 15px; line-height: 1.5; color: #333; max-width: 800px; margin: auto; padding: 20px;">
    
<div class="letter-header">
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
</div>

    
    <p style="margin-bottom: 20px;">{{issue_date}}</p>
    
    <p style="margin-bottom: 5px;">To,</p>
    <p style="margin-top: 0; margin-bottom: 25px; color: red;">{{to_address}}</p>
    
    <p style="margin-bottom: 25px; font-weight: bold; text-decoration: underline;">Subject: NO OBJECTION LETTER</p>
    
    <p style="margin-bottom: 10px;">Dear Sir/Madam,</p>
    <p style="margin-bottom: 15px;">On behalf of HFZA York Engineering Solutions FZC, I hereby state and confirm permission for <span style="color: #16a34a; font-weight: bold;">{{employee_name}}</span> holder of Passport no: <span style="color: #16a34a; font-weight: bold;">{{passport_number}}</span>, working with our company as <span style="color: #16a34a;">{{designation}}</span> since <span style="color: #16a34a;">{{joining_date}}</span>, for a monthly salary of <span style="color: #16a34a;">{{currency}} {{gross_salary}}/- ({{gross_salary_in_words}})</span>, to have <span style="color: red;">{{purpose}}</span>.</p>
    
    <p style="margin-bottom: 15px;">Therefore, we request you to grant him/her a <span style="color: red;">visa to {{destination_country}}</span>.</p>
    
    <p style="margin-bottom: 15px;">On behalf of HFZA York Engineering Solutions FZC, I guarantee that all expenses involved in his/her trips will be fully covered by our company (<strong>HFZA York Engineering Solutions FZC</strong>), also we hold ourselves responsible for his/her conduct to comply with the laws of <span style="color: red;">{{destination_country}}</span>.</p>
    
    <p style="margin-bottom: 40px;">Also, we assure you that <span style="color: #16a34a;">{{employee_name}}</span> will return to the UAE before the expiry of his/her visa.</p>
    
    <p style="margin: 0;">For York Engineering Solutions FZC</p>
    <div style="margin: 10px 0;">
        <img src="{{company_signature_url}}" style="width: 160px; height: auto; display: inline-block; vertical-align: middle;" />
        <img src="{{company_stamp_url}}" style="width: 120px; height: auto; display: inline-block; vertical-align: middle; margin-left: 20px;" />
    </div>
    <p style="margin: 0;">Shahid Chettianthodika</p>
    <p style="margin: 0;">Chief Financial Officer</p>
    <p style="margin: 0;">York Engineering Solutions FZC</p>
    <p style="margin: 0;">Sharjah, UAE</p>
    
    
<div class="letter-footer" style="margin-top: 90px; text-align: center; font-size: 9px; line-height: 1.15; color: #333; font-family: Arial, sans-serif;">
    <p style="margin: 1px 0;"><strong>P O Box: 42167 | WAREHOUSE LV27D | HAMRIYA FREE ZONE PHASE 2 | SHARJAH | UAE | TEL +971 6 5264382 | FAX +971 6 5264384</strong></p>
    <p style="margin: 1px 0;"><strong>E-mail: sales@yesmachinery.ae | Web: www.yesmachinery.ae | TRN: 100385872500003</strong></p>
    <p style="margin: 1px 0; font-size: 10px; font-family: \'DejaVu Sans\', sans-serif;" dir="rtl"><strong>ص.ب: ٤٢١٦٧ ، مستودع LV27D ، المنطقة الحرة بالحمرية منطقة ٢ - الشارقة - الإمارات العربية المتحدة ، تليفون : ٩٧١٦٥٢٦٤٣٨٢+ ، فاكس: ٩٧١٦٥٢٦٤٣٨٤+</strong></p>
    <div style="height: 6px; background-color: #7AC142; width: 100%; margin-top: 4px;"></div>
</div>

</div>',
                                'status' => 1,
                            ],
                        ],
                    ],
                    [
                        'document_name' => 'Salary Certificate',
                        'document_code' => 'salary_certificate',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => true,
                        'requires_hr_approval' => true,
                        'requires_reminder' => false,
                        'record_source' => 'generated',
                        'requires_attachments' => false,
                        'field_requires' => [
                            'salary_amount' => true,
                            'currency' => true,
                            'purpose' => true,
                        ],
                        'templates' => [
                            [
                                'template_name' => 'Standard Salary Certificate',
                                'template_code' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 15px; line-height: 1.5; color: #333; max-width: 800px; margin: auto; padding: 20px;">
    
<div class="letter-header">
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
</div>

    
    <p style="margin-bottom: 20px;">{{issue_date}}</p>
    
    <p style="margin-bottom: 5px;">To,</p>
    <p style="margin-top: 0; margin-bottom: 25px; color: red;">{{to_address}}</p>
    
    <p style="margin-bottom: 25px; font-weight: bold; text-decoration: underline;">SUB: SALARY CERTIFICATE</p>
    
    <p style="margin-bottom: 25px;">This is to certify that <span style="color: #16a34a; font-weight: bold;">{{employee_name}}</span> holder of Passport number: <span style="color: #16a34a; font-weight: bold;">{{passport_number}}</span> is working with York Engineering Solutions FZC as <span style="color: #16a34a;">{{designation}}</span> since <span style="color: #16a34a;">{{joining_date}}</span>. He/She presently draws a monthly salary of <span style="color: #16a34a;">{{currency}} {{gross_salary}}/- ({{gross_salary_in_words}})</span>.</p>
    
    <p style="margin-bottom: 40px;">This certificate is issued upon his/her request for the purpose of {{purpose}} and is provided without any financial responsibility or liability on the part of the company.</p>
    
    <p style="margin: 0; font-weight: bold;">For: York Engineering Solutions FZC</p>
    <div style="margin: 10px 0;">
        <img src="{{company_signature_url}}" style="width: 160px; height: auto; display: inline-block; vertical-align: middle;" />
        <img src="{{company_stamp_url}}" style="width: 120px; height: auto; display: inline-block; vertical-align: middle; margin-left: 20px;" />
    </div>
    <p style="margin: 0; font-weight: bold;">Shahid Chettianthodika</p>
    <p style="margin: 0; font-weight: bold;">Chief Financial Officer</p>
    
    <p style="margin-top: 30px;">For verification purpose please feel free contact: <a href="mailto:swathika@yesmachinery.ae" style="color: #2563eb; text-decoration: underline;">swathika@yesmachinery.ae</a></p>
    
    
<div class="letter-footer" style="margin-top: 90px; text-align: center; font-size: 9px; line-height: 1.15; color: #333; font-family: Arial, sans-serif;">
    <p style="margin: 1px 0;"><strong>P O Box: 42167 | WAREHOUSE LV27D | HAMRIYA FREE ZONE PHASE 2 | SHARJAH | UAE | TEL +971 6 5264382 | FAX +971 6 5264384</strong></p>
    <p style="margin: 1px 0;"><strong>E-mail: sales@yesmachinery.ae | Web: www.yesmachinery.ae | TRN: 100385872500003</strong></p>
    <p style="margin: 1px 0; font-size: 10px; font-family: \'DejaVu Sans\', sans-serif;" dir="rtl"><strong>ص.ب: ٤٢١٦٧ ، مستودع LV27D ، المنطقة الحرة بالحمرية منطقة ٢ - الشارقة - الإمارات العربية المتحدة ، تليفون : ٩٧١٦٥٢٦٤٣٨٢+ ، فاكس: ٩٧١٦٥٢٦٤٣٨٤+</strong></p>
    <div style="height: 6px; background-color: #7AC142; width: 100%; margin-top: 4px;"></div>
</div>

</div>',
                                'status' => 1,
                            ],
                        ],
                    ],
                    [
                        'document_name' => 'Salary Transfer Letter',
                        'document_code' => 'salary_transfer_letter',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => true,
                        'requires_hr_approval' => true,
                        'requires_reminder' => false,
                        'record_source' => 'generated',
                        'requires_attachments' => false,
                        'field_requires' => [
                            'salary_amount' => true,
                            'currency' => true,
                            'bank_iban' => true,
                            'to_address' => true,
                            'purpose' => true,
                        ],
                        'templates' => [
                            [
                                'template_name' => 'Standard Salary Transfer Letter',
                                'template_code' => '
<div style="font-family: \'Arial\', sans-serif; font-size: 14px; line-height: 1.5; color: #000; max-width: 800px; margin: auto; padding: 20px;">
    
<div class="letter-header">
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
</div>

    
    <p style="margin-bottom: 20px; font-weight: bold;">{{issue_date}}</p>
    
    <p style="margin-bottom: 5px;">To,</p>
    <p style="margin-top: 0; margin-bottom: 25px; color: red;">{{to_address}}</p>
    
    <p style="margin-bottom: 25px; font-weight: bold;">Subject: Salary Transfer Letter</p>
    
    <p style="margin-bottom: 15px;">This is to certify that <span style="color: #16a34a; font-weight: bold;">{{employee_name}}</span> holder of Passport number: <span style="color: #16a34a;">{{passport_number}}</span> is working with <span style="color: #16a34a;">York Engineering Solutions FZC</span> as <span style="color: #16a34a;">{{designation}}</span> since {{joining_date}}. He presently draws a salary of <span style="color: #16a34a;">{{currency}} {{gross_salary}}/- ({{gross_salary_in_words}})</span>. We undertake that we will transfer directly his/her salary each month into his account number <span style="color: #16a34a;">IBAN {{iban_number}}</span> with you. We will not transfer his/her salary to any other bank or account(s) or pay it directly to the employee unless we get a clearance letter from you.</p>
    
    <p style="margin-bottom: 15px;">In case of his/her resignation or termination, we will transfer his/her end of service benefits calculated according to United Arab Emirates labour law to his /her account with your bank.</p>
    
    <p style="margin-bottom: 40px;">This letter is issued upon the <span style="color: red;">{{purpose}}</span>, without any financial responsibility and/or liability on our part.</p>
    
    <p style="margin: 0;">For York Engineering Solutions FZC</p>
    <div style="margin: 10px 0;">
        <img src="{{company_signature_url}}" style="width: 160px; height: auto; display: inline-block; vertical-align: middle;" />
        <img src="{{company_stamp_url}}" style="width: 120px; height: auto; display: inline-block; vertical-align: middle; margin-left: 20px;" />
    </div>
    <p style="margin: 0;">Shahid Chettianthodika</p>
    <p style="margin: 0;">Chief Financial Officer</p>
    
    
<div class="letter-footer" style="margin-top: 90px; text-align: center; font-size: 9px; line-height: 1.15; color: #333; font-family: Arial, sans-serif;">
    <p style="margin: 1px 0;"><strong>P O Box: 42167 | WAREHOUSE LV27D | HAMRIYA FREE ZONE PHASE 2 | SHARJAH | UAE | TEL +971 6 5264382 | FAX +971 6 5264384</strong></p>
    <p style="margin: 1px 0;"><strong>E-mail: sales@yesmachinery.ae | Web: www.yesmachinery.ae | TRN: 100385872500003</strong></p>
    <p style="margin: 1px 0; font-size: 10px; font-family: \'DejaVu Sans\', sans-serif;" dir="rtl"><strong>ص.ب: ٤٢١٦٧ ، مستودع LV27D ، المنطقة الحرة بالحمرية منطقة ٢ - الشارقة - الإمارات العربية المتحدة ، تليفون : ٩٧١٦٥٢٦٤٣٨٢+ ، فاكس: ٩٧١٦٥٢٦٤٣٨٤+</strong></p>
    <div style="height: 6px; background-color: #7AC142; width: 100%; margin-top: 4px;"></div>
</div>

</div>',
                                'status' => 1,
                            ],
                        ],
                    ],
                    [
                        'document_name' => 'Pay Slip',
                        'document_code' => 'pay_slip',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => true,
                        'requires_hr_approval' => true,
                        'requires_reminder' => false,
                        'record_source' => 'generated',
                        'requires_attachments' => false,
                        'templates' => [
                            [
                                'template_name' => 'Standard Pay Slip Template',
                                'template_code' => '<div style="font-family: Arial, sans-serif; font-size: 13px; line-height: 1.5; color: #333; max-width: 800px; margin: auto; padding: 25px;">
    <!-- Header Section -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
        <div style="width: 30%;">
            <img src="{{company_logo_url}}" alt="YES Machinery" style="width: 75px; height: auto; display: block;" />
        </div>
        <div style="width: 70%; text-align: right;">
            <h3 style="margin: 0; font-size: 16px; font-weight: normal;">York Engineering Solutions FZC</h3>
            <p style="margin: 4px 0; font-size: 12px;">P O Box: 42167 | WAREHOUSE NO.: LV-27-D |<br>HAMRIYAH FREE ZONE PHASE 2 | SHARJAH | UAE</p>
            <br>
            <h4 style="margin: 0; text-align: center; font-weight: normal;">Payslip for the Month of {{payroll_month}}</h4>
        </div>
    </div>
    
    <!-- Employee Details -->
    <div style="display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 13px;">
        <table style="width: 48%; border: none;">
            <tr><td style="padding: 2px 0; width: 40%;">Employee Name</td><td style="padding: 2px 0;">{{employee_name}}</td></tr>
            <tr><td style="padding: 2px 0;">Pay Days</td><td style="padding: 2px 0;">{{pay_days}}</td></tr>
            <tr><td style="padding: 2px 0;">DOJ</td><td style="padding: 2px 0;">{{joining_date}}</td></tr>
            <tr><td style="padding: 2px 0;">Department</td><td style="padding: 2px 0;">{{department}}</td></tr>
        </table>
        <table style="width: 48%; border: none;">
            <tr><td style="padding: 2px 0; width: 40%;">Designation</td><td style="padding: 2px 0;">{{designation}}</td></tr>
            <tr><td style="padding: 2px 0;">Present Days</td><td style="padding: 2px 0;">{{present_days}}</td></tr>
            <tr><td style="padding: 2px 0;">Payment Mode</td><td style="padding: 2px 0;">{{payment_mode}}</td></tr>
            <tr><td style="padding: 2px 0;">Bank Name</td><td style="padding: 2px 0;">{{bank_name}}</td></tr>
            <tr><td style="padding: 2px 0;">Bank Ac No</td><td style="padding: 2px 0;">{{bank_account}}</td></tr>
        </table>
    </div>
    
    <!-- Salary Table -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; text-align: left; font-size: 13px;">
        <thead>
            <tr>
                <th style="padding: 5px; border: 1px solid #000;" colspan="2">Fixed Earnings</th>
                <th style="padding: 5px; border: 1px solid #000;" colspan="2">Monthly/Variable Earnings</th>
                <th style="padding: 5px; border: 1px solid #000;" colspan="2">Monthly Deductions</th>
                <th style="padding: 5px; border: 1px solid #000;" colspan="2">Statutory Deduction</th>
            </tr>
            <tr>
                <th style="padding: 5px; border: 1px solid #000; font-weight: normal;"></th>
                <th style="padding: 5px; border: 1px solid #000; font-weight: normal;">Amount</th>
                <th style="padding: 5px; border: 1px solid #000; font-weight: normal;"></th>
                <th style="padding: 5px; border: 1px solid #000; font-weight: normal;">Amount</th>
                <th style="padding: 5px; border: 1px solid #000; font-weight: normal;"></th>
                <th style="padding: 5px; border: 1px solid #000; font-weight: normal;">Amount</th>
                <th style="padding: 5px; border: 1px solid #000; font-weight: normal;"></th>
                <th style="padding: 5px; border: 1px solid #000; font-weight: normal;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding: 5px; border: 1px solid #000;">Basic Salary</td><td style="padding: 5px; border: 1px solid #000;">{{basic_salary}}</td>
                <td style="padding: 5px; border: 1px solid #000;">Basic Salary</td><td style="padding: 5px; border: 1px solid #000;">{{variable_basic_salary}}</td>
                <td style="padding: 5px; border: 1px solid #000;"></td><td style="padding: 5px; border: 1px solid #000;"></td>
                <td style="padding: 5px; border: 1px solid #000;"></td><td style="padding: 5px; border: 1px solid #000;"></td>
            </tr>
            <tr>
                <td style="padding: 5px; border: 1px solid #000;">Other Allowance</td><td style="padding: 5px; border: 1px solid #000;">{{other_allowance}}</td>
                <td style="padding: 5px; border: 1px solid #000;">Other Allowance</td><td style="padding: 5px; border: 1px solid #000;">{{variable_other_allowance}}</td>
                <td style="padding: 5px; border: 1px solid #000;"></td><td style="padding: 5px; border: 1px solid #000;"></td>
                <td style="padding: 5px; border: 1px solid #000;"></td><td style="padding: 5px; border: 1px solid #000;"></td>
            </tr>
            <!-- Empty rows to match design -->
            <tr>
                <td style="padding: 5px; border: 1px solid #000;">&nbsp;</td><td style="padding: 5px; border: 1px solid #000;"></td>
                <td style="padding: 5px; border: 1px solid #000;"></td><td style="padding: 5px; border: 1px solid #000;"></td>
                <td style="padding: 5px; border: 1px solid #000;"></td><td style="padding: 5px; border: 1px solid #000;"></td>
                <td style="padding: 5px; border: 1px solid #000;"></td><td style="padding: 5px; border: 1px solid #000;"></td>
            </tr>
            <tr>
                <td style="padding: 5px; border: 1px solid #000;">&nbsp;</td><td style="padding: 5px; border: 1px solid #000;"></td>
                <td style="padding: 5px; border: 1px solid #000;"></td><td style="padding: 5px; border: 1px solid #000;"></td>
                <td style="padding: 5px; border: 1px solid #000;"></td><td style="padding: 5px; border: 1px solid #000;"></td>
                <td style="padding: 5px; border: 1px solid #000;"></td><td style="padding: 5px; border: 1px solid #000;"></td>
            </tr>
            
            <tr>
                <td style="padding: 5px; border: 1px solid #000;">Total</td><td style="padding: 5px; border: 1px solid #000;">{{total_fixed_earnings}}</td>
                <td style="padding: 5px; border: 1px solid #000;"></td><td style="padding: 5px; border: 1px solid #000;">{{total_variable_earnings}}</td>
                <td style="padding: 5px; border: 1px solid #000;">Total</td><td style="padding: 5px; border: 1px solid #000;">{{total_monthly_deductions}}</td>
                <td style="padding: 5px; border: 1px solid #000;">Total</td><td style="padding: 5px; border: 1px solid #000;">{{total_statutory_deductions}}</td>
            </tr>
            <tr>
                <td style="padding: 5px; border: 1px solid #000; text-align: center;" colspan="2">Gross Salary</td>
                <td style="padding: 5px; border: 1px solid #000; text-align: center;" colspan="2">{{gross_salary}}</td>
                <td style="padding: 5px; border: 1px solid #000; text-align: center;" colspan="2">Net Pay</td>
                <td style="padding: 5px; border: 1px solid #000; text-align: center;" colspan="2">{{net_salary}}</td>
            </tr>
            <tr>
                <td colspan="8" style="padding: 5px; border: 1px solid #000; text-align: center;">{{amount_in_words}}</td>
            </tr>
        </tbody>
    </table>
    
    <!-- Footer Section -->
    <div style="margin-top: 50px;">
        <p style="margin: 0;">For: York Engineering Solutions FZC</p>
        <br><br><br>
        <p style="margin: 0;">Shahid Chettianthodika<br>Chief Financial Officer</p>
    </div>
    
    <div style="margin-top: 50px; font-size: 13px;">
        <p style="margin: 0;">For Verification Purpose Please Feel free to contact: swathika@yesmachinery.ae</p>
    </div>
</div>',
                                'status' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($categories as $catData) {
            $types = $catData['types'];
            unset($catData['types']);

            $category = DocumentCategory::query()->firstOrCreate(
                ['short_code' => $catData['short_code']],
                $catData
            );

            foreach ($types as $typeData) {
                $templates = $typeData['templates'] ?? [];
                unset($typeData['templates']);

                $fieldRequires = $typeData['field_requires'] ?? [];
                foreach ([
                    'requires_number' => 'ref_number',
                    'requires_expiry' => 'expiry_date',
                    'requires_attachments' => 'attachments',
                ] as $legacyField => $fieldKey) {
                    if (! empty($typeData[$legacyField])) {
                        $fieldRequires[$fieldKey] = true;
                    }

                    unset($typeData[$legacyField]);
                }

                $fieldRequires = array_merge($fieldRequires, match ($typeData['document_code']) {
                    'visa' => ['uid' => true],
                    'education' => ['issue_date' => true],
                    'experience' => [
                        'issue_date' => true,
                        'total_experience' => true,
                    ],
                    default => [],
                });
                $typeData['field_requires'] = $fieldRequires === [] ? null : (object) $fieldRequires;
                $typeData['category_id'] = $category->id;

                $docType = DocumentType::query()->firstOrCreate(
                    ['document_code' => $typeData['document_code']],
                    $typeData
                );

                foreach ($templates as $tmpl) {
                    $tmpl['document_type_id'] = $docType->id;
                    DocumentTemplate::query()->updateOrCreate(
                        ['template_name' => $tmpl['template_name'], 'document_type_id' => $docType->id],
                        $tmpl
                    );
                }
            }
        }
    }
}
