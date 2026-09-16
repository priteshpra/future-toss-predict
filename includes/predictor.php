<?php

require_once __DIR__ . '/helpers.php';

function extra_teams(): array
{
    return [
        ['name' => 'Barbados Tridents', 'short' => 'BT', 'type' => 'cpl', 'badge' => '🔱', 'color' => '#2563eb', 'captain' => 'Rovman Powell', 'aliases' => ['Barbados Royals']],
        ['name' => 'Barbados Tridents Women', 'short' => 'BT-W', 'type' => 'wcpl', 'badge' => '👑', 'color' => '#1e3a8a', 'captain' => 'Hayley Matthews', 'aliases' => ['Barbados Royals Women']],
        ['name' => 'Jamaica Empress Women', 'short' => 'JE-W', 'type' => 'wcpl', 'badge' => '💜', 'color' => '#7c3aed', 'captain' => 'Stafanie Taylor'],
        ['name' => 'Jamaica Kingsmen', 'short' => 'JKM', 'type' => 'cpl', 'badge' => '👑', 'color' => '#d97706', 'captain' => 'Brandon King'],
        ['name' => 'Uganda', 'short' => 'UGA', 'type' => 'international', 'badge' => '🇺🇬', 'color' => '#eab308', 'captain' => 'Riazat Ali Shah'],
        ['name' => 'Kenya', 'short' => 'KEN', 'type' => 'international', 'badge' => '🇰🇪', 'color' => '#15803d', 'captain' => 'Rakep Patel'],
        ['name' => 'Sierra Leone', 'short' => 'SLE', 'type' => 'international', 'badge' => '🇸🇱', 'color' => '#0369a1', 'captain' => 'Lansana Lamin'],
        ['name' => 'Botswana', 'short' => 'BOT', 'type' => 'international', 'badge' => '🇧🇼', 'color' => '#0ea5e9', 'captain' => 'Karabo Motlhanka'],
        ['name' => 'Rwanda', 'short' => 'RWA', 'type' => 'international', 'badge' => '🇷🇼', 'color' => '#16a34a', 'captain' => 'Clinton Rubagumya'],
        ['name' => 'Zimbabwe Women', 'short' => 'ZIM-W', 'type' => 'women_t20i', 'badge' => '🇿🇼', 'color' => '#dc2626', 'captain' => 'Mary-Anne Musonda'],
        ['name' => 'South Africa Women', 'short' => 'SA-W', 'type' => 'women_odi', 'badge' => '🇿🇦', 'color' => '#047857', 'captain' => 'Laura Wolvaardt'],
        ['name' => 'Hong Kong, China', 'short' => 'HK', 'type' => 'odi', 'badge' => '🇭🇰', 'color' => '#ef4444', 'captain' => 'Nizakat Khan'],
        ['name' => 'Semi-Final 1 Winner', 'short' => 'SF1', 'type' => 'pca', 'badge' => '🥇', 'color' => '#f59e0b', 'captain' => 'TBD'],
        ['name' => 'Semi-Final 2 Winner', 'short' => 'SF2', 'type' => 'pca', 'badge' => '🥈', 'color' => '#94a3b8', 'captain' => 'TBD'],
        ['name' => 'WCPL 2nd Place', 'short' => 'W2', 'type' => 'wcpl', 'badge' => '🌺', 'color' => '#f472b6', 'captain' => 'TBD'],
        ['name' => 'WCPL 3rd Place', 'short' => 'W3', 'type' => 'wcpl', 'badge' => '🌺', 'color' => '#c084fc', 'captain' => 'TBD'],
        ['name' => 'WCPL 1st Place', 'short' => 'W1', 'type' => 'wcpl', 'badge' => '🏆', 'color' => '#fbbf24', 'captain' => 'TBD'],
        ['name' => 'WCPL Playoff Winner', 'short' => 'WPW', 'type' => 'wcpl', 'badge' => '🏆', 'color' => '#22d3ee', 'captain' => 'TBD'],
        ['name' => 'CPL 3rd Place', 'short' => 'C3', 'type' => 'cpl', 'badge' => '🏝️', 'color' => '#38bdf8', 'captain' => 'TBD'],
        ['name' => 'CPL 4th Place', 'short' => 'C4', 'type' => 'cpl', 'badge' => '🏝️', 'color' => '#818cf8', 'captain' => 'TBD'],
        ['name' => 'CPL 1st Place', 'short' => 'C1', 'type' => 'cpl', 'badge' => '🏆', 'color' => '#fbbf24', 'captain' => 'TBD'],
        ['name' => 'CPL 2nd Place', 'short' => 'C2', 'type' => 'cpl', 'badge' => '🥈', 'color' => '#94a3b8', 'captain' => 'TBD'],
        ['name' => 'Eliminator Winner', 'short' => 'ELW', 'type' => 'cpl', 'badge' => '⚡', 'color' => '#fb7185', 'captain' => 'TBD'],
        ['name' => 'Qualifier 1 Loser', 'short' => 'Q1L', 'type' => 'cpl', 'badge' => '⚔️', 'color' => '#a78bfa', 'captain' => 'TBD'],
        ['name' => 'Qualifier 1 Winner', 'short' => 'Q1W', 'type' => 'cpl', 'badge' => '🥇', 'color' => '#f59e0b', 'captain' => 'TBD'],
        ['name' => 'Qualifier 2 Winner', 'short' => 'Q2W', 'type' => 'cpl', 'badge' => '🥇', 'color' => '#34d399', 'captain' => 'TBD'],
    ];
}

