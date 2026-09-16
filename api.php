<?php

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/predictor.php';
require_once __DIR__ . '/includes/live.php';
require_once __DIR__ . '/includes/telegram.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'health';

function load_store(): array
{
    return [
        'fixtures' => read_json(data_path('fixtures.json'), []),
        'custom' => read_json(data_path('custom.json'), []),
        'overrides' => read_json(data_path('overrides.json'), ['toss' => [], 'deleted' => []]),
    ];
}

function save_custom(array $custom): void
{
    write_json(data_path('custom.json'), $custom);
}

function save_overrides(array $ovr): void
{
    write_json(data_path('overrides.json'), $ovr);
}

function load_day_log(): array
{
    $log = read_json(data_path('day_log.json'), []);
    return is_array($log) ? $log : [];
}

function save_day_log(array $log): void
{
    write_json(data_path('day_log.json'), $log);
}

function match_pair_key(string $teamA, string $teamB, string $date): string
{
    $names = [normalize_name($teamA), normalize_name($teamB)];
    sort($names);
    return $date . '_' . $names[0] . '_' . $names[1];
}

function upsert_day_log_match(string $date, array $match): void
{
    $teamA = trim($match['teamA'] ?? '');
    $teamB = trim($match['teamB'] ?? '');
    if ($teamA === '' || $teamB === '' || $date === '') {
        return;
    }
    $log = load_day_log();
    if (!isset($log[$date]) || !is_array($log[$date])) {
        $log[$date] = [];
    }
    foreach ($log[$date] as $i => $row) {
        if (match_pair_key($row['teamA'] ?? '', $row['teamB'] ?? '', $date) === match_pair_key($teamA, $teamB, $date)) {
            $log[$date][$i] = array_merge($row, $match);
            save_day_log($log);
            return;
        }
    }
    $log[$date][] = $match;
    save_day_log($log);
}

function remove_day_log_match(string $date, string $teamA, string $teamB, string $id = ''): void
{
    $log = load_day_log();
    if (!isset($log[$date]) || !is_array($log[$date])) {
        return;
    }
    $log[$date] = array_values(array_filter($log[$date], function ($m) use ($id, $teamA, $teamB, $date) {
        if ($id && ($m['id'] ?? '') === $id) {
            return false;
        }
        return match_pair_key($m['teamA'] ?? '', $m['teamB'] ?? '', $date) !== match_pair_key($teamA, $teamB, $date);
    }));
    save_day_log($log);
}

function toss_overrides_as_matches(array $tossMap, string $date): array
{
    $out = [];
    $seen = [];
    foreach ($tossMap as $key => $payload) {
        if (!is_array($payload) || empty($payload['tossWinner'])) {
            continue;
        }
        if (!preg_match('/^(.*)_(\d{4}-\d{2}-\d{2})$/', (string) $key, $m)) {
            continue;
        }
        if ($m[2] !== $date) {
            continue;
        }
        $teamA = trim((string) ($payload['teamA'] ?? ''));
        $teamB = trim((string) ($payload['teamB'] ?? ''));
        if ($teamA === '' || $teamB === '') {
            $parts = explode('_', $m[1]);
            if (count($parts) !== 2) {
                continue;
            }
            $teamA = trim(ucwords($parts[0]));
            $teamB = trim(ucwords($parts[1]));
        }
        $fp = match_pair_key($teamA, $teamB, $date);
        if (isset($seen[$fp])) {
            continue;
        }
        $seen[$fp] = true;
        $out[] = array_merge([
            'id' => 'saved_' . md5($fp),
            'teamA' => $teamA,
            'teamB' => $teamB,
            'league' => $payload['league'] ?? 'custom',
            'tournament' => $payload['tournament'] ?? ($teamA . ' vs ' . $teamB),
            'format' => $payload['format'] ?? 'T20',
            'time' => $payload['time'] ?? '07:30 PM IST',
            'venue' => $payload['venue'] ?? 'International Cricket Ground',
        ], $payload);
    }
    return $out;
}

