<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/live.php';

function tg_config_path(): string
{
    return data_path('tg_config.json');
}

function tg_bets_path(): string
{
    return data_path('tg_bets.json');
}

function default_tg_config(): array
{
    return [
        'channel' => 'BetfairTossbookOrignal',
        'webUrl' => 'https://web.telegram.org/k/#@BetfairTossbookOrignal',
        'tmeUrl' => 'https://t.me/s/BetfairTossbookOrignal',
        'targetUsers' => ['Rahul Dada'],
        'hideOthers' => true,
        'sound' => 'bell',
    ];
}

function load_tg_config(): array
{
    $saved = read_json(tg_config_path(), []);
    return array_merge(default_tg_config(), is_array($saved) ? $saved : []);
}

function save_tg_config(array $cfg): array
{
    $base = load_tg_config();
    if (isset($cfg['targetUsers'])) {
        $users = is_array($cfg['targetUsers']) ? $cfg['targetUsers'] : preg_split('/,/', (string) $cfg['targetUsers']);
        $base['targetUsers'] = array_values(array_filter(array_map('trim', $users)));
        if (!$base['targetUsers']) {
            $base['targetUsers'] = ['Rahul Dada'];
        }
    }
    if (isset($cfg['hideOthers'])) {
        $base['hideOthers'] = (bool) $cfg['hideOthers'];
    }
    if (isset($cfg['sound'])) {
        $base['sound'] = (string) $cfg['sound'];
    }
    write_json(tg_config_path(), $base);
    return $base;
}

function parse_amount_number(?string $raw): float
{
    if (!$raw) {
        return 0.0;
    }
    $n = preg_replace('/[^0-9.]/', '', $raw);
    return $n === '' ? 0.0 : (float) $n;
}

function format_inr(float $n): string
{
    return '₹' . number_format($n, 0, '.', ',');
}

function parse_tg_html(string $html): array
{
    $blocks = preg_split('/<div class="tgme_widget_message_wrap/', $html);
    $posts = [];
    for ($i = 1, $len = count($blocks); $i < $len; $i++) {
        $block = $blocks[$i];
        if (!preg_match('/data-post="([^"]+)"/', $block, $pm)) {
            continue;
        }
        $postId = $pm[1];
        $iso = date('c');
        if (preg_match('/<time[^>]*datetime="([^"]+)"/', $block, $tm)) {
            $iso = $tm[1];
        }
        $display = $iso;
        try {
            $d = new DateTimeImmutable($iso);
            $display = $d->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('h:i:s A');
        } catch (Throwable $e) {
        }

        $rawText = '';
        if (preg_match('/<div class="tgme_widget_message_text[^"]*"[^>]*>([\s\S]*?)<\/div>/', $block, $tx)) {
            $rawText = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $tx[1])), ENT_QUOTES | ENT_HTML5));
        }
        if ($rawText === '') {
            continue;
        }

        $userName = null;
        $teamName = null;
        $amount = null;
        $action = null;
        $type = 'ANNOUNCEMENT';

        if (preg_match('/USER\s*NAME\s*[-:]\s*([^\n\r]+)/i', $rawText, $m)) {
            $userName = trim($m[1]);
        }
        if (preg_match('/TEAM\s*NAME\s*[-:]\s*([^\n\r]+)/i', $rawText, $m)) {
            $teamName = trim($m[1]);
            $type = 'BET_PLACED';
        }
        if (preg_match('/AMOUNT\s*[-:]\s*([^\n\r]+)/i', $rawText, $m)) {
            $amount = trim($m[1]);
        }
        if (preg_match('/DEPOSIT\/WITHDRAWAL\s*[-:]\s*([^\n\r]+)/i', $rawText, $m)) {
            $action = trim($m[1]);
            $type = 'DEPOSIT_WITHDRAWAL';
        }
        if (!$teamName && $userName && preg_match('/TOSS|WINNER|BET/i', $rawText)) {
            $type = 'BET_PLACED';
        }
        if (preg_match('/UPCOMING MATCHES/i', $rawText)) {
            $type = 'SCHEDULE';
        }

        $posts[] = [
            'postId' => $postId,
            'isoTime' => $iso,
            'displayTime' => $display,
            'rawText' => $rawText,
            'userName' => $userName ?: ($type === 'ANNOUNCEMENT' ? 'System / Admin' : 'Anonymous'),
            'teamName' => $teamName,
            'amount' => $amount,
            'amountValue' => parse_amount_number($amount),
            'action' => $action ?: ($type === 'BET_PLACED' ? 'BET_PLACED' : 'UPDATE'),
            'type' => $type,
            'channel' => '@BetfairTossbookOrignal',
            'messageUrl' => 'https://t.me/' . $postId,
            'webTelegramUrl' => 'https://web.telegram.org/k/#@BetfairTossbookOrignal',
        ];
    }
    return $posts;
}