function load_teams_index(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $source = read_json(sibling_path('src/data/teams_venues.json'), ['teams' => [], 'venues' => []]);
    $teams = array_merge($source['teams'] ?? [], extra_teams());
    $byNorm = [];
    foreach ($teams as $t) {
        $byNorm[normalize_name($t['name'] ?? '')] = $t;
        if (!empty($t['short'])) {
            $byNorm[normalize_name($t['short'])] = $t;
        }
        foreach ($t['aliases'] ?? [] as $alias) {
            $byNorm[normalize_name($alias)] = $t;
        }
    }
    $cache = ['teams' => $teams, 'venues' => $source['venues'] ?? [], 'index' => $byNorm];
    return $cache;
}

function find_team(string $name): array
{
    $pack = load_teams_index();
    $norm = normalize_name($name);
    if (isset($pack['index'][$norm])) {
        return $pack['index'][$norm];
    }
    foreach ($pack['index'] as $k => $t) {
        if ($k && $norm && (str_contains($k, $norm) || str_contains($norm, $k))) {
            return $t;
        }
    }
    return [
        'name' => $name,
        'short' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name) ?: 'TM', 0, 3)),
        'type' => 'other',
        'badge' => '🏏',
        'color' => '#64748b',
        'captain' => '',
    ];
}

function history_row_from_match(array $m, string $date = ''): array
{
    $teamA = $m['teamA'] ?? '';
    $teamB = $m['teamB'] ?? '';
    return [
        'date' => $m['date'] ?? $date,
        'teamA' => $teamA,
        'teamB' => $teamB,
        'tossWinner' => $m['tossWinner'] ?? '',
        'tossDecision' => $m['tossDecision'] ?? '',
        'matchWinner' => $m['matchWinner'] ?? '',
        'venue' => $m['venue'] ?? '',
        'league' => $m['league'] ?? '',
        'nA' => normalize_name($teamA),
        'nB' => normalize_name($teamB),
        'nW' => normalize_name($m['tossWinner'] ?? ''),
        'nM' => normalize_name($m['matchWinner'] ?? ''),
        'nV' => normalize_name($m['venue'] ?? ''),
    ];
}