function enrich_match(array $m, string $date): array
{
    $teamA = trim($m['teamA'] ?? '');
    $teamB = trim($m['teamB'] ?? '');
    $time = $m['time'] ?? '07:30 PM IST';
    $tossTime = $m['tossTime'] ?? toss_time_from_match($time);
    $mins = minutes_until_toss($date, $tossTime);
    $status = strtoupper($m['status'] ?? 'UPCOMING');
    $analysis = analyze_toss($teamA, $teamB, $m['venue'] ?? '', $date);
    $tossWinner = $m['tossWinner'] ?? null;

    if ($tossWinner) {
        if ($status === 'UPCOMING') {
            $status = 'LIVE';
        }
    } elseif ($mins <= 0 && $status === 'UPCOMING' && $date <= ist_today()) {
        $status = 'TOSS_WINDOW';
    }

    $phase = 'upcoming';
    if ($tossWinner) {
        $phase = ($status === 'COMPLETED') ? 'done' : 'live';
    } elseif ($status === 'COMPLETED' || $mins < -90) {
        $phase = 'done';
    } elseif ($mins <= 0) {
        $phase = 'toss_now';
    } elseif ($mins <= 30) {
        $phase = 'alert_30';
    } elseif ($mins <= 90) {
        $phase = 'warming';
    }

    $tA = $analysis['teamA'];
    $tB = $analysis['teamB'];
    [$loadA, $loadB] = market_load_share($teamA, $teamB, $date, $m['league'] ?? '', $mins);

    return [
        'id' => $m['id'] ?? ('m_' . md5($teamA . $teamB . $date . $time)),
        'date' => $date,
        'teamA' => $teamA,
        'teamB' => $teamB,
        'teamABadge' => $tA['badge'],
        'teamBBadge' => $tB['badge'],
        'teamAColor' => $tA['color'],
        'teamBColor' => $tB['color'],
        'teamACaptain' => $tA['captain'],
        'teamBCaptain' => $tB['captain'],
        'teamAHome' => $tA['home'],
        'teamBHome' => $tB['home'],
        'league' => $m['league'] ?? 'custom',
        'tournament' => $m['tournament'] ?? ($teamA . ' vs ' . $teamB),
        'format' => $m['format'] ?? 'T20',
        'time' => $time,
        'tossTime' => $tossTime,
        'venue' => $m['venue'] ?? 'International Cricket Ground',
        'status' => $status,
        'phase' => $phase,
        'minutesToToss' => $mins,
        'tossWinner' => $tossWinner,
        'tossDecision' => $m['tossDecision'] ?? null,
        'matchWinner' => $m['matchWinner'] ?? null,
        'liveScore' => $m['liveScore'] ?? null,
        'note' => $m['note'] ?? null,
        'custom' => !empty($m['custom']),
        'prediction' => [
            'winner' => $analysis['prediction']['favoredWinner'],
            'probability' => $analysis['prediction']['favoredProbability'],
            'confidence' => $analysis['prediction']['confidence'],
            'decision' => $analysis['prediction']['likelyDecision'],
            'teamAPct' => $tA['probability'],
            'teamBPct' => $tB['probability'],
            'tossLoadA' => $loadA,
            'tossLoadB' => $loadB,
            'insights' => $analysis['prediction']['insights'],
            'locked' => $mins <= 30,
        ],
        'form' => [
            'aLast5' => $tA['last5Wins'] . '/' . $tA['last5Total'],
            'bLast5' => $tB['last5Wins'] . '/' . $tB['last5Total'],
            'aLast5Pct' => $tA['last5Pct'],
            'bLast5Pct' => $tB['last5Pct'],
            'aLast10' => ($tA['last10Wins'] ?? 0) . '/' . ($tA['last10Total'] ?? 0),
            'bLast10' => ($tB['last10Wins'] ?? 0) . '/' . ($tB['last10Total'] ?? 0),
            'aCareer' => (($tA['record']['won'] ?? 0) . '/' . ($tA['record']['played'] ?? 0)),
            'bCareer' => (($tB['record']['won'] ?? 0) . '/' . ($tB['record']['played'] ?? 0)),
            'aCareerPct' => $tA['record']['pct'] ?? 50,
            'bCareerPct' => $tB['record']['pct'] ?? 50,
            'aStreak' => $tA['streak']['text'],
            'bStreak' => $tB['streak']['text'],
            'h2h' => $analysis['headToHead'],
        ],
        'venueStats' => $analysis['venue'],
        'analysis' => $analysis,
    ];
}

