<?php

declare(strict_types=1);

const BASE_STAT = 10;
const MAX_LEVEL = 100;
const MAX_FATIGUE = 6;
const FATIGUE_RECOVERY_MINUTES = 60;

function statKeys(): array
{
    return [
        'aim',
        'refleks',
        'mobilnosc',
        'kontrola_odrzutu',
        'granaty',
        'szczescie',
    ];
}

function statDescriptions(): array
{
    return [
        'aim' => 'Wpływa na celność i headshoty.',
        'refleks' => 'Wpływa na szybkość reakcji i flicki.',
        'mobilnosc' => 'Wpływa na prędkość ruchu i strafe.',
        'kontrola_odrzutu' => 'Wpływa na stabilność spraya.',
        'granaty' => 'Wpływa na celność i skuteczność utility.',
        'szczescie' => 'Wpływa na dropy i nagrody.',
    ];
}

function rankConfig(): array
{
    return [
        'silver' => [
            'name' => 'Silver',
            'opp_base' => 48,
            'maps' => ['cs_office', 'de_vertigo', 'cs_italy', 'de_train', 'de_nuke', 'cs_agency', 'de_dust2', 'de_ancient', 'de_mirage', 'de_overpass'],
            'xp' => ['win' => [80, 150], 'draw' => [30, 70]],
        ],
        'gold' => [
            'name' => 'Gold',
            'opp_base' => 78,
            'maps' => ['de_inferno', 'de_anubis', 'de_dust2', 'de_mirage', 'de_nuke', 'de_overpass', 'de_vertigo', 'de_ancient', 'de_train', 'cs_agency'],
            'xp' => ['win' => [280, 480], 'draw' => [110, 220]],
        ],
        'kalach' => [
            'name' => 'Kałach',
            'opp_base' => 115,
            'maps' => ['de_inferno', 'de_anubis', 'de_dust2', 'de_mirage', 'de_nuke', 'de_overpass', 'de_vertigo', 'cs_office', 'cs_italy', 'de_cbble'],
            'xp' => ['win' => [750, 1250], 'draw' => [300, 550]],
        ],
        'supreme' => [
            'name' => 'Supreme',
            'opp_base' => 165,
            'maps' => ['de_inferno', 'de_anubis', 'de_dust2', 'de_mirage', 'de_nuke', 'de_overpass', 'de_vertigo', 'de_ancient', 'de_train', 'de_shortdust'],
            'xp' => ['win' => [1900, 3200], 'draw' => [750, 1400]],
        ],
        'global' => [
            'name' => 'Global',
            'opp_base' => 235,
            'maps' => ['de_inferno (pro)', 'de_anubis (pro)', 'de_dust2 (pro)', 'de_mirage (pro)', 'de_nuke (pro)', 'de_overpass (pro)', 'de_vertigo (pro)', 'de_ancient (pro)', 'de_train (pro)', 'cs_agency (pro)'],
            'xp' => ['win' => [6200, 10500], 'draw' => [2600, 4800]],
        ],
    ];
}

function rankOrder(): array
{
    return array_keys(rankConfig());
}

function rankName(string $rank): string
{
    return rankConfig()[$rank]['name'] ?? 'Silver';
}

function rankMaps(string $rank): array
{
    return rankConfig()[$rank]['maps'] ?? rankConfig()['silver']['maps'];
}

function xpRequiredForLevel(int $level): int
{
    if ($level <= 1) {
        return 100;
    }

    return (int) round(100 * pow($level, 1.8));
}

function levelProgressPercent(int $level, int $xp): float
{
    $required = xpRequiredForLevel($level);

    if ($required <= 0) {
        return 100;
    }

    return min(100, max(0, ($xp / $required) * 100));
}

function automaticBonusByLevel(int $level): int
{
    return intdiv(max(0, $level - 1), 10);
}

function totalAssignedStats(array $player): int
{
    $total = 0;

    foreach (statKeys() as $key) {
        $total += (int) ($player[$key] ?? BASE_STAT);
    }

    return $total;
}