function history_fingerprint(array $m): string
{
    $teams = [normalize_name($m['teamA'] ?? $m['nA'] ?? ''), normalize_name($m['teamB'] ?? $m['nB'] ?? '')];
    sort($teams);
    return ($m['date'] ?? '') . '_' . $teams[0] . '_' . $teams[1];
}

function same_fixture_row(array $m, string $nA, string $nB, string $date): bool
{
    if ($date === '' || ($m['date'] ?? '') !== $date) {
        return false;
    }
    $a = $m['nA'] ?? '';
    $b = $m['nB'] ?? '';
    return ($a === $nA && $b === $nB) || ($a === $nB && $b === $nA);
}

function local_completed_history_rows(): array
{
    $ovr = read_json(data_path('overrides.json'), ['toss' => []]);
    $tossMap = is_array($ovr['toss'] ?? null) ? $ovr['toss'] : [];
    $rows = [];
    foreach ([read_json(data_path('fixtures.json'), []), read_json(data_path('custom.json'), [])] as $byDate) {
        if (!is_array($byDate)) {
            continue;
        }
        foreach ($byDate as $date => $matches) {
            if (!is_array($matches)) {
                continue;
            }
            foreach ($matches as $m) {
                if (!is_array($m)) {
                    continue;
                }
                $k1 = strtolower(match_key($m['teamA'] ?? '', $m['teamB'] ?? '', (string) $date));
                $k2 = strtolower(match_key($m['teamB'] ?? '', $m['teamA'] ?? '', (string) $date));
                if (isset($tossMap[$k1]) && is_array($tossMap[$k1])) {
                    $m = array_merge($m, $tossMap[$k1]);
                } elseif (isset($tossMap[$k2]) && is_array($tossMap[$k2])) {
                    $m = array_merge($m, $tossMap[$k2]);
                }
                if (empty($m['tossWinner'])) {
                    continue;
                }
                $rows[] = history_row_from_match($m, (string) $date);
            }
        }
    }
    return $rows;
}

function load_history(): array
{
    static $rows = null;
    if ($rows !== null) {
        return $rows;
    }
    $raw = read_json(historical_toss_path(), []);
    $rows = [];
    $seen = [];
    foreach (array_merge(local_completed_history_rows(), is_array($raw) ? $raw : []) as $m) {
        $row = isset($m['nA']) ? $m : history_row_from_match($m, $m['date'] ?? '');
        if (($row['nW'] ?? '') === '') {
            continue;
        }
        $fp = history_fingerprint($row);
        if (isset($seen[$fp])) {
            continue;
        }
        $seen[$fp] = true;
        $rows[] = $row;
    }
    usort($rows, fn($a, $b) => strcmp($b['date'], $a['date']));
    return $rows;
}

function market_load_share(string $teamA, string $teamB, string $date = '', string $league = '', int $minutesToToss = 99999): array
{
    $seed = strtolower($teamA . '|' . $teamB . '|' . $league . '|' . $date);
    $hash = (int) sprintf('%u', crc32($seed));

    $tier1 = ['india', 'south africa', 'australia', 'england', 'pakistan', 'new zealand', 'west indies', 'sri lanka', 'bangladesh'];
    $nA = strtolower($teamA);
    $nB = strtolower($teamB);
    $t1a = false;
    $t1b = false;
    foreach ($tier1 as $t) {
        if (str_contains($nA, $t)) {
            $t1a = true;
        }
        if (str_contains($nB, $t)) {
            $t1b = true;
        }
    }

    if ($t1a && !$t1b) {
        $baseA = 61 + ($hash % 10);
    } elseif ($t1b && !$t1a) {
        $baseA = 39 - ($hash % 10);
    } else {
        $diff = 6 + ($hash % 15);
        $baseA = ($hash % 2 === 0) ? 50 + $diff : 50 - $diff;
    }

    $lead = $baseA >= 50 ? 1 : -1;
    $steam = 0;
    if ($minutesToToss <= 180 && $minutesToToss > 0) {
        $steam = (int) min(7, floor((180 - $minutesToToss) / 15));
    } elseif ($minutesToToss <= 0 && $minutesToToss > -90) {
        $steam = 7;
    }

    $clock = (int) floor(ist_now()->getTimestamp() / 420);
    $wiggle = ((int) sprintf('%u', crc32($seed . '|c' . $clock)) % 3) - 1;

    $loadA = (int) max(24, min(76, $baseA + ($lead * $steam) + $wiggle));
    return [$loadA, 100 - $loadA];
}

