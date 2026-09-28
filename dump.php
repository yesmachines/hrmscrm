<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=hrms_new", "root", "");
$stmt = $pdo->query("SHOW FULL COLUMNS FROM document_templates WHERE Field = 'template_code'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($row);
