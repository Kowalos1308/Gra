<?php
header('Content-Type: application/json; charset=utf-8');

$dataFile = __DIR__ . '/data.json';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

function readJsonFile($path)
{
    if (!file_exists($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function writeJsonFile($path, $data)
{
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        return false;
    }

    return file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) !== false;
}

function readBody()
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

$data = readJsonFile($dataFile);
if ($data === null) {
    http_response_code(500);
    echo json_encode(['error' => 'Nie udało się odczytać pliku data.json']);
    exit;
}

if ($method === 'GET' && $action === 'data') {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST' && $action === 'orders') {
    $body = readBody();
    if ($body === null || !isset($body['orders']) || !is_array($body['orders'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Nieprawidłowe dane zamówień.']);
        exit;
    }

    $data['orders'] = $body['orders'];
    if (!writeJsonFile($dataFile, $data)) {
        http_response_code(500);
        echo json_encode(['error' => 'Nie udało się zapisać zamówień.']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

if ($method === 'POST' && $action === 'users') {
    $body = readBody();
    if ($body === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Nieprawidłowe dane wejściowe.']);
        exit;
    }

    $name = trim((string)($body['name'] ?? ''));
    $password = trim((string)($body['password'] ?? ''));
    $deviceIds = isset($body['deviceIds']) && is_array($body['deviceIds']) ? $body['deviceIds'] : [];

    if ($name === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Podaj nazwę i hasło użytkownika.']);
        exit;
    }

    foreach ($data['users'] as $user) {
        if (mb_strtolower((string)$user['name']) === mb_strtolower($name)) {
            http_response_code(409);
            echo json_encode(['error' => 'Użytkownik już istnieje.']);
            exit;
        }
    }

    $data['users'][] = ['name' => $name, 'password' => $password];
    $data['orders'][$name] = [];

    foreach ($deviceIds as $id) {
        $data['orders'][$name][(string)$id] = 0;
    }

    if (!writeJsonFile($dataFile, $data)) {
        http_response_code(500);
        echo json_encode(['error' => 'Nie udało się zapisać użytkownika.']);
        exit;
    }

    http_response_code(201);
    echo json_encode(['ok' => true, 'user' => ['name' => $name, 'password' => $password]]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Nie znaleziono endpointu API.']);
