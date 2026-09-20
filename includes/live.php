<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/domestic.php';

function http_get(string $url, int $timeout = 4): ?string
{
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_HTTPHEADER => ['Accept: application/json,text/xml,text/html;q=0.9,*/*;q=0.8'],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body !== false && $code < 400) {
            return $body;
        }
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'header' => "User-Agent: $ua\r\nAccept: text/html,*/*\r\n",
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    return $body !== false ? $body : null;
}

function clean_live_team(string $raw): string
{
    $t = preg_replace('/under[- ]?19s?/i', 'UNDERnineteen', $raw);
    $t = preg_replace('/u[- ]?19s?/i', 'UNDERnineteen', $t);
    $t = preg_replace('/\d+\/\d+/', '', $t);
    $t = preg_replace('/\d+/', '', $t);
    $t = str_ireplace('UNDERnineteen', 'Under-19s', $t);
    $t = str_replace(['&', '*'], '', $t);
    $t = preg_replace('/\(.*?\)/', '', $t);
    return trim(preg_replace('/\s+/', ' ', $t));
}

function cache_get(string $key, int $ttl)
{
    $file = data_path('cache_' . preg_replace('/[^a-z0-9_]/', '', $key) . '.json');
    if (!is_file($file)) {
        return null;
    }
    $pack = read_json($file, null);
    if (!is_array($pack) || empty($pack['ts']) || (time() - (int) $pack['ts']) > $ttl) {
        return null;
    }
    return $pack['data'] ?? null;
}

function cache_set(string $key, $data): void
{
    write_json(data_path('cache_' . preg_replace('/[^a-z0-9_]/', '', $key) . '.json'), [
        'ts' => time(),
        'data' => $data,
    ]);
}

function fetch_live_feed(): array
{
    $cached = cache_get('live_rss', 45);
    if (is_array($cached)) {
        return $cached;
    }
    $xml = http_get('https://static.cricinfo.com/rss/livescores.xml', 3);
    if (!$xml) {
        $xml = http_get('https://www.espncricinfo.com/rss/livescores.xml', 3);
    }
    $out = [];
    if ($xml) {
        $prev = libxml_use_internal_errors(true);
        $sx = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if ($sx && isset($sx->channel->item)) {
            foreach ($sx->channel->item as $item) {
                $title = trim((string) $item->title);
                $link = trim((string) $item->link);
                $desc = trim((string) $item->description);
                if (!preg_match('/\s+v(?:s|\/s)?\s+/i', $title)) {
                    continue;
                }
                $parts = preg_split('/\s+v(?:s|\/s)?\s+/i', $title);
                if (count($parts) < 2) {
                    continue;
                }
                $teamA = clean_live_team($parts[0]);
                $teamB = clean_live_team($parts[1]);
                if ($teamA === '' || $teamB === '') {
                    continue;
                }
                $tossWinner = null;
                $tossDecision = null;
                if (preg_match('/(.*?) won the toss and (?:opted to|elected to|chose to) (bat|field|bowl)/i', $desc, $tm)) {
                    $tossWinner = trim($tm[1]);
                    $tossDecision = strtolower($tm[2]) === 'bowl' ? 'field' : strtolower($tm[2]);
                }
                $matchId = null;
                if (preg_match('/\/(\d+)\.html/', $link, $mm)) {
                    $matchId = $mm[1];
                }
                $status = 'UPCOMING';
                $low = strtolower($desc . ' ' . $title);
                if (str_contains($low, 'won by') || str_contains($low, 'match over') || str_contains($low, 'drawn')) {
                    $status = 'COMPLETED';
                } elseif (str_contains($title, '/') || str_contains($desc, '/') || $tossWinner || str_contains($low, 'stumps') || str_contains($low, 'lead')) {
                    $status = 'LIVE';
                }
                $out[] = [
                    'sourceId' => $matchId,
                    'teamA' => $teamA,
                    'teamB' => $teamB,
                    'title' => $title,
                    'liveScore' => $desc ?: $title,
                    'link' => $link,
                    'tossWinner' => $tossWinner,
                    'tossDecision' => $tossDecision,
                    'status' => $status,
                    'venue' => null,
                ];
            }
        }
    }

    cache_set('live_rss', $out);
    return $out;
}

