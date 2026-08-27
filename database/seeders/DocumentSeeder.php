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
                        'document_name' => 'Employee Nomination Form',
                        'document_code' => 'nomination_form',
                        'requires_number' => false,
                        'requires_expiry' => false,
                        'editable_before_approval' => true,
                        'requires_hr_approval' => true,
                        'requires_reminder' => true,
                        'record_source' => 'uploaded',
                        'requires_attachments' => true,
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
                        'templates' => [
                            [
                                'template_name' => 'Standard NOC Template',
                                'template_code' => '<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333; max-width: 800px; margin: auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 8px;">
    <div style="text-align: center; border-bottom: 2px solid #0284c7; padding-bottom: 15px; margin-bottom: 25px;">
        <h2 style="margin: 0; color: #0f172a; text-transform: uppercase;">{{company_name}}</h2>
        <p style="margin: 5px 0 0; color: #64748b; font-size: 12px;">{{company_address}} | Contact: {{company_phone}} | Email: {{company_email}}</p>
    </div>
    
    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
        <div><strong>Ref No:</strong> {{document_number}}</div>
        <div><strong>Date:</strong> {{issue_date}}</div>
    </div>
    
    <div style="margin-bottom: 25px;">
        <p style="margin: 0;"><strong>To:</strong></p>
        <p style="margin: 0;">{{to_address}}</p>
    </div>
    
    <h3 style="text-align: center; text-decoration: underline; margin-bottom: 25px; color: #0f172a;">TO WHOM IT MAY CONCERN / NO OBJECTION CERTIFICATE</h3>
    
    <p>This is to certify that <strong>{{employee_name}}</strong> (Employee ID: <strong>{{employee_code}}</strong>, Passport No: <strong>{{passport_number}}</strong>) is currently employed with <strong>{{company_name}}</strong> as <strong>{{designation}}</strong> in the <strong>{{department}}</strong> department since <strong>{{joining_date}}</strong>.</p>
    
    <p>This No Objection Certificate is issued upon the employee\'s request for the purpose of <strong>{{purpose}}</strong>.</p>
    
    <p>We confirm that our organization has no objection whatsoever with regard to the aforementioned purpose.</p>
    
    <div style="margin-top: 50px;">
        <p style="margin: 0;">Sincerely,</p>
        <p style="margin: 40px 0 0; font-weight: bold;">Authorized Signatory</p>
        <p style="margin: 0; color: #64748b;">{{company_name}} - Human Resources Department</p>
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
                        'templates' => [
                            [
                                'template_name' => 'Standard Salary Certificate',
                                'template_code' => '<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333; max-width: 800px; margin: auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 8px;">
    <div style="text-align: center; border-bottom: 2px solid #0284c7; padding-bottom: 15px; margin-bottom: 25px;">
        <h2 style="margin: 0; color: #0f172a; text-transform: uppercase;">{{company_name}}</h2>
        <p style="margin: 5px 0 0; color: #64748b; font-size: 12px;">{{company_address}} | Contact: {{company_phone}} | Email: {{company_email}}</p>
    </div>
    
    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
        <div><strong>Ref No:</strong> {{document_number}}</div>
        <div><strong>Date:</strong> {{issue_date}}</div>
    </div>
    
    <div style="margin-bottom: 25px;">
        <p style="margin: 0;"><strong>To:</strong></p>
        <p style="margin: 0;">{{to_address}}</p>
    </div>
    
    <h3 style="text-align: center; text-decoration: underline; margin-bottom: 25px; color: #0f172a;">SALARY CERTIFICATE</h3>
    
    <p>This is to certify that <strong>{{employee_name}}</strong> (Employee ID: <strong>{{employee_code}}</strong>, Passport No: <strong>{{passport_number}}</strong>) is a permanent employee of <strong>{{company_name}}</strong> holding the position of <strong>{{designation}}</strong> since <strong>{{joining_date}}</strong>.</p>
    
    <p>The monthly remuneration breakdown is as follows:</p>
    
    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <thead>
            <tr style="background-color: #f1f5f9;">
                <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: left;">Component</th>
                <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: right;">Amount (AED)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="border: 1px solid #cbd5e1; padding: 8px 10px;">Basic Salary</td>
                <td style="border: 1px solid #cbd5e1; padding: 8px 10px; text-align: right;">{{basic_salary}}</td>
            </tr>
            <tr>
                <td style="border: 1px solid #cbd5e1; padding: 8px 10px;">Housing Allowance</td>
                <td style="border: 1px solid #cbd5e1; padding: 8px 10px; text-align: right;">{{housing_allowance}}</td>
            </tr>
            <tr>
                <td style="border: 1px solid #cbd5e1; padding: 8px 10px;">Transport & Other Allowances</td>
                <td style="border: 1px solid #cbd5e1; padding: 8px 10px; text-align: right;">{{other_allowance}}</td>
            </tr>
            <tr style="font-weight: bold; background-color: #f8fafc;">
                <td style="border: 1px solid #cbd5e1; padding: 10px;">Total Gross Salary (Monthly)</td>
                <td style="border: 1px solid #cbd5e1; padding: 10px; text-align: right; color: #0f766e;">{{gross_salary}}</td>
            </tr>
        </tbody>
    </table>
    
    <p>This certificate is issued upon the request of the employee for <strong>{{purpose}}</strong> without any financial liability on the part of the company.</p>
    
    <div style="margin-top: 50px;">
        <p style="margin: 0;">For <strong>{{company_name}}</strong>,</p>
        <p style="margin: 40px 0 0; font-weight: bold;">Human Resources Director</p>
        <p style="margin: 0; color: #64748b;">Authorized Signatory</p>
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
                        'templates' => [
                            [
                                'template_name' => 'Standard Salary Transfer Letter',
                                'template_code' => '<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333; max-width: 800px; margin: auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 8px;">
    <div style="text-align: center; border-bottom: 2px solid #0284c7; padding-bottom: 15px; margin-bottom: 25px;">
        <h2 style="margin: 0; color: #0f172a; text-transform: uppercase;">{{company_name}}</h2>
        <p style="margin: 5px 0 0; color: #64748b; font-size: 12px;">{{company_address}} | Contact: {{company_phone}} | Email: {{company_email}}</p>
    </div>
    
    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
        <div><strong>Ref No:</strong> {{document_number}}</div>
        <div><strong>Date:</strong> {{issue_date}}</div>
    </div>
    
    <div style="margin-bottom: 25px;">
        <p style="margin: 0;"><strong>To: The Branch Manager</strong></p>
        <p style="margin: 0;">{{bank_name}}</p>
        <p style="margin: 0;">{{to_address}}</p>
    </div>
    
    <h3 style="text-align: center; text-decoration: underline; margin-bottom: 25px; color: #0f172a;">IRREVOCABLE SALARY TRANSFER LETTER</h3>
    
    <p>Dear Sir/Madam,</p>
    
    <p>We confirm that <strong>{{employee_name}}</strong> (Employee ID: <strong>{{employee_code}}</strong>, Passport No: <strong>{{passport_number}}</strong>) is an employee of <strong>{{company_name}}</strong> currently working as <strong>{{designation}}</strong> with a monthly salary of <strong>AED {{gross_salary}}</strong>.</p>
    
    <p>As requested by the employee, we hereby agree to credit the employee\'s monthly net salary directly into their bank account with your bank:</p>
    
    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; margin: 15px 0;">
        <p style="margin: 4px 0;"><strong>Account Holder:</strong> {{employee_name}}</p>
        <p style="margin: 4px 0;"><strong>Account Number:</strong> {{account_number}}</p>
        <p style="margin: 4px 0;"><strong>IBAN:</strong> {{iban_number}}</p>
    </div>
    
    <p>We undertake not to transfer the salary to any other bank without obtaining written clearance from your bank.</p>
    
    <div style="margin-top: 50px;">
        <p style="margin: 0;">Sincerely,</p>
        <p style="margin: 40px 0 0; font-weight: bold;">Head of Finance / HR</p>
        <p style="margin: 0; color: #64748b;">{{company_name}}</p>
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
                                'template_code' => '<div style="font-family: Arial, sans-serif; font-size: 13px; line-height: 1.5; color: #333; max-width: 800px; margin: auto; padding: 25px; border: 1px solid #cbd5e1; border-radius: 8px;">
    <div style="text-align: center; border-bottom: 2px solid #0284c7; padding-bottom: 12px; margin-bottom: 20px;">
        <h2 style="margin: 0; color: #0f172a; text-transform: uppercase;">{{company_name}}</h2>
        <p style="margin: 4px 0; font-weight: bold; color: #0284c7;">PAYSLIP FOR THE MONTH OF {{payroll_month}}</p>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; background-color: #f8fafc; padding: 12px; border-radius: 6px;">
        <div>
            <p style="margin: 3px 0;"><strong>Employee Name:</strong> {{employee_name}}</p>
            <p style="margin: 3px 0;"><strong>Employee ID:</strong> {{employee_code}}</p>
            <p style="margin: 3px 0;"><strong>Designation:</strong> {{designation}}</p>
        </div>
        <div>
            <p style="margin: 3px 0;"><strong>Department:</strong> {{department}}</p>
            <p style="margin: 3px 0;"><strong>Joining Date:</strong> {{joining_date}}</p>
            <p style="margin: 3px 0;"><strong>Bank Account:</strong> {{bank_account_masked}}</p>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #ecfdf5; border-bottom: 2px solid #10b981;">
                    <th style="padding: 8px; text-align: left;">Earnings</th>
                    <th style="padding: 8px; text-align: right;">Amount (AED)</th>
                </tr>
            </thead>
            <tbody>
                <tr><td style="padding: 6px 8px; border-bottom: 1px solid #f1f5f9;">Basic Salary</td><td style="padding: 6px 8px; text-align: right; border-bottom: 1px solid #f1f5f9;">{{basic_salary}}</td></tr>
                <tr><td style="padding: 6px 8px; border-bottom: 1px solid #f1f5f9;">Housing Allowance</td><td style="padding: 6px 8px; text-align: right; border-bottom: 1px solid #f1f5f9;">{{housing_allowance}}</td></tr>
                <tr><td style="padding: 6px 8px; border-bottom: 1px solid #f1f5f9;">Transport Allowance</td><td style="padding: 6px 8px; text-align: right; border-bottom: 1px solid #f1f5f9;">{{transport_allowance}}</td></tr>
                <tr style="font-weight: bold; background-color: #f0fdf4;"><td style="padding: 8px;">Total Earnings</td><td style="padding: 8px; text-align: right; color: #15803d;">{{gross_earnings}}</td></tr>
            </tbody>
        </table>
        
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #fef2f2; border-bottom: 2px solid #ef4444;">
                    <th style="padding: 8px; text-align: left;">Deductions</th>
                    <th style="padding: 8px; text-align: right;">Amount (AED)</th>
                </tr>
            </thead>
            <tbody>
                <tr><td style="padding: 6px 8px; border-bottom: 1px solid #f1f5f9;">Unpaid Leaves</td><td style="padding: 6px 8px; text-align: right; border-bottom: 1px solid #f1f5f9;">{{deductions_unpaid}}</td></tr>
                <tr><td style="padding: 6px 8px; border-bottom: 1px solid #f1f5f9;">Other Deductions</td><td style="padding: 6px 8px; text-align: right; border-bottom: 1px solid #f1f5f9;">{{deductions_other}}</td></tr>
                <tr style="font-weight: bold; background-color: #fff1f2;"><td style="padding: 8px;">Total Deductions</td><td style="padding: 8px; text-align: right; color: #b91c1c;">{{total_deductions}}</td></tr>
            </tbody>
        </table>
    </div>
    
    <div style="background-color: #0f172a; color: #fff; padding: 12px 20px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <span style="font-size: 14px; font-weight: bold;">NET PAYABLE AMOUNT:</span>
        <span style="font-size: 18px; font-weight: bold; color: #38bdf8;">AED {{net_salary}}</span>
    </div>
    
    <p style="text-align: center; color: #94a3b8; font-size: 11px; margin-top: 30px;">This is a computer-generated payslip and does not require a physical signature.</p>
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
