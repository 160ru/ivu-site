<?php
/**
 * Заявка на обратный звонок.
 *
 * POST {phone, name?, hp}
 * Складывает заявку в таблицу callbacks и отправляет письмо.
 * Читает панель /admin/ — там её видно и можно отметить обработанной.
 */

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$base = dirname(__DIR__);
$dbFile = $base . '/data/catalog.db';
$configFile = $base . '/data/config.php';

if (is_file($configFile)) {
    require_once $configFile;
}

function out(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cleanText(string $value, int $limit): string
{
    $value = trim(strip_tags($value));
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return mb_substr($value, 0, $limit);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    out(['error' => 'метод не поддерживается'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

// ловушка для роботов
if (!empty($data['hp'])) {
    out(['ok' => true]);
}

$phone = cleanText((string) ($data['phone'] ?? ''), 40);
$name = cleanText((string) ($data['name'] ?? ''), 60);

$digits = preg_replace('/\D+/', '', $phone) ?? '';
if (strlen($digits) < 10 || strlen($digits) > 15) {
    out(['error' => 'Проверьте номер: нужно не меньше 10 цифр'], 400);
}

try {
    $pdo = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE TABLE IF NOT EXISTS callbacks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        phone TEXT NOT NULL,
        name TEXT DEFAULT "",
        created TEXT NOT NULL,
        processed INTEGER NOT NULL DEFAULT 0,
        ip_hash TEXT DEFAULT ""
    )');

    $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'ivu-callback');

    $rate = $pdo->prepare('SELECT COUNT(*) FROM callbacks WHERE ip_hash = :ip AND created > :since');
    $rate->execute([':ip' => $ipHash, ':since' => date('c', time() - 3600)]);
    if ((int) $rate->fetchColumn() >= 5) {
        out(['error' => 'Слишком много заявок подряд. Попробуйте через час.'], 429);
    }

    $stmt = $pdo->prepare('INSERT INTO callbacks (phone, name, created, ip_hash)
        VALUES (:phone, :name, :created, :ip)');
    $stmt->execute([
        ':phone' => $phone,
        ':name' => $name,
        ':created' => date('c'),
        ':ip' => $ipHash,
    ]);
} catch (Throwable $error) {
    out(['error' => 'Не удалось сохранить заявку. Позвоните сами.'], 500);
}

$notifyTo = defined('COMMENT_EMAIL') && COMMENT_EMAIL !== '' ? COMMENT_EMAIL : 'it@ivu.ru';
$subject = 'Просят перезвонить: ' . $phone;
$body = 'Номер: ' . $phone . "\n"
    . ($name !== '' ? 'Имя: ' . $name . "\n" : '')
    . 'Когда: ' . date('d.m.Y H:i') . " (Москва)\n"
    . "Источник: всплывающее окно «нерабочее время»\n\n"
    . "Список заявок: https://ivu.ru/admin/\n";

@mail(
    $notifyTo,
    '=?UTF-8?B?' . base64_encode($subject) . '?=',
    $body,
    "From: ivu.ru <no-reply@ivu.ru>\r\nContent-Type: text/plain; charset=UTF-8\r\n"
);

out(['ok' => true]);
