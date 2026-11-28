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
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/notify.php';

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
$note = cleanText((string) ($data['note'] ?? ''), 140);
$email = mb_strtolower(cleanText((string) ($data['email'] ?? ''), 120));
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = '';
}
$name = cleanText((string) ($data['name'] ?? ''), 60);

$digits = preg_replace('/\D+/', '', $phone) ?? '';

/** Похож ли номер на настоящий российский. */
function looksRealPhone(string $digits): bool
{
    $length = strlen($digits);

    if ($length === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
        $national = substr($digits, 1);
    } elseif ($length === 10) {
        $national = $digits;
    } else {
        return false;
    }

    // все цифры одинаковые: 0000000000, 1111111111
    if (count(array_unique(str_split($national))) === 1) {
        return false;
    }

    // подряд идущие: 1234567890, 9876543210
    $ascending = '0123456789';
    $descending = '9876543210';
    if (strpos($ascending, $national) !== false || strpos($descending, $national) !== false) {
        return false;
    }

    // код оператора не начинается с 0 или 1
    if ($national[0] === '0' || $national[0] === '1') {
        return false;
    }

    return true;
}

if (!looksRealPhone($digits)) {
    out(['error' => 'Проверьте номер телефона — такого не бывает'], 400);
}

// метка формы: должна быть подписана нашим секретом и не старше двух часов
$tokenTs = (int) ($data['ts'] ?? 0);
$tokenSig = (string) ($data['sig'] ?? '');
$secret = defined('ADMIN_PASSWORD_HASH') ? (string) ADMIN_PASSWORD_HASH : 'ivu-site-form';
$expected = hash_hmac('sha256', (string) $tokenTs, $secret);

if ($tokenTs <= 0 || $tokenSig === '' || !hash_equals($expected, $tokenSig)) {
    out(['error' => 'Обновите страницу и попробуйте снова'], 400);
}
if (time() - $tokenTs < 2 || time() - $tokenTs > 7200) {
    out(['error' => 'Форма устарела, обновите страницу'], 400);
}

// капча Яндекс SmartCaptcha — если настроены ключи
if (defined('SMARTCAPTCHA_SERVER_KEY') && SMARTCAPTCHA_SERVER_KEY !== '') {
    $captcha = (string) ($data['captcha'] ?? '');
    $ch = curl_init('https://smartcaptcha.yandexcloud.net/validate');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => (string) SMARTCAPTCHA_SERVER_KEY,
            'token' => $captcha,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
    ]);
    $answer = json_decode((string) curl_exec($ch), true);
    curl_close($ch);

    if (!is_array($answer) || empty($answer['status']) || $answer['status'] !== 'ok') {
        out(['error' => 'Подтвердите, что вы не робот, и попробуйте снова'], 400);
    }
}

// ссылки в заявке — почти всегда реклама
if (preg_match('~(https?://|www\.|\.ru/|\.com)~iu', $note . ' ' . $name)) {
    out(['ok' => true]);
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
        note TEXT DEFAULT "",
        email TEXT DEFAULT "",
        created TEXT NOT NULL,
        processed INTEGER NOT NULL DEFAULT 0,
        ip_hash TEXT DEFAULT ""
    )');

    // на старых базах колонки note нет — добавляем
    $columns = $pdo->query('PRAGMA table_info(callbacks)')->fetchAll();
    $hasNote = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'note') {
            $hasNote = true;
        }
    }
    if (!$hasNote) {
        $pdo->exec('ALTER TABLE callbacks ADD COLUMN note TEXT DEFAULT ""');
    }

    // почта заказчика — появилась позже, добавляем на старых базах
    $hasEmail = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'email') {
            $hasEmail = true;
        }
    }
    if (!$hasEmail) {
        $pdo->exec('ALTER TABLE callbacks ADD COLUMN email TEXT DEFAULT ""');
    }

    $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'ivu-callback');

    // общий предохранитель от массовой рассылки
    $total = $pdo->prepare('SELECT COUNT(*) FROM callbacks WHERE created > :since');
    $total->execute([':since' => date('c', time() - 3600)]);
    if ((int) $total->fetchColumn() >= 40) {
        out(['ok' => true]);
    }

    $rate = $pdo->prepare('SELECT COUNT(*) FROM callbacks WHERE ip_hash = :ip AND created > :since');
    $rate->execute([':ip' => $ipHash, ':since' => date('c', time() - 3600)]);
    if ((int) $rate->fetchColumn() >= 5) {
        out(['error' => 'Слишком много заявок подряд. Попробуйте через час.'], 429);
    }

    $stmt = $pdo->prepare('INSERT INTO callbacks (phone, name, note, email, created, ip_hash)
        VALUES (:phone, :name, :note, :email, :created, :ip)');
    $stmt->execute([
        ':phone' => $phone,
        ':name' => $name,
        ':note' => $note,
        ':email' => $email,
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
    . ($note !== '' ? 'Что: ' . $note . "\n" : '')
    . 'Когда: ' . date('d.m.Y H:i') . " (Москва)\n"
    . "Источник: всплывающее окно «нерабочее время»\n\n"
    . "Список заявок: https://ivu.ru/admin/\n";

ivuMail($notifyTo, $subject, $body);

// сообщение в Телеграм владельцу сайта
$tg = "📦 <b>Заявка с сайта</b>\n"
    . 'Телефон: <b>' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . "</b>\n"
    . ($note !== '' ? 'Что: ' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . "\n" : '')
    . ($email !== '' ? 'Почта: ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "\n" : '')
    . 'Когда: ' . date('d.m.Y H:i');
ivuTelegram($tg);

// подтверждение заказчику — если оставил почту
if ($email !== '') {
    $confirmSubject = 'Заявка принята — Интер-Техника-Плюс';
    $confirmBody = "Здравствуйте!

"
        . "Мы получили вашу заявку на сайте ivu.ru.

"
        . ($note !== '' ? 'Что: ' . $note . "\n" : '')
        . 'Телефон: ' . $phone . "\n
"
        . "Перезвоним в рабочее время: Пн–Пт с 11:00 до 18:00.
"
        . "Если вопрос срочный — позвоните сами: +7 4932 353070.

"
        . "ООО «Интер-Техника-Плюс», Иваново, ул. Степанова, 24
";
    ivuMail($email, $confirmSubject, $confirmBody);
}

out(['ok' => true]);