function spentDevelopmentPoints(array $player): int
{
    $baseSum = count(statKeys()) * BASE_STAT;
    $autoBonus = automaticBonusByLevel((int) $player['level']) * count(statKeys());

    return totalAssignedStats($player) - $baseSum - $autoBonus;
}

function availableDevelopmentPoints(array $player): int
{
    $earned = max(0, ((int) $player['level']) - 1);
    $spent = max(0, spentDevelopmentPoints($player));

    return max(0, $earned - $spent);
}

function refreshFatigue(PDO $pdo, int $playerId): void
{
    $stmt = $pdo->prepare('SELECT fatigue, fatigue_updated_at FROM players WHERE id = :id');
    $stmt->execute(['id' => $playerId]);
    $player = $stmt->fetch();

    if (!$player) {
        return;
    }

    $fatigue = (int) $player['fatigue'];
    if ($fatigue <= 0) {
        return;
    }

    $lastUpdate = strtotime((string) $player['fatigue_updated_at']);
    if ($lastUpdate === false) {
        return;
    }

    $elapsed = time() - $lastUpdate;
    $recoverPoints = intdiv(max(0, $elapsed), FATIGUE_RECOVERY_MINUTES * 60);

    if ($recoverPoints <= 0) {
        return;
    }

    $newFatigue = max(0, $fatigue - $recoverPoints);
    $newTime = $lastUpdate + ($recoverPoints * FATIGUE_RECOVERY_MINUTES * 60);

    $update = $pdo->prepare('UPDATE players SET fatigue = :fatigue, fatigue_updated_at = :updated_at WHERE id = :id');
    $update->execute([
        'fatigue' => $newFatigue,
        'updated_at' => date('Y-m-d H:i:s', $newTime),
        'id' => $playerId,
    ]);
}

function fatigueMinutesToRecoverOne(array $player): int
{
    $fatigue = (int) ($player['fatigue'] ?? 0);
    if ($fatigue <= 0) {
        return 0;
    }

    $lastUpdate = strtotime((string) ($player['fatigue_updated_at'] ?? ''));
    if ($lastUpdate === false) {
        return FATIGUE_RECOVERY_MINUTES;
    }

    $nextRecoverAt = $lastUpdate + (FATIGUE_RECOVERY_MINUTES * 60);
    $remaining = max(0, $nextRecoverAt - time());

    return (int) ceil($remaining / 60);
}

function playerPower(array $player): int
{
    return (int) $player['aim'] + (int) $player['refleks'] + (int) $player['mobilnosc'] + (int) $player['kontrola_odrzutu'] + (int) $player['granaty'];
}

function chooseRandomMap(string $rank): string
{
    $maps = rankMaps($rank);
    return $maps[array_rand($maps)];
}

function simulateClanMatch(array $player): array
{
    $rank = (string) $player['rank_tier'];
    $config = rankConfig()[$rank] ?? rankConfig()['silver'];

    $pPower = playerPower($player);
    $oppPower = $config['opp_base'] + random_int(-12, 18);
    $roundWinProb = 0.5 + (($pPower - $oppPower) * 0.004);
    $roundWinProb = max(0.23, min(0.77, $roundWinProb));

    $playerScore = 0;
    $oppScore = 0;
    $rounds = [];

    for ($round = 1; $round <= 24; $round++) {
        $playerWonRound = (mt_rand() / mt_getrandmax()) <= $roundWinProb;

        if ($playerWonRound) {
            $playerScore++;
        } else {
            $oppScore++;
        }

        $rounds[] = [
            'round' => $round,
            'player_score' => $playerScore,
            'opp_score' => $oppScore,
            'winner' => $playerWonRound ? 'player' : 'opponent',
            'swap' => $round === 12,
        ];

        if ($playerScore >= 13 || $oppScore >= 13) {
            break;
        }
    }

    if ($playerScore === 12 && $oppScore === 12) {
        $result = 'draw';
    } elseif ($playerScore >= 13) {
        $result = 'win';
    } else {
        $result = 'loss';
    }

    $xp = 0;
    if ($result === 'win') {
        $xp = random_int($config['xp']['win'][0], $config['xp']['win'][1]);
    } elseif ($result === 'draw') {
        $xp = random_int($config['xp']['draw'][0], $config['xp']['draw'][1]);
    }

    return [
        'map' => chooseRandomMap($rank),
        'rank' => $rank,
        'player_power' => $pPower,
        'opp_power' => $oppPower,
        'round_win_prob' => $roundWinProb,
        'player_score' => $playerScore,
        'opp_score' => $oppScore,
        'result' => $result,
        'xp' => $xp,
        'rounds' => $rounds,
    ];
}