function merge_live(array $match, array $liveRows): array
{
    foreach ($liveRows as $lm) {
        $same = (names_match($match['teamA'], $lm['teamA']) && names_match($match['teamB'], $lm['teamB']))
            || (names_match($match['teamA'], $lm['teamB']) && names_match($match['teamB'], $lm['teamA']));
        if (!$same) {
            continue;
        }
        if (!empty($match['userEdited']) || !empty($match['userToss'])) {
            $match['liveLinked'] = true;
            break;
        }
        if (!empty($lm['venue']) && (empty($match['venue']) || str_contains($match['venue'], 'International'))) {
            $match['venue'] = $lm['venue'];
        }
        if (!empty($match['tossWinner'])) {
            $match['liveLinked'] = true;
            break;
        }
        if (!empty($lm['tossWinner'])) {
            $match['tossWinner'] = $lm['tossWinner'];
            $match['tossDecision'] = $lm['tossDecision'] ?: ($match['tossDecision'] ?? null);
            if (($match['status'] ?? '') === 'UPCOMING') {
                $match['status'] = 'LIVE';
            }
        } elseif (($lm['status'] ?? '') === 'LIVE' && ($match['status'] ?? '') === 'UPCOMING') {
            $match['status'] = 'LIVE';
        }
        $match['liveLinked'] = true;
        break;
    }
    return $match;
}

function canonical_schedule_team(string $name): string
{
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if (!function_exists('load_teams_index') || !function_exists('normalize_name')) {
        return $name;
    }
    $pack = load_teams_index();
    $try = [$name];
    if (preg_match('/u[- ]?19s?$/i', $name) || preg_match('/under[- ]?19s?$/i', $name)) {
        $try[] = preg_replace('/u[- ]?19s?$/i', 'Under-19s', $name);
        $try[] = preg_replace('/under[- ]?19s?$/i', 'U19', $name);
    }
    foreach ($try as $cand) {
        $n = normalize_name($cand);
        if ($n !== '' && isset($pack['index'][$n])) {
            return $pack['index'][$n]['name'] ?? $name;
        }
    }
    return $name;
}

function skip_slate_match(array $m): bool
{
    $blob = strtolower(implode(' ', [
        $m['tournament'] ?? '',
        $m['title'] ?? '',
        $m['format'] ?? '',
        $m['liveScore'] ?? '',
        $m['teamA'] ?? '',
        $m['teamB'] ?? '',
    ]));
    return (bool) preg_match('/\bday\s*[2-5]\b/', $blob);
}

function american_series_watchlist(array $byDate): array
{
    $watch = ['13053' => 'north-american-cup-2026'];
    $today = function_exists('ist_today') ? ist_today() : date('Y-m-d');
    $window = [$today];
    if (function_exists('ist_shift')) {
        $window[] = ist_shift($today, -1);
        $window[] = ist_shift($today, 1);
        $window[] = ist_shift($today, 2);
    }
    foreach ($window as $d) {
        foreach (($byDate[$d] ?? []) as $m) {
            $blob = strtolower(($m['seriesSlug'] ?? '') . ' ' . ($m['tournament'] ?? ''));
            if (!is_americas_t20_blob($blob)) {
                continue;
            }
            $sid = (string) ($m['seriesId'] ?? '');
            if ($sid !== '') {
                $watch[$sid] = (string) ($m['seriesSlug'] ?? $watch[$sid] ?? '');
            }
        }
    }
    return $watch;
}

