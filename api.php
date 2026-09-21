<?php

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/predictor.php';
require_once __DIR__ . '/includes/live.php';
require_once __DIR__ . '/includes/telegram.php';
require_once __DIR__ . '/includes/tossbook.php';

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

function save_fixtures(array $fixtures): void
{
    write_json(data_path('fixtures.json'), $fixtures);
}

function normalize_match_time(string $time): string
{
    $time = trim($time);
    if ($time === '') {
        return '07:30 PM IST';
    }
    if (!str_contains(strtoupper($time), 'IST')) {
        $time .= ' IST';
    }
    return $time;
}

function match_row_hit(array $m, string $id, string $teamA, string $teamB): bool
{
    if ($id !== '' && ($m['id'] ?? '') === $id) {
        return true;
    }
    $a = $m['teamA'] ?? '';
    $b = $m['teamB'] ?? '';
    return (names_match($a, $teamA) && names_match($b, $teamB))
        || (names_match($a, $teamB) && names_match($b, $teamA));
}

function patch_date_bucket(array &$bucket, string $oldDate, string $newDate, string $id, string $teamA, string $teamB, array $patch): bool
{
    if (!isset($bucket[$oldDate]) || !is_array($bucket[$oldDate])) {
        return false;
    }
    foreach ($bucket[$oldDate] as $i => $row) {
        if (!is_array($row) || !match_row_hit($row, $id, $teamA, $teamB)) {
            continue;
        }
        $updated = array_merge($row, $patch);
        if ($oldDate === $newDate) {
            $bucket[$oldDate][$i] = $updated;
            return true;
        }
        unset($bucket[$oldDate][$i]);
        $bucket[$oldDate] = array_values($bucket[$oldDate]);
        if ($bucket[$oldDate] === []) {
            unset($bucket[$oldDate]);
        }
        if (!isset($bucket[$newDate]) || !is_array($bucket[$newDate])) {
            $bucket[$newDate] = [];
        }
        $bucket[$newDate][] = $updated;
        return true;
    }
    return false;
}

function find_field_patch(array $fields, array $m, string $date): ?array
{
    if (!$fields) {
        return null;
    }
    $id = (string) ($m['id'] ?? '');
    $k1 = strtolower(match_key($m['teamA'] ?? '', $m['teamB'] ?? '', $date));
    $k2 = strtolower(match_key($m['teamB'] ?? '', $m['teamA'] ?? '', $date));
    if ($id !== '' && isset($fields[$id]) && is_array($fields[$id])) {
        return $fields[$id];
    }
    if (isset($fields[$k1]) && is_array($fields[$k1])) {
        return $fields[$k1];
    }
    if (isset($fields[$k2]) && is_array($fields[$k2])) {
        return $fields[$k2];
    }
    foreach ($fields as $patch) {
        if (!is_array($patch)) {
            continue;
        }
        $pd = (string) ($patch['date'] ?? $patch['origDate'] ?? '');
        if ($pd !== '' && $pd !== $date) {
            continue;
        }
        if ($id !== '' && (string) ($patch['id'] ?? '') === $id) {
            return $patch;
        }
        $pairs = [
            [$patch['teamA'] ?? '', $patch['teamB'] ?? ''],
            [$patch['origTeamA'] ?? '', $patch['origTeamB'] ?? ''],
        ];
        foreach ($pairs as [$a, $b]) {
            if ($a === '' || $b === '') {
                continue;
            }
            if ((sides_match($m['teamA'] ?? '', $a) && sides_match($m['teamB'] ?? '', $b))
                || (sides_match($m['teamA'] ?? '', $b) && sides_match($m['teamB'] ?? '', $a))
            ) {
                return $patch;
            }
        }
    }
    return null;
}

function apply_field_override(array $m, array $fields, string $date): array
{
    $patch = find_field_patch($fields, $m, $date);
    if (!is_array($patch)) {
        return $m;
    }
    $keepId = $m['id'] ?? ($patch['id'] ?? null);
    unset($patch['origDate'], $patch['origTeamA'], $patch['origTeamB']);
    $out = array_merge($m, $patch);
    if ($keepId) {
        $out['id'] = $keepId;
    }
    $out['userEdited'] = true;
    return $out;
}

