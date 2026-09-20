<?php

function featured_domestic_fixtures(): array
{
    $odisha = 'Barabati Stadium, Cuttack';
    $wapl = 'ACA International Cricket Stadium, Mangalagiri';
    return [
        [
            'id' => 'dom_odisha_2026_1',
            'date' => '2026-09-20',
            'teamA' => 'Sambalpur Warriors',
            'teamB' => 'Cuttack Panthers',
            'league' => 'odisha',
            'tournament' => 'Odisha T20 2026 · 1st T20',
            'format' => 'T20',
            'time' => '07:30 PM IST',
            'tossTime' => '07:30 PM IST',
            'venue' => $odisha,
            'status' => 'UPCOMING',
        ],
        [
            'id' => 'dom_odisha_2026_2',
            'date' => '2026-09-21',
            'teamA' => 'Keonjhar Miners',
            'teamB' => 'Bhubaneswar Tigers',
            'league' => 'odisha',
            'tournament' => 'Odisha T20 2026 · 2nd T20',
            'format' => 'T20',
            'time' => '02:30 PM IST',
            'venue' => $odisha,
            'status' => 'UPCOMING',
        ],
        [
            'id' => 'dom_odisha_2026_3',
            'date' => '2026-09-21',
            'teamA' => 'Rourkela Superstars',
            'teamB' => 'Sambalpur Warriors',
            'league' => 'odisha',
            'tournament' => 'Odisha T20 2026 · 3rd T20',
            'format' => 'T20',
            'time' => '07:30 PM IST',
            'venue' => $odisha,
            'status' => 'UPCOMING',
        ],
        [
            'id' => 'dom_wapl_2026_3',
            'date' => '2026-09-20',
            'teamA' => 'Amaravati E Champions',
            'teamB' => 'Rayalaseema XEN Stars',
            'league' => 'wapl',
            'tournament' => 'WAPL 2026 · 3rd T20',
            'format' => 'T20',
            'time' => '01:30 PM IST',
            'venue' => $wapl,
            'status' => 'UPCOMING',
        ],
        [
            'id' => 'dom_wapl_2026_4',
            'date' => '2026-09-20',
            'teamA' => 'Godavari Golden Eagles',
            'teamB' => 'Vizag Fire Birds',
            'league' => 'wapl',
            'tournament' => 'WAPL 2026 · 4th T20',
            'format' => 'T20',
            'time' => '05:30 PM IST',
            'venue' => $wapl,
            'status' => 'UPCOMING',
        ],
        [
            'id' => 'dom_wapl_2026_5',
            'date' => '2026-09-21',
            'teamA' => 'Godavari Golden Eagles',
            'teamB' => 'Amaravati E Champions',
            'league' => 'wapl',
            'tournament' => 'WAPL 2026 · 5th T20',
            'format' => 'T20',
            'time' => '01:30 PM IST',
            'venue' => $wapl,
            'status' => 'UPCOMING',
        ],
    ];
}

function featured_domestic_rows_for_date(string $date): array
{
    $out = [];
    foreach (featured_domestic_fixtures() as $m) {
        if (($m['date'] ?? '') === $date) {
            $out[] = $m;
        }
    }
    return $out;
}

function featured_domestic_history(): array
{
    $v = 'Barabati Stadium, Cuttack';
    return [
        ['date' => '2025-09-26', 'teamA' => 'Sambalpur Warriors', 'teamB' => 'Cuttack Panthers', 'tossWinner' => 'Sambalpur Warriors', 'tossDecision' => 'bowl', 'venue' => $v, 'league' => 'odisha', 'format' => 'T20'],
        ['date' => '2025-09-22', 'teamA' => 'Sambalpur Warriors', 'teamB' => 'Cuttack Panthers', 'tossWinner' => 'Sambalpur Warriors', 'tossDecision' => 'bowl', 'venue' => $v, 'league' => 'odisha', 'format' => 'T20'],
    ];
}