function schedule_rows_for_date(string $date): array
{
    $byDate = fetch_cricbuzz_schedule();
    $picked = [];
    foreach ($byDate as $htmlDate => $rows) {
        if (!is_array($rows)) {
            continue;
        }
        foreach ($rows as $m) {
            $id = (string) ($m['id'] ?? '');
            $blob = ($m['tournament'] ?? '') . ' ' . ($m['seriesSlug'] ?? '');
            $time = (string) ($m['time'] ?? default_slate_time($blob));
            if (is_americas_t20_blob($blob) && parse_ist_minutes($time) === parse_ist_minutes('07:30 PM IST')) {
                $time = default_slate_time($blob);
            }
            [$disp, $shown, $toss] = home_americas_slate((string) $htmlDate, $time, $blob);
            if ($disp !== $date) {
                continue;
            }
            $m['time'] = $shown;
            $m['tossTime'] = $toss;
            $key = $id !== '' ? $id : strtolower(($m['teamA'] ?? '') . '|' . ($m['teamB'] ?? ''));
            $picked[$key] = $m;
        }
    }
    if (function_exists('featured_domestic_rows_for_date')) {
        foreach (featured_domestic_rows_for_date($date) as $m) {
            $id = (string) ($m['id'] ?? '');
            $key = $id !== '' ? $id : strtolower(($m['teamA'] ?? '') . '|' . ($m['teamB'] ?? ''));
            $picked[$key] = $m;
        }
    }
    foreach (american_series_watchlist($byDate) as $sid => $slug) {
        foreach (fetch_cricbuzz_series_matches((string) $sid, (string) $slug) as $row) {
            $teamA = (string) ($row['teamA'] ?? '');
            $teamB = (string) ($row['teamB'] ?? '');
            if ($teamA === '' || $teamB === '' || (function_exists('is_placeholder_team') && (is_placeholder_team($teamA) || is_placeholder_team($teamB)))) {
                continue;
            }
            $blob = ($row['series'] ?? '') . ' ' . ($row['desc'] ?? '') . ' ' . ($row['seriesId'] ?? '');
            $time = (string) ($row['time'] ?? default_slate_time($blob));
            [$disp, $shown, $toss] = home_americas_slate((string) ($row['date'] ?? ''), $time, $blob);
            if ($disp !== $date) {
                continue;
            }
            $id = 'cb_' . ($row['matchId'] ?? '');
            $base = $picked[$id] ?? [];
            $picked[$id] = array_merge($base, [
                'id' => $id,
                'teamA' => $teamA,
                'teamB' => $teamB,
                'league' => $base['league'] ?? (function_exists('guess_live_league')
                    ? guess_live_league(['teamA' => $teamA, 'teamB' => $teamB, 'title' => $blob])
                    : 't20i'),
                'tournament' => trim(($row['series'] ?? 'North American Cup') . (!empty($row['desc']) ? ' · ' . $row['desc'] : '')),
                'format' => guess_format_label(($row['format'] ?? '') . ' ' . $blob),
                'time' => $shown,
                'tossTime' => $toss,
                'venue' => $base['venue'] ?? 'Jimmy Powell Oval, George Town',
                'status' => $base['status'] ?? 'UPCOMING',
                'seriesId' => (string) ($row['seriesId'] ?? $sid),
                'seriesSlug' => (string) ($slug ?: ($base['seriesSlug'] ?? 'north-american-cup-2026')),
            ]);
        }
    }
    return array_values($picked);
}

function merge_schedule_by_date(array $base, array $add): array
{
    foreach ($add as $date => $rows) {
        if (!is_array($rows)) {
            continue;
        }
        if (!isset($base[$date]) || !is_array($base[$date])) {
            $base[$date] = [];
        }
        $have = [];
        foreach ($base[$date] as $m) {
            $id = (string) ($m['id'] ?? '');
            $pair = strtolower(trim($m['teamA'] ?? '') . '|' . trim($m['teamB'] ?? ''));
            if ($id !== '') {
                $have[$id] = true;
            }
            $have[$pair] = true;
            $have[strtolower(trim($m['teamB'] ?? '') . '|' . trim($m['teamA'] ?? ''))] = true;
        }
        foreach ($rows as $m) {
            if (!is_array($m)) {
                continue;
            }
            $id = (string) ($m['id'] ?? '');
            $pair = strtolower(trim($m['teamA'] ?? '') . '|' . trim($m['teamB'] ?? ''));
            $rev = strtolower(trim($m['teamB'] ?? '') . '|' . trim($m['teamA'] ?? ''));
            if (($id !== '' && isset($have[$id])) || isset($have[$pair]) || isset($have[$rev])) {
                continue;
            }
            $base[$date][] = $m;
            if ($id !== '') {
                $have[$id] = true;
            }
            $have[$pair] = true;
        }
    }
    return $base;
}

function fetch_cricbuzz_schedule(): array
{
    $cached = cache_get('cricbuzz_sched_v3', 600);
    if (is_array($cached) && $cached) {
        return $cached;
    }
    $urls = [
        'https://www.cricbuzz.com/cricket-schedule/upcoming-series/all',
        'https://www.cricbuzz.com/cricket-schedule/upcoming-series/league',
        'https://www.cricbuzz.com/cricket-schedule/upcoming-series/women',
        'https://www.cricbuzz.com/cricket-schedule/upcoming-series/domestic',
        'https://www.cricbuzz.com/cricket-schedule/upcoming-series/international',
    ];
    $merged = [];
    foreach ($urls as $url) {
        $html = http_get($url, 6);
        if (!is_string($html) || $html === '') {
            continue;
        }
        $parsed = parse_cricbuzz_schedule_html($html);
        if ($parsed) {
            $merged = merge_schedule_by_date($merged, $parsed);
        }
    }
    if ($merged) {
        cache_set('cricbuzz_sched_v3', $merged);
        return $merged;
    }
    foreach (['cricbuzz_sched_v3', 'cricbuzz_sched_v2'] as $key) {
        $stale = cache_get($key, 86400);
        if (is_array($stale) && $stale) {
            return $stale;
        }
    }
    return [];
}

