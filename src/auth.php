<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/game.php';

function startSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function currentPlayer(): ?array
{
    startSession();

    if (empty($_SESSION['player_id'])) {
        return null;
    }

    $pdo = db();
    refreshFatigue($pdo, (int) $_SESSION['player_id']);

    $stmt = $pdo->prepare('SELECT * FROM players WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['player_id']]);

    $player = $stmt->fetch();

    return $player ?: null;
}

function login(string $nick, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM players WHERE nick = :nick');
    $stmt->execute(['nick' => $nick]);
    $player = $stmt->fetch();

    if (!$player || !password_verify($password, $player['password_hash'])) {
        return false;
    }

    startSession();
    $_SESSION['player_id'] = (int) $player['id'];

    return true;
}

function registerPlayer(string $nick, string $password): array
{
    $pdo = db();

    $check = $pdo->prepare('SELECT id FROM players WHERE nick = :nick');
    $check->execute(['nick' => $nick]);
    if ($check->fetch()) {
        return [false, 'Nick jest już zajęty.'];
    }

    $insert = $pdo->prepare(
        'INSERT INTO players (nick, password_hash, level, xp, money, aim, refleks, mobilnosc, kontrola_odrzutu, granaty, szczescie)
         VALUES (:nick, :password_hash, 1, 0, 0, 10, 10, 10, 10, 10, 10)'
    );

    $insert->execute([
        'nick' => $nick,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    return [true, 'Konto utworzone. Możesz się zalogować.'];
}

function logout(): void
{
    startSession();
    $_SESSION = [];
    session_destroy();
}