function apply_toss_override(array $m, array $tossMap, string $date): array
{
    $k1 = strtolower(match_key($m['teamA'] ?? '', $m['teamB'] ?? '', $date));
    $k2 = strtolower(match_key($m['teamB'] ?? '', $m['teamA'] ?? '', $date));
    $ovr = ($tossMap[$k1] ?? null) ?: ($tossMap[$k2] ?? null);
    if (!is_array($ovr)) {
        foreach ($tossMap as $payload) {
            if (!is_array($payload) || empty($payload['tossWinner'])) {
                continue;
            }
            if (($payload['date'] ?? '') !== $date) {
                continue;
            }
            if ((sides_match($m['teamA'] ?? '', $payload['teamA'] ?? '') && sides_match($m['teamB'] ?? '', $payload['teamB'] ?? ''))
                || (sides_match($m['teamA'] ?? '', $payload['teamB'] ?? '') && sides_match($m['teamB'] ?? '', $payload['teamA'] ?? ''))
            ) {
                $ovr = $payload;
                break;
            }
        }
    }
    if (!is_array($ovr) || empty($ovr['tossWinner'])) {
        return $m;
    }
    $m['tossWinner'] = $ovr['tossWinner'];
    $m['tossDecision'] = $ovr['tossDecision'] ?? ($m['tossDecision'] ?? 'bowl');
    $m['matchWinner'] = $ovr['matchWinner'] ?? ($m['matchWinner'] ?? $ovr['tossWinner']);
    $m['status'] = $ovr['status'] ?? 'COMPLETED';
    $m['userToss'] = true;
    return $m;
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
    $protected = ['teamA', 'teamB', 'time', 'tossTime', 'venue', 'tournament', 'league', 'format', 'tossWinner', 'tossDecision', 'matchWinner'];
    foreach ($log[$date] as $i => $row) {
        $same = match_pair_key($row['teamA'] ?? '', $row['teamB'] ?? '', $date) === match_pair_key($teamA, $teamB, $date)
            || (!empty($match['id']) && ($row['id'] ?? '') === $match['id']);
        if (!$same) {
            continue;
        }
        $merged = $row;
        $incomingEdit = !empty($match['userEdited']);
        $incomingToss = !empty($match['userToss']);
        $lock = (!empty($row['userEdited']) || !empty($row['userToss'])) && !$incomingEdit && !$incomingToss;
        foreach ($match as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            if ($lock && in_array($k, $protected, true)) {
                continue;
            }
            $merged[$k] = $v;
        }
        if ($incomingEdit) {
            $merged['userEdited'] = true;
        } elseif (!empty($row['userEdited'])) {
            $merged['userEdited'] = true;
            foreach ($protected as $k) {
                if ($k === 'tossWinner' || $k === 'tossDecision' || $k === 'matchWinner') {
                    continue;
                }
                if (!empty($row[$k])) {
                    $merged[$k] = $row[$k];
                }
            }
        }
        if ($incomingToss && !empty($match['tossWinner'])) {
            $merged['userToss'] = true;
            $merged['tossWinner'] = $match['tossWinner'];
            $merged['tossDecision'] = $match['tossDecision'] ?? ($row['tossDecision'] ?? 'bowl');
        } elseif (!empty($row['tossWinner'])) {
            $merged['userToss'] = true;
            $merged['tossWinner'] = $row['tossWinner'];
            if (!empty($row['tossDecision'])) {
                $merged['tossDecision'] = $row['tossDecision'];
            }
        }
        $log[$date][$i] = $merged;
        if (!empty($row['tossWinner']) || !empty($match['tossWinner'])) {
            if (!empty($row['tossWinner'])) {
                $log[$date][$i]['tossWinner'] = $row['tossWinner'];
                if (!empty($row['tossDecision'])) {
                    $log[$date][$i]['tossDecision'] = $row['tossDecision'];
                }
            }
            if (!empty($row['liveLock']) && is_array($row['liveLock'])) {
                $log[$date][$i]['liveLock'] = $row['liveLock'];
            }
        }
        save_day_log($log);
        return;
    }
    $log[$date][] = $match;
    save_day_log($log);
}