function is_placeholder_team(string $name): bool
{
    $n = strtolower(trim($name));
    if ($n === '' || $n === 'tbd' || $n === 'tba') {
        return true;
    }
    return (bool) preg_match('/\b(\d+(st|nd|rd|th) place|playoff winner|eliminator winner|qualifier [12] (winner|loser)|semi-final [12] winner)\b/', $n);
}

function placeholder_round(array $m): string
{
    $blob = strtolower(($m['id'] ?? '') . ' ' . ($m['tournament'] ?? '') . ' ' . ($m['teamA'] ?? '') . ' ' . ($m['teamB'] ?? ''));
    if (preg_match('/eliminator/', $blob)) {
        return 'eliminator';
    }
    if (preg_match('/qualifier\s*1|\bq1\b/', $blob)) {
        return 'q1';
    }
    if (preg_match('/qualifier\s*2|\bq2\b/', $blob)) {
        return 'q2';
    }
    if (preg_match('/semi/', $blob)) {
        return 'semi';
    }
    if (preg_match('/playoff/', $blob)) {
        return 'playoff';
    }
    if (preg_match('/\bfinal\b/', $blob)) {
        return 'final';
    }
    return '';
}

function is_overnight_ist_fixture(array $m): bool
{
    $mins = parse_ist_minutes($m['tossTime'] ?? $m['time'] ?? '');
    return $mins >= 0 && $mins < (12 * 60);
}

function collect_source_rows(array $store, array $log, string $date): array
{
    $rows = [];
    foreach (($store['fixtures'][$date] ?? []) as $m) {
        $rows[] = $m;
    }
    foreach (($store['custom'][$date] ?? []) as $m) {
        $m['custom'] = true;
        $m['league'] = $m['league'] ?: 'custom';
        $rows[] = $m;
    }
    foreach (($log[$date] ?? []) as $m) {
        if (!is_array($m) || rows_cover_live_match($rows, $m)) {
            continue;
        }
        $rows[] = $m;
    }
    foreach (toss_overrides_as_matches($store['overrides']['toss'] ?? [], $date) as $m) {
        if (rows_cover_live_match($rows, $m)) {
            continue;
        }
        $rows[] = $m;
    }
    return $rows;
}

function is_indexed_team(string $name): bool
{
    $pack = load_teams_index();
    $n = normalize_name($name);
    if ($n === '') {
        return false;
    }
    if (isset($pack['index'][$n])) {
        return true;
    }
    foreach ($pack['index'] as $k => $t) {
        if (strlen((string) $k) < 6) {
            continue;
        }
        if (str_contains($n, (string) $k) || str_contains((string) $k, $n)) {
            return true;
        }
    }
    return false;
}

