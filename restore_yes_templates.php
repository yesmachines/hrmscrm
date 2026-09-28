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

$logo = '
<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px;">
    <div>
        <div style="background-color: #7AC142; color: white; padding: 15px 10px; width: 80px; text-align: center;">
            <div style="font-size: 28px; font-weight: 900; line-height: 1; font-family: Arial, sans-serif;">YES</div>
            <div style="font-size: 10px; font-weight: bold; letter-spacing: 0.5px; margin-top: 5px;">MACHINERY</div>
        </div>
    </div>
    <div style="border-bottom: 2px solid #7AC142; padding-bottom: 5px;">
        <h3 style="margin: 0; font-family: Arial, sans-serif; font-size: 16px; color: #333; font-weight: bold;">YORK ENGINEERING SOLUTIONS FZC</h3>
    </div>
</div>
';

$footer = '
<div style="margin-top: 50px; text-align: center; font-size: 10px; color: #333; font-family: Arial, sans-serif;">
    <p style="margin: 2px 0;"><strong>P O Box: 42167 | WAREHOUSE LV27D | HAMRIYA FREE ZONE PHASE 2 | SHARJAH | UAE | TEL +971 6 5264382 | FAX +971 6 5264384</strong></p>
    <p style="margin: 2px 0;"><strong>E-mail: sales@yesmachinery.ae | Web: www.yesmachinery.ae | TRN: 100385872500003</strong></p>
    <p style="margin: 2px 0; font-size: 11px; font-family: \'DejaVu Sans\', sans-serif;" dir="rtl"><strong>ص.ب: ٤٢١٦٧ ، مستودع LV27D ، المنطقة الحرة بالحمرية منطقة ٢ - الشارقة - الإمارات العربية المتحدة ، تليفون : ٩٧١٦٥٢٦٤٣٨٢+ ، فاكس: ٩٧١٦٥٢٦٤٣٨٤+</strong></p>
    <div style="height: 12px; background-color: #7AC142; width: 100%; margin-top: 8px;"></div>
</div>
';

$templates = [
    'Standard NOC Template' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 15px; line-height: 1.5; color: #333; max-width: 800px; margin: auto; padding: 20px;">
    ' . $logo . '
    
    <p style="margin-bottom: 20px;">{{issue_date}}</p>
    
    <p style="margin-bottom: 5px;">To,</p>
    <p style="margin-top: 0; margin-bottom: 25px; color: red;">{{to_address}}</p>
    
    <p style="margin-bottom: 25px; font-weight: bold; text-decoration: underline;">Subject: NO OBJECTION LETTER</p>
    
    <p style="margin-bottom: 10px;">Dear Sir/Madam,</p>
    <p style="margin-bottom: 15px;">On behalf of HFZA York Engineering Solutions FZC, I hereby state and confirm permission for <span style="color: #16a34a; font-weight: bold;">{{employee_name}}</span> holder of Passport no: <span style="color: #16a34a; font-weight: bold;">{{passport_number}}</span>, working with our company as <span style="color: #16a34a;">{{designation}}</span> since <span style="color: #16a34a;">{{joining_date}}</span>, for a monthly salary of <span style="color: #16a34a;">AED {{gross_salary}}/- ({{gross_salary_in_words}})</span>, to have <span style="color: red;">{{purpose}}</span>.</p>
    
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
    
    ' . $footer . '
</div>'
];

$stmt = $pdo->prepare("UPDATE document_templates SET template_code = ? WHERE template_name = ?");
foreach($templates as $name => $code) {
    $stmt->execute([$code, $name]);
}
echo "Templates completely restored!";
