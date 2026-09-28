<?php
$content = file_get_contents('binlog_dump.txt');
preg_match_all('/### UPDATE `hrmscrm`\.`document_templates`\s+### WHERE\s+.*?###   @3=\'(.*?)\'\s+###   @4=\'(.*?)\'\s+###   @5=1\s+### SET/s', $content, $matches);

if(empty($matches[0])) {
    echo "No matches found.";
} else {
    $out = [];
    foreach($matches[1] as $index => $name) {
        $html = $matches[2][$index];
        // The binlog dumps single quotes escaped, but here they might just be logged.
        // We will decode it.
        $out[$name] = str_replace(["\\n", "\\'"], ["\n", "'"], $html);
    }
    file_put_contents('recovered_templates.json', json_encode($out, JSON_PRETTY_PRINT));
    echo "Recovered " . count($out) . " templates.\n";
}
