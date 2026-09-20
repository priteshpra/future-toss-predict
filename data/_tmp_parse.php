<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/live.php';

$html = file_get_contents(__DIR__ . '/_tmp_live.html');
$by = parse_cricbuzz_live_html($html, 'https://www.cricbuzz.com/cricket-match/live-scores');
echo "dates " . implode(',', array_keys($by)) . PHP_EOL;
foreach ($by as $d => $rows) {
    echo $d . ' ' . count($rows) . PHP_EOL;
    foreach ($rows as $m) {
        echo '  ' . $m['teamA'] . ' vs ' . $m['teamB'] . ' | ' . $m['status'] . ' | ' . $m['time'] . PHP_EOL;
    }
}
