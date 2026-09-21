<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/domestic.php';

function extra_teams(): array
{
    return [
        ['name' => 'Barbados Tridents', 'short' => 'BT', 'type' => 'cpl', 'badge' => '🔱', 'color' => '#2563eb', 'captain' => 'Rovman Powell', 'aliases' => ['Barbados Royals']],
        ['name' => 'Barbados Tridents Women', 'short' => 'BT-W', 'type' => 'wcpl', 'badge' => '👑', 'color' => '#1e3a8a', 'captain' => 'Hayley Matthews', 'aliases' => ['Barbados Royals Women']],
        ['name' => 'Jamaica Empress Women', 'short' => 'JE-W', 'type' => 'wcpl', 'badge' => '💜', 'color' => '#7c3aed', 'captain' => 'Stafanie Taylor'],
        ['name' => 'Antigua and Barbuda Falcons', 'short' => 'ABF', 'type' => 'cpl', 'badge' => '🦅', 'color' => '#ef4444', 'captain' => 'Imad Wasim', 'aliases' => ['Falcons']],
        ['name' => 'Jamaica Kingsmen', 'short' => 'JKM', 'type' => 'cpl', 'badge' => '👑', 'color' => '#d97706', 'captain' => 'Brandon King', 'aliases' => ['Kingsmen']],
        ['name' => 'Western Australia', 'short' => 'WAus', 'type' => 'odi', 'badge' => '🟡', 'color' => '#facc15', 'captain' => ''],
        ['name' => 'Tasmania', 'short' => 'TAS', 'type' => 'odi', 'badge' => '🟢', 'color' => '#16a34a', 'captain' => ''],
        ['name' => 'Bahamas', 'short' => 'BAH', 'type' => 't20i', 'badge' => '🇧🇸', 'color' => '#38bdf8', 'captain' => ''],
        ['name' => 'Edinburgh Castle Rockers', 'short' => 'ECR', 'type' => 'etpl', 'badge' => '🏰', 'color' => '#1d4ed8', 'captain' => ''],
        ['name' => 'Belfast Wolves', 'short' => 'BFW', 'type' => 'etpl', 'badge' => '🐺', 'color' => '#16a34a', 'captain' => ''],
        ['name' => 'Amsterdam Flames', 'short' => 'ADF', 'type' => 'etpl', 'badge' => '🔥', 'color' => '#dc2626', 'captain' => ''],
        ['name' => 'India A Women', 'short' => 'IND-A-W', 'type' => 'women_odi', 'badge' => '🇮🇳', 'color' => '#2563eb', 'captain' => ''],
        ['name' => 'Australia A Women', 'short' => 'AUS-A-W', 'type' => 'women_odi', 'badge' => '🇦🇺', 'color' => '#f59e0b', 'captain' => ''],
        ['name' => 'India Under-19s', 'short' => 'IND-U19', 'type' => 'international', 'badge' => '🇮🇳', 'color' => '#1d4ed8', 'captain' => '', 'aliases' => ['India U19', 'India U-19', 'India U19s', 'India Under-19']],
        ['name' => 'Australia Under-19s', 'short' => 'AUS-U19', 'type' => 'international', 'badge' => '🇦🇺', 'color' => '#f59e0b', 'captain' => '', 'aliases' => ['Australia U19', 'Australia U-19', 'Australia U19s', 'Australia Under-19']],
        ['name' => 'Leicestershire', 'short' => 'LEIC', 'type' => 'odi', 'badge' => '🦊', 'color' => '#dc2626', 'captain' => ''],
        ['name' => 'Middlesex', 'short' => 'MDX', 'type' => 'odi', 'badge' => '🎖️', 'color' => '#1e3a8a', 'captain' => ''],
        ['name' => 'Cayman Islands', 'short' => 'CAYM', 'type' => 't20i', 'badge' => '🇰🇾', 'color' => '#eab308', 'captain' => ''],
        ['name' => 'Bermuda', 'short' => 'BER', 'type' => 't20i', 'badge' => '🇧🇲', 'color' => '#2563eb', 'captain' => ''],
        ['name' => 'Nigeria', 'short' => 'NGA', 'type' => 't20i', 'badge' => '🇳🇬', 'color' => '#16a34a', 'captain' => ''],
        ['name' => 'Ghana', 'short' => 'GH', 'type' => 't20i', 'badge' => '🇬🇭', 'color' => '#f97316', 'captain' => ''],
        ['name' => 'Sambalpur Warriors', 'short' => 'SW', 'type' => 'odisha', 'badge' => '🟠', 'color' => '#ea580c', 'captain' => 'Gaurav Choudhury', 'aliases' => ['Sambalpur']],
        ['name' => 'Cuttack Panthers', 'short' => 'CP', 'type' => 'odisha', 'badge' => '🐆', 'color' => '#0f172a', 'captain' => 'Biplab Samantaray', 'aliases' => ['Kataka Panthers', 'Kataka', 'Cuttack']],
        ['name' => 'Keonjhar Miners', 'short' => 'KM', 'type' => 'odisha', 'badge' => '⛏️', 'color' => '#b45309', 'captain' => 'Subhranshu Senapati', 'aliases' => ['Keonjhar']],
        ['name' => 'Bhubaneswar Tigers', 'short' => 'BT', 'type' => 'odisha', 'badge' => '🐯', 'color' => '#f59e0b', 'captain' => 'Swastik Samal', 'aliases' => ['Bhubaneswar']],
        ['name' => 'Rourkela Superstars', 'short' => 'RS', 'type' => 'odisha', 'badge' => '⭐', 'color' => '#2563eb', 'captain' => 'Govinda Poddar', 'aliases' => ['Rourkela']],
        ['name' => 'Puri Titans', 'short' => 'PT', 'type' => 'odisha', 'badge' => '🔱', 'color' => '#7c3aed', 'captain' => 'Aditya Rout', 'aliases' => ['Puri']],
        ['name' => 'Godavari Golden Eagles', 'short' => 'GGE', 'type' => 'wapl', 'badge' => '🦅', 'color' => '#d97706', 'captain' => '', 'aliases' => ['Godavari', 'Godavari Eagles']],
        ['name' => 'Vizag Fire Birds', 'short' => 'VFB', 'type' => 'wapl', 'badge' => '🔥', 'color' => '#ef4444', 'captain' => '', 'aliases' => ['Vizag', 'Fire Birds']],
        ['name' => 'Amaravati E Champions', 'short' => 'AEC', 'type' => 'wapl', 'badge' => '🏆', 'color' => '#16a34a', 'captain' => '', 'aliases' => ['Amaravati', 'Amravati', 'E Champions']],
        ['name' => 'Rayalaseema XEN Stars', 'short' => 'RXS', 'type' => 'wapl', 'badge' => '✨', 'color' => '#7c3aed', 'captain' => '', 'aliases' => ['Rayalaseema', 'Rayalsema', 'XEN Stars']],
        ['name' => 'Bageshwar Aerial', 'short' => 'BAG', 'type' => 'upl', 'badge' => '🪁', 'color' => '#0ea5e9', 'captain' => 'Prema Rawat', 'aliases' => ['Bageshwar', 'Bageshwar Aerial Women']],
        ['name' => 'Mussoorie Queens', 'short' => 'MSQ', 'type' => 'upl', 'badge' => '👑', 'color' => '#a855f7', 'captain' => 'Nandini Kashyap', 'aliases' => ['Mussoorie', 'Mussoorie Queens Women']],
        ['name' => 'Haridwar Elmas', 'short' => 'HRE', 'type' => 'upl', 'badge' => '🔱', 'color' => '#ea580c', 'captain' => '', 'aliases' => ['Haridwar Spring Elmas', 'Haridwar']],
        ['name' => 'Nainital Tigers', 'short' => 'NTT', 'type' => 'upl', 'badge' => '🐯', 'color' => '#16a34a', 'captain' => '', 'aliases' => ['Nainital SG Tigers', 'Nainital']],
        ['name' => 'Eastern Storm', 'short' => 'ESTORM', 'type' => 'odi', 'badge' => '⚡', 'color' => '#7c3aed', 'captain' => ''],
        ['name' => 'Border', 'short' => 'BOR', 'type' => 'odi', 'badge' => '🛡️', 'color' => '#0f766e', 'captain' => ''],
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
    if ($a === '' || $b === '' || $nA === '' || $nB === '') {
        return false;
    }
    if (($a === $nA && $b === $nB) || ($a === $nB && $b === $nA)) {
        return true;
    }
    if (!function_exists('history_side_hit')) {
        return false;
    }
    return (history_side_hit($a, $nA) && history_side_hit($b, $nB))
        || (history_side_hit($a, $nB) && history_side_hit($b, $nA));
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

function load_history(bool $reset = false): array
{
    static $rows = null;
    if ($reset) {
        $rows = null;
        return [];
    }
    if ($rows !== null) {
        return $rows;
    }
    $localFile = data_path('historical_toss.json');
    $sibling = sibling_path('src/data/historical_toss.json');
    $chunks = [local_completed_history_rows()];
    if (function_exists('featured_domestic_history')) {
        $chunks[] = featured_domestic_history();
    }
    $local = read_json($localFile, []);
    if (is_array($local) && $local) {
        $chunks[] = $local;
    }
    $fallbackPath = historical_toss_path();
    foreach ([$sibling, $fallbackPath] as $path) {
        if (!is_string($path) || $path === '' || !is_file($path)) {
            continue;
        }
        if (is_file($localFile) && realpath($path) === realpath($localFile)) {
            continue;
        }
        $raw = read_json($path, []);
        if (is_array($raw) && $raw) {
            $chunks[] = $raw;
        }
    }
    $rows = [];
    $seen = [];
    foreach ($chunks as $chunk) {
        foreach ($chunk as $m) {
            if (!is_array($m)) {
                continue;
            }
            $row = isset($m['nA']) ? $m : history_row_from_match($m, $m['date'] ?? '');
            if (($row['nW'] ?? '') === '') {
                continue;
            }
            $hid = (string) ($m['id'] ?? $row['id'] ?? '');
            if ($hid !== '' && str_contains($hid, '_gen_')) {
                continue;
            }
            $fp = history_fingerprint($row);
            if (isset($seen[$fp])) {
                continue;
            }
            $seen[$fp] = true;
            $rows[] = $row;
        }
    }
    usort($rows, fn($a, $b) => strcmp($b['date'], $a['date']));
    return $rows;
}

function history_invalidate(): void
{
    load_history(true);
}

function market_load_share(string $teamA, string $teamB, string $date = '', string $league = '', int $minutesToToss = 99999): array
{
    return [50, 50];
}

function apply_live_toss_markets(array $match, ?array $punter = null, ?array $website = null): array
{
    $pred = $match['prediction'] ?? [];
    $teamA = $match['teamA'] ?? '';
    $teamB = $match['teamB'] ?? '';
    $histA = (int) ($pred['teamAPct'] ?? 50);
    $histB = 100 - $histA;

    $punter = is_array($punter) ? $punter : null;
    $website = is_array($website) ? $website : [];

    $tgA = $punter ? (float) ($punter['amountA'] ?? 0) : 0.0;
    $tgB = $punter ? (float) ($punter['amountB'] ?? 0) : 0.0;
    $tgTotal = $tgA + $tgB;
    $hasTg = $tgTotal > 0;

    $webA = $website ? (float) ($website['amountA'] ?? 0) : 0.0;
    $webB = $website ? (float) ($website['amountB'] ?? 0) : 0.0;
    $webTotal = $webA + $webB;
    $onBook = !empty($website['onBook']);
    $hasWeb = $onBook && $webTotal > 0;

    $amtA = $hasTg ? $tgA : $webA;
    $amtB = $hasTg ? $tgB : $webB;
    $usedTotal = $amtA + $amtB;
    $hasLoad = $usedTotal > 0;
    $loadSrc = $hasTg ? 'telegram' : ($hasWeb ? 'website' : null);
    $loadA = 50;
    $loadB = 50;
    if ($hasLoad) {
        $srcRow = $hasTg ? $punter : $website;
        if ($amtA > 0 && $amtB > 0) {
            $loadA = (int) ($srcRow['pctA'] ?? round($amtA / $usedTotal * 100));
        } else {
            $loadA = $amtA > 0 ? 78 : 22;
        }
        $loadA = max(18, min(82, $loadA));
        $loadB = 100 - $loadA;
    }

    $last5Winner = $pred['last5Winner'] ?? null;
    $last10Winner = $pred['last10Winner'] ?? null;
    $l5Gap = abs((int) ($pred['last5Gap'] ?? 0));
    $histWinner = $last5Winner ?: null;
    $histHasEdge = (bool) $histWinner;
    $loadWinner = !$hasLoad ? null : ($loadA === $loadB ? null : ($loadA > $loadB ? $teamA : $teamB));
    $tgWinner = ($hasTg && $tgA !== $tgB) ? ($tgA > $tgB ? $teamA : $teamB) : null;
    $webWinner = ($hasWeb && $webA !== $webB) ? ($webA > $webB ? $teamA : $teamB) : null;
    $same = static function (?string $x, ?string $y): bool {
        return $x && $y && strcasecmp($x, $y) === 0;
    };
    $triple = $same($last5Winner, $tgWinner) && $same($last5Winner, $webWinner);
    $agree = $hasLoad && $loadWinner && $histHasEdge && $same($loadWinner, $histWinner);
    $split = $hasLoad && $loadWinner && $histHasEdge && !$same($loadWinner, $histWinner);
    $loadOnly = $hasLoad && $loadWinner && !$histHasEdge;

    $clampSide = static function (string $winner, string $teamA, int $lo, int $hi) use (&$blendA, &$blendB, &$winPct): void {
        $winPct = max($lo, min($hi, $winPct));
        if (strcasecmp($winner, $teamA) === 0) {
            $blendA = $winPct;
            $blendB = 100 - $winPct;
        } else {
            $blendB = $winPct;
            $blendA = 100 - $winPct;
        }
    };

    if ($split) {
        $winner = $histWinner;
        $blendA = $histA;
        $blendB = $histB;
        $winPct = max($blendA, $blendB);
        $conf = 'SPLIT · last 5 vs load disagree — NO PICK';
    } elseif ($triple) {
        $winner = $last5Winner;
        $blendA = (int) round(($histA * 0.18) + ($loadA * 0.52) + (($hasWeb ? (int) ($website['pctA'] ?? $loadA) : $loadA) * 0.30));
        $winPct = max($blendA, 100 - $blendA);
        $clampSide($winner, $teamA, 68, 80);
        $conf = 'BEST PICK · last 5 + Telegram + website teeno same side';
    } elseif ($agree) {
        $winner = $loadWinner;
        $loadW = $usedTotal >= 20000 ? 0.74 : ($usedTotal >= 3000 ? 0.66 : 0.58);
        $blendA = (int) round(($histA * (1 - $loadW)) + ($loadA * $loadW));
        $winPct = max($blendA, 100 - $blendA);
        $lo = ($l5Gap >= 3 || $usedTotal >= 3000) ? 64 : 60;
        $hi = ($l5Gap >= 3 && $usedTotal >= 3000) ? 78 : 74;
        $clampSide($winner, $teamA, $lo, $hi);
        $conf = $winPct >= 66 ? 'STRONG PICK · last 5 toss + load AGREE' : 'PLAY · last 5 toss + load agree';
    } elseif ($loadOnly) {
        $winner = $loadWinner;
        $blendA = $loadA;
        $winPct = max($blendA, 100 - $blendA);
        $clampSide($winner, $teamA, 54, 64);
        $conf = 'SOFT LOAD · last 5 even, load hi note hai — lock nahi';
    } else {
        $winner = $histWinner;
        $blendA = $histA;
        $blendB = $histB;
        $winPct = $histWinner ? max($blendA, $blendB) : 50;
        $conf = $histWinner
            ? 'Last-5 toss note only — wait for load'
            : 'No pick — last 5 even, mapped load nahi';
    }
    $blendB = 100 - $blendA;

    $webPctA = (int) ($website['pctA'] ?? 50);
    $webPctB = (int) ($website['pctB'] ?? (100 - $webPctA));
    $insights = is_array($pred['insights'] ?? null) ? $pred['insights'] : [];
    $tgLine = $hasTg
        ? ('Telegram ₹: ' . $teamA . ' ' . ($punter['amountALabel'] ?? '') . ' vs ' . $teamB . ' ' . ($punter['amountBLabel'] ?? '') . '.')
        : 'Telegram ₹: is match pe mapped toss money nahi mila.';
    $webLine = !$onBook
        ? 'Website load: toss-book board pe ye match listed nahi hai.'
        : ($hasWeb
            ? ('Website load: ' . ($website['label'] ?? ('favouring ' . ($website['leanTeam'] ?? ''))) . " ({$webPctA}%–{$webPctB}%).")
            : 'Website load: toss-book pe listed hai, abhi load nahi aaya.');
    $comboLine = $triple
        ? ("BEST PICK: {$winner} ({$winPct}%) — last 5 + Telegram + website teeno same side.")
        : ($split
            ? ("NO PICK: last 5 → {$histWinner} ({$histA}%) · load → {$loadWinner} ({$loadA}%). Split pe skip.")
            : ($agree
                ? ("STRONG PICK: {$winner} ({$winPct}%) — last 5 toss aur load same side.")
                : ($loadOnly
                    ? ("SOFT LOAD: {$winner} ({$winPct}%) — last 5 even. Load note hai, lock nahi.")
                    : ($histWinner
                        ? ("Early note: {$winner} ({$winPct}%) last-5 se. Load abhi nahi — PLAY nahi.")
                        : 'No pick: last 5 even, mapped load nahi. Tipper wait karta hai.'))));
    array_unshift($insights, $tgLine, $webLine, $comboLine);

    $mins = (int) ($match['minutesToToss'] ?? 99999);
    $histNote = $histWinner ?: 'even';
    if ($split) {
        $action = 'SKIP';
        $grade = 'skip';
        $reportLine = "SKIP — last 5 {$histWinner}, load {$loadWinner}. Best tipper split pe nahi khelta.";
        $reportPick = null;
    } elseif ($triple) {
        $action = 'PLAY';
        $grade = 'play';
        $reportLine = "BEST PICK: {$winner} — last 5 + Telegram + website teeno same side. Strongest available call, lock nahi.";
        $reportPick = $winner;
    } elseif ($agree && ($usedTotal >= 3000 || $l5Gap >= 3)) {
        $action = 'PLAY';
        $grade = 'play';
        $reportLine = "STRONG PICK: {$winner} — last 5 toss + load dono same side. Best available lean.";
        $reportPick = $winner;
    } elseif ($agree) {
        $action = 'PLAY';
        $grade = 'play';
        $reportLine = "PLAY: {$winner} — last 5 aur load agree. Toss se 5–10 min pehle load ek baar confirm karo.";
        $reportPick = $winner;
    } elseif ($loadOnly) {
        $action = 'LEAN';
        $grade = 'lean';
        $reportLine = "SOFT LOAD: {$winner} — last 5 even. Load note hai, PLAY nahi. Confirm 5–10 min pehle.";
        $reportPick = $winner;
    } elseif (!$hasLoad && $mins > 15) {
        $action = 'WAIT';
        $grade = 'wait';
        $reportLine = !$histHasEdge
            ? 'WAIT — last 5 toss even. Koi pick nahi. Load 5–10 min pehle aayega.'
            : ("EARLY NOTE: {$histWinner} last-5 pe lean hai, lekin load nahi. Yeh lock nahi — 10 min pehle board dekho.");
        $reportPick = $histHasEdge ? $histWinner : null;
    } elseif (!$hasLoad) {
        $action = 'WAIT';
        $grade = 'wait';
        $reportLine = "WAIT FOR LOAD — toss window. Last 5 {$histNote}. Telegram/website money aate hi pick lock.";
        $reportPick = $histHasEdge ? $histWinner : null;
    } else {
        $action = 'LEAN';
        $grade = 'lean';
        $reportLine = "SOFT LEAN: {$winner} — signals mixed. Halka call, skip bhi valid.";
        $reportPick = $winner;
    }
    $pred['tipperReport'] = [
        'action' => $action,
        'grade' => $grade,
        'pick' => $reportPick,
        'headline' => $reportLine,
        'when' => $mins > 15 ? 'Asli load toss se 5–10 min pehle aata hai' : 'Toss window — load ab live check ho raha hai',
        'lastToss' => $histWinner,
        'load' => $loadWinner,
    ];

    $pred['teamAPct'] = $histA;
    $pred['teamBPct'] = $histB;
    $pred['tossLoadA'] = $loadA;
    $pred['tossLoadB'] = $loadB;
    $pred['webLoadA'] = $onBook ? $webPctA : 50;
    $pred['webLoadB'] = $onBook ? $webPctB : 50;
    $pred['hasLoad'] = $hasLoad;
    $pred['hasTgLoad'] = $hasTg;
    $pred['hasWebLoad'] = $hasWeb;
    $pred['onBook'] = $onBook;
    $pred['winner'] = $reportPick;
    $pred['probability'] = $reportPick ? $winPct : 50;
    $pred['confidence'] = $conf;
    $pred['strong'] = ($action === 'PLAY' && ($triple || $agree));
    $pred['pickStrength'] = $triple ? 'best' : ($action === 'PLAY' ? 'strong' : strtolower($action));
    $pred['insights'] = $insights;
    $pred['last5Winner'] = $last5Winner;
    $pred['last10Winner'] = $last10Winner;
    $pred['sources'] = [
        'lastToss' => ['winner' => $histWinner, 'pctA' => $histA, 'pctB' => $histB, 'last5' => $last5Winner, 'gap' => $l5Gap],
        'load' => ['winner' => $loadWinner, 'pctA' => $loadA, 'pctB' => $loadB, 'hasMoney' => $hasLoad, 'amountA' => $amtA, 'amountB' => $amtB, 'source' => $loadSrc],
        'telegram' => ['hasMoney' => $hasTg, 'pctA' => $hasTg ? (int) ($punter['pctA'] ?? $loadA) : 50, 'pctB' => $hasTg ? (int) ($punter['pctB'] ?? $loadB) : 50, 'amountA' => $tgA, 'amountB' => $tgB],
        'website' => ['onBook' => $onBook, 'hasLean' => $hasWeb, 'winner' => $website['leanTeam'] ?? null, 'pctA' => $webPctA, 'pctB' => $webPctB],
        'agree' => $agree || $triple,
        'split' => $split,
        'triple' => $triple,
        'loadOnly' => $loadOnly,
    ];
    $pred['model'] = 'Last 5 toss first + load confirm (best of best)';

    $a = $match['analysis']['teamA'] ?? [];
    $b = $match['analysis']['teamB'] ?? [];
    $h2h = $match['form']['h2h'] ?? ($match['analysis']['headToHead'] ?? []);
    $edge = static function ($av, $bv) use ($teamA, $teamB) {
        if ((float) $av === (float) $bv) {
            return 'Even';
        }
        return ((float) $av > (float) $bv) ? $teamA : $teamB;
    };
    if ($triple) {
        $headline = "BEST PICK {$winner} ({$winPct}%): last 5 + Telegram + website teeno same side.";
    } elseif ($agree) {
        $headline = "STRONG PICK {$winner} ({$winPct}%): last 5 toss aur load dono agree.";
    } elseif ($split) {
        $headline = "NO PICK: last 5 {$histWinner} pe hai, load {$loadWinner} pe — split skip.";
    } elseif ($loadOnly) {
        $headline = "SOFT LOAD {$winner} ({$winPct}%): last 5 even. Load note hai, PLAY lock nahi.";
    } elseif ($hasLoad && $winner) {
        $headline = ($loadSrc === 'website' ? 'Website load' : 'Telegram ₹') . " + last 5 mix: {$winner} ({$winPct}%) — yeh lock nahi.";
    } else {
        $headline = $histHasEdge
            ? "EARLY NOTE {$histWinner}: last 5 toss edge ({$histA}% vs {$histB}%). Load nahi mila — strong pick nahi."
            : 'WAIT: last 5 toss even, mapped load nahi. Strong pick load aane ke baad.';
    }
    $pred['whyPick'] = [
        'picked' => $reportPick,
        'pct' => $winPct,
        'headline' => $headline,
        'factors' => [
            ['name' => 'Last 5 toss', 'a' => ($a['last5Wins'] ?? 0) . '/' . ($a['last5Total'] ?? 0) . ' (' . ($a['last5Pct'] ?? 50) . '%)', 'b' => ($b['last5Wins'] ?? 0) . '/' . ($b['last5Total'] ?? 0) . ' (' . ($b['last5Pct'] ?? 50) . '%)', 'edge' => $edge($a['last5Pct'] ?? 50, $b['last5Pct'] ?? 50)],
            ['name' => 'Last 10 toss', 'a' => ($a['last10Wins'] ?? 0) . '/' . ($a['last10Total'] ?? 0) . ' (' . ($a['last10Pct'] ?? 50) . '%)', 'b' => ($b['last10Wins'] ?? 0) . '/' . ($b['last10Total'] ?? 0) . ' (' . ($b['last10Pct'] ?? 50) . '%)', 'edge' => $edge($a['last10Pct'] ?? 50, $b['last10Pct'] ?? 50)],
            ['name' => 'Career toss', 'a' => ($a['record']['won'] ?? 0) . '/' . ($a['record']['played'] ?? 0) . ' (' . ($a['record']['pct'] ?? 50) . '%)', 'b' => ($b['record']['won'] ?? 0) . '/' . ($b['record']['played'] ?? 0) . ' (' . ($b['record']['pct'] ?? 50) . '%)', 'edge' => $edge($a['record']['pct'] ?? 50, $b['record']['pct'] ?? 50)],
            ['name' => 'H2H toss', 'a' => (string) ($h2h['teamAWins'] ?? 0), 'b' => (string) ($h2h['teamBWins'] ?? 0), 'edge' => $edge($h2h['teamAWins'] ?? 0, $h2h['teamBWins'] ?? 0)],
            ['name' => 'Telegram ₹', 'a' => $hasTg ? ($punter['amountALabel'] ?? ($loadA . '%')) : '—', 'b' => $hasTg ? ($punter['amountBLabel'] ?? ($loadB . '%')) : '—', 'edge' => $hasTg ? ($loadWinner ?: 'Even') : 'No TG money'],
            ['name' => 'Website load', 'a' => $onBook ? ($webPctA . '%') : '—', 'b' => $onBook ? ($webPctB . '%') : '—', 'edge' => !$onBook ? 'Not listed' : ($hasWeb ? ($website['leanTeam'] ?: 'Even') : 'No load yet')],
        ],
    ];

    $match['prediction'] = $pred;
    if ($punter) {
        $match['punterLoad'] = $punter;
    }
    $match['websiteLoad'] = $website;
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
    if ($side === $norm) {
        return true;
    }
    $short = strlen($side) <= strlen($norm) ? $side : $norm;
    $long = strlen($side) <= strlen($norm) ? $norm : $side;
    if ($short === '' || !str_contains($long, $short)) {
        return false;
    }
    $pos = strpos($long, $short);
    $prefix = $pos === false ? $long : substr($long, 0, $pos);
    return $prefix === '';
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
    $l5nA = count($last5A);
    $l5nB = count($last5B);
    $l5Gap = $a5 - $b5;
    $l10Gap = $a10 - $b10;
    $l5SmoothA = $l5nA >= 3 ? (($a5 + 0.4) / ($l5nA + 0.8)) * 100 : 50.0;
    $l5SmoothB = $l5nB >= 3 ? (($b5 + 0.4) / ($l5nB + 0.8)) * 100 : 50.0;
    $recentSmoothA = count($recentA) >= 4 ? (($a10 + 0.8) / (count($recentA) + 1.6)) * 100 : 50.0;
    $recentSmoothB = count($recentB) >= 4 ? (($b10 + 0.8) / (count($recentB) + 1.6)) * 100 : 50.0;
    $careerSmoothA = $recA['played'] ? (($recA['won'] + 6) / ($recA['played'] + 12)) * 100 : 50.0;
    $careerSmoothB = $recB['played'] ? (($recB['won'] + 6) / ($recB['played'] + 12)) * 100 : 50.0;

    $scoreA = 50.0;
    $scoreA += ($l5SmoothA - $l5SmoothB) * 0.62;
    $scoreA += ($recentSmoothA - $recentSmoothB) * 0.26;
    $scoreA += ($careerSmoothA - $careerSmoothB) * 0.05;
    if (count($h2h) >= 3) {
        $scoreA += ((((($hA + 1) / (count($h2h) + 2)) * 100) - 50) * 0.07);
    }
    $stA = streak_of($recentA, $nA);
    $stB = streak_of($recentB, $nB);
    if ($stA['type'] === 'W' && $stA['count'] >= 2) {
        $scoreA += min($stA['count'] * 1.6, 5.5);
    } elseif ($stA['type'] === 'L' && $stA['count'] >= 2) {
        $scoreA -= min($stA['count'] * 1.4, 4.5);
    }
    if ($stB['type'] === 'W' && $stB['count'] >= 2) {
        $scoreA -= min($stB['count'] * 1.6, 5.5);
    } elseif ($stB['type'] === 'L' && $stB['count'] >= 2) {
        $scoreA += min($stB['count'] * 1.4, 4.5);
    }
    if ($homeA && !$homeB) {
        $scoreA += 1.4;
    } elseif ($homeB && !$homeA) {
        $scoreA -= 1.4;
    }

    $last5Winner = ($l5nA >= 2 && $l5nB >= 2 && abs($l5Gap) >= 2)
        ? ($l5Gap > 0 ? $teamA : $teamB)
        : null;
    $last10Winner = (count($recentA) >= 6 && count($recentB) >= 6 && abs($l10Gap) >= 3)
        ? ($l10Gap > 0 ? $teamA : $teamB)
        : null;

    if (!$hasA && !$hasB) {
        $pA = 50;
        $pB = 50;
        $conf = 'Even 50-50 (thin toss history)';
    } else {
        $pA = (int) round($scoreA);
        $absGap = abs($l5Gap);
        if ($l5nA >= 4 && $l5nB >= 4 && $absGap >= 3) {
            $pA = max(34, min(76, $pA));
        } elseif ($l5nA >= 2 && $l5nB >= 2 && $absGap >= 2) {
            $pA = max(38, min(72, $pA));
        } else {
            $pA = max(44, min(60, $pA));
        }
        $pB = 100 - $pA;
        if ($pA === $pB && $last5Winner) {
            $pA = strcasecmp($last5Winner, $teamA) === 0 ? 58 : 42;
            $pB = 100 - $pA;
        } elseif ($pA === $pB && $last10Winner) {
            $pA = strcasecmp($last10Winner, $teamA) === 0 ? 56 : 44;
            $pB = 100 - $pA;
        }
        $lead = max($pA, $pB);
        $conf = $last5Winner && $absGap >= 3
            ? 'Last 5 toss is a clear edge'
            : ($last5Winner ? 'Last 5 toss lean is the call' : ($lead >= 54 ? 'Slight last-toss lean' : 'Last 5 toss even — wait for load'));
    }

    $favored = $last5Winner ?: null;
    $favP = $favored ? max($pA, $pB) : 50;
    $insights = [];
    $insights[] = $last5Winner
        ? "Strong last-5 toss: {$last5Winner} ({$teamA} {$a5}/{$l5nA} vs {$teamB} {$b5}/{$l5nB}). Career/H2H backup only."
        : "Last-5 toss even/thin — no strong history pick yet ({$teamA} {$a5}/{$l5nA} vs {$teamB} {$b5}/{$l5nB}).";
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
        $norm = normalize_name($name);
        $last5Rows = [];
        foreach ($last5 as $m) {
            if (($m['nW'] ?? '') === '') {
                continue;
            }
            $didWin = toss_won_by($m, $norm);
            $dec = ($m['tossDecision'] ?? '') === 'bat' ? 'bat' : ((($m['tossDecision'] ?? '') === 'field' || ($m['tossDecision'] ?? '') === 'bowl') ? 'bowl' : '');
            $last5Rows[] = [
                'date' => $m['date'] ?? '',
                'vs' => opponent_name($m, $norm),
                'won' => $didWin,
                'decision' => $didWin ? $dec : '',
            ];
            if (count($last5Rows) >= 5) {
                break;
            }
        }
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
            'last5Rows' => $last5Rows,
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
            'last5Winner' => $last5Winner,
            'last5Gap' => $l5Gap,
            'last10Winner' => $last10Winner,
            'model' => 'Last 5 toss first + last 10 backup + load confirm',
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
