<?php
$lines = file('binlog_dump.txt');
$inUpdate = false;
$out = '';
$count = 0;
foreach($lines as $line) {
    if(strpos($line, 'UPDATE `hrmscrm`.`document_templates`') !== false) {
        $inUpdate = true;
        $count++;
    }
    if($inUpdate) {
        $out .= $line;
        if(strpos($line, '### SET') !== false) {
            $inUpdate = false;
            // The next lines are the new row, we just wanted the old row (before SET)
        }
    }
    if($count >= 3 && !$inUpdate) {
        break; // we got all 3 templates
    }
}
file_put_contents('extracted_old.txt', $out);