function guess_live_league(array $lm): string
{
    $blob = strtolower(($lm['teamA'] ?? '') . ' ' . ($lm['teamB'] ?? '') . ' ' . ($lm['title'] ?? '') . ' ' . ($lm['tournament'] ?? ''));
    if (str_contains($blob, 'women') && (str_contains($blob, 'trinbago') || str_contains($blob, 'guyana') || str_contains($blob, 'barbados') || str_contains($blob, 'empress') || str_contains($blob, 'wcpl'))) {
        return 'wcpl';
    }
    if (str_contains($blob, 'knight riders') || str_contains($blob, 'amazon warriors') || str_contains($blob, 'kingsmen') || str_contains($blob, 'tridents') || str_contains($blob, 'patriots') || str_contains($blob, ' cpl')) {
        return 'cpl';
    }
    if (str_contains($blob, 'belfast') || str_contains($blob, 'rotterdam') || str_contains($blob, 'dublin') || str_contains($blob, 'glasgow') || str_contains($blob, 'amsterdam') || str_contains($blob, 'edinburgh')) {
        return 'etpl';
    }
    if (str_contains($blob, 'ludhiana') || str_contains($blob, 'mohali') || str_contains($blob, 'amritsar') || str_contains($blob, 'jalandhar') || str_contains($blob, 'punjab')) {
        return 'pca';
    }
    if (str_contains($blob, 'women') && str_contains($blob, 'odi')) {
        return 'women_odi';
    }
    if (str_contains($blob, 'women')) {
        return 'women_t20i';
    }
    if (str_contains($blob, 'test') || str_contains($blob, 'stumps')) {
        return 'test';
    }
    if (str_contains($blob, 'odi')) {
        return 'odi';
    }
    $tA = find_team($lm['teamA'] ?? '');
    if (!empty($tA['type']) && $tA['type'] !== 'other') {
        return $tA['type'];
    }
    return 't20i';
}

function should_list_live_match(array $lm): bool
{
    $a = $lm['teamA'] ?? '';
    $b = $lm['teamB'] ?? '';
    if ($a === '' || $b === '' || is_placeholder_team($a) || is_placeholder_team($b)) {
        return false;
    }
    $blob = strtolower(($lm['title'] ?? '') . ' ' . $a . ' ' . $b);
    if (preg_match('/cpl|wcpl|caribbean|etpl|european t20|punjab|pca|sher-e|t20i|odi|women/', $blob)) {
        return true;
    }
    return is_indexed_team($a) && is_indexed_team($b);
}

function live_row_as_fixture(array $lm, string $date): array
{
    $league = guess_live_league($lm);
    $status = strtoupper((string) ($lm['status'] ?? 'UPCOMING'));
    return [
        'id' => 'live_' . ($lm['sourceId'] ?: md5(($lm['teamA'] ?? '') . ($lm['teamB'] ?? '') . $date)),
        'teamA' => $lm['teamA'],
        'teamB' => $lm['teamB'],
        'league' => $league,
        'tournament' => $lm['title'] ?: (($lm['teamA'] ?? '') . ' vs ' . ($lm['teamB'] ?? '')),
        'format' => str_contains(strtolower($lm['title'] ?? ''), 'odi') ? 'ODI' : (str_contains(strtolower($lm['title'] ?? ''), 'test') ? 'Test' : 'T20'),
        'time' => $lm['time'] ?? ($status === 'LIVE' ? '10:00 AM IST' : '07:30 PM IST'),
        'venue' => $lm['venue'] ?: 'International Cricket Ground',
        'status' => $status === 'COMPLETED' ? 'COMPLETED' : ($status === 'LIVE' ? 'LIVE' : 'UPCOMING'),
        'tossWinner' => $lm['tossWinner'] ?? null,
        'tossDecision' => $lm['tossDecision'] ?? null,
        'liveScore' => $lm['liveScore'] ?? null,
    ];
}

function rows_cover_live_match(array $rows, array $lm): bool
{
    foreach ($rows as $m) {
        $same = (names_match($m['teamA'] ?? '', $lm['teamA'] ?? '') && names_match($m['teamB'] ?? '', $lm['teamB'] ?? ''))
            || (names_match($m['teamA'] ?? '', $lm['teamB'] ?? '') && names_match($m['teamB'] ?? '', $lm['teamA'] ?? ''));
        if ($same) {
            return true;
        }
    }
    return false;
}

