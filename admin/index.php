<?php
/**
 * Панель каталога: вход по паролю, список товаров, добавление и правка.
 *
 * Адрес: /admin/  Пароль хранится хешем в data/config.php (вне public_html).
 */

declare(strict_types=1);

session_start();

$base = dirname(__DIR__, 2);           // /home/<логин>/<домен>
$configFile = $base . '/data/config.php';
$dbFile = $base . '/data/catalog.db';
$uploadDir = dirname(__DIR__) . '/uploads/products';
$uploadUrl = '/uploads/products/';

if (!is_file($configFile)) {
    http_response_code(500);
    exit('Нет файла настроек data/config.php — обратитесь к разработчику.');
}
require $configFile;

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
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
        $pdo->exec('CREATE TABLE IF NOT EXISTS items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category TEXT NOT NULL DEFAULT "new",
            title TEXT NOT NULL,
            price INTEGER NOT NULL DEFAULT 0,
            condition TEXT DEFAULT "",
            shown_date TEXT DEFAULT "",
            description TEXT DEFAULT "",
            avito TEXT DEFAULT "",
            sold INTEGER NOT NULL DEFAULT 0,
            created TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS photos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id INTEGER NOT NULL,
            file TEXT NOT NULL,
            ord INTEGER NOT NULL DEFAULT 0
        )');
    }
    return $pdo;
}

function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function checkCsrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string) $token)) {
        http_response_code(400);
        exit('Устаревшая форма — обновите страницу.');
    }
}

/** Перезаписывает data/config.php, сохраняя уже заданные ключи. */
function writeConfig(array $values, ?string $newPassword = null): bool
{
    global $configFile;
    $hash = defined('ADMIN_PASSWORD_HASH') ? ADMIN_PASSWORD_HASH : '';
    if ($newPassword !== null && $newPassword !== '') {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    $clientId = $values['yandex_id'] ?? (defined('YANDEX_CLIENT_ID') ? YANDEX_CLIENT_ID : '');
    $clientSecret = $values['yandex_secret'] ?? (defined('YANDEX_CLIENT_SECRET') ? YANDEX_CLIENT_SECRET : '');

    $content = "<?php\n"
        . "define('ADMIN_PASSWORD_HASH', " . var_export($hash, true) . ");\n"
        . "define('YANDEX_CLIENT_ID', " . var_export(trim((string) $clientId), true) . ");\n"
        . "define('YANDEX_CLIENT_SECRET', " . var_export(trim((string) $clientSecret), true) . ");\n";

    return @file_put_contents($configFile, $content) !== false;
}

function translit(string $text): string
{    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
        'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k',
        'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
        'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];
    $text = mb_strtolower($text);
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text);
    $text = trim((string) $text, '-');
    return $text === '' ? 'tovar' : mb_substr($text, 0, 40);
}

/** Сохраняет загруженный файл, сжимает до 1400 px по длинной стороне. */
function storePhoto(array $file, string $slug): ?string
{
    global $uploadDir;
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return null;
    }
    [$width, $height] = $info;
    $source = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if ($source === false) {
        return null;
    }

    $max = 1400;
    $scale = min(1, $max / max($width, $height));
    $newWidth = max(1, (int) round($width * $scale));
    $newHeight = max(1, (int) round($height * $scale));
    $canvas = imagecreatetruecolor($newWidth, $newHeight);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $name = $slug . '-' . bin2hex(random_bytes(3)) . '.jpg';
    $path = rtrim($uploadDir, '/') . '/' . $name;
    imagejpeg($canvas, $path, 85);
    imagedestroy($canvas);
    imagedestroy($source);

    return $name;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$message = '';
$error = '';

// ---------- вход ----------
if ($action === 'login') {
    checkCsrf();
    $password = (string) ($_POST['password'] ?? '');
    if (password_verify($password, ADMIN_PASSWORD_HASH)) {
        $_SESSION['auth'] = true;
        session_regenerate_id(true);
        header('Location: /admin/');
        exit;
    }
    $error = 'Неверный пароль';
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: /admin/');
    exit;
}

