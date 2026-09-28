<?php
$lines = file('binlog_dump.txt');
$inUpdate = false;
$currentHtml = '';
$currentName = '';
$out = [];
$readingString = false;
foreach($lines as $line) {
    if(strpos($line, 'UPDATE `hrmscrm`.`document_templates`') !== false) {
        $inUpdate = true;
    }
    if($inUpdate) {
        if(preg_match('/###   @3=\'(.*?)\'/', $line, $matches)) {
            $currentName = $matches[1];
        }
        if(preg_match('/###   @4=\'(.*)/', $line, $matches)) {
            $currentHtml = $matches[1];
            $readingString = true;
        } elseif ($readingString) {
            if(preg_match('/^\'$/', trim($line)) || preg_match('/^\' ###/', trim($line)) || preg_match('/^###   @5=1/', $line)) {
                $readingString = false;
                $out[$currentName] = trim(str_replace(["\\n", "\\'", "\\\""], ["\n", "'", "\""], rtrim($currentHtml, "'\r\n ")));
            } else {
                $currentHtml .= $line;
            }
        }
        if(strpos($line, '### SET') !== false) {
            $inUpdate = false;
            $readingString = false;
        }
    }
}
file_put_contents('recovered_templates.json', json_encode($out, JSON_PRETTY_PRINT));
echo "Recovered " . count($out) . " templates.\n";