function parse_cricbuzz_schedule_html(string $html): array
{
    $html = preg_replace('/<!-- -->/', ' ', $html);
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
    $months = [
        'JAN' => 1,
        'FEB' => 2,
        'MAR' => 3,
        'APR' => 4,
        'MAY' => 5,
        'JUN' => 6,
        'JUL' => 7,
        'AUG' => 8,
        'SEP' => 9,
        'OCT' => 10,
        'NOV' => 11,
        'DEC' => 12,
    ];
    if (!preg_match_all('/<h3[^>]*>\s*([A-Z]{3}),\s*([A-Z]{3})\s+(\d{1,2})\s+(\d{4})\s*<\/h3>(.*?)(?=<h3[^>]*>\s*[A-Z]{3},|$)/is', $html, $blocks, PREG_SET_ORDER)) {
        return [];
    }
    $byDate = [];
    foreach ($blocks as $block) {
        $mon = $months[strtoupper($block[2])] ?? 0;
        if (!$mon) {
            continue;
        }
        $date = sprintf('%04d-%02d-%02d', (int) $block[4], $mon, (int) $block[3]);
        $chunk = $block[5];
        if (!preg_match_all('/href="\/live-cricket-scores\/(\d+)\/[^"]*"[^>]*>(.*?)<\/a>/is', $chunk, $ms, PREG_SET_ORDER)) {
            continue;
        }
        $series = '';
        if (preg_match('/title="([^"]+)"[^>]*>[^<]*<\/a><\/div><div class="w-\[67%\]/is', $chunk, $sm)) {
            $series = trim($sm[1]);
        }
        foreach ($ms as $m) {
            $sid = $m[1];
            $inner = $m[2];
            $venue = '';
            if (preg_match('/<div[^>]*>(.*?)<\/div>/is', $inner, $vm)) {
                $venue = trim(preg_replace('/\s+/', ' ', strip_tags($vm[1])));
                $inner = preg_replace('/<div[^>]*>.*?<\/div>/is', '', $inner);
            }
            $text = trim(preg_replace('/\s+/', ' ', strip_tags($inner)));
            $text = preg_replace('/\s*,\s*Live Cricket Score.*/i', '', $text);
            if (!preg_match('/^(.+?)\s+vs\s+(.+?)(?:,\s*(.+))?$/i', $text, $tm)) {
                continue;
            }
            $teamA = trim($tm[1]);
            $teamB = trim($tm[2]);
            $label = trim($tm[3] ?? '');
            if ($teamA === '' || $teamB === '' || preg_match('/\bday\s*[2-5]\b/i', $label)) {
                continue;
            }
            $teamA = canonical_schedule_team($teamA);
            $teamB = canonical_schedule_team($teamB);
            $nearSeries = $series;
            $pos = strpos($chunk, $m[0]);
            $seriesId = '';
            $seriesSlug = '';
            if ($pos !== false) {
                $before = substr($chunk, max(0, $pos - 1200), min(1200, $pos));
                if (preg_match_all('/title="([^"]+)"/', $before, $titles) && !empty($titles[1])) {
                    $nearSeries = html_entity_decode(end($titles[1]), ENT_QUOTES | ENT_HTML5);
                }
                if (preg_match_all('/href="\/cricket-series\/(\d+)\/([^"\/]+)(?:\/[^"]*)?"/', $before, $sr)) {
                    $seriesId = (string) end($sr[1]);
                    $seriesSlug = (string) end($sr[2]);
                }
            }
            $league = function_exists('guess_live_league')
                ? guess_live_league(['teamA' => $teamA, 'teamB' => $teamB, 'title' => $nearSeries . ' ' . $label])
                : 't20i';
            $row = [
                'id' => 'cb_' . $sid,
                'teamA' => $teamA,
                'teamB' => $teamB,
                'league' => $league,
                'tournament' => trim($nearSeries . ($label !== '' ? ' · ' . $label : '')),
                'format' => guess_format_label($label . ' ' . $nearSeries),
                'time' => default_slate_time($label . ' ' . $nearSeries),
                'venue' => $venue !== '' ? $venue : 'International Cricket Ground',
                'status' => 'UPCOMING',
                'seriesId' => $seriesId,
                'seriesSlug' => $seriesSlug,
            ];
            $pair = strtolower(trim($teamA) . '|' . trim($teamB) . '|' . $date);
            $rev = strtolower(trim($teamB) . '|' . trim($teamA) . '|' . $date);
            if (isset($byDate[$date][$pair]) || isset($byDate[$date][$rev])) {
                continue;
            }
            $byDate[$date][$pair] = $row;
        }
    }
    foreach ($byDate as $date => $rows) {
        $byDate[$date] = array_values($rows);
    }
    return $byDate;
}

