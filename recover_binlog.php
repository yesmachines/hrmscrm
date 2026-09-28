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

$content = file_get_contents('binlog_dump.txt');

// We look for: ### WHERE ... ###   @3='Standard NOC Template' \n ###   @4='<div ...'
$templates = ['Standard NOC Template', 'Standard Salary Certificate', 'Standard Salary Transfer Letter'];

foreach($templates as $name) {
    if(preg_match("/### WHERE.*?###   @3='$name'\s+###   @4='(.*?)'\s+###   @5=1\s+### SET/s", $content, $matches)) {
        $html = $matches[1];
        $html = str_replace(["\\n", "\\'", "\\\""], ["\n", "'", "\""], $html);
        
        // Now carefully inject the stamp and signature just before Shahid Chettianthodika
        $search = '<br><br><br>';
        $replace = '<div style="margin: 10px 0;">
            <img src="{{company_signature_url}}" style="width: 160px; height: auto; display: inline-block; vertical-align: middle;" />
            <img src="{{company_stamp_url}}" style="width: 120px; height: auto; display: inline-block; vertical-align: middle; margin-left: 20px;" />
        </div>';
        $html = str_replace($search, $replace, $html);

        $stmt = $pdo->prepare("UPDATE document_templates SET template_code = ? WHERE template_name = ?");
        $stmt->execute([$html, $name]);
        echo "Restored $name from binlog!\n";
    }
}
