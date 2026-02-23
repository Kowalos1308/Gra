<?php

declare(strict_types=1);

const BASE_STAT = 10;
const MAX_LEVEL = 100;

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

function grantXp(PDO $pdo, int $playerId, int $amount): void
{
    if ($amount <= 0) {
        return;
    }

    $stmt = $pdo->prepare('SELECT id, level, xp, money FROM players WHERE id = :id');
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
