<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/live.php';
require_once __DIR__ . '/telegram.php';

function tossbook_supabase_url(): string
{
    return 'https://corhfhqdjmflsdnjcdmm.supabase.co';
}

function tossbook_supabase_key(): string
{
    // Public publishable key shipped in the toss-book frontend.
    return 'sb_publishable_TF0olMWEh6C2ZxvrX2AHMg_RvhBOAan';
}

function tossbook_http_json(string $url, ?array $post = null, int $timeout = 8): array
{
    $headers = [
        'apikey: ' . tossbook_supabase_key(),
        'Authorization: Bearer ' . tossbook_supabase_key(),
        'Accept: application/json',
        'Content-Type: application/json',
    ];
    $body = null;
    $code = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
        ];
        if ($post !== null) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($post, JSON_FORCE_OBJECT);
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }
    $json = is_string($body) ? json_decode($body, true) : null;
    return [
        'ok' => $code >= 200 && $code < 300 && is_array($json),
        'code' => $code,
        'data' => is_array($json) ? $json : [],
    ];
}

function tossbook_parse_board_date(string $raw): ?string
{
    $raw = trim($raw);
    foreach (['j M Y', 'd M Y', 'j F Y', 'd F Y'] as $fmt) {
        $dt = DateTimeImmutable::createFromFormat('!' . $fmt, $raw, new DateTimeZone('Asia/Kolkata'));
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d');
        }
    }
    return null;
}

function tossbook_fmt_board_time(string $raw): string
{
    $raw = trim($raw);
    if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', $raw)) {
        return strtoupper(preg_replace('/\s+/', ' ', $raw)) . (stripos($raw, 'IST') === false ? ' IST' : '');
    }
    if (preg_match('/(\d{1,2}):(\d{2})/', $raw, $m)) {
        return format_ist_time(((int) $m[1]) * 60 + (int) $m[2]);
    }
    return $raw;
}

function parse_tossbook_schedule_text(string $text): array
{
    if (!preg_match('/UPCOMING MATCHES\s*[-:]\s*([^\n\r]+)/i', $text, $hm)) {
        return [];
    }
    $date = tossbook_parse_board_date(trim($hm[1]));
    if (!$date) {
        return [];
    }
    $out = [];
    if (!preg_match_all('/^\s*\d+\.\s*(.+)$/m', $text, $lines, PREG_OFFSET_CAPTURE)) {
        return [];
    }
    $n = count($lines[0]);
    for ($i = 0; $i < $n; $i++) {
        $line = trim($lines[1][$i][0]);
        if (!preg_match('/^(.+?)\s+vs\s+(.+)$/i', $line, $tm)) {
            continue;
        }
        $start = $lines[0][$i][1] + strlen($lines[0][$i][0]);
        $end = $i + 1 < $n ? $lines[0][$i + 1][1] : strlen($text);
        $block = substr($text, $start, max(0, $end - $start));
        $league = '';
        $time = '';
        $status = '';
        foreach (preg_split('/\r\n|\n|\r/', $block) as $row) {
            $row = trim($row);
            if ($row === '') {
                continue;
            }
            if (preg_match('/^Time:\s*(.+)$/i', $row, $mm)) {
                $time = tossbook_fmt_board_time($mm[1]);
                continue;
            }
            if (preg_match('/^Status:\s*(.+)$/i', $row, $mm)) {
                $status = trim($mm[1]);
                continue;
            }
            if ($league === '' && !preg_match('/^\d+\./', $row)) {
                $league = $row;
            }
        }
        $teamA = trim($tm[1]);
        $teamB = trim($tm[2]);
        if ($teamA === '' || $teamB === '') {
            continue;
        }
        $open = $status === '' || preg_match('/open/i', $status);
        $out[] = [
            'id' => 'tb_' . md5($date . '|' . strtolower($teamA) . '|' . strtolower($teamB) . '|' . $time),
            'date' => $date,
            'teamA' => $teamA,
            'teamB' => $teamB,
            'league' => $league,
            'time' => $time,
            'status' => $open ? 'OPEN' : strtoupper($status ?: 'LISTED'),
            'open' => $open,
        ];
    }
    return $out;
}

