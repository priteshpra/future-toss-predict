<?php

require_once __DIR__ . '/helpers.php';

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
        if (!empty($lm['liveScore'])) {
            $match['liveScore'] = $lm['liveScore'];
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

function schedule_rows_for_date(string $date): array
{
    $byDate = fetch_cricbuzz_schedule();
    $rows = $byDate[$date] ?? [];
    return is_array($rows) ? array_values($rows) : [];
}

function fetch_cricbuzz_schedule(): array
{
    $cached = cache_get('cricbuzz_sched', 900);
    if (is_array($cached)) {
        return $cached;
    }
    $html = http_get('https://www.cricbuzz.com/cricket-schedule/upcoming-series/all', 10);
    if (!$html) {
        $html = http_get('https://www.cricbuzz.com/cricket-schedule/upcoming-series/international', 8);
    }
    $parsed = is_string($html) && $html !== '' ? parse_cricbuzz_schedule_html($html) : [];
    if (!$parsed) {
        $stale = cache_get('cricbuzz_sched', 86400);
        if (is_array($stale)) {
            return $stale;
        }
        return [];
    }
    cache_set('cricbuzz_sched', $parsed);
    return $parsed;
}

function parse_cricbuzz_schedule_html(string $html): array
{
    $html = preg_replace('/<!-- -->/', ' ', $html);
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
    $months = [
        'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
        'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12,
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
            if ($pos !== false) {
                $before = substr($chunk, max(0, $pos - 1200), min(1200, $pos));
                if (preg_match_all('/title="([^"]+)"/', $before, $titles) && !empty($titles[1])) {
                    $nearSeries = html_entity_decode(end($titles[1]), ENT_QUOTES | ENT_HTML5);
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
    if (str_contains($b, 'caribbean premier') || str_contains($b, ' cpl')) {
        return '04:30 AM IST';
    }
    if (str_contains($b, 'odi')) {
        return '01:00 PM IST';
    }
    return '07:30 PM IST';
}