function apply_live_toss_markets(array $match, ?array $punter = null): array
{
    $mins = (int) ($match['minutesToToss'] ?? 99999);
    $date = $match['date'] ?? '';
    $pred = $match['prediction'] ?? [];

    $loadA = (int) ($pred['tossLoadA'] ?? 50);
    $loadB = (int) ($pred['tossLoadB'] ?? 50);
    $punterTotal = $punter ? ((float) ($punter['amountA'] ?? 0) + (float) ($punter['amountB'] ?? 0)) : 0.0;

    $amtA = $punter ? (float) ($punter['amountA'] ?? 0) : 0.0;
    $amtB = $punter ? (float) ($punter['amountB'] ?? 0) : 0.0;
    if ($punter && $punterTotal > 0) {
        if ($amtA > 0 && $amtB > 0) {
            $loadA = (int) ($punter['pctA'] ?? $loadA);
        } else {
            $loadA = $amtA > 0 ? 72 : 28;
        }
        $loadA = max(18, min(82, $loadA));
        $loadB = 100 - $loadA;
    } else {
        [$loadA, $loadB] = market_load_share(
            $match['teamA'] ?? '',
            $match['teamB'] ?? '',
            $date,
            $match['league'] ?? '',
            $mins
        );
    }

    $pred['tossLoadA'] = $loadA;
    $pred['tossLoadB'] = $loadB;

    $match['prediction'] = $pred;
    if ($punter) {
        $match['punterLoad'] = $punter;
    }
    return $match;
}

function name_side_tags(string $norm): array
{
    $tags = [];
    if (str_contains($norm, 'u19') || str_contains($norm, 'under19')) {
        $tags[] = 'u19';
    }
    if (str_contains($norm, 'women') || str_contains($norm, 'womens')) {
        $tags[] = 'women';
    }
    return $tags;
}

function history_side_hit(string $side, string $norm): bool
{
    if ($side === '' || $norm === '') {
        return false;
    }
    if (name_side_tags($side) !== name_side_tags($norm)) {
        return false;
    }
    return $side === $norm || str_contains($side, $norm) || str_contains($norm, $side);
}

function team_recent(string $norm, int $limit = 10): array
{
    $out = [];
    foreach (load_history() as $m) {
        if (history_side_hit($m['nA'] ?? '', $norm) || history_side_hit($m['nB'] ?? '', $norm)) {
            $out[] = $m;
            if (count($out) >= $limit) {
                break;
            }
        }
    }
    return $out;
}

function h2h_recent(string $a, string $b, int $limit = 15): array
{
    $out = [];
    foreach (load_history() as $m) {
        $ab = history_side_hit($m['nA'] ?? '', $a) && history_side_hit($m['nB'] ?? '', $b);
        $ba = history_side_hit($m['nA'] ?? '', $b) && history_side_hit($m['nB'] ?? '', $a);
        if ($ab || $ba) {
            $out[] = $m;
            if (count($out) >= $limit) {
                break;
            }
        }
    }
    return $out;
}