function find_day_log_row(string $date, array $match): ?array
{
    $log = load_day_log();
    $id = (string) ($match['id'] ?? '');
    $pair = match_pair_key($match['teamA'] ?? '', $match['teamB'] ?? '', $date);
    foreach (($log[$date] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ($id !== '' && ($row['id'] ?? '') === $id) {
            return $row;
        }
        if (match_pair_key($row['teamA'] ?? '', $row['teamB'] ?? '', $date) === $pair) {
            return $row;
        }
    }
    return null;
}

function strip_done_prediction(array $pred): array
{
    $pred['winner'] = null;
    $pred['probability'] = 50;
    $pred['confidence'] = 'Toss done';
    $pred['tipperReport'] = null;
    $pred['whyPick'] = null;
    $pred['hasLoad'] = false;
    $pred['hasTgLoad'] = false;
    $pred['hasWebLoad'] = false;
    $pred['onBook'] = false;
    $pred['strong'] = false;
    $pred['insights'] = [];
    $pred['sources'] = [];
    return $pred;
}

function apply_frozen_or_strip(array $match): array
{
    if (empty($match['tossWinner'])) {
        return $match;
    }
    $date = (string) ($match['date'] ?? '');
    $row = $date !== '' ? find_day_log_row($date, $match) : null;
    $lock = is_array($row['liveLock'] ?? null) ? $row['liveLock'] : null;
    if (is_array($lock) && is_array($lock['prediction'] ?? null)) {
        $match['prediction'] = $lock['prediction'];
        if (array_key_exists('punterLoad', $lock)) {
            $match['punterLoad'] = $lock['punterLoad'];
        }
        if (array_key_exists('websiteLoad', $lock)) {
            $match['websiteLoad'] = $lock['websiteLoad'];
        }
    } else {
        $match['prediction'] = strip_done_prediction(is_array($match['prediction'] ?? null) ? $match['prediction'] : []);
        $match['punterLoad'] = null;
    }
    $match['predictionFrozen'] = true;
    if (isset($match['analysis']['prediction']) && is_array($match['analysis']['prediction'])) {
        $match['analysis']['prediction']['favoredWinner'] = null;
        $match['analysis']['prediction']['insights'] = [];
    }
    return $match;
}

function save_live_lock(array $match): void
{
    if (!empty($match['tossWinner'])) {
        return;
    }
    $date = (string) ($match['date'] ?? '');
    $teamA = trim((string) ($match['teamA'] ?? ''));
    $teamB = trim((string) ($match['teamB'] ?? ''));
    if ($date === '' || $teamA === '' || $teamB === '') {
        return;
    }
    $existing = find_day_log_row($date, $match);
    if (is_array($existing) && !empty($existing['tossWinner'])) {
        return;
    }
    upsert_day_log_match($date, [
        'id' => $match['id'] ?? '',
        'teamA' => $teamA,
        'teamB' => $teamB,
        'date' => $date,
        'liveLock' => [
            'prediction' => $match['prediction'] ?? [],
            'punterLoad' => $match['punterLoad'] ?? null,
            'websiteLoad' => $match['websiteLoad'] ?? null,
            'lockedAt' => ist_now()->format('c'),
        ],
    ]);
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

    $out = [
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
        'liveScore' => null,
        'note' => $m['note'] ?? null,
        'custom' => !empty($m['custom']),
        'prediction' => [
            'winner' => $tossWinner ? null : ($analysis['prediction']['favoredWinner'] ?? null),
            'probability' => $tossWinner ? 50 : ($analysis['prediction']['favoredProbability'] ?? 50),
            'confidence' => $tossWinner ? 'Toss done' : ($analysis['prediction']['confidence'] ?? ''),
            'decision' => $analysis['prediction']['likelyDecision'],
            'teamAPct' => $tA['probability'],
            'teamBPct' => $tB['probability'],
            'tossLoadA' => $loadA,
            'tossLoadB' => $loadB,
            'insights' => $tossWinner ? [] : ($analysis['prediction']['insights'] ?? []),
            'locked' => $mins <= 30,
            'last5Winner' => $analysis['prediction']['last5Winner'] ?? null,
            'last5Gap' => $analysis['prediction']['last5Gap'] ?? 0,
            'last10Winner' => $analysis['prediction']['last10Winner'] ?? null,
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
    if ($tossWinner) {
        return apply_frozen_or_strip($out);
    }
    return $out;
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
    $fields = $store['overrides']['fields'] ?? [];
    $rows = [];
    $push = function (array $m) use (&$rows, $fields, $date) {
        if (skip_slate_match($m)) {
            return;
        }
        $m = apply_field_override($m, $fields, $date);
        foreach ($rows as $i => $ex) {
            $same = (sides_match($ex['teamA'] ?? '', $m['teamA'] ?? '') && sides_match($ex['teamB'] ?? '', $m['teamB'] ?? ''))
                || (sides_match($ex['teamA'] ?? '', $m['teamB'] ?? '') && sides_match($ex['teamB'] ?? '', $m['teamA'] ?? ''))
                || (!empty($m['id']) && ($ex['id'] ?? '') === $m['id']);
            if (!$same) {
                continue;
            }
            if (!empty($m['userEdited']) || !empty($m['userToss'])) {
                $rows[$i] = array_merge($ex, $m);
            } elseif (!empty($ex['userEdited']) || !empty($ex['userToss'])) {
                if (!empty($m['liveScore'])) {
                    $rows[$i]['liveScore'] = $m['liveScore'];
                }
            }
            return;
        }
        $rows[] = $m;
    };
    foreach (($store['fixtures'][$date] ?? []) as $m) {
        $push($m);
    }
    foreach (($store['custom'][$date] ?? []) as $m) {
        $m['custom'] = true;
        $m['league'] = $m['league'] ?: 'custom';
        $push($m);
    }
    foreach (($log[$date] ?? []) as $m) {
        if (is_array($m)) {
            $push($m);
        }
    }
    foreach (toss_overrides_as_matches($store['overrides']['toss'] ?? [], $date) as $m) {
        $push($m);
    }
    foreach (schedule_rows_for_date($date) as $m) {
        $push($m);
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
    if (str_contains($blob, 'knight riders') || str_contains($blob, 'amazon warriors') || str_contains($blob, 'kingsmen') || str_contains($blob, 'falcons') || str_contains($blob, 'tridents') || str_contains($blob, 'patriots') || str_contains($blob, ' cpl')) {
        return 'cpl';
    }
    if (str_contains($blob, 'belfast') || str_contains($blob, 'rotterdam') || str_contains($blob, 'dublin') || str_contains($blob, 'glasgow') || str_contains($blob, 'amsterdam') || str_contains($blob, 'edinburgh')) {
        return 'etpl';
    }
    if (str_contains($blob, 'ludhiana') || str_contains($blob, 'mohali') || str_contains($blob, 'amritsar') || str_contains($blob, 'jalandhar') || str_contains($blob, 'punjab')) {
        return 'pca';
    }
    if (str_contains($blob, 'odisha') || str_contains($blob, 'sambalpur') || str_contains($blob, 'kataka') || str_contains($blob, 'cuttack panthers') || str_contains($blob, 'keonjhar') || str_contains($blob, 'rourkela') || str_contains($blob, 'puri titans') || str_contains($blob, 'bhubaneswar tigers')) {
        return 'odisha';
    }
    if (str_contains($blob, 'wapl') || str_contains($blob, 'andhra premier') || str_contains($blob, 'godavari') || str_contains($blob, 'vizag fire') || str_contains($blob, 'rayalaseema') || str_contains($blob, 'amaravati') || str_contains($blob, 'amravati')) {
        return 'wapl';
    }
    if (str_contains($blob, 'uttarakhand premier') || str_contains($blob, 'pithoragarh') || str_contains($blob, 'bageshwar') || str_contains($blob, 'mussoorie') || str_contains($blob, 'dehradun warriors') || str_contains($blob, 'haridwar elmas') || str_contains($blob, 'nainital tigers')) {
        return 'upl';
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
    return !skip_slate_match($lm);
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

function sides_match(string $a, string $b): bool
{
    if (names_match($a, $b)) {
        return true;
    }
    $ca = normalize_name(find_team($a)['name'] ?? $a);
    $cb = normalize_name(find_team($b)['name'] ?? $b);
    return $ca !== '' && $ca === $cb;
}

function rows_cover_live_match(array $rows, array $lm): bool
{
    foreach ($rows as $m) {
        $same = (sides_match($m['teamA'] ?? '', $lm['teamA'] ?? '') && sides_match($m['teamB'] ?? '', $lm['teamB'] ?? ''))
            || (sides_match($m['teamA'] ?? '', $lm['teamB'] ?? '') && sides_match($m['teamB'] ?? '', $lm['teamA'] ?? ''));
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
    $plain = array_map(static fn($t) => $t[0], $tagged);
    $tomorrowRows = $date === $today ? collect_source_rows($store, $log, ist_shift($date, 1)) : [];
    $live = [];
    if ($date === $today) {
        try {
            $live = fetch_live_feed();
        } catch (Throwable $e) {
            $live = [];
        }
        foreach ($live as $lm) {
            if (!should_list_live_match($lm) || rows_cover_live_match($plain, $lm)) {
                continue;
            }
            $liveStatus = strtoupper((string) ($lm['status'] ?? 'UPCOMING'));
            if ($liveStatus !== 'LIVE' && $liveStatus !== 'COMPLETED' && rows_cover_live_match($tomorrowRows, $lm)) {
                continue;
            }
            $fx = live_row_as_fixture($lm, $date);
            if (skip_slate_match($fx)) {
                continue;
            }
            $fx = apply_field_override($fx, $store['overrides']['fields'] ?? [], $date);
            $fx = apply_toss_override($fx, $store['overrides']['toss'] ?? [], $date);
            $tagged[] = [$fx, $date];
            $plain[] = $fx;
            if (empty($fx['userEdited']) && empty($fx['userToss'])) {
                upsert_day_log_match($date, $fx);
            }
        }
    }

    $rowsForPlaceholder = array_map(static fn($t) => $t[0], $tagged);
    $plainForHist = array_map(static fn($t) => array_merge($t[0], ['date' => $t[1]]), $tagged);
    $cbToss = [];
    try {
        $cbToss = hydrate_cricbuzz_toss_history($plainForHist);
    } catch (Throwable $e) {
        $cbToss = ['byId' => [], 'byPair' => []];
    }
    $out = [];
    foreach ($tagged as [$m, $rowDate]) {
        $k1 = match_key($m['teamA'] ?? '', $m['teamB'] ?? '', $rowDate);
        $k2 = match_key($m['teamB'] ?? '', $m['teamA'] ?? '', $rowDate);
        if (in_array(strtolower($k1), $deleted, true) || in_array(strtolower($k2), $deleted, true)) {
            continue;
        }
        if ($live && empty($m['userEdited'])) {
            $m = merge_live($m, $live);
        } elseif ($live) {
            foreach ($live as $lm) {
                $same = (sides_match($m['teamA'] ?? '', $lm['teamA'] ?? '') && sides_match($m['teamB'] ?? '', $lm['teamB'] ?? ''))
                    || (sides_match($m['teamA'] ?? '', $lm['teamB'] ?? '') && sides_match($m['teamB'] ?? '', $lm['teamA'] ?? ''));
                if ($same && !empty($lm['liveScore'])) {
                    $m['liveScore'] = $lm['liveScore'];
                    break;
                }
            }
        }
        $m = apply_field_override($m, $store['overrides']['fields'] ?? [], $rowDate);
        $m = apply_toss_override($m, $store['overrides']['toss'] ?? [], $rowDate);
        if (empty($m['date'])) {
            $m['date'] = $rowDate;
        }
        $m = apply_cricbuzz_ground_toss($m, $cbToss);
        if (skip_slate_match($m)) {
            continue;
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
        if (skip_slate_match($m) || is_placeholder_team($m['teamA'] ?? '') || is_placeholder_team($m['teamB'] ?? '')) {
            continue;
        }
        $include($m, $date);
    }
    return count($seen);
}

function calendar_days(int $span = 1): array
{
    $store = load_store();
    $log = load_day_log();
    $today = ist_now();
    $todayStr = $today->format('Y-m-d');
    $wanted = [];
    for ($i = -1; $i <= 1; $i++) {
        $wanted[ist_shift($todayStr, $i)] = $i;
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
            $websiteBoard = ['date' => $date, 'matches' => [], 'listed' => 0];
            $tgFeed = ['posts' => []];
            $nearToss = false;
            foreach ($matches as $mm0) {
                if (!empty($mm0['tossWinner'])) {
                    continue;
                }
                $mins = (int) ($mm0['minutesToToss'] ?? 99999);
                if ($mins <= 15 && $mins >= -8) {
                    $nearToss = true;
                    break;
                }
            }
            try {
                $tgFeed = fetch_telegram_bets($nearToss);
                foreach (build_punter_load($tgFeed['posts'] ?? [], $matches, $date)['matches'] as $row) {
                    $loadMap[$row['id']] = $row;
                }
                $websiteBoard = build_tossbook_board($tgFeed['posts'] ?? [], $date, $matches);
            } catch (Throwable $e) {
                $loadMap = [];
            }
            foreach ($matches as &$mm) {
                if (!empty($mm['tossWinner'])) {
                    $mm = apply_frozen_or_strip($mm);
                    continue;
                }
                $web = website_load_for_match($mm, $websiteBoard['matches'] ?? []);
                $mm = apply_live_toss_markets($mm, $loadMap[$mm['id']] ?? null, $web);
                save_live_lock($mm);
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
                'websiteBoard' => $websiteBoard,
            ]);
            break;

        case 'predict':
            $teamA = trim($_GET['teamA'] ?? $_POST['teamA'] ?? '');
            $teamB = trim($_GET['teamB'] ?? $_POST['teamB'] ?? '');
            $venue = trim($_GET['venue'] ?? $_POST['venue'] ?? '');
            if ($teamA === '' || $teamB === '') {
                json_ok(['error' => 'Both teams required'], 400);
            }
            $analysis = analyze_toss($teamA, $teamB, $venue, ist_today());
            $tmp = [
                'id' => 'sim_' . md5($teamA . '|' . $teamB),
                'teamA' => $teamA,
                'teamB' => $teamB,
                'date' => ist_today(),
                'minutesToToss' => 20,
                'prediction' => [
                    'teamAPct' => (int) ($analysis['teamA']['probability'] ?? 50),
                    'last5Winner' => $analysis['prediction']['last5Winner'] ?? null,
                    'last5Gap' => $analysis['prediction']['last5Gap'] ?? 0,
                    'last10Winner' => $analysis['prediction']['last10Winner'] ?? null,
                    'insights' => $analysis['prediction']['insights'] ?? [],
                    'confidence' => $analysis['prediction']['confidence'] ?? '',
                ],
                'analysis' => $analysis,
                'form' => ['h2h' => $analysis['headToHead'] ?? []],
            ];
            try {
                $tgFeed = fetch_telegram_bets();
                $pl = build_punter_load($tgFeed['posts'] ?? [], [$tmp], ist_today());
                $row = $pl['matches'][0] ?? null;
                $board = build_tossbook_board($tgFeed['posts'] ?? [], ist_today(), [$tmp]);
                $web = website_load_for_match($tmp, $board['matches'] ?? []);
                $tmp = apply_live_toss_markets($tmp, $row, $web);
                $analysis['prediction'] = array_merge($analysis['prediction'], $tmp['prediction'] ?? []);
            } catch (Throwable $e) {
            }
            json_ok($analysis);
            break;

        case 'leaderboard':
            $websiteBoard = ['date' => ist_today(), 'matches' => [], 'listed' => 0];
            try {
                $tgFeed = fetch_telegram_bets();
                $websiteBoard = build_tossbook_board($tgFeed['posts'] ?? [], ist_today(), []);
            } catch (Throwable $e) {
            }
            json_ok([
                'teams' => toss_leaderboard($_GET['league'] ?? 'all'),
                'websiteBoard' => $websiteBoard,
            ]);
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

        case 'update_match':
            $body = request_json();
            $origDate = normalize_date($body['origDate'] ?? $body['date'] ?? ist_today());
            $newDate = normalize_date($body['date'] ?? $origDate);
            $origA = trim($body['origTeamA'] ?? $body['teamA'] ?? '');
            $origB = trim($body['origTeamB'] ?? $body['teamB'] ?? '');
            $teamA = trim($body['teamA'] ?? '');
            $teamB = trim($body['teamB'] ?? '');
            $id = trim((string) ($body['id'] ?? ''));
            if ($teamA === '' || $teamB === '' || strcasecmp($teamA, $teamB) === 0) {
                json_ok(['error' => 'Enter two different teams'], 400);
            }
            $time = normalize_match_time((string) ($body['time'] ?? '07:30 PM'));
            $patch = [
                'teamA' => $teamA,
                'teamB' => $teamB,
                'league' => $body['league'] ?? 'custom',
                'tournament' => trim((string) ($body['tournament'] ?? '')) ?: ($teamA . ' vs ' . $teamB),
                'format' => $body['format'] ?? 'T20',
                'time' => $time,
                'tossTime' => toss_time_from_match($time),
                'venue' => trim((string) ($body['venue'] ?? '')) ?: 'International Cricket Ground',
                'userEdited' => true,
            ];
            if ($id !== '') {
                $patch['id'] = $id;
            }
            $store = load_store();
            $fixtures = $store['fixtures'];
            $custom = $store['custom'];
            $hitFx = patch_date_bucket($fixtures, $origDate, $newDate, $id, $origA, $origB, $patch);
            $hitCustom = patch_date_bucket($custom, $origDate, $newDate, $id, $origA, $origB, $patch + ['custom' => true]);
            if ($hitFx) {
                save_fixtures($fixtures);
            }
            if ($hitCustom) {
                save_custom($custom);
            } elseif (!$hitFx) {
                if (!isset($custom[$newDate]) || !is_array($custom[$newDate])) {
                    $custom[$newDate] = [];
                }
                $custom[$newDate][] = array_merge($patch, [
                    'custom' => true,
                    'id' => $id !== '' ? $id : ('custom_' . time()),
                ]);
                save_custom($custom);
            }

            $log = load_day_log();
            $hitLog = patch_date_bucket($log, $origDate, $newDate, $id, $origA, $origB, $patch);
            if ($hitLog) {
                save_day_log($log);
            } else {
                upsert_day_log_match($newDate, array_merge(['id' => $id ?: ('saved_' . md5($teamA . $teamB . $newDate))], $patch));
            }

            $ovr = $store['overrides'];
            if (!isset($ovr['fields']) || !is_array($ovr['fields'])) {
                $ovr['fields'] = [];
            }
            $oldKeys = [
                $id,
                strtolower(match_key($origA, $origB, $origDate)),
                strtolower(match_key($origB, $origA, $origDate)),
            ];
            foreach ($oldKeys as $k) {
                if ($k !== '') {
                    unset($ovr['fields'][$k]);
                }
            }
            $stored = $patch;
            $stored['date'] = $newDate;
            $stored['origDate'] = $origDate;
            $stored['origTeamA'] = $origA;
            $stored['origTeamB'] = $origB;
            $stored['userEdited'] = true;
            if ($id !== '') {
                $ovr['fields'][$id] = $stored;
            }
            $ovr['fields'][strtolower(match_key($teamA, $teamB, $newDate))] = $stored;
            $ovr['fields'][strtolower(match_key($teamB, $teamA, $newDate))] = $stored;

            foreach (
                [
                    strtolower(match_key($origA, $origB, $origDate)),
                    strtolower(match_key($origB, $origA, $origDate)),
                ] as $oldToss
            ) {
                if (isset($ovr['toss'][$oldToss]) && is_array($ovr['toss'][$oldToss])) {
                    $tossRow = array_merge($ovr['toss'][$oldToss], $patch, ['date' => $newDate]);
                    unset($ovr['toss'][$oldToss]);
                    $ovr['toss'][strtolower(match_key($teamA, $teamB, $newDate))] = $tossRow;
                    $ovr['toss'][strtolower(match_key($teamB, $teamA, $newDate))] = $tossRow;
                }
            }

            $ovr['deleted'] = array_values(array_filter($ovr['deleted'] ?? [], function ($k) use ($teamA, $teamB, $newDate) {
                $k = strtolower((string) $k);
                return $k !== strtolower(match_key($teamA, $teamB, $newDate))
                    && $k !== strtolower(match_key($teamB, $teamA, $newDate));
            }));
            save_overrides($ovr);
            json_ok(['ok' => true, 'match' => enrich_match(array_merge($patch, ['date' => $newDate]), $newDate)]);
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
            @set_time_limit(90);
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
                'userToss' => true,
            ];
            $ovr['toss'][strtolower(match_key($teamA, $teamB, $date))] = $payload;
            $ovr['toss'][strtolower(match_key($teamB, $teamA, $date))] = $payload;
            save_overrides($ovr);
            $tossRow = array_merge(['id' => $body['id'] ?? ('saved_' . md5($teamA . $teamB . $date))], $payload);
            upsert_day_log_match($date, $tossRow);
            $fx = $store['fixtures'];
            $cu = $store['custom'];
            patch_date_bucket($fx, $date, $date, (string) ($body['id'] ?? ''), $teamA, $teamB, $payload);
            patch_date_bucket($cu, $date, $date, (string) ($body['id'] ?? ''), $teamA, $teamB, $payload);
            save_fixtures($fx);
            save_custom($cu);
            json_ok(['ok' => true, 'data' => $payload]);
            break;

        default:
            json_ok(['error' => 'Unknown action'], 404);
    }
} catch (Throwable $e) {
    json_ok(['error' => $e->getMessage()], 500);
}
