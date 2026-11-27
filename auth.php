<?php
/**
 * Вход через Яндекс ID.
 *
 * ?action=me        — кто вошёл (JSON)
 * ?action=start     — отправляет на страницу Яндекса
 * ?action=callback  — принимает код, получает профиль
 * ?action=logout    — выйти
 *
 * Работает, только если в data/config.php заданы YANDEX_CLIENT_ID
 * и YANDEX_CLIENT_SECRET. Пока их нет — ?action=me отвечает
 * {"configured":false}, и форма вопросов работает по-старому.
 */

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$base = dirname(__DIR__);
$configFile = $base . '/data/config.php';

if (is_file($configFile)) {
    require $configFile;
}

$clientId = defined('YANDEX_CLIENT_ID') ? (string) YANDEX_CLIENT_ID : '';
$clientSecret = defined('YANDEX_CLIENT_SECRET') ? (string) YANDEX_CLIENT_SECRET : '';
$configured = $clientId !== '' && $clientSecret !== '';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'ivu.ru';
$redirectUri = $scheme . '://' . $host . '/auth.php?action=callback';

$action = $_GET['action'] ?? 'me';

function jsonOut(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sessionUser(): ?array
{
    return isset($_SESSION['yandex']) && is_array($_SESSION['yandex']) ? $_SESSION['yandex'] : null;
}

if ($action === 'me') {
    $user = sessionUser();
    jsonOut([
        'configured' => $configured,
        'authorized' => $user !== null,
        'user' => $user,
        'redirect' => $redirectUri,
    ]);
}

if ($action === 'logout') {
    unset($_SESSION['yandex']);
    $back = $_SERVER['HTTP_REFERER'] ?? '/used.html';
    header('Location: ' . $back);
    exit;
}

if (!$configured) {
    jsonOut(['error' => 'Вход через Яндекс не настроен'], 400);
}

if ($action === 'start') {
    $state = bin2hex(random_bytes(12));
    $_SESSION['yandex_state'] = $state;
    $url = 'https://oauth.yandex.ru/authorize?' . http_build_query([
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'state' => $state,
        'scope' => 'login:email login:info',
    ]);
    header('Location: ' . $url);
    exit;
}

if ($action === 'callback') {
    $state = $_GET['state'] ?? '';
    $code = $_GET['code'] ?? '';

    if (empty($_SESSION['yandex_state']) || !hash_equals($_SESSION['yandex_state'], (string) $state)) {
        jsonOut(['error' => 'Сессия входа устарела — попробуйте снова'], 400);
    }
    if ($code === '') {
        jsonOut(['error' => 'Яндекс не передал код авторизации'], 400);
    }

    $token = exchangeCode($code, $clientId, $clientSecret);
    if ($token === null) {
        jsonOut(['error' => 'Не удалось получить доступ у Яндекса'], 502);
    }

    $profile = fetchProfile($token);
    if ($profile === null) {
        jsonOut(['error' => 'Не удалось получить профиль'], 502);
    }

    $_SESSION['yandex'] = [
        'id' => (string) ($profile['id'] ?? ''),
        'name' => (string) ($profile['display_name'] ?? ($profile['real_name'] ?? 'Пользователь Яндекса')),
        'email' => (string) ($profile['default_email'] ?? ''),
    ];
    unset($_SESSION['yandex_state']);

    header('Location: /used.html');
    exit;
}

jsonOut(['error' => 'Неизвестное действие'], 404);

function exchangeCode(string $code, string $clientId, string $clientSecret): ?string
{
    $ch = curl_init('https://oauth.yandex.ru/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200 || !is_string($response)) {
        return null;
    }
    $data = json_decode($response, true);
    return isset($data['access_token']) ? (string) $data['access_token'] : null;
}

function fetchProfile(string $token): ?array
{
    $ch = curl_init('https://login.yandex.ru/info?format=json');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: OAuth ' . $token],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200 || !is_string($response)) {
        return null;
    }
    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}
