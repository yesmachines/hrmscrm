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

$stmt = $pdo->query("SELECT id, template_name, template_code FROM document_templates LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} - Name: {$row['template_name']}\n";
    if (strpos($row['template_code'], 'company_signature_url') !== false) {
        echo "--> Contains signature.\n";
    } else {
        echo "--> MISSING signature.\n";
    }
}
