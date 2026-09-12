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
            'tossLoadA' => $tA['tossLoad'],
            'tossLoadB' => $tB['tossLoad'],
            'insights' => $analysis['prediction']['insights'],
            'locked' => $mins <= 30,
        ],
        'form' => [
            'aLast5' => $tA['last5Wins'] . '/' . $tA['last5Total'],
            'bLast5' => $tB['last5Wins'] . '/' . $tB['last5Total'],
            'aLast5Pct' => $tA['last5Pct'],
            'bLast5Pct' => $tB['last5Pct'],
            'aStreak' => $tA['streak']['text'],
            'bStreak' => $tB['streak']['text'],
            'h2h' => $analysis['headToHead'],
        ],
        'venueStats' => $analysis['venue'],
        'analysis' => $analysis,
    ];
}

function collect_day(string $date, string $league = 'all'): array
{
    $store = load_store();
    $deleted = array_map('strtolower', $store['overrides']['deleted'] ?? []);
    $rows = [];

    foreach (($store['fixtures'][$date] ?? []) as $m) {
        $rows[] = $m;
    }
    foreach (($store['custom'][$date] ?? []) as $m) {
        $m['custom'] = true;
        $m['league'] = $m['league'] ?: 'custom';
        $rows[] = $m;
    }

    $live = [];
    if ($date === ist_today()) {
        try {
            $live = fetch_live_feed();
        } catch (Throwable $e) {
            $live = [];
        }
    }

    $out = [];
    foreach ($rows as $m) {
        $k1 = match_key($m['teamA'] ?? '', $m['teamB'] ?? '', $date);
        $k2 = match_key($m['teamB'] ?? '', $m['teamA'] ?? '', $date);
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
        if ($league !== 'all' && ($m['league'] ?? '') !== $league) {
            if (!($league === 'custom' && !empty($m['custom']))) {
                continue;
            }
        }
        $out[] = enrich_match($m, $date);
    }

    usort($out, function ($a, $b) {
        $doneA = !empty($a['tossWinner']) ? 1 : 0;
        $doneB = !empty($b['tossWinner']) ? 1 : 0;
        if ($doneA !== $doneB) {
            return $doneA <=> $doneB;
        }
        $timeDiff = parse_ist_minutes($a['time'] ?? '') <=> parse_ist_minutes($b['time'] ?? '');
        if ($timeDiff !== 0) {
            return $timeDiff;
        }
        return strcasecmp($a['teamA'] ?? '', $b['teamA'] ?? '');
    });

    return $out;
}

function calendar_days(int $span = 10): array
{
    $store = load_store();
    $today = ist_now();
    $days = [];
    for ($i = -2; $i <= $span; $i++) {
        $d = $today->modify(($i >= 0 ? '+' : '') . $i . ' days')->format('Y-m-d');
        $count = count($store['fixtures'][$d] ?? []) + count($store['custom'][$d] ?? []);
        $label = 'Day';
        if ($i === 0) {
            $label = 'Today';
        } elseif ($i === 1) {
            $label = 'Tomorrow';
        } elseif ($i === -1) {
            $label = 'Yesterday';
        }
        $days[] = [
            'date' => $d,
            'label' => $label,
            'weekday' => (new DateTimeImmutable($d))->format('D'),
            'pretty' => (new DateTimeImmutable($d))->format('d M'),
            'count' => $count,
            'offset' => $i,
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
                'calendar' => calendar_days(12),
            ]);
            break;

        case 'matches':
            $date = normalize_date($_GET['date'] ?? ist_today());
            $league = $_GET['league'] ?? 'all';
            $matches = collect_day($date, $league);
            try {
                $tgFeed = fetch_telegram_bets();
                $loadMap = [];
                foreach (build_punter_load($tgFeed['posts'] ?? [], $matches)['matches'] as $row) {
                    $loadMap[$row['id']] = $row;
                }
                foreach ($matches as &$mm) {
                    if (isset($loadMap[$mm['id']])) {
                        $mm['punterLoad'] = $loadMap[$mm['id']];
                    }
                }
                unset($mm);
            } catch (Throwable $e) {
            }
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
                'tossWinner' => $winner ?: null,
                'tossDecision' => $body['tossDecision'] ?? 'bowl',
                'matchWinner' => $body['matchWinner'] ?? $winner,
                'status' => $winner ? 'COMPLETED' : 'UPCOMING',
            ];
            $ovr['toss'][strtolower(match_key($teamA, $teamB, $date))] = $payload;
            $ovr['toss'][strtolower(match_key($teamB, $teamA, $date))] = $payload;
            save_overrides($ovr);
            json_ok(['ok' => true, 'data' => $payload]);
            break;

        default:
            json_ok(['error' => 'Unknown action'], 404);
    }
} catch (Throwable $e) {
    json_ok(['error' => $e->getMessage()], 500);
}
