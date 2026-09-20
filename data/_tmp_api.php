<?php
$url = 'http://127.0.0.1/future-toss-predict/api.php?action=matches&date=2026-09-20&league=all&refresh=1';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 90,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
echo "http $code err=$err len=" . strlen((string) $body) . PHP_EOL;
$j = json_decode((string) $body, true);
echo 'total ' . ($j['total'] ?? 'err') . ' matches ' . count($j['matches'] ?? []) . PHP_EOL;
foreach ($j['matches'] ?? [] as $m) {
    echo ($m['teamA'] ?? '?') . ' vs ' . ($m['teamB'] ?? '?') . ' | ' . ($m['status'] ?? '') . PHP_EOL;
}