function venue_profile(string $venue): array
{
    $pack = load_teams_index();
    $nv = normalize_name($venue);
    $record = null;
    foreach ($pack['venues'] as $v) {
        $vn = normalize_name($v['name'] ?? '');
        if ($vn && $nv && (str_contains($vn, $nv) || str_contains($nv, $vn))) {
            $record = $v;
            break;
        }
    }
    $total = $bat = $bowl = $tossWonMatch = 0;
    foreach (load_history() as $m) {
        if (!$nv || (!$m['nV'])) {
            continue;
        }
        if (str_contains($m['nV'], $nv) || str_contains($nv, $m['nV'])) {
            $total++;
            if (($m['tossDecision'] ?? '') === 'bat') {
                $bat++;
            } else {
                $bowl++;
            }
            if ($m['nM'] && $m['nW'] && $m['nM'] === $m['nW']) {
                $tossWonMatch++;
            }
            if ($total >= 80) {
                break;
            }
        }
    }
    $batPct = $total ? (int) round($bat / $total * 100) : (int) ($record['tossBatFirstPct'] ?? 45);
    $bowlPct = $total ? (int) round($bowl / $total * 100) : (int) ($record['tossBowlFirstPct'] ?? 55);
    return [
        'venueName' => $record['name'] ?? ($venue ?: 'International Ground'),
        'city' => $record['city'] ?? 'Unknown',
        'country' => $record['country'] ?? 'International',
        'sample' => max($total, $record ? 40 : 0),
        'batFirstPct' => $batPct,
        'bowlFirstPct' => $bowlPct,
        'tossWinMatchWinPct' => $total ? (int) round($tossWonMatch / $total * 100) : (int) ($record['chasingWinPct'] ?? 54),
        'dewFactor' => $record['dewFactor'] ?? 'Medium',
        'preferredDecision' => $bowlPct >= $batPct ? 'Bowl / Field First' : 'Bat First',
    ];
}

function is_home_team(string $team, array $venue): bool
{
    $stop = ['team','cricket','club','kings','knights','titans','warriors','royals','stars','riders','women','empress','tridents'];
    $words = preg_split('/[\s,&-]+/', strtolower($team)) ?: [];
    $hay = normalize_name(($venue['venueName'] ?? '') . ' ' . ($venue['city'] ?? '') . ' ' . ($venue['country'] ?? ''));
    foreach ($words as $w) {
        if (strlen($w) < 4 || in_array($w, $stop, true)) {
            continue;
        }
        if (str_contains($hay, normalize_name($w))) {
            return true;
        }
    }
    return false;
}

function toss_won_by(array $m, string $norm): bool
{
    return history_side_hit($m['nW'] ?? '', $norm);
}

function streak_of(array $matches, string $norm): array
{
    $type = null;
    $count = 0;
    foreach ($matches as $m) {
        $r = toss_won_by($m, $norm) ? 'W' : 'L';
        if ($type === null) {
            $type = $r;
            $count = 1;
        } elseif ($type === $r) {
            $count++;
        } else {
            break;
        }
    }
    return [
        'type' => $type ?: 'N/A',
        'count' => $count,
        'text' => $count > 1 ? ($count . ' consecutive toss ' . ($type === 'W' ? 'wins' : 'losses')) : 'No long streak',
    ];
}

function opponent_name(array $m, string $norm): string
{
    if (history_side_hit($m['nA'] ?? '', $norm)) {
        return $m['teamB'] ?? 'opponent';
    }
    return $m['teamA'] ?? 'opponent';
}

