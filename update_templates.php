<?php
$env = [];
$lines = file('.env');
foreach ($lines as $line) {
    if (strpos($line, '=') !== false) {
        list($k, $v) = explode('=', trim($line), 2);
        $env[$k] = $v;
    }
}

$pdo = new PDO("mysql:host={$env['DB_HOST']};dbname={$env['DB_DATABASE']};charset=utf8mb4", $env['DB_USERNAME'], $env['DB_PASSWORD']);

$templates = [
    'Standard NOC Template' => '<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333; max-width: 800px; margin: auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 8px;">
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
    
    <div style="margin-top: 40px;">
        <p style="margin: 0;">Sincerely,</p>
        <div style="margin: 10px 0;">
            <img src="{{company_signature_url}}" style="width: 160px; height: auto; display: inline-block; vertical-align: middle;" />
            <img src="{{company_stamp_url}}" style="width: 120px; height: auto; display: inline-block; vertical-align: middle; margin-left: 20px;" />
        </div>
        <p style="margin: 0; font-weight: bold;">Authorized Signatory</p>
        <p style="margin: 0; color: #64748b;">{{company_name}} - Human Resources Department</p>
    </div>
</div>',
    'Standard Salary Certificate' => '<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333; max-width: 800px; margin: auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 8px;">
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
    
    <div style="margin-top: 40px;">
        <p style="margin: 0;">For <strong>{{company_name}}</strong>,</p>
        <div style="margin: 10px 0;">
            <img src="{{company_signature_url}}" style="width: 160px; height: auto; display: inline-block; vertical-align: middle;" />
            <img src="{{company_stamp_url}}" style="width: 120px; height: auto; display: inline-block; vertical-align: middle; margin-left: 20px;" />
        </div>
        <p style="margin: 0; font-weight: bold;">Human Resources Director</p>
        <p style="margin: 0; color: #64748b;">Authorized Signatory</p>
    </div>
</div>',
    'Standard Salary Transfer Letter' => '<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333; max-width: 800px; margin: auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 8px;">
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
    
    <div style="margin-top: 40px;">
        <p style="margin: 0;">Sincerely,</p>
        <div style="margin: 10px 0;">
            <img src="{{company_signature_url}}" style="width: 160px; height: auto; display: inline-block; vertical-align: middle;" />
            <img src="{{company_stamp_url}}" style="width: 120px; height: auto; display: inline-block; vertical-align: middle; margin-left: 20px;" />
        </div>
        <p style="margin: 0; font-weight: bold;">Head of Finance / HR</p>
        <p style="margin: 0; color: #64748b;">{{company_name}}</p>
    </div>
</div>'
];

$stmt = $pdo->prepare("UPDATE document_templates SET template_code = ? WHERE template_name = ?");
foreach($templates as $name => $code) {
    $stmt->execute([$code, $name]);
}
echo "Templates updated via PDO!";
