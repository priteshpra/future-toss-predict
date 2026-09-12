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

function load_history(): array
{
    static $rows = null;
    if ($rows !== null) {
        return $rows;
    }
    $raw = read_json(sibling_path('src/data/historical_toss.json'), []);
    $rows = [];
    foreach ($raw as $m) {
        $rows[] = [
            'date' => $m['date'] ?? '',
            'teamA' => $m['teamA'] ?? '',
            'teamB' => $m['teamB'] ?? '',
            'tossWinner' => $m['tossWinner'] ?? '',
            'tossDecision' => $m['tossDecision'] ?? '',
            'matchWinner' => $m['matchWinner'] ?? '',
            'venue' => $m['venue'] ?? '',
            'league' => $m['league'] ?? '',
            'nA' => normalize_name($m['teamA'] ?? ''),
            'nB' => normalize_name($m['teamB'] ?? ''),
            'nW' => normalize_name($m['tossWinner'] ?? ''),
            'nM' => normalize_name($m['matchWinner'] ?? ''),
            'nV' => normalize_name($m['venue'] ?? ''),
        ];
    }
    usort($rows, fn($a, $b) => strcmp($b['date'], $a['date']));
    return $rows;
}

function team_recent(string $norm, int $limit = 10): array
{
    $out = [];
    foreach (load_history() as $m) {
        if ($m['nA'] === $norm || $m['nB'] === $norm || str_contains($m['nA'], $norm) || str_contains($m['nB'], $norm) || str_contains($norm, $m['nA']) || str_contains($norm, $m['nB'])) {
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
        $ab = ($m['nA'] === $a || str_contains($m['nA'], $a) || str_contains($a, $m['nA'])) && ($m['nB'] === $b || str_contains($m['nB'], $b) || str_contains($b, $m['nB']));
        $ba = ($m['nA'] === $b || str_contains($m['nA'], $b) || str_contains($b, $m['nA'])) && ($m['nB'] === $a || str_contains($m['nB'], $a) || str_contains($a, $m['nB']));
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

function streak_of(array $matches, string $norm): array
{
    $type = null;
    $count = 0;
    foreach ($matches as $m) {
        $won = $m['nW'] && (str_contains($m['nW'], $norm) || str_contains($norm, $m['nW']));
        $r = $won ? 'W' : 'L';
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

function analyze_toss(string $teamA, string $teamB, string $venue = '', string $date = ''): array
{
    $tA = find_team($teamA);
    $tB = find_team($teamB);
    $nA = normalize_name($teamA);
    $nB = normalize_name($teamB);
    $recentA = team_recent($nA, 10);
    $recentB = team_recent($nB, 10);
    $h2h = h2h_recent($nA, $nB, 15);
    $venueStats = venue_profile($venue);
    $homeA = is_home_team($teamA, $venueStats);
    $homeB = is_home_team($teamB, $venueStats);

    $last5A = array_slice($recentA, 0, 5);
    $last5B = array_slice($recentB, 0, 5);
    $wins = function (array $rows, string $norm): int {
        $c = 0;
        foreach ($rows as $m) {
            if ($m['nW'] && (str_contains($m['nW'], $norm) || str_contains($norm, $m['nW']))) {
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
        if ($m['nW'] && (str_contains($m['nW'], $nA) || str_contains($nA, $m['nW']))) {
            $hA++;
        }
    }
    $hB = max(0, count($h2h) - $hA);
    $pct = fn($w, $t) => $t ? (int) round($w / $t * 100) : 50;

    $hasA = count($last5A) > 0;
    $hasB = count($last5B) > 0;
    $smoothA = $hasA ? (($a5 + 3) / (count($last5A) + 6)) * 100 : 50.0;
    $smoothB = $hasB ? (($b5 + 3) / (count($last5B) + 6)) * 100 : 50.0;

    $scoreA = 50.0;
    $scoreA += ($smoothA - $smoothB) * 0.28;
    if (count($h2h) >= 1) {
        $scoreA += (((( $hA + 2) / (count($h2h) + 4)) * 100) - 50) * 0.18;
    }
    $stA = streak_of($recentA, $nA);
    $stB = streak_of($recentB, $nB);
    if ($stA['type'] === 'W' && $stA['count'] >= 2) {
        $scoreA += min($stA['count'] * 1.0, 3.5);
    } elseif ($stA['type'] === 'L' && $stA['count'] >= 2) {
        $scoreA -= min($stA['count'] * 0.8, 2.5);
    }
    if ($stB['type'] === 'W' && $stB['count'] >= 2) {
        $scoreA -= min($stB['count'] * 1.0, 3.5);
    } elseif ($stB['type'] === 'L' && $stB['count'] >= 2) {
        $scoreA += min($stB['count'] * 0.8, 2.5);
    }
    if ($homeA && !$homeB) {
        $scoreA += 2.8;
    } elseif ($homeB && !$homeA) {
        $scoreA -= 2.8;
    }
    if (($venueStats['bowlFirstPct'] ?? 50) >= 58) {
        $scoreA += $a5 > $b5 ? 1.1 : ($a5 < $b5 ? -1.1 : 0);
    }

    if (!$hasA && !$hasB) {
        $pA = 50;
        $pB = 50;
        $conf = 'Even 50-50 (thin history)';
    } elseif ($scoreA >= 50.5) {
        $pA = min(58, (int) round(50 + ($scoreA - 50) * 1.15));
        $pB = 100 - $pA;
        $conf = $pA >= 56 ? 'Moderate statistical edge' : 'Marginal edge';
    } elseif ($scoreA <= 49.5) {
        $pA = max(42, (int) round(50 - (50 - $scoreA) * 1.15));
        $pB = 100 - $pA;
        $conf = $pB >= 56 ? 'Moderate statistical edge' : 'Marginal edge';
    } else {
        $pA = 52;
        $pB = 48;
        $conf = 'Slight calling lean';
    }

    $favored = $pA >= $pB ? $teamA : $teamB;
    $favP = max($pA, $pB);
    $insights = [];
    if (!$hasA && !$hasB) {
        $insights[] = "Pure coin: limited toss history for both sides.";
    } else {
        $insights[] = "AI lock lean: {$favored} at {$favP}% from form, H2H, home and venue calling.";
    }
    if ($last5A) {
        $insights[] = "{$teamA} won {$a5}/" . count($last5A) . " of last recorded tosses ({$pct($a5, count($last5A))}%).";
    }
    if ($last5B) {
        $insights[] = "{$teamB} won {$b5}/" . count($last5B) . " of last recorded tosses ({$pct($b5, count($last5B))}%).";
    }
    if ($h2h) {
        $insights[] = "Head-to-head toss: {$teamA} {$hA} – {$hB} {$teamB}.";
    }
    if ($homeA xor $homeB) {
        $insights[] = 'Home calling edge: ' . ($homeA ? $teamA : $teamB) . ' at ' . $venueStats['venueName'] . '.';
    }
    $insights[] = $venueStats['venueName'] . ' toss winners prefer ' . $venueStats['preferredDecision'] . " ({$venueStats['bowlFirstPct']}% bowl).";
    if ($stA['count'] >= 2) {
        $insights[] = "{$teamA} captain calling: {$stA['text']}.";
    }
    if ($stB['count'] >= 2) {
        $insights[] = "{$teamB} captain calling: {$stB['text']}.";
    }

    $loadA = (int) round(48 + ($pA - 50) * 2.4);
    $loadB = 100 - $loadA;

    return [
        'teamA' => [
            'name' => $teamA,
            'badge' => $tA['badge'] ?? '🏏',
            'color' => $tA['color'] ?? '#3b82f6',
            'captain' => $tA['captain'] ?? '',
            'short' => $tA['short'] ?? '',
            'probability' => $pA,
            'last5Wins' => $a5,
            'last5Total' => count($last5A),
            'last5Pct' => $pct($a5, count($last5A)),
            'last10Pct' => $pct($a10, count($recentA)),
            'home' => $homeA,
            'streak' => $stA,
            'tossLoad' => $loadA,
        ],
        'teamB' => [
            'name' => $teamB,
            'badge' => $tB['badge'] ?? '🏏',
            'color' => $tB['color'] ?? '#ef4444',
            'captain' => $tB['captain'] ?? '',
            'short' => $tB['short'] ?? '',
            'probability' => $pB,
            'last5Wins' => $b5,
            'last5Total' => count($last5B),
            'last5Pct' => $pct($b5, count($last5B)),
            'last10Pct' => $pct($b10, count($recentB)),
            'home' => $homeB,
            'streak' => $stB,
            'tossLoad' => $loadB,
        ],
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
            'model' => 'Bayesian Laplace + home + venue + captain streak',
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
            if ($m['nW'] && (str_contains($m['nW'], $n) || str_contains($n, $m['nW']))) {
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