function grantXp(PDO $pdo, int $playerId, int $amount): void
{
    if ($amount <= 0) {
        return;
    }

    $stmt = $pdo->prepare('SELECT id, level, xp FROM players WHERE id = :id');
    $stmt->execute(['id' => $playerId]);
    $player = $stmt->fetch();

    if (!$player) {
        return;
    }

    $level = (int) $player['level'];
    $xp = (int) $player['xp'] + $amount;

    while ($level < MAX_LEVEL) {
        $needed = xpRequiredForLevel($level);

        if ($xp < $needed) {
            break;
        }

        $xp -= $needed;
        $level++;
    }

    $update = $pdo->prepare('UPDATE players SET level = :level, xp = :xp WHERE id = :id');
    $update->execute([
        'level' => $level,
        'xp' => $xp,
        'id' => $playerId,
    ]);
}

function getMapProgress(PDO $pdo, int $playerId, string $rank): array
{
    $maps = rankMaps($rank);
    $stmt = $pdo->prepare('SELECT map_name FROM player_map_progress WHERE player_id = :player_id AND rank_tier = :rank_tier');
    $stmt->execute([
        'player_id' => $playerId,
        'rank_tier' => $rank,
    ]);

    $wonMaps = [];
    foreach ($stmt->fetchAll() as $row) {
        $wonMaps[$row['map_name']] = true;
    }

    $progress = [];
    foreach ($maps as $map) {
        $progress[] = [
            'map' => $map,
            'won' => isset($wonMaps[$map]),
        ];
    }

    return $progress;
}

function applyMatchResult(PDO $pdo, array $player, array $match): array
{
    $messages = [];
    $rank = (string) $player['rank_tier'];

    $updateFatigue = $pdo->prepare('UPDATE players SET fatigue = LEAST(:max_fatigue, fatigue + 1), fatigue_updated_at = NOW() WHERE id = :id');
    $updateFatigue->execute([
        'max_fatigue' => MAX_FATIGUE,
        'id' => $player['id'],
    ]);

    if ($match['xp'] > 0) {
        grantXp($pdo, (int) $player['id'], (int) $match['xp']);
    }

    if ($match['result'] === 'win') {
        $insert = $pdo->prepare('INSERT IGNORE INTO player_map_progress (player_id, rank_tier, map_name) VALUES (:player_id, :rank_tier, :map_name)');
        $insert->execute([
            'player_id' => $player['id'],
            'rank_tier' => $rank,
            'map_name' => $match['map'],
        ]);

        $countStmt = $pdo->prepare('SELECT COUNT(*) AS won_count FROM player_map_progress WHERE player_id = :player_id AND rank_tier = :rank_tier');
        $countStmt->execute([
            'player_id' => $player['id'],
            'rank_tier' => $rank,
        ]);
        $wonCount = (int) ($countStmt->fetch()['won_count'] ?? 0);

        if ($wonCount >= 10) {
            $order = rankOrder();
            $index = array_search($rank, $order, true);
            if ($index !== false && $index < count($order) - 1) {
                $nextRank = $order[$index + 1];

                $promote = $pdo->prepare('UPDATE players SET rank_tier = :rank WHERE id = :id');
                $promote->execute([
                    'rank' => $nextRank,
                    'id' => $player['id'],
                ]);

                $clear = $pdo->prepare('DELETE FROM player_map_progress WHERE player_id = :player_id AND rank_tier = :rank_tier');
                $clear->execute([
                    'player_id' => $player['id'],
                    'rank_tier' => $nextRank,
                ]);

                $messages[] = 'Ranga odblokowana! Awans na ' . rankName($nextRank) . '.';
            } else {
                $messages[] = 'Wszystkie mapy rangi Global podbite!';
            }
        }
    }

    return $messages;
}