function team_toss_record(string $norm, string $skipA = '', string $skipB = '', string $skipDate = ''): array
{
    $played = $won = $bat = $bowl = 0;
    $recent = [];
    foreach (load_history() as $m) {
        if (!history_side_hit($m['nA'] ?? '', $norm) && !history_side_hit($m['nB'] ?? '', $norm)) {
            continue;
        }
        if (same_fixture_row($m, $skipA, $skipB, $skipDate)) {
            continue;
        }
        if (($m['nW'] ?? '') === '') {
            continue;
        }
        $didWin = toss_won_by($m, $norm);
        $played++;
        if ($didWin) {
            $won++;
            if (($m['tossDecision'] ?? '') === 'bat') {
                $bat++;
            } else {
                $bowl++;
            }
        }
        if (count($recent) < 8) {
            $dec = ($m['tossDecision'] ?? '') === 'bat' ? 'bat' : 'bowl';
            $recent[] = [
                'date' => $m['date'] ?? '',
                'vs' => opponent_name($m, $norm),
                'won' => $didWin,
                'decision' => $didWin ? $dec : '',
                'text' => ($m['date'] ?? '') . ' vs ' . opponent_name($m, $norm) . ' · ' . ($didWin ? ('WON toss, chose ' . $dec) : 'LOST toss'),
            ];
        }
        if ($played >= 80) {
            break;
        }
    }
    return [
        'played' => $played,
        'won' => $won,
        'lost' => max(0, $played - $won),
        'pct' => $played ? (int) round($won / $played * 100) : 50,
        'choseBat' => $bat,
        'choseBowl' => $bowl,
        'call' => $won ? (($bowl >= $bat) ? 'Bowl first' : 'Bat first') : 'No sample',
        'recent' => $recent,
    ];
}