function collect_day(string $date, string $league = 'all'): array
{
    $store = load_store();
    $log = load_day_log();
    $deleted = array_map('strtolower', $store['overrides']['deleted'] ?? []);
    $tagged = [];
    foreach (collect_source_rows($store, $log, $date) as $m) {
        $tagged[] = [$m, $date];
    }

    $today = ist_today();
    if ($date === $today) {
        $tomorrow = ist_shift($date, 1);
        $plain = array_map(static fn($t) => $t[0], $tagged);
        foreach (collect_source_rows($store, $log, $tomorrow) as $m) {
            if (!is_overnight_ist_fixture($m) || rows_cover_live_match($plain, $m)) {
                continue;
            }
            $tagged[] = [$m, $tomorrow];
            $plain[] = $m;
        }
    }

    $live = [];
    if ($date === $today) {
        try {
            $live = fetch_live_feed();
        } catch (Throwable $e) {
            $live = [];
        }
        $plain = array_map(static fn($t) => $t[0], $tagged);
        foreach ($live as $lm) {
            if (!should_list_live_match($lm) || rows_cover_live_match($plain, $lm)) {
                continue;
            }
            $fx = live_row_as_fixture($lm, $date);
            $tagged[] = [$fx, $date];
            $plain[] = $fx;
            upsert_day_log_match($date, $fx);
        }
    }

    $rowsForPlaceholder = array_map(static fn($t) => $t[0], $tagged);
    $out = [];
    foreach ($tagged as [$m, $rowDate]) {
        $k1 = match_key($m['teamA'] ?? '', $m['teamB'] ?? '', $rowDate);
        $k2 = match_key($m['teamB'] ?? '', $m['teamA'] ?? '', $rowDate);
        if (in_array(strtolower($k1), $deleted, true) || in_array(strtolower($k2), $deleted, true)) {
            continue;
        }
        $ovr = ($store['overrides']['toss'][strtolower($k1)] ?? null) ?: ($store['overrides']['toss'][strtolower($k2)] ?? null);
        if (is_array($ovr)) {
            $m = array_merge($m, $ovr);
        }
        if ($live) {
            $m = merge_live($m, $live);
        }
        if (is_placeholder_team($m['teamA'] ?? '') || is_placeholder_team($m['teamB'] ?? '')) {
            $round = placeholder_round($m);
            $hasNamed = false;
            foreach ($rowsForPlaceholder as $other) {
                if (($other['league'] ?? '') !== ($m['league'] ?? '')) {
                    continue;
                }
                if (is_placeholder_team($other['teamA'] ?? '') || is_placeholder_team($other['teamB'] ?? '')) {
                    continue;
                }
                if ($round !== '' && placeholder_round($other) === $round) {
                    $hasNamed = true;
                    break;
                }
            }
            if ($hasNamed) {
                continue;
            }
        }
        if ($league !== 'all' && ($m['league'] ?? '') !== $league) {
            if (!($league === 'custom' && !empty($m['custom']))) {
                continue;
            }
        }
        $out[] = enrich_match($m, $rowDate);
    }

    usort($out, function ($a, $b) {
        $doneA = !empty($a['tossWinner']) ? 1 : 0;
        $doneB = !empty($b['tossWinner']) ? 1 : 0;
        if ($doneA !== $doneB) {
            return $doneA <=> $doneB;
        }
        $dateDiff = strcmp($a['date'] ?? '', $b['date'] ?? '');
        if ($dateDiff !== 0) {
            return $dateDiff;
        }
        $timeDiff = parse_ist_minutes($a['time'] ?? '') <=> parse_ist_minutes($b['time'] ?? '');
        if ($timeDiff !== 0) {
            return $timeDiff;
        }
        return strcasecmp($a['teamA'] ?? '', $b['teamA'] ?? '');
    });

    return $out;
}