function tossbook_schedules_from_posts(array $posts): array
{
    $best = [];
    foreach ($posts as $p) {
        $text = (string) ($p['rawText'] ?? '');
        $rows = parse_tossbook_schedule_text($text);
        if (!$rows) {
            continue;
        }
        $date = $rows[0]['date'];
        $iso = (string) ($p['isoTime'] ?? '');
        if (!isset($best[$date]) || strcmp($iso, $best[$date]['iso'] ?? '') > 0) {
            $best[$date] = ['iso' => $iso, 'matches' => $rows];
        }
    }
    $out = [];
    foreach ($best as $pack) {
        foreach ($pack['matches'] as $row) {
            $out[] = $row;
        }
    }
    return $out;
}

function tossbook_oldest_post_id(array $posts): ?string
{
    $min = null;
    foreach ($posts as $p) {
        $id = (string) ($p['postId'] ?? '');
        if ($id === '') {
            continue;
        }
        if ($min === null || strcmp($id, $min) < 0) {
            $min = $id;
        }
    }
    return $min;
}

function fetch_tossbook_schedule(array $tgPosts = []): array
{
    $fromPosts = tossbook_schedules_from_posts($tgPosts);
    $cached = cache_get('tossbook_sched', 3600);
    $freshDates = [];
    foreach ($fromPosts as $row) {
        if (!empty($row['date'])) {
            $freshDates[$row['date']] = true;
        }
    }
    $merged = $fromPosts;
    foreach ($cached['matches'] ?? [] as $row) {
        $d = (string) ($row['date'] ?? '');
        if ($d === '' || isset($freshDates[$d])) {
            continue;
        }
        $merged[] = $row;
    }
    $pack = [
        'matches' => $merged,
        'fetchedAt' => ist_now()->format('h:i:s A') . ' IST',
        'pages' => 0,
    ];
    if ($merged) {
        cache_set('tossbook_sched', $pack);
    }
    return $pack;
}

function fetch_tossbook_rpc_leans(): array
{
    $cached = cache_get('tossbook_leans', 45);
    if (is_array($cached)) {
        return $cached;
    }
    $res = tossbook_http_json(tossbook_supabase_url() . '/rest/v1/rpc/match_load', []);
    $rows = [];
    if (!empty($res['ok'])) {
        foreach ($res['data'] as $row) {
            if (!empty($row['id'])) {
                $rows[] = [
                    'id' => (string) $row['id'],
                    'lean' => strtolower((string) ($row['lean'] ?? '')),
                ];
            }
        }
    }
    if ($rows) {
        cache_set('tossbook_leans', $rows);
    }
    return $rows;
}

function book_match_hits_fixture(array $book, array $match): bool
{
    $ba = (string) ($book['teamA'] ?? '');
    $bb = (string) ($book['teamB'] ?? '');
    $fa = (string) ($match['teamA'] ?? '');
    $fb = (string) ($match['teamB'] ?? '');
    if ($ba === '' || $bb === '' || $fa === '' || $fb === '') {
        return false;
    }
    return (team_hits_name($ba, $fa) && team_hits_name($bb, $fb))
        || (team_hits_name($ba, $fb) && team_hits_name($bb, $fa));
}