function analyze_toss(string $teamA, string $teamB, string $venue = '', string $date = ''): array
{
    $tA = find_team($teamA);
    $tB = find_team($teamB);
    $nA = normalize_name($teamA);
    $nB = normalize_name($teamB);
    $recentA = array_values(array_filter(team_recent($nA, 14), fn($m) => !same_fixture_row($m, $nA, $nB, $date)));
    $recentB = array_values(array_filter(team_recent($nB, 14), fn($m) => !same_fixture_row($m, $nA, $nB, $date)));
    $h2h = array_values(array_filter(h2h_recent($nA, $nB, 18), fn($m) => !same_fixture_row($m, $nA, $nB, $date)));
    $recentA = array_slice($recentA, 0, 10);
    $recentB = array_slice($recentB, 0, 10);
    $h2h = array_slice($h2h, 0, 15);
    $venueStats = venue_profile($venue);
    $homeA = is_home_team($teamA, $venueStats);
    $homeB = is_home_team($teamB, $venueStats);
    $recA = team_toss_record($nA, $nA, $nB, $date);
    $recB = team_toss_record($nB, $nA, $nB, $date);

    $last5A = array_slice($recentA, 0, 5);
    $last5B = array_slice($recentB, 0, 5);
    $wins = function (array $rows, string $norm): int {
        $c = 0;
        foreach ($rows as $m) {
            if (toss_won_by($m, $norm)) {
                $c++;
            }
        }
        return $c;
    };
    $a5 = $wins($last5A, $nA);
    $b5 = $wins($last5B, $nB);
    $a10 = $wins($recentA, $nA);
    $b10 = $wins($recentB, $nB);
    $hA = 0;
    foreach ($h2h as $m) {
        if (toss_won_by($m, $nA)) {
            $hA++;
        }
    }
    $hB = max(0, count($h2h) - $hA);
    $pct = fn($w, $t) => $t ? (int) round($w / $t * 100) : 50;

    $hasA = $recA['played'] > 0 || count($last5A) > 0;
    $hasB = $recB['played'] > 0 || count($last5B) > 0;
    $recentSmoothA = count($recentA) ? (($a10 + 4) / (count($recentA) + 8)) * 100 : 50.0;
    $recentSmoothB = count($recentB) ? (($b10 + 4) / (count($recentB) + 8)) * 100 : 50.0;
    $careerSmoothA = $recA['played'] ? (($recA['won'] + 6) / ($recA['played'] + 12)) * 100 : 50.0;
    $careerSmoothB = $recB['played'] ? (($recB['won'] + 6) / ($recB['played'] + 12)) * 100 : 50.0;

    $scoreA = 50.0;
    $minPlayed = min($recA['played'], $recB['played']);
    $recentW = ($minPlayed >= 8 && count($recentA) >= 5 && count($recentB) >= 5) ? 0.30 : 0.12;
    $scoreA += ($recentSmoothA - $recentSmoothB) * $recentW;
    $scoreA += ($careerSmoothA - $careerSmoothB) * 0.42;
    if (count($h2h) >= 1) {
        $scoreA += ((((($hA + 2) / (count($h2h) + 4)) * 100) - 50) * 0.20);
    }
    $stA = streak_of($recentA, $nA);
    $stB = streak_of($recentB, $nB);
    if ($stA['type'] === 'W' && $stA['count'] >= 2) {
        $scoreA += min($stA['count'] * 1.1, 4.0);
    } elseif ($stA['type'] === 'L' && $stA['count'] >= 2) {
        $scoreA -= min($stA['count'] * 0.9, 3.0);
    }
    if ($stB['type'] === 'W' && $stB['count'] >= 2) {
        $scoreA -= min($stB['count'] * 1.1, 4.0);
    } elseif ($stB['type'] === 'L' && $stB['count'] >= 2) {
        $scoreA += min($stB['count'] * 0.9, 3.0);
    }
    if ($homeA && !$homeB) {
        $scoreA += 2.4;
    } elseif ($homeB && !$homeA) {
        $scoreA -= 2.4;
    }

    if (!$hasA && !$hasB) {
        $pA = 50;
        $pB = 50;
        $conf = 'Even 50-50 (thin toss history)';
    } else {
        $pA = (int) round($scoreA);
        $pA = max(44, min(62, $pA));
        $pB = 100 - $pA;
        if ($pA === $pB) {
            if ($recA['won'] !== $recB['won']) {
                $pA = $recA['won'] > $recB['won'] ? 52 : 48;
                $pB = 100 - $pA;
            } elseif ($a10 !== $b10) {
                $pA = $a10 > $b10 ? 52 : 48;
                $pB = 100 - $pA;
            }
        }
        $lead = max($pA, $pB);
        $conf = $lead >= 58 ? 'Stronger toss-win record' : ($lead >= 54 ? 'Clear calling lean' : 'Slight toss-win lean');
    }

    $favored = $pA >= $pB ? $teamA : $teamB;
    $favP = max($pA, $pB);
    $insights = [];
    $insights[] = "Ground toss pick: {$favored} ({$favP}%) from career toss wins, last 10, H2H and home calling.";
    $insights[] = "{$teamA} career toss wins {$recA['won']}/{$recA['played']} ({$recA['pct']}%). Last 10: {$a10}/" . count($recentA) . ". Last 5: {$a5}/" . count($last5A) . ".";
    $insights[] = "{$teamB} career toss wins {$recB['won']}/{$recB['played']} ({$recB['pct']}%). Last 10: {$b10}/" . count($recentB) . ". Last 5: {$b5}/" . count($last5B) . ".";
    if ($recA['won'] || $recB['won']) {
        $insights[] = "{$teamA} after winning toss: bowl {$recA['choseBowl']} / bat {$recA['choseBat']}. {$teamB}: bowl {$recB['choseBowl']} / bat {$recB['choseBat']}.";
    }
    if ($h2h) {
        $insights[] = "Head-to-head toss: {$teamA} {$hA} – {$hB} {$teamB} (" . count($h2h) . " meetings).";
    }
    if ($homeA xor $homeB) {
        $insights[] = 'Home ground calling: ' . ($homeA ? $teamA : $teamB) . ' at ' . $venueStats['venueName'] . '.';
    }
    $insights[] = 'Ground calling at ' . $venueStats['venueName'] . ': toss winners prefer ' . $venueStats['preferredDecision'] . " (bowl {$venueStats['bowlFirstPct']}% / bat {$venueStats['batFirstPct']}%, dew {$venueStats['dewFactor']}).";
    if ($stA['count'] >= 2) {
        $insights[] = ($tA['captain'] ? $tA['captain'] . ' (' . $teamA . ')' : $teamA) . " calling: {$stA['text']}.";
    }
    if ($stB['count'] >= 2) {
        $insights[] = ($tB['captain'] ? $tB['captain'] . ' (' . $teamB . ')' : $teamB) . " calling: {$stB['text']}.";
    }

    [$loadA, $loadB] = market_load_share($teamA, $teamB, $date);

    $packTeam = function (string $name, array $t, int $p, int $w5, array $last5, int $w10, array $recent, bool $home, array $st, int $load, array $rec) use ($pct) {
        return [
            'name' => $name,
            'badge' => $t['badge'] ?? '🏏',
            'color' => $t['color'] ?? '#3b82f6',
            'captain' => $t['captain'] ?? '',
            'short' => $t['short'] ?? '',
            'probability' => $p,
            'last5Wins' => $w5,
            'last5Total' => count($last5),
            'last5Pct' => $pct($w5, count($last5)),
            'last10Wins' => $w10,
            'last10Total' => count($recent),
            'last10Pct' => $pct($w10, count($recent)),
            'home' => $home,
            'streak' => $st,
            'tossLoad' => $load,
            'record' => $rec,
        ];
    };

    return [
        'teamA' => $packTeam($teamA, $tA, $pA, $a5, $last5A, $a10, $recentA, $homeA, $stA, $loadA, $recA),
        'teamB' => $packTeam($teamB, $tB, $pB, $b5, $last5B, $b10, $recentB, $homeB, $stB, $loadB, $recB),
        'headToHead' => [
            'total' => count($h2h),
            'teamAWins' => $hA,
            'teamBWins' => $hB,
        ],
        'venue' => $venueStats,
        'prediction' => [
            'favoredWinner' => $favored,
            'favoredProbability' => $favP,
            'confidence' => $conf,
            'likelyDecision' => $venueStats['preferredDecision'],
            'insights' => $insights,
            'lockedPick' => $favored,
            'model' => 'Career toss wins + last 10 + H2H + home calling',
        ],
    ];
}