function count_day_matches(string $date, array $store, array $log): int
{
    $seen = [];
    $add = function (string $a, string $b, string $keyDate) use (&$seen) {
        $a = trim($a);
        $b = trim($b);
        if ($a === '' || $b === '') {
            return;
        }
        $seen[match_pair_key($a, $b, $keyDate)] = true;
    };
    $include = function (array $m, string $keyDate) use ($add) {
        $add($m['teamA'] ?? '', $m['teamB'] ?? '', $keyDate);
    };
    foreach (collect_source_rows($store, $log, $date) as $m) {
        $include($m, $date);
    }
    if ($date === ist_today()) {
        $tomorrow = ist_shift($date, 1);
        $plain = collect_source_rows($store, $log, $date);
        foreach (collect_source_rows($store, $log, $tomorrow) as $m) {
            if (!is_overnight_ist_fixture($m) || rows_cover_live_match($plain, $m)) {
                continue;
            }
            $include($m, $tomorrow);
        }
    }
    return count($seen);
}

function calendar_days(int $span = 1): array
{
    $store = load_store();
    $log = load_day_log();
    $today = ist_now();
    $todayStr = $today->format('Y-m-d');
    $ahead = max(1, $span);
    $horizon = 10;
    $from = ist_shift($todayStr, -2);
    $to = ist_shift($todayStr, $horizon);

    $wanted = [];
    for ($i = -1; $i <= $ahead; $i++) {
        $wanted[ist_shift($todayStr, $i)] = $i;
    }
    $scanDates = [];
    foreach (array_keys($store['fixtures'] ?? []) as $d) {
        $scanDates[$d] = true;
    }
    foreach (array_keys($store['custom'] ?? []) as $d) {
        $scanDates[$d] = true;
    }
    foreach (array_keys($log) as $d) {
        $scanDates[$d] = true;
    }
    foreach (array_keys($store['overrides']['toss'] ?? []) as $k) {
        if (preg_match('/(\d{4}-\d{2}-\d{2})$/', (string) $k, $m)) {
            $scanDates[$m[1]] = true;
        }
    }
    foreach (array_keys($scanDates) as $d) {
        if ($d < $from || $d > $to || isset($wanted[$d])) {
            continue;
        }
        if (count_day_matches($d, $store, $log) < 1) {
            continue;
        }
        $wanted[$d] = (int) ((new DateTimeImmutable($todayStr))->diff(new DateTimeImmutable($d))->format('%r%a'));
    }

    ksort($wanted);
    $days = [];
    foreach ($wanted as $d => $offset) {
        $label = 'Day';
        if ($d === $todayStr) {
            $label = 'Today';
        } elseif ($d === ist_shift($todayStr, 1)) {
            $label = 'Tomorrow';
        } elseif ($d === ist_shift($todayStr, -1)) {
            $label = 'Yesterday';
        }
        $days[] = [
            'date' => $d,
            'label' => $label,
            'weekday' => (new DateTimeImmutable($d))->format('D'),
            'pretty' => (new DateTimeImmutable($d))->format('d M'),
            'count' => count_day_matches($d, $store, $log),
            'offset' => (int) $offset,
        ];
    }
    return $days;
}

