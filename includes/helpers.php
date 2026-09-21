<?php

function data_path(string $file): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $file;
}

function sibling_path(string $rel): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'cricket-toss-analyzer' . DIRECTORY_SEPARATOR . $rel;
}

function historical_toss_path(): string
{
    $local = data_path('historical_toss.json');
    if (is_file($local)) {
        return $local;
    }
    $env = getenv('HISTORICAL_TOSS_PATH');
    if (is_string($env) && $env !== '' && is_file($env)) {
        return $env;
    }
    return sibling_path('src/data/historical_toss.json');
}

function read_json(string $path, $fallback = [])
{
    if (!is_file($path)) {
        return $fallback;
    }
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $fallback;
}

function write_json(string $path, $data): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return (bool) file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function ist_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
}

function ist_today(): string
{
    return ist_now()->format('Y-m-d');
}

function ist_shift(string $date, int $days): string
{
    return (new DateTimeImmutable($date, new DateTimeZone('Asia/Kolkata')))
        ->modify(($days >= 0 ? '+' : '') . $days . ' days')
        ->format('Y-m-d');
}

function normalize_date(?string $date): string
{
    $date = trim((string) $date);
    if ($date === '') {
        return ist_today();
    }
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) {
        return sprintf('%s-%02d-%02d', $m[1], $m[2], $m[3]);
    }
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $date, $m)) {
        return sprintf('%s-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    return $date;
}

function normalize_name(?string $name): string
{
    $n = strtolower(trim((string) $name));
    $n = preg_replace('/\bsaint\b/', 'st', $n);
    $n = preg_replace('/[^a-z0-9]/', '', $n);
    return $n ?: '';
}

function parse_ist_minutes(?string $time): int
{
    if (!$time) {
        return 19 * 60 + 30;
    }
    if (!preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', $time, $m)) {
        return 19 * 60 + 30;
    }
    $h = (int) $m[1];
    $min = (int) $m[2];
    $ampm = strtoupper($m[3]);
    if ($ampm === 'PM' && $h < 12) {
        $h += 12;
    }
    if ($ampm === 'AM' && $h === 12) {
        $h = 0;
    }
    return $h * 60 + $min;
}

function format_ist_time(int $minutes): string
{
    $minutes = ($minutes + 24 * 60) % (24 * 60);
    $h = intdiv($minutes, 60);
    $min = $minutes % 60;
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $h12 = $h % 12;
    if ($h12 === 0) {
        $h12 = 12;
    }
    return sprintf('%02d:%02d %s IST', $h12, $min, $ampm);
}

function toss_time_from_match(?string $time): string
{
    return format_ist_time(parse_ist_minutes($time) - 30);
}

function minutes_until_toss(string $date, string $tossTime): int
{
    $now = ist_now();
    $mins = parse_ist_minutes($tossTime);
    $h = intdiv($mins, 60);
    $m = $mins % 60;
    $tossAt = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        sprintf('%s %02d:%02d:00', $date, $h, $m),
        new DateTimeZone('Asia/Kolkata')
    );
    if (!$tossAt) {
        return 99999;
    }
    return (int) floor(($tossAt->getTimestamp() - $now->getTimestamp()) / 60);
}

function match_key(string $teamA, string $teamB, string $date): string
{
    return strtolower(trim($teamA) . '_' . trim($teamB) . '_' . $date);
}

function names_match(string $a, string $b): bool
{
    $na = normalize_name($a);
    $nb = normalize_name($b);
    if ($na === '' || $nb === '') {
        return false;
    }
    if ($na === $nb) {
        return true;
    }
    return str_contains($na, $nb) || str_contains($nb, $na);
}

function json_ok($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : $_POST;
}

function leagues(): array
{
    return [
        ['id' => 'all', 'name' => 'All Leagues', 'icon' => '🏆'],
        ['id' => 'odi', 'name' => 'Men ODI', 'icon' => '🏏'],
        ['id' => 'women_odi', 'name' => 'Women ODI', 'icon' => '👑'],
        ['id' => 't20i', 'name' => 'T20I', 'icon' => '🌍'],
        ['id' => 'women_t20i', 'name' => 'Women T20I', 'icon' => '💠'],
        ['id' => 'pca', 'name' => 'Punjab T20', 'icon' => '🦁'],
        ['id' => 'etpl', 'name' => 'European League', 'icon' => '🇪🇺'],
        ['id' => 'cpl', 'name' => 'CPL', 'icon' => '🏝️'],
        ['id' => 'wcpl', 'name' => 'CPL Women', 'icon' => '🌺'],
        ['id' => 'odisha', 'name' => 'Odisha T20', 'icon' => '🟠'],
        ['id' => 'wapl', 'name' => 'WAPL', 'icon' => '🦅'],
        ['id' => 'upl', 'name' => 'UPL', 'icon' => '⛰️'],
        ['id' => 'test', 'name' => 'Test', 'icon' => '📜'],
        ['id' => 'custom', 'name' => 'My Custom', 'icon' => '✨'],
    ];
}