function guess_format_label(string $blob): string
{
    $b = strtolower($blob);
    if (str_contains($b, 'test')) {
        return 'Test';
    }
    if (str_contains($b, 'odi')) {
        return 'ODI';
    }
    return 'T20';
}

function default_slate_time(string $blob): string
{
    $b = strtolower($blob);
    if (is_americas_t20_blob($b)) {
        return '12:00 AM IST';
    }
    if (str_contains($b, 'caribbean premier') || str_contains($b, ' cpl')) {
        return '04:30 AM IST';
    }
    if (str_contains($b, 'odi')) {
        return '01:00 PM IST';
    }
    return '07:30 PM IST';
}

function is_americas_t20_blob(string $blob): bool
{
    $b = strtolower($blob);
    return str_contains($b, 'north american')
        || str_contains($b, 'american t20')
        || str_contains($b, 'america cup')
        || str_contains($b, 'american cup')
        || str_contains($b, 'north-american-cup');
}

function home_americas_slate(string $istDate, string $time, string $blob): array
{
    if ($istDate === '' || !is_americas_t20_blob($blob)) {
        return [$istDate, $time, $time];
    }
    $mins = parse_ist_minutes($time);
    if ($mins >= 3 * 60) {
        return [$istDate, $time, $time];
    }
    $prev = function_exists('ist_shift') ? ist_shift($istDate, -1) : $istDate;
    $night = format_ist_time(23 * 60 + 56);
    return [$prev, $night, $night];
}

function parse_cricbuzz_series_match_payload(string $html): array
{
    $u = str_replace(['\\"', '\\\\'], ['"', '\\'], $html);
    if (!preg_match_all('/"matchId":(\d+),"seriesId":(\d+),"seriesName":"([^"]+)","matchDesc":"([^"]+)","matchFormat":"([^"]+)","startDate":"(\d+)","endDate":"[^"]*","state":"([^"]+)"/', $u, $ms, PREG_SET_ORDER)) {
        return [];
    }
    $out = [];
    foreach ($ms as $m) {
        $id = (string) $m[1];
        $seriesId = (string) $m[2];
        $key = $id . ':' . $seriesId;
        if (isset($out[$key])) {
            continue;
        }
        $pos = strpos($u, '"matchId":' . $id . ',"seriesId":' . $seriesId);
        $chunk = $pos === false ? '' : substr($u, $pos, 1200);
        $t1 = preg_match('/"team1":\{"teamId":\d+,"teamName":"([^"]+)"/', $chunk, $a) ? html_entity_decode($a[1], ENT_QUOTES | ENT_HTML5) : '';
        $t2 = preg_match('/"team2":\{"teamId":\d+,"teamName":"([^"]+)"/', $chunk, $b) ? html_entity_decode($b[1], ENT_QUOTES | ENT_HTML5) : '';
        if ($t1 === '' || $t2 === '') {
            continue;
        }
        $msTs = (int) floor(((int) $m[6]) / 1000);
        $dt = (new DateTimeImmutable('@' . max(1, $msTs)))->setTimezone(new DateTimeZone('Asia/Kolkata'));
        $out[$key] = [
            'matchId' => $id,
            'seriesId' => $seriesId,
            'series' => html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5),
            'desc' => html_entity_decode($m[4], ENT_QUOTES | ENT_HTML5),
            'format' => $m[5],
            'date' => $dt->format('Y-m-d'),
            'time' => $dt->format('h:i A') . ' IST',
            'state' => $m[7],
            'teamA' => function_exists('canonical_schedule_team') ? canonical_schedule_team($t1) : $t1,
            'teamB' => function_exists('canonical_schedule_team') ? canonical_schedule_team($t2) : $t2,
        ];
    }
    return array_values($out);
}

function parse_cricbuzz_scorecard_toss(string $html): ?array
{
    $text = '';
    if (preg_match('/font-bold">\s*Toss\s*<\/div>\s*<div>(.*?)<\/div>/is', $html, $m)) {
        $text = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5));
    }
    if ($text === '' && preg_match('/([A-Za-z][A-Za-z0-9 .,&\'-]{1,60}) won the toss and opt(?:ed)? to (Bat|Bowl|Field)/i', $html, $m)) {
        $text = trim($m[0]);
    }
    if ($text === '' || !preg_match('/([A-Za-z][A-Za-z0-9 .,&\'-]{1,60}) won the toss and opt(?:ed)? to (Bat|Bowl|Field)/i', $text, $m)) {
        return null;
    }
    $winner = trim($m[1]);
    $dec = strtolower($m[2]) === 'bat' ? 'bat' : 'bowl';
    if (function_exists('canonical_schedule_team')) {
        $winner = canonical_schedule_team($winner);
    }
    return ['tossWinner' => $winner, 'tossDecision' => $dec];
}

