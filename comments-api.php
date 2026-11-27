<?php
/**
 * Вопросы и комментарии к товарам.
 *
 * GET  comments-api.php?item=12          — опубликованные вопросы товара
 * POST comments-api.php (JSON)           — отправить вопрос на проверку
 *      { item, name, text, contact, hp }
 *
 * Новые вопросы сохраняются неподтверждёнными: их видно в панели /admin/,
 * на сайте они появляются после нажатия «Опубликовать».
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$base = dirname(__DIR__);
$dbFile = $base . '/data/catalog.db';
$configFile = $base . '/data/config.php';

session_start();
if (is_file($configFile)) {
    require_once $configFile;
}
$yandexReady = defined('YANDEX_CLIENT_ID') && YANDEX_CLIENT_ID !== ''
    && defined('YANDEX_CLIENT_SECRET') && YANDEX_CLIENT_SECRET !== '';
$loggedUser = isset($_SESSION['yandex']) && is_array($_SESSION['yandex']) ? $_SESSION['yandex'] : null;

function out(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function db(): PDO
{
    global $dbFile;
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE IF NOT EXISTS comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            text TEXT NOT NULL,
            contact TEXT DEFAULT "",
            created TEXT NOT NULL,
            approved INTEGER NOT NULL DEFAULT 0,
            ip_hash TEXT DEFAULT ""
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        )');
    }
    return $pdo;
}

function clean(string $value, int $limit): string
{
    $value = trim(strip_tags($value));
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return mb_substr($value, 0, $limit);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $pdo = db();

    // ---------- чтение ----------
    if ($method === 'GET') {
        $item = (int) ($_GET['item'] ?? 0);
        if ($item <= 0) {
            out(['error' => 'нет товара'], 400);
        }

        $stmt = $pdo->prepare('SELECT name, text, created FROM comments
            WHERE item_id = :id AND approved = 1 ORDER BY created ASC LIMIT 100');
        $stmt->execute([':id' => $item]);

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $items[] = [
                'name' => $row['name'],
                'text' => $row['text'],
                'date' => date('d.m.Y', strtotime($row['created'])),
            ];
        }

        out(['count' => count($items), 'items' => $items]);
    }

    if ($method !== 'POST') {
        out(['error' => 'метод не поддерживается'], 405);
    }

    // ---------- отправка ----------
    $raw = file_get_contents('php://input');
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    // ловушка для роботов: скрытое поле должно остаться пустым
    if (!empty($data['hp'])) {
        out(['ok' => true]);   // роботу отвечаем «успешно», чтобы не искал обход
    }

    $item = (int) ($data['item'] ?? 0);
    $name = clean((string) ($data['name'] ?? ''), 60);
    $text = clean((string) ($data['text'] ?? ''), 1500);
    $contact = clean((string) ($data['contact'] ?? ''), 80);

    // Если вход через Яндекс настроен — писать могут только вошедшие,
    // имя берём из профиля.
    if ($yandexReady) {
        if ($loggedUser === null) {
            out(['error' => 'Чтобы задать вопрос, войдите через Яндекс'], 401);
        }
        $name = clean((string) ($loggedUser['name'] ?? 'Пользователь Яндекса'), 60);
    }

    if ($item <= 0) {
        out(['error' => 'Товар не найден'], 400);
    }
    if (mb_strlen($name) < 2) {
        out(['error' => 'Напишите, как к вам обращаться'], 400);
    }
    if (mb_strlen($text) < 10) {
        out(['error' => 'Вопрос слишком короткий — добавьте пару слов'], 400);
    }

    $exists = $pdo->prepare('SELECT COUNT(*) FROM items WHERE id = :id');
    $exists->execute([':id' => $item]);
    if ((int) $exists->fetchColumn() === 0) {
        out(['error' => 'Товар не найден'], 404);
    }

    $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'ivu-catalog');
    $rate = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE ip_hash = :ip AND created > :since');
    $rate->execute([':ip' => $ipHash, ':since' => date('c', time() - 3600)]);
    if ((int) $rate->fetchColumn() >= 5) {
        out(['error' => 'Слишком много сообщений подряд. Попробуйте через час.'], 429);
    }

    $autoApprove = $pdo->query("SELECT value FROM settings WHERE key = 'auto_approve'")->fetchColumn();

    $stmt = $pdo->prepare('INSERT INTO comments (item_id, name, text, contact, created, approved, ip_hash)
        VALUES (:item, :name, :text, :contact, :created, :approved, :ip)');
    $stmt->execute([
        ':item' => $item,
        ':name' => $name,
        ':text' => $text,
        ':contact' => $contact,
        ':created' => date('c'),
        ':approved' => $autoApprove === '1' ? 1 : 0,
        ':ip' => $ipHash,
    ]);

    out(['ok' => true, 'moderated' => $autoApprove !== '1']);
} catch (Throwable $error) {
    out(['error' => 'Не удалось сохранить. Попробуйте позже.'], 500);
}