try {
    switch ($action) {
        case 'health':
            json_ok(['ok' => true, 'now' => ist_now()->format('c'), 'today' => ist_today()]);
            break;

        case 'meta':
            $teams = array_map(fn($t) => [
                'name' => $t['name'],
                'short' => $t['short'] ?? '',
                'badge' => $t['badge'] ?? '🏏',
                'type' => $t['type'] ?? '',
            ], load_teams_index()['teams']);
            usort($teams, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            json_ok([
                'today' => ist_today(),
                'now' => ist_now()->format('h:i A') . ' IST',
                'leagues' => leagues(),
                'teams' => $teams,
                'calendar' => calendar_days(1),
            ]);
            break;

        case 'matches':
            $date = normalize_date($_GET['date'] ?? ist_today());
            $league = $_GET['league'] ?? 'all';
            $matches = collect_day($date, $league);
            if (!$matches && $league !== 'all' && $date === ist_today()) {
                $matches = collect_day(ist_shift($date, 1), $league);
            }
            $loadMap = [];
            try {
                $tgFeed = fetch_telegram_bets();
                foreach (build_punter_load($tgFeed['posts'] ?? [], $matches, $date)['matches'] as $row) {
                    $loadMap[$row['id']] = $row;
                }
            } catch (Throwable $e) {
                $loadMap = [];
            }
            foreach ($matches as &$mm) {
                $mm = apply_live_toss_markets($mm, $loadMap[$mm['id']] ?? null);
            }
            unset($mm);
            $alerts = array_values(array_filter($matches, fn($m) => in_array($m['phase'], ['alert_30', 'toss_now'], true) && empty($m['tossWinner'])));
            json_ok([
                'date' => $date,
                'today' => ist_today(),
                'now' => ist_now()->format('h:i A') . ' IST',
                'total' => count($matches),
                'alerts' => $alerts,
                'matches' => $matches,
            ]);
            break;

        case 'predict':
            $teamA = trim($_GET['teamA'] ?? $_POST['teamA'] ?? '');
            $teamB = trim($_GET['teamB'] ?? $_POST['teamB'] ?? '');
            $venue = trim($_GET['venue'] ?? $_POST['venue'] ?? '');
            if ($teamA === '' || $teamB === '') {
                json_ok(['error' => 'Both teams required'], 400);
            }
            json_ok(analyze_toss($teamA, $teamB, $venue, ist_today()));
            break;

        case 'leaderboard':
            json_ok(['teams' => toss_leaderboard($_GET['league'] ?? 'all')]);
            break;

        case 'live':
            json_ok(['matches' => fetch_live_feed(), 'fetchedAt' => ist_now()->format('c')]);
            break;

        case 'add_custom':
            $body = request_json();
            $date = normalize_date($body['date'] ?? ist_today());
            $teamA = trim($body['teamA'] ?? '');
            $teamB = trim($body['teamB'] ?? '');
            if ($teamA === '' || $teamB === '' || strcasecmp($teamA, $teamB) === 0) {
                json_ok(['error' => 'Enter two different teams'], 400);
            }
            $time = trim($body['time'] ?? '07:30 PM');
            if (!str_contains(strtoupper($time), 'IST')) {
                $time .= ' IST';
            }
            $store = load_store();
            $custom = $store['custom'];
            if (!isset($custom[$date])) {
                $custom[$date] = [];
            }
            $match = [
                'id' => 'custom_' . time(),
                'teamA' => $teamA,
                'teamB' => $teamB,
                'league' => $body['league'] ?? 'custom',
                'tournament' => $body['tournament'] ?: ($teamA . ' vs ' . $teamB . ' (Custom)'),
                'format' => $body['format'] ?? 'T20',
                'time' => $time,
                'venue' => trim($body['venue'] ?? 'International Cricket Ground'),
                'status' => 'UPCOMING',
                'custom' => true,
            ];
            $custom[$date][] = $match;
            save_custom($custom);
            upsert_day_log_match($date, $match);
            $ovr = $store['overrides'];
            $ovr['deleted'] = array_values(array_filter($ovr['deleted'] ?? [], function ($k) use ($teamA, $teamB, $date) {
                return !in_array(strtolower($k), [match_key($teamA, $teamB, $date), match_key($teamB, $teamA, $date)], true);
            }));
            save_overrides($ovr);
            json_ok(['ok' => true, 'match' => enrich_match($match, $date)]);
            break;

        case 'delete_match':
            $body = request_json();
            $date = normalize_date($body['date'] ?? '');
            $teamA = trim($body['teamA'] ?? '');
            $teamB = trim($body['teamB'] ?? '');
            $id = $body['id'] ?? '';
            $store = load_store();
            $custom = $store['custom'];
            if (isset($custom[$date])) {
                $custom[$date] = array_values(array_filter($custom[$date], function ($m) use ($id, $teamA, $teamB) {
                    if ($id && ($m['id'] ?? '') === $id) {
                        return false;
                    }
                    return !(names_match($m['teamA'], $teamA) && names_match($m['teamB'], $teamB));
                }));
                save_custom($custom);
            }
            remove_day_log_match($date, $teamA, $teamB, $id);
            $ovr = $store['overrides'];
            $ovr['deleted'][] = match_key($teamA, $teamB, $date);
            $ovr['deleted'][] = match_key($teamB, $teamA, $date);
            $ovr['deleted'] = array_values(array_unique($ovr['deleted']));
            save_overrides($ovr);
            json_ok(['ok' => true]);
            break;

        case 'telegram_bets':
            $matches = [];
            try {
                $matches = collect_day(ist_today(), 'all');
            } catch (Throwable $e) {
                $matches = [];
            }
            $tgOpts = [
                'type' => $_GET['type'] ?? 'bets_only',
                'force' => !empty($_GET['force']),
                'limit' => (int) ($_GET['limit'] ?? 80),
            ];
            if (isset($_GET['hideOthers'])) {
                $tgOpts['hideOthers'] = $_GET['hideOthers'] === '1';
            }
            try {
                json_ok(telegram_payload($tgOpts, $matches));
            } catch (Throwable $e) {
                json_ok([
                    'ok' => false,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'channel' => '@BetfairTossbookOrignal',
                    'webUrl' => 'https://web.telegram.org/k/#@BetfairTossbookOrignal',
                    'config' => load_tg_config(),
                    'count' => 0,
                    'watchedCount' => 0,
                    'bets' => [],
                    'watched' => [],
                    'punterLoad' => ['teams' => [], 'matches' => []],
                ]);
            }
            break;

        case 'telegram_config':
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                json_ok(['ok' => true, 'config' => save_tg_config(request_json())]);
            }
            json_ok(['ok' => true, 'config' => load_tg_config()]);
            break;

        case 'set_toss':
            $body = request_json();
            $date = normalize_date($body['date'] ?? '');
            $teamA = trim($body['teamA'] ?? '');
            $teamB = trim($body['teamB'] ?? '');
            $winner = trim($body['tossWinner'] ?? '');
            $store = load_store();
            $ovr = $store['overrides'];
            $payload = [
                'teamA' => $teamA,
                'teamB' => $teamB,
                'date' => $date,
                'league' => $body['league'] ?? 'custom',
                'tournament' => $body['tournament'] ?? ($teamA . ' vs ' . $teamB),
                'format' => $body['format'] ?? 'T20',
                'time' => $body['time'] ?? '07:30 PM IST',
                'venue' => $body['venue'] ?? 'International Cricket Ground',
                'tossWinner' => $winner ?: null,
                'tossDecision' => $body['tossDecision'] ?? 'bowl',
                'matchWinner' => $body['matchWinner'] ?? $winner,
                'status' => $winner ? 'COMPLETED' : 'UPCOMING',
            ];
            $ovr['toss'][strtolower(match_key($teamA, $teamB, $date))] = $payload;
            $ovr['toss'][strtolower(match_key($teamB, $teamA, $date))] = $payload;
            save_overrides($ovr);
            upsert_day_log_match($date, array_merge(['id' => $body['id'] ?? ('saved_' . md5($teamA . $teamB . $date))], $payload));
            json_ok(['ok' => true, 'data' => $payload]);
            break;

        default:
            json_ok(['error' => 'Unknown action'], 404);
    }
} catch (Throwable $e) {
    json_ok(['error' => $e->getMessage()], 500);
}