function tossbook_money_for_book(array $book, array $bets): array
{
    $sumA = 0.0;
    $sumB = 0.0;
    $nA = 0;
    $nB = 0;
    foreach ($bets as $p) {
        $name = (string) ($p['teamName'] ?? '');
        if ($name === '') {
            continue;
        }
        $amt = (float) ($p['amountValue'] ?? 0);
        if (team_hits_name($name, $book['teamA'] ?? '')) {
            $sumA += $amt;
            $nA++;
        } elseif (team_hits_name($name, $book['teamB'] ?? '')) {
            $sumB += $amt;
            $nB++;
        }
    }
    $total = $sumA + $sumB;
    $lean = null;
    $leanTeam = null;
    if ($total > 0) {
        if ($sumA === $sumB) {
            $lean = 'even';
        } elseif ($sumA > $sumB) {
            $lean = 'A';
            $leanTeam = $book['teamA'];
        } else {
            $lean = 'B';
            $leanTeam = $book['teamB'];
        }
    }
    $pctA = $total > 0 ? (int) round($sumA / $total * 100) : 50;
    return [
        'amountA' => $sumA,
        'amountB' => $sumB,
        'betsA' => $nA,
        'betsB' => $nB,
        'amountALabel' => format_inr($sumA),
        'amountBLabel' => format_inr($sumB),
        'total' => $total,
        'totalLabel' => format_inr($total),
        'pctA' => $pctA,
        'pctB' => 100 - $pctA,
        'lean' => $lean,
        'leanTeam' => $leanTeam,
        'hasLean' => $total > 0,
    ];
}

function tossbook_day_bets(array $posts, string $date): array
{
    return array_values(array_filter($posts, function ($p) use ($date) {
        if (($p['type'] ?? '') !== 'BET_PLACED' || empty($p['teamName'])) {
            return false;
        }
        $ist = post_ist_date($p);
        return $ist === null || $ist === $date;
    }));
}

function infer_book_matches_from_bets(array $posts, string $date, array $fixtures = []): array
{
    $bets = tossbook_day_bets($posts, $date);
    if (!$bets) {
        return [];
    }
    $byTeam = [];
    foreach ($bets as $p) {
        $key = strtoupper(trim((string) ($p['teamName'] ?? '')));
        if ($key === '') {
            continue;
        }
        if (!isset($byTeam[$key])) {
            $byTeam[$key] = ['team' => trim((string) $p['teamName']), 'total' => 0.0];
        }
        $byTeam[$key]['total'] += (float) ($p['amountValue'] ?? 0);
    }
    $used = [];
    $out = [];
    foreach ($fixtures as $m) {
        $hitA = null;
        $hitB = null;
        foreach ($byTeam as $k => $row) {
            if (!empty($used[$k])) {
                continue;
            }
            if (team_hits_name($row['team'], (string) ($m['teamA'] ?? ''))) {
                $hitA = $k;
            } elseif (team_hits_name($row['team'], (string) ($m['teamB'] ?? ''))) {
                $hitB = $k;
            }
        }
        if (!$hitA && !$hitB) {
            continue;
        }
        if ($hitA) {
            $used[$hitA] = true;
        }
        if ($hitB) {
            $used[$hitB] = true;
        }
        $out[] = [
            'id' => 'tb_fx_' . md5(($m['id'] ?? '') . $date),
            'date' => $date,
            'teamA' => (string) ($m['teamA'] ?? ($hitA ? $byTeam[$hitA]['team'] : 'Team A')),
            'teamB' => (string) ($m['teamB'] ?? ($hitB ? $byTeam[$hitB]['team'] : 'Team B')),
            'league' => (string) ($m['tournament'] ?? $m['league'] ?? ''),
            'time' => (string) ($m['time'] ?? ''),
            'status' => 'OPEN',
            'open' => true,
        ];
    }
    $left = [];
    foreach ($byTeam as $k => $row) {
        if (empty($used[$k])) {
            $left[] = $row;
        }
    }
    usort($left, static fn($a, $b) => $b['total'] <=> $a['total']);
    while (count($left) >= 2) {
        $a = array_shift($left);
        $b = array_shift($left);
        $out[] = [
            'id' => 'tb_inf_' . md5($date . '|' . $a['team'] . '|' . $b['team']),
            'date' => $date,
            'teamA' => $a['team'],
            'teamB' => $b['team'],
            'league' => 'Toss-book',
            'time' => '',
            'status' => 'OPEN',
            'open' => true,
        ];
    }
    return $out;
}

