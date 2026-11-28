<?php
/**
 * Страница товара: /tovar/123
 *
 * Отдаётся сервером целиком — чтобы поисковик видел название, цену, описание
 * и микроразметку товара, а не пустой каркас.
 */

declare(strict_types=1);

session_start();

$base = dirname(__DIR__);
$dbFile = $base . '/data/catalog.db';
$configFile = $base . '/data/config.php';
$uploadUrl = '/uploads/products/';

if (is_file($configFile)) {
    require_once $configFile;
}
$yandexReady = defined('YANDEX_CLIENT_ID') && YANDEX_CLIENT_ID !== ''
    && defined('YANDEX_CLIENT_SECRET') && YANDEX_CLIENT_SECRET !== '';
$loggedUser = isset($_SESSION['yandex']) && is_array($_SESSION['yandex']) ? $_SESSION['yandex'] : null;

$id = (int) ($_GET['id'] ?? 0);

function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function money(int $value): string
{
    return $value > 0 ? number_format($value, 0, '', ' ') . ' ₽' : 'цена по запросу';
}

$item = null;
$photos = [];
$comments = [];

if ($id > 0 && is_file($dbFile)) {
    try {
        $pdo = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE IF NOT EXISTS comments (id INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id INTEGER NOT NULL, name TEXT NOT NULL, text TEXT NOT NULL, contact TEXT DEFAULT "",
            created TEXT NOT NULL, approved INTEGER NOT NULL DEFAULT 0, ip_hash TEXT DEFAULT "")');

        $stmt = $pdo->prepare('SELECT * FROM items WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch() ?: null;

        if ($item) {
            $stmt = $pdo->prepare('SELECT file FROM photos WHERE item_id = :id ORDER BY ord ASC, id ASC');
            $stmt->execute([':id' => $id]);
            $photos = array_map(static function ($p) { return $p['file']; }, $stmt->fetchAll());

            $stmt = $pdo->prepare('SELECT name, text, created FROM comments
                WHERE item_id = :id AND approved = 1 ORDER BY created ASC LIMIT 100');
            $stmt->execute([':id' => $id]);
            $comments = $stmt->fetchAll();
        }
    } catch (Throwable $error) {
        $item = null;
    }
}

if ($item === null) {
    http_response_code(404);
    $pageTitle = 'Товар не найден — Интер-Техника-Плюс';
    $pageDescription = 'Такой страницы нет.';
    $notFound = true;
} else {
    $notFound = false;
    $pageTitle = $item['title'] . ' — ' . money((int) $item['price']) . ' | Интер-Техника-Плюс';
    $plainDesc = trim(preg_replace('/\s+/u', ' ', (string) $item['description']) ?? '');
    $pageDescription = $plainDesc !== ''
        ? mb_substr($plainDesc, 0, 160)
        : $item['title'] . '. Иваново, ул. Степанова, 24. Телефон +7 4932 353070.';
}

$canonical = 'https://ivu.ru/tovar/' . $id;
$ogImage = $photos ? 'https://ivu.ru' . $uploadUrl . $photos[0] : 'https://ivu.ru/assets/og.png';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<?php if ($notFound): ?><meta name="robots" content="noindex, follow"><?php endif; ?>
<meta property="og:type" content="product">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta name="theme-color" content="#3060a0">
<link rel="icon" href="/assets/favicon.ico" sizes="any">
<link rel="icon" href="/assets/favicon-32.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png" sizes="180x180">
<link rel="stylesheet" href="/assets/style.css?v=20261161">
<?php if (!$notFound): ?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": <?= json_encode($item['title'], JSON_UNESCAPED_UNICODE) ?>,
  "description": <?= json_encode($pageDescription, JSON_UNESCAPED_UNICODE) ?>,
  <?php if ($photos): ?>"image": <?= json_encode('https://ivu.ru' . $uploadUrl . $photos[0], JSON_UNESCAPED_UNICODE) ?>,<?php endif; ?>
  <?php if ((int) $item['price'] > 0): ?>"offers": {
    "@type": "Offer",
    "price": <?= (int) $item['price'] ?>,
    "priceCurrency": "RUB",
    "availability": <?= (int) $item['sold'] === 1 ? '"https://schema.org/SoldOut"' : '"https://schema.org/InStock"' ?>,
    "seller": { "@type": "Organization", "name": "ООО «Интер-Техника-Плюс»" }
  },<?php endif; ?>
}
</script>
<?php endif; ?>
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="/index.html" aria-label="Интер-Техника-Плюс — на главную">
      <img class="brand-logo" src="/assets/logo.png?v=20261161" alt="Интер-Техника-Плюс" width="112" height="44">
      <span class="brand-name">Интер-Техника<span class="brand-plus">+</span></span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav">
      <span class="nav-toggle-bars" aria-hidden="true"></span>
      <span class="visually-hidden">Меню</span>
    </button>
    <nav class="nav" id="nav" aria-label="Основная навигация">
      <a href="/index.html#services">Услуги</a>
      <a href="/new.html">Техника</a>
      <a href="/used.html">Барахолка</a>
      <a href="/index.html#contacts">Контакты</a>
      <a class="nav__legal" href="/requisites.html">Реквизиты</a>
    </nav>
  </div>
</header>

<main class="container tovar">
<?php if ($notFound): ?>
  <article class="prose" style="padding-block:56px">
    <p class="eyebrow">Ошибка 404</p>
    <h1>Товар не найден</h1>
    <p>Возможно, он уже продан или ссылка устарела. Посмотрите витрины:</p>
    <p class="req-actions">
      <a class="btn" href="/used.html">Барахолка</a>
      <a class="btn btn--ghost" href="/new.html">Новая техника</a>
    </p>
  </article>
<?php else: ?>
  <nav class="breadcrumbs" aria-label="Хлебные крошки">
    <a href="/index.html">Главная</a>
    <span aria-hidden="true">/</span>
    <a href="<?= $item['category'] === 'used' ? '/used.html' : '/new.html' ?>">
      <?= $item['category'] === 'used' ? 'Барахолка' : 'Новая техника' ?>
    </a>
    <span aria-hidden="true">/</span>
    <span><?= e($item['title']) ?></span>
  </nav>

  <div class="tovar__layout">
    <div class="tovar__media">
      <?php if ($photos): ?>
        <img class="tovar__main" id="tovar-main" src="<?= e($uploadUrl . $photos[0]) ?>" alt="<?= e($item['title']) ?>" width="800" height="600">
        <?php if (count($photos) > 1): ?>
          <div class="tovar__thumbs">
            <?php foreach ($photos as $index => $photo): ?>
              <button type="button" class="tovar__thumb<?= $index === 0 ? ' is-active' : '' ?>" data-src="<?= e($uploadUrl . $photo) ?>">
                <img src="<?= e($uploadUrl . $photo) ?>" alt="" loading="lazy">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="tovar__nophoto">Без фотографии</div>
      <?php endif; ?>
    </div>

    <div class="tovar__info">
      <?php if ((int) $item['sold'] === 1): ?>
        <p class="product__badge product__badge--sold">Продано</p>
      <?php endif; ?>

      <h1 class="tovar__title"><?= e($item['title']) ?></h1>
      <p class="tovar__price"><?= e(money((int) $item['price'])) ?></p>

      <p class="tovar__meta">
        <?php if ($item['condition'] !== ''): ?><span><?= e($item['condition']) ?></span><?php endif; ?>
        <?php if ($item['shown_date'] !== ''): ?><span><?= e($item['shown_date']) ?></span><?php endif; ?>
      </p>

      <?php if ($item['description'] !== ''): ?>
        <div class="tovar__desc"><?= nl2br(e($item['description'])) ?></div>
      <?php endif; ?>

      <?php if ((int) $item['sold'] === 0): ?>
        <p class="tovar__actions">
          <a class="btn" href="https://yandex.ru/maps/org/1043257440/" target="_blank" rel="noopener">Заказать</a>
          <a class="btn btn--ghost" href="tel:+74932353070">+7 4932 353070</a>
        </p>
      <?php endif; ?>

      <?php if ((string) $item['avito'] !== ''): ?>
        <p><a class="link-arrow" href="<?= e($item['avito']) ?>" target="_blank" rel="noopener">Смотреть на Авито →</a></p>
      <?php endif; ?>

      <p class="tovar__note">
        Работаем с юридическими лицами: счёт, договор, закрывающие документы.
        Забрать можно по адресу: Иваново, ул. Степанова, 24, вход с левой стороны, 1 этаж.
      </p>
    </div>
  </div>

  <section class="tovar__comments" id="comments">
    <h2 class="tovar__h2">Вопросы по товару</h2>

    <div id="comment-list">
      <?php if (!$comments): ?>
        <p class="tovar__empty">Пока вопросов нет. Задайте первый — ответим.</p>
      <?php else: ?>
        <?php foreach ($comments as $comment): ?>
          <div class="tovar__comment">
            <p class="tovar__comment-head">
              <strong><?= e($comment['name']) ?></strong>
              <span><?= e(date('d.m.Y', strtotime($comment['created']))) ?></span>
            </p>
            <p class="tovar__comment-text"><?= nl2br(e($comment['text'])) ?></p>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($yandexReady && $loggedUser === null): ?>
      <p class="tovar__login-note">
        Вопросы могут задавать только авторизованные пользователи — так меньше спама.
      </p>
      <p><a class="btn btn--sm" href="/auth.php?action=start">Войти через Яндекс</a></p>
    <?php else: ?>
      <form class="tovar__form" id="comment-form">
        <input type="text" name="hp" class="tovar__hp" tabindex="-1" autocomplete="off">
        <?php if ($yandexReady): ?>
          <p class="tovar__login-note">Вы вошли как <strong><?= e($loggedUser['name'] ?? '') ?></strong></p>
        <?php else: ?>
          <label class="tovar__label"><span>Ваше имя</span><input type="text" name="name" maxlength="60" required></label>
        <?php endif; ?>
        <label class="tovar__label"><span>Телефон или почта (необязательно)</span><input type="text" name="contact" maxlength="80"></label>
        <label class="tovar__label"><span>Вопрос</span><textarea name="text" maxlength="1500" required></textarea></label>
        <p><button class="btn" type="submit">Отправить вопрос</button></p>
        <p class="tovar__status" id="comment-status" role="status"></p>
      </form>
    <?php endif; ?>
  </section>

  <script>
  (function () {
    var main = document.getElementById("tovar-main");
    document.querySelectorAll(".tovar__thumb").forEach(function (button) {
      button.addEventListener("click", function () {
        if (!main) return;
        main.src = button.getAttribute("data-src");
        document.querySelectorAll(".tovar__thumb").forEach(function (b) { b.classList.remove("is-active"); });
        button.classList.add("is-active");
      });
    });

    var form = document.getElementById("comment-form");
    if (!form) return;
    var status = document.getElementById("comment-status");

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      var data = {
        item: <?= (int) $id ?>,
        name: form.name ? form.name.value.trim() : "",
        text: form.text.value.trim(),
        contact: form.contact.value.trim(),
        hp: form.hp.value
      };
      status.textContent = "Отправляем…";
      status.className = "tovar__status";
      fetch("/comments-api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
      })
        .then(function (r) { return r.json(); })
        .then(function (result) {
          if (result.error) throw new Error(result.error);
          form.reset();
          status.textContent = "Спасибо! Вопрос получили — ответим и опубликуем после проверки.";
          status.className = "tovar__status tovar__status--ok";
        })
        .catch(function (error) {
          status.textContent = error.message || "Не удалось отправить.";
          status.className = "tovar__status tovar__status--err";
        });
    });
  })();
  </script>
<?php endif; ?>
</main>

<footer class="site-footer">
  <div class="container footer-inner">
    <p>© 1999–<?= date('Y') ?>, ООО «Интер-Техника-Плюс».</p>
    <nav aria-label="Служебные ссылки">
      <a href="/index.html">На главную</a>
      <a href="/used.html">Барахолка</a>
    </nav>
  </div>
</footer>
<script src="/assets/app.js?v=20261161" defer></script>
</body>
</html>
