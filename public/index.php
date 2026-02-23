<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

$config = require __DIR__ . '/../src/config.php';
startSession();

$message = null;
$error = null;

if (isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'register':
                $nick = trim((string) ($_POST['nick'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                if (mb_strlen($nick) < 3 || mb_strlen($nick) > 24) {
                    $error = 'Nick musi mieć 3-24 znaki.';
                    break;
                }
                if (mb_strlen($password) < 6) {
                    $error = 'Hasło musi mieć minimum 6 znaków.';
                    break;
                }
                [$ok, $msg] = registerPlayer($nick, $password);
                if ($ok) {
                    $message = $msg;
                } else {
                    $error = $msg;
                }
                break;

            case 'login':
                $nick = trim((string) ($_POST['nick'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                if (!login($nick, $password)) {
                    $error = 'Niepoprawny nick lub hasło.';
                } else {
                    $message = 'Zalogowano!';
                }
                break;

            case 'logout':
                logout();
                $message = 'Wylogowano.';
                break;

            case 'add_stat_point':
                $player = currentPlayer();
                if (!$player) {
                    $error = 'Musisz się zalogować.';
                    break;
                }

                $stat = (string) ($_POST['stat'] ?? '');
                if (!in_array($stat, statKeys(), true)) {
                    $error = 'Nieznana statystyka.';
                    break;
                }

                if (availableDevelopmentPoints($player) <= 0) {
                    $error = 'Brak punktów rozwoju.';
                    break;
                }

                $stmt = db()->prepare("UPDATE players SET {$stat} = {$stat} + 1 WHERE id = :id");
                $stmt->execute(['id' => $player['id']]);
                $message = 'Dodano punkt do statystyki.';
                break;

            case 'upload_avatar':
                $player = currentPlayer();
                if (!$player) {
                    $error = 'Musisz się zalogować.';
                    break;
                }

                if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                    $error = 'Nie udało się wgrać pliku.';
                    break;
                }

                if ($_FILES['avatar']['size'] > $config['max_avatar_size']) {
                    $error = 'Plik jest za duży (max 2MB).';
                    break;
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($_FILES['avatar']['tmp_name']);
                $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

                if (!isset($extMap[$mime])) {
                    $error = 'Dozwolone formaty: JPG, PNG, WEBP.';
                    break;
                }

                if (!is_dir($config['upload_dir'])) {
                    mkdir($config['upload_dir'], 0775, true);
                }

                $fileName = sprintf('avatar_%d_%s.%s', (int) $player['id'], bin2hex(random_bytes(4)), $extMap[$mime]);
                $targetPath = $config['upload_dir'] . '/' . $fileName;

                if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
                    $error = 'Błąd zapisu pliku.';
                    break;
                }

                $avatarPath = 'uploads/avatars/' . $fileName;
                $stmt = db()->prepare('UPDATE players SET avatar_path = :avatar WHERE id = :id');
                $stmt->execute([
                    'avatar' => $avatarPath,
                    'id' => $player['id'],
                ]);

                $message = 'Avatar został zaktualizowany.';
                break;

            case 'debug_gain_xp':
                $player = currentPlayer();
                if ($player) {
                    grantXp(db(), (int) $player['id'], 250);
                    $message = 'Dodano 250 exp (przycisk testowy).';
                }
                break;
        }
    } catch (Throwable $e) {
        $error = 'Wystąpił błąd: ' . $e->getMessage();
    }
}

$player = currentPlayer();
$statsInfo = statDescriptions();

function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Gra przeglądarkowa - baza</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <header class="topbar">
        <div>
            <h1>Gra przeglądarkowa (baza)</h1>
            <?php if ($player): ?>
                <p>Nick: <strong><?= e($player['nick']) ?></strong></p>
                <div class="xp-wrap">
                    <?php $progress = levelProgressPercent((int) $player['level'], (int) $player['xp']); ?>
                    <div class="xp-label">Poziom <?= (int) $player['level'] ?> · EXP <?= (int) $player['xp'] ?>/<?= xpRequiredForLevel((int) $player['level']) ?></div>
                    <div class="xp-bar"><span style="width: <?= number_format($progress, 2, '.', '') ?>%"></span></div>
                </div>
            <?php else: ?>
                <p>Zarejestruj się lub zaloguj, żeby wejść do gry.</p>
            <?php endif; ?>
        </div>
        <?php if ($player): ?>
            <div class="resources">
                <div>Kasa: <strong><?= (int) $player['money'] ?>$</strong></div>
                <div>Punkty rozwoju: <strong><?= availableDevelopmentPoints($player) ?></strong></div>
                <form method="post">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit">Wyloguj</button>
                </form>
            </div>
        <?php endif; ?>
    </header>

    <?php if ($message): ?><p class="msg ok"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="msg err"><?= e($error) ?></p><?php endif; ?>

    <?php if (!$player): ?>
        <main class="auth-grid">
            <section>
                <h2>Rejestracja postaci</h2>
                <form method="post" class="card">
                    <input type="hidden" name="action" value="register">
                    <label>Nick <input name="nick" required minlength="3" maxlength="24"></label>
                    <label>Hasło <input name="password" required type="password" minlength="6"></label>
                    <button type="submit">Stwórz postać</button>
                </form>
            </section>
            <section>
                <h2>Logowanie</h2>
                <form method="post" class="card">
                    <input type="hidden" name="action" value="login">
                    <label>Nick <input name="nick" required></label>
                    <label>Hasło <input name="password" required type="password"></label>
                    <button type="submit">Zaloguj</button>
                </form>
            </section>
        </main>
    <?php else: ?>
        <main class="game-layout">
            <aside class="sidebar">
                <h3>Menu gry</h3>
                <ul>
                    <li>Podgląd gracza</li>
                    <li>Mecze (wkrótce)</li>
                    <li>Pojedynki (wkrótce)</li>
                    <li>Skrzynie (wkrótce)</li>
                    <li>Sklep (wkrótce)</li>
                </ul>
                <form method="post" class="debug-form">
                    <input type="hidden" name="action" value="debug_gain_xp">
                    <button type="submit">+250 EXP (test)</button>
                </form>
            </aside>

            <section class="game-content">
                <h2>Podgląd gracza</h2>

                <div class="profile-card">
                    <div class="avatar-box">
                        <?php if (!empty($player['avatar_path']) && file_exists(__DIR__ . '/../' . $player['avatar_path'])): ?>
                            <img src="../<?= e($player['avatar_path']) ?>" alt="Avatar gracza">
                        <?php else: ?>
                            <div class="avatar-placeholder">Brak avatara</div>
                        <?php endif; ?>

                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_avatar">
                            <input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp" required>
                            <button type="submit">Wgraj avatar</button>
                        </form>
                    </div>

                    <div class="stats-box">
                        <h3>Statystyki</h3>
                        <?php foreach (statKeys() as $key): ?>
                            <div class="stat-row">
                                <div>
                                    <strong><?= e(mb_convert_case(str_replace('_', ' ', $key), MB_CASE_TITLE, 'UTF-8')) ?></strong>
                                    <p><?= e($statsInfo[$key]) ?></p>
                                </div>
                                <div class="stat-value">
                                    <span><?= (int) $player[$key] ?></span>
                                    <form method="post">
                                        <input type="hidden" name="action" value="add_stat_point">
                                        <input type="hidden" name="stat" value="<?= e($key) ?>">
                                        <button type="submit">+1</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </main>
    <?php endif; ?>
</div>
</body>
</html>