function build_tossbook_board(array $tgPosts, string $date, array $fixtures = []): array
{
    $sched = fetch_tossbook_schedule($tgPosts);
    $listed = [];
    foreach ($sched['matches'] ?? [] as $row) {
        if (($row['date'] ?? '') === $date) {
            $listed[] = $row;
        }
    }
    if (!$listed) {
        $listed = infer_book_matches_from_bets($tgPosts, $date, $fixtures);
    }
    $bets = tossbook_day_bets($tgPosts, $date);
    $board = [];
    foreach ($listed as $row) {
        $money = tossbook_money_for_book($row, $bets);
        $board[] = array_merge($row, $money, [
            'label' => ($money['hasLean'] && $money['lean'] && $money['lean'] !== 'even')
                ? ('Load currently favouring ' . $money['leanTeam'])
                : ($money['hasLean'] ? 'Load evenly balanced right now' : 'No load yet — open for bets'),
        ]);
    }
    usort($board, static function ($a, $b) {
        $hot = ((int) !empty($b['hasLean'])) <=> ((int) !empty($a['hasLean']));
        if ($hot !== 0) {
            return $hot;
        }
        return ($b['total'] ?? 0) <=> ($a['total'] ?? 0);
    });
    return [
        'date' => $date,
        'fetchedAt' => $sched['fetchedAt'] ?? (ist_now()->format('h:i:s A') . ' IST'),
        'listed' => count($board),
        'inferred' => empty(array_filter($sched['matches'] ?? [], fn($r) => ($r['date'] ?? '') === $date)),
        'matches' => $board,
    ];
}

function website_load_for_match(array $match, array $boardMatches): ?array
{
    $date = (string) ($match['date'] ?? '');
    foreach ($boardMatches as $book) {
        if ($date !== '' && ($book['date'] ?? '') !== $date) {
            continue;
        }
        if (!book_match_hits_fixture($book, $match)) {
            continue;
        }
        $sameOrder = team_hits_name($book['teamA'] ?? '', $match['teamA'] ?? '')
            && team_hits_name($book['teamB'] ?? '', $match['teamB'] ?? '');
        $amtA = (float) ($sameOrder ? ($book['amountA'] ?? 0) : ($book['amountB'] ?? 0));
        $amtB = (float) ($sameOrder ? ($book['amountB'] ?? 0) : ($book['amountA'] ?? 0));
        $total = $amtA + $amtB;
        $pctA = $total > 0 ? (int) round($amtA / $total * 100) : 50;
        $leanTeam = null;
        $lean = null;
        if ($total > 0) {
            if ($amtA === $amtB) {
                $lean = 'even';
            } else {
                $lean = $amtA > $amtB ? 'A' : 'B';
                $leanTeam = $amtA > $amtB ? ($match['teamA'] ?? '') : ($match['teamB'] ?? '');
            }
        }
        return [
            'onBook' => true,
            'bookTeamA' => $book['teamA'] ?? '',
            'bookTeamB' => $book['teamB'] ?? '',
            'bookTime' => $book['time'] ?? '',
            'bookStatus' => $book['status'] ?? '',
            'amountA' => $amtA,
            'amountB' => $amtB,
            'amountALabel' => format_inr($amtA),
            'amountBLabel' => format_inr($amtB),
            'pctA' => $pctA,
            'pctB' => 100 - $pctA,
            'lean' => $lean,
            'leanTeam' => $leanTeam,
            'hasLean' => $total > 0,
            'label' => $total <= 0
                ? 'Listed on toss-book · no load yet'
                : ($leanTeam
                    ? ('Website load favouring ' . $leanTeam)
                    : 'Website load evenly balanced'),
        ];
    }
    return [
        'onBook' => false,
        'hasLean' => false,
        'pctA' => 50,
        'pctB' => 50,
        'lean' => null,
        'leanTeam' => null,
        'amountA' => 0,
        'amountB' => 0,
        'amountALabel' => format_inr(0),
        'amountBLabel' => format_inr(0),
        'label' => 'Not listed on toss-book',
    ];
}
