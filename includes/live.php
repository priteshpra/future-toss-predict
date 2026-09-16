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
        if (!empty($lm['venue']) && (empty($match['venue']) || str_contains($match['venue'], 'International'))) {
            $match['venue'] = $lm['venue'];
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