function cricbuzz_series_from_html(string $html): array
{
    if (preg_match('/href="\/cricket-series\/(\d+)\/([^"\/]+)\/(?:matches|venues)/', $html, $m)) {
        return ['id' => (string) $m[1], 'slug' => (string) $m[2]];
    }
    return [];
}

function fetch_cricbuzz_series_matches(string $seriesId, string $slug = ''): array
{
    $seriesId = preg_replace('/\D+/', '', $seriesId) ?? '';
    if ($seriesId === '') {
        return [];
    }
    $cacheKey = 'cb_seriesv3_' . $seriesId;
    $cached = cache_get($cacheKey, 1200);
    if (is_array($cached)) {
        return $cached;
    }
    $slug = trim($slug, '/');
    $urls = [];
    if ($slug !== '') {
        $urls[] = 'https://www.cricbuzz.com/cricket-series/' . $seriesId . '/' . $slug . '/matches';
    }
    $urls[] = 'https://www.cricbuzz.com/cricket-series/' . $seriesId . '/matches';
    $rows = [];
    foreach ($urls as $url) {
        $html = http_get($url, 8);
        if (!$html) {
            continue;
        }
        $parsed = parse_cricbuzz_series_match_payload($html);
        foreach ($parsed as $row) {
            if (($row['seriesId'] ?? '') !== $seriesId) {
                continue;
            }
            $rows[$row['matchId']] = $row;
        }
        if ($rows) {
            break;
        }
    }
    $rows = array_values($rows);
    if (!$rows) {
        $stale = cache_get('cb_seriesv2_' . $seriesId, 86400);
        if (is_array($stale) && $stale) {
            foreach ($stale as &$row) {
                if (empty($row['time']) && is_americas_t20_blob(($row['series'] ?? '') . ' ' . ($row['desc'] ?? ''))) {
                    $row['time'] = '12:00 AM IST';
                }
            }
            unset($row);
            return $stale;
        }
        return [];
    }
    cache_set($cacheKey, $rows);
    return $rows;
}

function fetch_cricbuzz_match_toss(string $matchId, bool $live = false): ?array
{
    $matchId = preg_replace('/\D+/', '', $matchId) ?? '';
    if ($matchId === '') {
        return null;
    }
    $ttl = $live ? 90 : 43200;
    $cacheKey = 'cb_tosscard_' . $matchId;
    $cached = cache_get($cacheKey, $ttl);
    if (is_array($cached) && !empty($cached['tossWinner'])) {
        return $cached;
    }
    if ($live) {
        $known = cache_get($cacheKey, 43200);
        if (is_array($known) && !empty($known['tossWinner'])) {
            return $known;
        }
    }
    $html = http_get('https://www.cricbuzz.com/live-cricket-scorecard/' . $matchId, 8);
    if (!$html) {
        return is_array($cached) ? $cached : null;
    }
    $toss = parse_cricbuzz_scorecard_toss($html);
    $series = cricbuzz_series_from_html($html);
    $pack = array_merge($toss ?: ['tossWinner' => null, 'tossDecision' => null], [
        'seriesId' => $series['id'] ?? '',
        'seriesSlug' => $series['slug'] ?? '',
    ]);
    cache_set($cacheKey, $pack);
    return $pack;
}

function local_historical_toss_store(): array
{
    $raw = read_json(data_path('historical_toss.json'), []);
    return is_array($raw) ? $raw : [];
}

function save_local_historical_toss(array $rows): void
{
    $byFp = [];
    foreach ($rows as $m) {
        if (!is_array($m) || empty($m['tossWinner']) || empty($m['teamA']) || empty($m['teamB'])) {
            continue;
        }
        $row = [
            'id' => $m['id'] ?? ('cb_' . ($m['matchId'] ?? md5(($m['teamA'] ?? '') . ($m['teamB'] ?? '') . ($m['date'] ?? '')))),
            'league' => $m['league'] ?? '',
            'date' => $m['date'] ?? '',
            'format' => $m['format'] ?? 'T20',
            'teamA' => $m['teamA'],
            'teamB' => $m['teamB'],
            'venue' => $m['venue'] ?? '',
            'tossWinner' => $m['tossWinner'],
            'tossDecision' => $m['tossDecision'] ?? '',
            'matchWinner' => $m['matchWinner'] ?? '',
            'source' => $m['source'] ?? 'cricbuzz',
        ];
        $fp = function_exists('history_fingerprint')
            ? history_fingerprint(history_row_from_match($row, $row['date']))
            : ($row['date'] . '_' . strtolower($row['teamA'] . '_' . $row['teamB']));
        $byFp[$fp] = $row;
    }
    write_json(data_path('historical_toss.json'), array_values($byFp));
    if (function_exists('history_invalidate')) {
        history_invalidate();
    }
}