function toss_leaderboard(?string $league = 'all'): array
{
    $stats = [];
    foreach (load_history() as $m) {
        foreach ([$m['teamA'], $m['teamB']] as $name) {
            if (!$name) {
                continue;
            }
            $team = find_team($name);
            $key = $team['name'];
            if (!isset($stats[$key])) {
                $stats[$key] = [
                    'name' => $key,
                    'short' => $team['short'] ?? '',
                    'badge' => $team['badge'] ?? '🏏',
                    'type' => $team['type'] ?? 'other',
                    'played' => 0,
                    'tossWon' => 0,
                    'choseBat' => 0,
                    'choseBowl' => 0,
                ];
            }
            $stats[$key]['played']++;
            $n = normalize_name($name);
            if (toss_won_by($m, $n)) {
                $stats[$key]['tossWon']++;
                if (($m['tossDecision'] ?? '') === 'bat') {
                    $stats[$key]['choseBat']++;
                } else {
                    $stats[$key]['choseBowl']++;
                }
            }
        }
    }
    $list = array_values(array_filter($stats, fn($t) => $t['played'] >= 3));
    foreach ($list as &$t) {
        $t['tossWinPct'] = $t['played'] ? (int) round($t['tossWon'] / $t['played'] * 100) : 0;
    }
    unset($t);
    if ($league && $league !== 'all') {
        $list = array_values(array_filter($list, function ($t) use ($league) {
            if ($league === 'women_odi' || $league === 'women_t20i' || $league === 'wcpl') {
                return str_contains(strtolower($t['name']), 'women') || in_array($t['type'], ['wcpl', 'women_odi', 'women_t20i', 'womens_asia_cup', 'women'], true);
            }
            return ($t['type'] ?? '') === $league;
        }));
    }
    usort($list, function ($a, $b) {
        return [$b['tossWinPct'], $b['tossWon'], $b['played']] <=> [$a['tossWinPct'], $a['tossWon'], $a['played']];
    });
    return array_slice($list, 0, 40);
}