function load_stored_bets(): array
{
    $data = read_json(tg_bets_path(), ['posts' => []]);
    return is_array($data['posts'] ?? null) ? $data['posts'] : [];
}

function save_stored_bets(array $posts): void
{
    $posts = array_slice($posts, 0, 400);
    write_json(tg_bets_path(), ['posts' => $posts, 'updatedAt' => date('c')]);
}

function fetch_telegram_bets(bool $force = false): array
{
    if (!$force) {
        $cached = cache_get('tg_feed', 30);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $stored = load_stored_bets();
    $known = [];
    foreach ($stored as $p) {
        if (!empty($p['postId'])) {
            $known[$p['postId']] = true;
        }
    }

    $html = http_get('https://t.me/s/BetfairTossbookOrignal', 5);
    $fresh = $html ? parse_tg_html($html) : [];
    foreach ($fresh as $p) {
        if (empty($known[$p['postId']])) {
            array_unshift($stored, $p);
            $known[$p['postId']] = true;
        }
    }
    usort($stored, fn($a, $b) => strcmp($b['isoTime'] ?? '', $a['isoTime'] ?? ''));
    save_stored_bets($stored);

    $pack = [
        'status' => $html ? 'connected' : ($stored ? 'cached' : 'error'),
        'error' => $html ? null : 'Telegram preview not reachable right now',
        'fetchedAt' => ist_now()->format('h:i:s A') . ' IST',
        'posts' => $stored,
    ];
    cache_set('tg_feed', $pack);
    return $pack;
}

function tg_norm_name(string $s): string
{
    return preg_replace('/[^a-z0-9]/', '', strtolower($s)) ?? '';
}

function user_is_target(string $userName, array $targets, string $rawText = ''): bool
{
    $u = tg_norm_name($userName);
    $hay = tg_norm_name($userName . ' ' . $rawText);
    foreach ($targets as $t) {
        $n = tg_norm_name((string) $t);
        if ($n === '') {
            continue;
        }
        if ($u === $n || ($n !== 'rahul' && (str_contains($u, $n) || (str_contains($n, $u) && strlen($u) >= 5)))) {
            return true;
        }
        if (str_contains($hay, $n)) {
            return true;
        }
    }
    return false;
}

function team_aliases(): array
{
    return [
        'mohali' => ['mohalikings', 'mohali'],
        'mohalikings' => ['mohali'],
        'amritsar' => ['amritsarsoormas', 'punjab'],
        'amritsarsoormas' => ['amritsar', 'punjab'],
        'punjab' => ['amritsarsoormas', 'amritsar'],
        'ludhiana' => ['ludhianalions'],
        'ludhianalions' => ['ludhiana'],
        'jalandhar' => ['jalandharwarriors'],
        'jalandharwarriors' => ['jalandhar'],
        'kenya' => ['kenya'],
        'uganda' => ['uganda'],
        'africaa' => ['southafricaa', 'southafrica'],
        'southafricaa' => ['africaa', 'southafrica'],
        'oman' => ['oman'],
        'hongkong' => ['hongkongchina', 'hongkong'],
        'hongkongchina' => ['hongkong'],
        'nepal' => ['nepal'],
        'uae' => ['unitedarabemirates', 'uae'],
        'unitedarabemirates' => ['uae'],
        'belfast' => ['belfastwolves'],
        'belfastwolves' => ['belfast'],
        'rotterdam' => ['rotterdamdockers'],
        'rotterdamdockers' => ['rotterdam'],
        'dublin' => ['dublinguardians'],
        'dublinguardians' => ['dublin'],
        'glasgow' => ['glasgowcosmic'],
        'glasgowcosmic' => ['glasgow'],
        'tkrw' => ['trinbagoknightriderswomen'],
        'trinbagow' => ['trinbagoknightriderswomen'],
        'trinbagoknightridersw' => ['trinbagoknightriderswomen'],
        'gaww' => ['guyanaamazonwarriorswomen'],
        'guyanaw' => ['guyanaamazonwarriorswomen'],
        'pakistan' => ['pakistan'],
        'england' => ['england'],
        'antigua' => ['antiguaandbarbudafalcons', 'falcons'],
        'falcons' => ['antiguaandbarbudafalcons'],
        'jamaica' => ['jamaicakingsmen', 'kingsmen'],
        'kingsmen' => ['jamaicakingsmen'],
        'sharjha' => ['sharjah', 'sharjahwarriors'],
        'sharjah' => ['sharjahwarriors', 'sharjha'],
        'sharjahwarriors' => ['sharjah', 'sharjha'],
        'miemirates' => ['emirates'],
        'desertvipers' => ['vipers'],
        'gulfgiants' => ['giants', 'gulf'],
        'abudhabiknightriders' => ['knightriders', 'adkr'],
        'dubaicapitals' => ['capitals'],
        'sambalpur' => ['sambalpurwarriors'],
        'sambalpurwarriors' => ['sambalpur'],
        'kataka' => ['katakapanthers', 'cuttackpanthers', 'cuttack'],
        'katakapanthers' => ['kataka', 'cuttackpanthers', 'cuttack'],
        'cuttack' => ['cuttackpanthers', 'kataka', 'katakapanthers'],
        'cuttackpanthers' => ['cuttack', 'kataka', 'katakapanthers'],
        'keonjhar' => ['keonjharminers'],
        'keonjharminers' => ['keonjhar'],
        'bhubaneswar' => ['bhubaneswartigers'],
        'bhubaneswartigers' => ['bhubaneswar'],
        'rourkela' => ['rourkelasuperstars'],
        'rourkelasuperstars' => ['rourkela'],
        'puri' => ['purititans'],
        'purititans' => ['puri'],
        'godavari' => ['godavarigoldeneagles', 'godavarieagles'],
        'godavarigoldeneagles' => ['godavari'],
        'vizag' => ['vizagfirebirds', 'firebirds'],
        'vizagfirebirds' => ['vizag', 'firebirds'],
        'amaravati' => ['amaravatiechampions', 'amravati'],
        'amravati' => ['amaravati', 'amaravatiechampions'],
        'amaravatiechampions' => ['amaravati', 'amravati'],
        'rayalsema' => ['rayalaseema', 'rayalaseemaxenstars'],
        'rayalaseema' => ['rayalsema', 'rayalaseemaxenstars'],
        'rayalaseemaxenstars' => ['rayalaseema', 'rayalsema'],
        'cayman' => ['caymanislands'],
        'caymanislands' => ['cayman'],
        'bermuda' => ['bermuda'],
        'nigeria' => ['nigeria'],
        'ghana' => ['ghana'],
        'indiau19' => ['indiaunder19s', 'indiaunder19'],
        'indiaunder19s' => ['indiau19', 'indiaunder19'],
        'australiau19' => ['australiaunder19s'],
        'leicestershire' => ['leics'],
        'middlesex' => ['middx'],
        'tasmania' => ['tasmania'],
        'zimbabwe' => ['zimbabwe'],
    ];
}

function team_age_gender_tags(string $raw): array
{
    $s = strtolower($raw);
    $n = normalize_name($raw);
    $tags = [];
    if (str_contains($n, 'u19') || str_contains($n, 'under19') || preg_match('/\bu19\b|\bunder[- ]?19\b/', $s)) {
        $tags[] = 'u19';
    }
    if (str_contains($n, 'women') || preg_match('/\bwomen\b|\bwomens\b|\bw\b/', $s)) {
        $tags[] = 'women';
    }
    return $tags;
}

function team_hits_name(string $betTeam, string $fixtureTeam): bool
{
    $a = normalize_name($betTeam);
    $b = normalize_name($fixtureTeam);
    if ($a === '' || $b === '') {
        return false;
    }
    if (team_age_gender_tags($betTeam) !== team_age_gender_tags($fixtureTeam)) {
        return false;
    }
    if (function_exists('history_side_hit')) {
        if (history_side_hit($a, $b) || history_side_hit($b, $a)) {
            return true;
        }
    } elseif ($a === $b) {
        return true;
    }
    $aliases = team_aliases();
    foreach ($aliases[$a] ?? [] as $alias) {
        $alias = normalize_name($alias);
        if ($alias !== '' && (history_side_hit($alias, $b) || history_side_hit($b, $alias))) {
            return true;
        }
    }
    foreach ($aliases[$b] ?? [] as $alias) {
        $alias = normalize_name($alias);
        if ($alias !== '' && (history_side_hit($alias, $a) || history_side_hit($a, $alias))) {
            return true;
        }
    }
    return false;
}

function tg_match_is_settled(array $row): bool
{
    if (!empty($row['tossWinner']) || !empty($row['matchWinner'])) {
        return true;
    }
    $status = strtoupper((string) ($row['status'] ?? ''));
    if (in_array($status, ['COMPLETED', 'LIVE'], true)) {
        return true;
    }
    return ($row['phase'] ?? '') === 'done';
}

function post_ist_date(array $p): ?string
{
    try {
        return (new DateTimeImmutable($p['isoTime'] ?? 'now'))
            ->setTimezone(new DateTimeZone('Asia/Kolkata'))
            ->format('Y-m-d');
    } catch (Throwable $e) {
        return null;
    }
}

function build_punter_load(array $posts, array $matches = [], ?string $date = null): array
{
    $bets = array_values(array_filter($posts, function ($p) use ($date) {
        if (($p['type'] ?? '') !== 'BET_PLACED' || empty($p['teamName'])) {
            return false;
        }
        if (!$date) {
            return true;
        }
        $ist = post_ist_date($p);
        return $ist === null || $ist === $date;
    }));
    $byTeam = [];
    foreach ($bets as $p) {
        $key = strtoupper(trim($p['teamName']));
        if (!isset($byTeam[$key])) {
            $byTeam[$key] = [
                'team' => $p['teamName'],
                'total' => 0.0,
                'bets' => 0,
                'users' => [],
            ];
        }
        $byTeam[$key]['total'] += (float) ($p['amountValue'] ?? parse_amount_number($p['amount'] ?? ''));
        $byTeam[$key]['bets']++;
        $u = $p['userName'] ?? 'Unknown';
        $byTeam[$key]['users'][$u] = ($byTeam[$key]['users'][$u] ?? 0) + 1;
    }
    $teams = array_values($byTeam);
    usort($teams, fn($a, $b) => $b['total'] <=> $a['total']);
    foreach ($teams as &$t) {
        $t['totalLabel'] = format_inr($t['total']);
        $t['users'] = array_keys($t['users']);
    }
    unset($t);

    $matchLoads = [];
    foreach ($matches as $m) {
        $teamA = $m['teamA'] ?? '';
        $teamB = $m['teamB'] ?? '';
        $sumA = 0.0;
        $sumB = 0.0;
        $nA = 0;
        $nB = 0;
        foreach ($bets as $p) {
            if (team_hits_name($p['teamName'], $teamA)) {
                $sumA += (float) ($p['amountValue'] ?? 0);
                $nA++;
            } elseif (team_hits_name($p['teamName'], $teamB)) {
                $sumB += (float) ($p['amountValue'] ?? 0);
                $nB++;
            }
        }
        $total = $sumA + $sumB;
        $leader = $sumA === $sumB ? null : ($sumA > $sumB ? $teamA : $teamB);
        $pctA = $total > 0 ? (int) round($sumA / $total * 100) : 50;
        $pctB = 100 - $pctA;
        $matchLoads[] = [
            'id' => $m['id'] ?? '',
            'teamA' => $teamA,
            'teamB' => $teamB,
            'tournament' => $m['tournament'] ?? '',
            'time' => $m['time'] ?? '',
            'status' => $m['status'] ?? '',
            'phase' => $m['phase'] ?? '',
            'matchWinner' => $m['matchWinner'] ?? null,
            'tossWinner' => $m['tossWinner'] ?? null,
            'amountA' => $sumA,
            'amountB' => $sumB,
            'betsA' => $nA,
            'betsB' => $nB,
            'amountALabel' => format_inr($sumA),
            'amountBLabel' => format_inr($sumB),
            'leader' => $leader,
            'leaderPct' => $total > 0 ? (int) round(max($sumA, $sumB) / $total * 100) : 0,
            'pctA' => $pctA,
            'pctB' => $pctB,
            'totalLabel' => format_inr($total),
        ];
    }
    usort($matchLoads, function ($a, $b) {
        $doneA = tg_match_is_settled($a) ? 1 : 0;
        $doneB = tg_match_is_settled($b) ? 1 : 0;
        if ($doneA !== $doneB) {
            return $doneA <=> $doneB;
        }
        return ($b['amountA'] + $b['amountB']) <=> ($a['amountA'] + $a['amountB']);
    });

    return [
        'teams' => array_slice($teams, 0, 30),
        'matches' => $matchLoads,
    ];
}

function telegram_payload(array $options = [], array $matches = []): array
{
    $cfg = load_tg_config();
    $feed = fetch_telegram_bets(!empty($options['force']));
    $posts = $feed['posts'] ?? [];
    $type = $options['type'] ?? 'bets_only';
    if ($type === 'bets_only') {
        $posts = array_values(array_filter($posts, fn($p) => ($p['type'] ?? '') === 'BET_PLACED'));
    }
    $hide = array_key_exists('hideOthers', $options) ? (bool) $options['hideOthers'] : (bool) $cfg['hideOthers'];
    $targets = $cfg['targetUsers'];
    $filtered = [];
    $watched = [];
    foreach ($posts as $p) {
        if (user_is_target($p['userName'] ?? '', $targets, $p['rawText'] ?? '')) {
            $p['watched'] = true;
            $watched[] = $p;
        } else {
            $p['watched'] = false;
        }
        $filtered[] = $p;
    }
    if ($hide && $targets) {
        $filtered = $watched;
    }

    $load = build_punter_load($feed['posts'] ?? [], $matches, ist_today());

    return [
        'ok' => true,
        'status' => $feed['status'],
        'error' => $feed['error'] ?? null,
        'fetchedAt' => $feed['fetchedAt'],
        'channel' => '@BetfairTossbookOrignal',
        'webUrl' => $cfg['webUrl'],
        'tmeUrl' => $cfg['tmeUrl'],
        'config' => $cfg,
        'count' => count($filtered),
        'watchedCount' => count($watched),
        'bets' => array_slice($filtered, 0, (int) ($options['limit'] ?? 80)),
        'watched' => array_slice($watched, 0, 40),
        'punterLoad' => $load,
    ];
}