function hydrate_cricbuzz_toss_history(array $matches): array
{
    static $once = null;
    if (is_array($once)) {
        return $once;
    }
    $today = function_exists('ist_today') ? ist_today() : date('Y-m-d');
    $index = ['byId' => [], 'byPair' => []];
    $wanted = [];
    $series = [];
    $slateFetch = [];
    foreach ($matches as $m) {
        if (!is_array($m)) {
            continue;
        }
        foreach ([$m['teamA'] ?? '', $m['teamB'] ?? ''] as $name) {
            $n = function_exists('normalize_name') ? normalize_name($name) : strtolower($name);
            if ($n !== '') {
                $wanted[$n] = $name;
            }
        }
        if (!empty($m['seriesId'])) {
            $series[(string) $m['seriesId']] = (string) ($m['seriesSlug'] ?? '');
        }
        $md = (string) ($m['date'] ?? '');
        if ($md !== '' && $md > $today) {
            continue;
        }
        if (!empty($m['tossWinner']) || !empty($m['userToss'])) {
            continue;
        }
        if (preg_match('/^cb_(\d+)$/', (string) ($m['id'] ?? ''), $mm)) {
            $slateFetch[] = $mm[1];
        }
    }

    $local = local_historical_toss_store();
    $have = [];
    foreach ($local as $row) {
        if (!is_array($row)) {
            continue;
        }
        $fp = function_exists('history_fingerprint')
            ? history_fingerprint(history_row_from_match($row, $row['date'] ?? ''))
            : '';
        if ($fp !== '') {
            $have[$fp] = true;
        }
        if (empty($row['tossWinner'])) {
            continue;
        }
        if (!empty($row['id']) && preg_match('/(\d+)/', (string) $row['id'], $mm)) {
            $index['byId'][$mm[1]] = $row;
        }
        $pair = strtolower(trim((string) ($row['teamA'] ?? '')) . '|' . trim((string) ($row['teamB'] ?? '')) . '|' . ($row['date'] ?? ''));
        $rev = strtolower(trim((string) ($row['teamB'] ?? '')) . '|' . trim((string) ($row['teamA'] ?? '')) . '|' . ($row['date'] ?? ''));
        $index['byPair'][$pair] = $row;
        $index['byPair'][$rev] = $row;
        if (!empty($row['seriesId'])) {
            $series[(string) $row['seriesId']] = (string) ($row['seriesSlug'] ?? ($series[(string) $row['seriesId']] ?? ''));
        }
    }

    $added = false;
    $fetched = 0;
    foreach (array_unique($slateFetch) as $mid) {
        if (!empty($index['byId'][$mid]['tossWinner'])) {
            continue;
        }
        if ($fetched >= 6) {
            break;
        }
        $pack = fetch_cricbuzz_match_toss($mid, true);
        $fetched++;
        if (!$pack || empty($pack['tossWinner'])) {
            continue;
        }
        $index['byId'][$mid] = $pack;
        if (!empty($pack['seriesId'])) {
            $series[$pack['seriesId']] = $pack['seriesSlug'] ?? ($series[$pack['seriesId']] ?? '');
        }
        foreach ($matches as $m) {
            if (!is_array($m) || !preg_match('/^cb_' . preg_quote((string) $mid, '/') . '$/', (string) ($m['id'] ?? ''))) {
                continue;
            }
            $local[] = [
                'id' => 'cb_' . $mid,
                'league' => $m['league'] ?? '',
                'date' => $m['date'] ?? $today,
                'format' => $m['format'] ?? 'T20',
                'teamA' => $m['teamA'] ?? '',
                'teamB' => $m['teamB'] ?? '',
                'venue' => $m['venue'] ?? '',
                'tossWinner' => $pack['tossWinner'],
                'tossDecision' => $pack['tossDecision'] ?? '',
                'source' => 'cricbuzz',
            ];
            $added = true;
            break;
        }
    }

    $doCrawl = !cache_get('cb_hist_crawl', 21600);
    if ($doCrawl) {
        try {
            foreach (fetch_cricbuzz_schedule() as $rows) {
                if (!is_array($rows)) {
                    continue;
                }
                foreach ($rows as $row) {
                    if (!is_array($row) || empty($row['seriesId'])) {
                        continue;
                    }
                    $hit = false;
                    foreach ($wanted as $label) {
                        if (names_match($row['teamA'] ?? '', $label) || names_match($row['teamB'] ?? '', $label)) {
                            $hit = true;
                            break;
                        }
                    }
                    if ($hit) {
                        $series[(string) $row['seriesId']] = (string) ($row['seriesSlug'] ?? ($series[(string) $row['seriesId']] ?? ''));
                    }
                }
            }
        } catch (Throwable $e) {
        }
        $candidates = [];
        foreach ($series as $sid => $slug) {
            $rows = fetch_cricbuzz_series_matches((string) $sid, (string) $slug);
            foreach ($rows as $row) {
                $hit = false;
                foreach ($wanted as $norm => $label) {
                    if (
                        (function_exists('history_side_hit') && (history_side_hit(normalize_name($row['teamA']), $norm) || history_side_hit(normalize_name($row['teamB']), $norm)))
                        || names_match($row['teamA'], $label)
                        || names_match($row['teamB'], $label)
                    ) {
                        $hit = true;
                        break;
                    }
                }
                if ($hit) {
                    $candidates[$row['matchId']] = $row;
                }
            }
        }
        uasort($candidates, static fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
        $histFetched = 0;
        foreach ($candidates as $row) {
            $fp = function_exists('history_fingerprint')
                ? history_fingerprint(history_row_from_match($row, $row['date'] ?? ''))
                : '';
            if ($fp !== '' && isset($have[$fp])) {
                continue;
            }
            $state = strtolower($row['state'] ?? '');
            if (in_array($state, ['preview', 'upcoming'], true)) {
                continue;
            }
            $known = $index['byId'][$row['matchId']] ?? null;
            $needFetch = !($known && !empty($known['tossWinner']));
            if ($needFetch && $histFetched >= 8) {
                continue;
            }
            $isLive = in_array($state, ['in progress', 'toss'], true);
            $toss = $needFetch ? fetch_cricbuzz_match_toss($row['matchId'], $isLive) : $known;
            if ($needFetch) {
                $histFetched++;
            }
            if (!$toss || empty($toss['tossWinner'])) {
                continue;
            }
            $index['byId'][$row['matchId']] = $toss;
            $pair = strtolower(trim($row['teamA']) . '|' . trim($row['teamB']) . '|' . ($row['date'] ?? ''));
            $index['byPair'][$pair] = $toss;
            $index['byPair'][strtolower(trim($row['teamB']) . '|' . trim($row['teamA']) . '|' . ($row['date'] ?? ''))] = $toss;
            $local[] = [
                'id' => 'cb_' . $row['matchId'],
                'league' => '',
                'date' => $row['date'] ?? '',
                'format' => $row['format'] ?? 'T20',
                'teamA' => $row['teamA'],
                'teamB' => $row['teamB'],
                'venue' => '',
                'tossWinner' => $toss['tossWinner'],
                'tossDecision' => $toss['tossDecision'] ?? '',
                'source' => 'cricbuzz',
            ];
            $added = true;
            if ($fp !== '') {
                $have[$fp] = true;
            }
        }
        cache_set('cb_hist_crawl', ['ok' => 1, 'at' => time()]);
    }

    if ($added) {
        save_local_historical_toss($local);
    }
    $once = $index;
    return $index;
}

function apply_cricbuzz_ground_toss(array $m, array $index): array
{
    if (!empty($m['userToss']) || !empty($m['tossWinner'])) {
        return $m;
    }
    $id = '';
    if (preg_match('/(?:cb_|live_)(\d+)/', (string) ($m['id'] ?? ''), $mm)) {
        $id = $mm[1];
    }
    $toss = ($id !== '' && isset($index['byId'][$id])) ? $index['byId'][$id] : null;
    if (!$toss) {
        $date = $m['date'] ?? '';
        $pair = strtolower(trim($m['teamA'] ?? '') . '|' . trim($m['teamB'] ?? '') . '|' . $date);
        $rev = strtolower(trim($m['teamB'] ?? '') . '|' . trim($m['teamA'] ?? '') . '|' . $date);
        $toss = $index['byPair'][$pair] ?? $index['byPair'][$rev] ?? null;
    }
    if ($toss && !empty($toss['tossWinner'])) {
        $m['tossWinner'] = $toss['tossWinner'];
        $m['tossDecision'] = $toss['tossDecision'] ?? ($m['tossDecision'] ?? null);
        if (($m['status'] ?? '') === 'UPCOMING') {
            $m['status'] = 'LIVE';
        }
    }
    return $m;
}
