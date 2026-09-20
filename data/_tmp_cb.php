<?php
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/live.php';

$urls = [
    'html-all' => 'https://www.cricbuzz.com/cricket-schedule/upcoming-series/all',
    'cb-upcoming' => 'https://www.cricbuzz.com/api/cricket-schedule/upcoming',
    'cb-live' => 'https://www.cricbuzz.com/matches/v1/live',
    'cb-up' => 'https://www.cricbuzz.com/matches/v1/upcoming',
    'cb-recent' => 'https://www.cricbuzz.com/matches/v1/recent',
    'espn' => 'https://hs-consumer-api.espncricinfo.com/v1/pages/matches/current?lang=en&latest=true',
];
foreach ($urls as $k => $u) {
    $h = http_get($u, 12);
    $len = strlen((string) $h);
    $head = substr(ltrim((string) $h), 0, 80);
    echo $k . ' len=' . $len . ' head=' . preg_replace('/\s+/', ' ', $head) . PHP_EOL;
}
$sched = fetch_cricbuzz_schedule();
echo 'parsed dates ' . count($sched) . ' today=' . count($sched[ist_today()] ?? []) . PHP_EOL;