$authorized = !empty($_SESSION['auth']);

// ---------- действия только для авторизованных ----------
if ($authorized && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (in_array($action, ['save', 'delete', 'sold', 'photo-delete', 'comment-approve', 'comment-delete', 'auto-approve'], true)) {
        checkCsrf();
    }

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $category = ($_POST['category'] ?? 'new') === 'used' ? 'used' : 'new';
        $fields = [
            'category' => $category,
            'title' => $title,
            'price' => max(0, (int) ($_POST['price'] ?? 0)),
            'condition' => trim((string) ($_POST['condition'] ?? '')),
            'shown_date' => trim((string) ($_POST['shown_date'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'avito' => trim((string) ($_POST['avito'] ?? '')),
            'sold' => isset($_POST['sold']) ? 1 : 0,
        ];

        if ($title === '') {
            $error = 'Название обязательно';
        } elseif ($id > 0) {
            $stmt = db()->prepare('UPDATE items SET category=:category, title=:title, price=:price,
                condition=:condition, shown_date=:shown_date, description=:description,
                avito=:avito, sold=:sold WHERE id=:id');
            $stmt->execute($fields + [':id' => $id]);
            $message = 'Товар обновлён';
        } else {
            $fields['created'] = date('c');
            $stmt = db()->prepare('INSERT INTO items (category, title, price, condition, shown_date,
                description, avito, sold, created) VALUES (:category, :title, :price, :condition,
                :shown_date, :description, :avito, :sold, :created)');
            $stmt->execute($fields);
            $id = (int) db()->lastInsertId();
            $message = 'Товар добавлен';
        }

        if ($id > 0 && !empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
            $slug = translit($title);
            $ord = (int) db()->query('SELECT COALESCE(MAX(ord),0) FROM photos WHERE item_id = ' . $id)->fetchColumn();
            $added = 0;
            foreach ($_FILES['photos']['name'] as $index => $name) {
                if ($added >= 6) {
                    break;
                }
                $file = [
                    'name' => $name,
                    'tmp_name' => $_FILES['photos']['tmp_name'][$index],
                    'error' => $_FILES['photos']['error'][$index],
                    'size' => $_FILES['photos']['size'][$index],
                ];
                $stored = storePhoto($file, $slug);
                if ($stored === null) {
                    continue;
                }
                $ord++;
                $stmt = db()->prepare('INSERT INTO photos (item_id, file, ord) VALUES (:item, :file, :ord)');
                $stmt->execute([':item' => $id, ':file' => $stored, ':ord' => $ord]);
                $added++;
            }
            if ($added > 0) {
                $message .= ', фото: ' . $added;
            }
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $photos = db()->prepare('SELECT file FROM photos WHERE item_id = :id');
            $photos->execute([':id' => $id]);
            foreach ($photos->fetchAll() as $photo) {
                @unlink(rtrim($uploadDir, '/') . '/' . $photo['file']);
            }
            db()->prepare('DELETE FROM photos WHERE item_id = :id')->execute([':id' => $id]);
            db()->prepare('DELETE FROM items WHERE id = :id')->execute([':id' => $id]);
            $message = 'Товар удалён';
        }
    }

    if ($action === 'sold') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            db()->prepare('UPDATE items SET sold = 1 - sold WHERE id = :id')->execute([':id' => $id]);
            $message = 'Отметка «продано» переключена';
        }
    }

    if ($action === 'password') {
        $old = (string) ($_POST['old'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        if (!password_verify($old, ADMIN_PASSWORD_HASH)) {
            $error = 'Текущий пароль неверный';
        } elseif (mb_strlen($new) < 8) {
            $error = 'Новый пароль короче 8 символов';
        } else {
            if (writeConfig(['yandex_id' => defined('YANDEX_CLIENT_ID') ? YANDEX_CLIENT_ID : '',
                             'yandex_secret' => defined('YANDEX_CLIENT_SECRET') ? YANDEX_CLIENT_SECRET : ''],
                            $new)) {
                $message = 'Пароль изменён';
            } else {
                $error = 'Не удалось записать файл настроек';
            }
        }
    }

    if ($action === 'yandex') {
        $clientId = trim((string) ($_POST['yandex_id'] ?? ''));
        $clientSecret = trim((string) ($_POST['yandex_secret'] ?? ''));
        if (writeConfig(['yandex_id' => $clientId, 'yandex_secret' => $clientSecret])) {
            $message = ($clientId !== '' && $clientSecret !== '')
                ? 'Вход через Яндекс включён: теперь вопросы задают только авторизованные'
                : 'Ключи очищены — вопросы снова принимаются без входа';
        } else {
            $error = 'Не удалось записать файл настроек';
        }
    }
    if ($action === 'comment-approve') {
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        if ($commentId > 0) {
            db()->prepare('UPDATE comments SET approved = 1 - approved WHERE id = :id')
                ->execute([':id' => $commentId]);
            $message = 'Вопрос опубликован или снят с публикации';
        }
    }

    if ($action === 'comment-delete') {
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        if ($commentId > 0) {
            db()->prepare('DELETE FROM comments WHERE id = :id')->execute([':id' => $commentId]);
            $message = 'Вопрос удалён';
        }
    }

    if ($action === 'auto-approve') {
        $value = isset($_POST['auto_approve']) ? '1' : '0';
        db()->prepare('INSERT INTO settings (key, value) VALUES (:k, :v)
            ON CONFLICT(key) DO UPDATE SET value = :v')->execute([':k' => 'auto_approve', ':v' => $value]);
        $message = $value === '1'
            ? 'Вопросы публикуются сразу, без проверки'
            : 'Вопросы ждут проверки перед публикацией';
    }

    if ($action === 'photo-delete') {
        $photoId = (int) ($_POST['photo_id'] ?? 0);
        if ($photoId > 0) {
            $stmt = db()->prepare('SELECT file FROM photos WHERE id = :id');
            $stmt->execute([':id' => $photoId]);
            $photo = $stmt->fetch();
            if ($photo) {
                @unlink(rtrim($uploadDir, '/') . '/' . $photo['file']);
                db()->prepare('DELETE FROM photos WHERE id = :id')->execute([':id' => $photoId]);
                $message = 'Фото удалено';
            }
        }
    }
}

// ---------- данные для страницы ----------
$edit = null;
$editPhotos = [];
$list = [];
$total = 0;
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

if ($authorized) {
    db();
    $editId = (int) ($_GET['edit'] ?? 0);
    if ($editId > 0) {
        $stmt = db()->prepare('SELECT * FROM items WHERE id = :id');
        $stmt->execute([':id' => $editId]);
        $edit = $stmt->fetch() ?: null;
        if ($edit) {
            $stmt = db()->prepare('SELECT * FROM photos WHERE item_id = :id ORDER BY ord ASC, id ASC');
            $stmt->execute([':id' => $editId]);
            $editPhotos = $stmt->fetchAll();
        }
    }

    $where = '1=1';
    $params = [];
    if ($search !== '') {
        $where = '(title LIKE :q OR description LIKE :q)';
        $params[':q'] = '%' . $search . '%';
    }
    $countStmt = db()->prepare('SELECT COUNT(*) FROM items WHERE ' . $where);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = db()->prepare('SELECT i.*, (SELECT COUNT(*) FROM photos p WHERE p.item_id = i.id) AS photos_count
        FROM items i WHERE ' . $where . ' ORDER BY i.created DESC LIMIT :lim OFFSET :off');
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $list = $stmt->fetchAll();

    $comments = db()->query('SELECT c.*, i.title AS item_title, i.category AS item_category
        FROM comments c LEFT JOIN items i ON i.id = c.item_id
        ORDER BY c.approved ASC, c.created DESC LIMIT 100')->fetchAll();
    $pendingCount = 0;
    foreach ($comments as $comment) {
        if ((int) $comment['approved'] === 0) {
            $pendingCount++;
        }
    }
    $autoApprove = db()->query("SELECT value FROM settings WHERE key = 'auto_approve'")->fetchColumn() === '1';
}

$pages = max(1, (int) ceil($total / $perPage));
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Каталог — панель</title>
<link rel="stylesheet" href="/assets/style.css?v=20261155">
<style>
  .admin { max-width: 1000px; margin-inline: auto; padding: 32px 20px 64px; }
  .admin fieldset { border: 1px solid var(--line); border-radius: var(--radius); padding: 18px 20px; margin: 0 0 18px; }
  .admin legend { padding: 0 8px; font-size: .75rem; letter-spacing: .14em; text-transform: uppercase; color: var(--red-text); font-weight: 600; }
  .admin label { display: block; margin-bottom: 12px; font-size: .9rem; }
  .admin label > span { display: block; margin-bottom: 6px; color: var(--ink-soft); }
  .admin input[type=text], .admin input[type=number], .admin input[type=password], .admin select, .admin textarea {
    width: 100%; padding: 10px 12px; font: inherit; color: var(--ink);
    background: #fff; border: 1px solid var(--line); border-radius: 6px;
  }
  .admin textarea { min-height: 90px; resize: vertical; }
  .admin .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  .admin table { width: 100%; border-collapse: collapse; font-size: .9rem; }
  .admin th, .admin td { text-align: left; padding: 8px 6px; border-bottom: 1px solid var(--line); vertical-align: top; }
  .admin .thumbs { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px; }
  .admin .thumb { position: relative; width: 110px; }
  .admin .thumb img { width: 110px; height: 82px; object-fit: contain; background: #fff; border: 1px solid var(--line); border-radius: 6px; }
  .admin .thumb button { position: absolute; top: 2px; right: 2px; border: 0; background: rgba(0,0,0,.65); color: #fff; border-radius: 4px; cursor: pointer; font-size: 12px; padding: 2px 6px; }
  .admin .msg { padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: .9rem; }
  .admin .msg--ok { background: #e8f5ec; color: #1c6b3a; }
  .admin .msg--err { background: #fdecea; color: #a4281f; }
  .admin .inline { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
  .admin .small { font-size: .82rem; color: var(--ink-soft); }
  @media (max-width: 600px) { .admin .row2 { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<main class="admin">

<?php if (!$authorized): ?>
  <p class="eyebrow">Служебная страница</p>
  <h1 class="section-title">Каталог — вход</h1>
  <?php if ($error): ?><p class="msg msg--err"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="/admin/" style="max-width:360px">
    <input type="hidden" name="action" value="login">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <label>
      <span>Пароль</span>
      <input type="password" name="password" autofocus autocomplete="current-password">
    </label>
    <button class="btn" type="submit">Войти</button>
  </form>

<?php else: ?>
  <div class="inline" style="justify-content:space-between;margin-bottom:18px">
    <div>
      <p class="eyebrow" style="margin-bottom:6px">Служебная страница</p>
      <h1 class="section-title" style="font-size:1.8rem">Каталог</h1>
    </div>
    <form method="post" action="/admin/">
      <input type="hidden" name="action" value="logout">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <button class="btn btn--ghost btn--sm" type="submit">Выйти</button>
    </form>
  </div>

  <?php if ($message): ?><p class="msg msg--ok"><?= e($message) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="msg msg--err"><?= e($error) ?></p><?php endif; ?>

  <fieldset>
    <legend><?= $edit ? 'Изменить товар' : 'Новый товар' ?></legend>
    <form method="post" action="/admin/" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">

      <div class="row2">
        <label>
          <span>Витрина</span>
          <select name="category">
            <option value="new"<?= $edit && $edit['category'] === 'new' ? ' selected' : '' ?>>Новая техника</option>
            <option value="used"<?= $edit && $edit['category'] === 'used' ? ' selected' : '' ?>>Барахолка</option>
          </select>
        </label>
        <label>
          <span>Состояние</span>
          <input type="text" name="condition" value="<?= e($edit['condition'] ?? '') ?>" placeholder="Новое / Б/У / После проверки">
        </label>
      </div>

      <label>
        <span>Название</span>
        <input type="text" name="title" required value="<?= e($edit['title'] ?? '') ?>">
      </label>

      <div class="row2">
        <label>
          <span>Цена, ₽</span>
          <input type="number" name="price" min="0" step="1" value="<?= (int) ($edit['price'] ?? 0) ?>">
        </label>
        <label>
          <span>Подпись (необязательно)</span>
          <input type="text" name="shown_date" value="<?= e($edit['shown_date'] ?? '') ?>" placeholder="в наличии">
        </label>
      </div>

      <label>
        <span>Описание</span>
        <textarea name="description"><?= e($edit['description'] ?? '') ?></textarea>
      </label>

      <label>
        <span>Ссылка на Авито (необязательно)</span>
        <input type="text" name="avito" value="<?= e($edit['avito'] ?? '') ?>" placeholder="https://www.avito.ru/…">
      </label>

      <label class="inline">
        <input type="checkbox" name="sold" value="1"<?= !empty($edit['sold']) ? ' checked' : '' ?>>
        <span style="margin:0">Продано — показывать с плашкой</span>
      </label>

      <label>
        <span>Фотографии (до 6 за раз, сжимаются автоматически)</span>
        <input type="file" name="photos[]" accept="image/*" multiple>
      </label>

      <?php if ($editPhotos): ?>
        <div class="thumbs">
          <?php foreach ($editPhotos as $photo): ?>
            <div class="thumb">
              <img src="<?= e($uploadUrl . $photo['file']) ?>" alt="">
              <form method="post" action="/admin/">
                <input type="hidden" name="action" value="photo-delete">
                <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                <button type="submit" title="Удалить фото">×</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <p style="margin-top:16px" class="inline">
        <button class="btn" type="submit"><?= $edit ? 'Сохранить' : 'Добавить' ?></button>
        <?php if ($edit): ?><a class="btn btn--ghost" href="/admin/">Отмена</a><?php endif; ?>
      </p>
    </form>
  </fieldset>

  <fieldset>
    <legend>Пароль</legend>
    <form method="post" action="/admin/" class="row2">
      <input type="hidden" name="action" value="password">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <label><span>Текущий пароль</span><input type="password" name="old" autocomplete="current-password"></label>
      <label><span>Новый пароль (от 8 символов)</span><input type="password" name="new" autocomplete="new-password"></label>
      <p style="grid-column:1/-1;margin:0"><button class="btn btn--sm" type="submit">Сменить пароль</button></p>
    </form>
  </fieldset>

  <fieldset>
    <legend>Вход через Яндекс — вопросы от авторизованных</legend>
    <form method="post" action="/admin/" class="row2">
      <input type="hidden" name="action" value="yandex">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <label><span>Client ID</span>
        <input type="text" name="yandex_id" value="<?= e(defined('YANDEX_CLIENT_ID') ? YANDEX_CLIENT_ID : '') ?>" placeholder="a1b2c3…">
      </label>
      <label><span>Client secret</span>
        <input type="password" name="yandex_secret" value="<?= e(defined('YANDEX_CLIENT_SECRET') ? YANDEX_CLIENT_SECRET : '') ?>" autocomplete="off">
      </label>
      <p class="small" style="grid-column:1/-1;margin:0 0 8px">
        Redirect URI для регистрации приложения:<br>
        <code>https://<?= e($_SERVER['HTTP_HOST'] ?? 'ivu.ru') ?>/auth.php?action=callback</code>
      </p>
      <p style="grid-column:1/-1;margin:0">
        <button class="btn btn--sm" type="submit">Сохранить ключи</button>
        <span class="small">— пока поля пустые, вопросы принимаются без входа</span>
      </p>
    </form>
  </fieldset>
  <fieldset>
    <legend>Вопросы<?= $pendingCount > 0 ? ' — ждут проверки: ' . (int) $pendingCount : '' ?></legend>

    <form method="post" action="/admin/" class="inline" style="margin-bottom:14px">
      <input type="hidden" name="action" value="auto-approve">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <label class="inline" style="margin:0">
        <input type="checkbox" name="auto_approve" value="1"<?= $autoApprove ? ' checked' : '' ?>>
        <span style="margin:0">Публиковать без проверки</span>
      </label>
      <button class="btn btn--sm btn--ghost" type="submit">Применить</button>
    </form>

    <table>
      <thead>
        <tr><th>Вопрос</th><th>Товар</th><th>Статус</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($comments as $comment): ?>
        <tr>
          <td>
            <strong><?= e($comment['name']) ?></strong>
            <div class="small"><?= e($comment['text']) ?></div>
            <?php if ((string) $comment['contact'] !== ''): ?>
              <div class="small">Связь: <?= e($comment['contact']) ?></div>
            <?php endif; ?>
            <div class="small"><?= e(date('d.m.Y H:i', strtotime($comment['created']))) ?></div>
          </td>
          <td class="small"><?= e($comment['item_title'] ?? '— товар удалён —') ?></td>
          <td class="small"><?= (int) $comment['approved'] === 1 ? 'опубликован' : 'ждёт проверки' ?></td>
          <td>
            <div class="inline">
              <form method="post" action="/admin/">
                <input type="hidden" name="action" value="comment-approve">
                <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                <input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
                <button class="btn btn--sm btn--ghost" type="submit"><?= (int) $comment['approved'] === 1 ? 'Снять' : 'Опубликовать' ?></button>
              </form>
              <form method="post" action="/admin/" onsubmit="return confirm('Удалить вопрос?')">
                <input type="hidden" name="action" value="comment-delete">
                <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                <input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
                <button class="btn btn--sm btn--ghost" type="submit">Удалить</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$comments): ?>
        <tr><td colspan="4" class="small">Вопросов пока нет.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </fieldset>

  <fieldset>
    <legend>Товары: <?= (int) $total ?></legend>
    <form method="get" action="/admin/" class="inline" style="margin-bottom:12px">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Поиск по названию или описанию" style="max-width:320px">
      <button class="btn btn--sm btn--ghost" type="submit">Найти</button>
      <?php if ($search !== ''): ?><a class="small" href="/admin/">сбросить</a><?php endif; ?>
    </form>

    <table>
      <thead>
        <tr><th>Товар</th><th>Витрина</th><th>Цена</th><th>Фото</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($list as $row): ?>
        <tr>
          <td>
            <strong><?= e($row['title']) ?></strong>
            <?php if ((int) $row['sold'] === 1): ?><span class="small"> — продано</span><?php endif; ?>
          </td>
          <td class="small"><?= $row['category'] === 'used' ? 'барахолка' : 'новая' ?></td>
          <td class="small"><?= (int) $row['price'] > 0 ? number_format((int) $row['price'], 0, '', ' ') . ' ₽' : 'по запросу' ?></td>
          <td class="small"><?= (int) $row['photos_count'] ?></td>
          <td>
            <div class="inline">
              <a class="btn btn--sm btn--ghost" href="/admin/?edit=<?= (int) $row['id'] ?>">Изменить</a>
              <form method="post" action="/admin/">
                <input type="hidden" name="action" value="sold">
                <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <button class="btn btn--sm btn--ghost" type="submit"><?= (int) $row['sold'] === 1 ? 'Вернуть' : 'Продано' ?></button>
              </form>
              <form method="post" action="/admin/" onsubmit="return confirm('Удалить товар и его фото?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <button class="btn btn--sm btn--ghost" type="submit">Удалить</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?>
        <tr><td colspan="5" class="small">Пока пусто.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>

    <?php if ($pages > 1): ?>
      <p class="inline" style="margin-top:14px">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <?php if ($i === $page): ?>
            <strong><?= $i ?></strong>
          <?php else: ?>
            <a href="/admin/?page=<?= $i ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>
      </p>
    <?php endif; ?>
  </fieldset>
<?php endif; ?>

</main>
</body>
</html>
