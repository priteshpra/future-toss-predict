<?php
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/live.php';

$html = http_get('https://www.cricbuzz.com/cricket-schedule/upcoming-series/all', 12);
echo 'has h3 pattern ' . (preg_match('/<h3[^>]*>\s*[A-Z]{3},/', $html ?? '') ? 'yes' : 'no') . PHP_EOL;
echo 'href live ' . preg_match_all('/href="\/live-cricket-scores\/(\d+)\//', $html ?? '', $m) . PHP_EOL;
echo 'sample ids ' . implode(',', array_slice($m[1] ?? [], 0, 8)) . PHP_EOL;
echo 'next_f ' . substr_count($html ?? '', '__next_f') . PHP_EOL;
echo 'matchId ' . preg_match_all('/"matchId":(\d+)/', $html ?? '', $mm) . PHP_EOL;

$live = http_get('https://www.cricbuzz.com/cricket-match/live-scores', 12);
echo 'live page len ' . strlen((string) $live) . PHP_EOL;
echo 'live href ' . preg_match_all('/href="\/live-cricket-scores\/(\d+)\//', $live ?? '', $lm) . PHP_EOL;
echo 'live vs ' . preg_match_all('/\s+vs\s+/i', $live ?? '') . PHP_EOL;
file_put_contents(__DIR__ . '/_tmp_live.html', substr((string) $live, 0, 80000));
echo 'today ' . ist_today() . PHP_EOL;
