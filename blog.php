<?php
/**
 * Блог: список записей и страница записи.
 *
 *   /blog/            — список
 *   /blog/<slug>      — запись
 *
 * Данные в той же базе, что и каталог: таблица posts.
 */

declare(strict_types=1);

session_start();

$base = dirname(__DIR__);
$dbFile = $base . '/data/catalog.db';
$uploadUrl = '/uploads/blog/';
$configFile = $base . '/data/config.php';

if (is_file($configFile)) {
    require_once $configFile;
}
$yandexReady = defined('YANDEX_CLIENT_ID') && YANDEX_CLIENT_ID !== ''
    && defined('YANDEX_CLIENT_SECRET') && YANDEX_CLIENT_SECRET !== '';
$loggedUser = isset($_SESSION['yandex']) && is_array($_SESSION['yandex']) ? $_SESSION['yandex'] : null;

function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/** Простейшая разметка: пустая строка — новый абзац, «## » — подзаголовок, «- » — пункт списка. */
function formatBody(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    if ($text === '') {
        return '';
    }
    $blocks = preg_split('/\n{2,}/', $text) ?: [];
    $html = '';
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }
        if (strpos($block, '## ') === 0) {
            $html .= '<h2>' . e(mb_substr($block, 3)) . '</h2>' . "\n";
            continue;
        }
        if (strpos($block, '- ') === 0) {
            $items = preg_split('/\n/', $block) ?: [];
            $html .= '<ul>' . "\n";
            foreach ($items as $item) {
                $item = trim($item);
                if ($item === '') {
                    continue;
                }
                $html .= '  <li>' . links(e(preg_replace('/^-\s*/', '', $item) ?? '')) . '</li>' . "\n";
            }
            $html .= '</ul>' . "\n";
            continue;
        }
        $html .= '<p>' . links(nl2br(e($block))) . '</p>' . "\n";
    }
    return $html;
}


/** Превращает [текст](адрес) в ссылку. Адрес — только http(s) или путь от корня. */
function links(string $html): string
{
    return preg_replace_callback(
        '/\[([^\]]{1,120})\]\((https?:\/\/[^)\s]{1,300}|\/[^)\s]{0,300})\)/u',
        static function (array $match): string {
            $url = htmlspecialchars($match[2], ENT_QUOTES, 'UTF-8');
            return '<a href="' . $url . '">' . $match[1] . '</a>';
        },
        $html
    ) ?? $html;
}
$pdo = null;
$posts = [];
$post = null;
$postComments = [];

if (is_file($dbFile)) {
    try {
        $pdo = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT NOT NULL UNIQUE,
            title TEXT NOT NULL,
            excerpt TEXT DEFAULT "",
            body TEXT DEFAULT "",
            cover TEXT DEFAULT "",
            published INTEGER NOT NULL DEFAULT 0,
            created TEXT NOT NULL,
            updated TEXT DEFAULT "" )');
    } catch (Throwable $error) {
        $pdo = null;
    }
}

$slug = trim((string) ($_GET['slug'] ?? ''));

if ($slug !== '' && strpos((string) ($_SERVER['REQUEST_URI'] ?? ''), 'blog.php') !== false) {
    header('Location: https://ivu.ru/blog/' . $slug, true, 301);
    exit;
}

if ($pdo !== null) {
    if ($slug !== '') {
        $stmt = $pdo->prepare('SELECT * FROM posts WHERE slug = :slug AND published = 1');
        $stmt->execute([':slug' => $slug]);
        $post = $stmt->fetch() ?: null;

        if ($post !== null) {
            $pdo->exec('CREATE TABLE IF NOT EXISTS post_comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                text TEXT NOT NULL,
                contact TEXT DEFAULT "",
                created TEXT NOT NULL,
                approved INTEGER NOT NULL DEFAULT 0,
                ip_hash TEXT DEFAULT ""
            )');
            $stmt = $pdo->prepare('SELECT name, text, created FROM post_comments
                WHERE post_id = :id AND approved = 1 ORDER BY created ASC LIMIT 200');
            $stmt->execute([':id' => $post['id']]);
            $postComments = $stmt->fetchAll();
        }
    } else {
        $posts = $pdo->query('SELECT slug, title, excerpt, cover, created FROM posts
            WHERE published = 1 ORDER BY created DESC LIMIT 50')->fetchAll();
    }
}

$notFound = ($slug !== '' && $post === null);

if ($notFound) {
    http_response_code(404);
    $pageTitle = 'Запись не найдена — блог Интер-Техника-Плюс';
    $pageDescription = 'Такой страницы нет.';
} elseif ($post !== null) {
    $plain = trim(preg_replace('/\s+/u', ' ', (string) $post['body']) ?? '');
    $pageTitle = $post['title'] . ' — блог Интер-Техника-Плюс';
    $pageDescription = (string) $post['excerpt'] !== ''
        ? mb_substr((string) $post['excerpt'], 0, 160)
        : mb_substr($plain, 0, 160);
} else {
    $pageTitle = 'Блог — Интер-Техника-Плюс, Иваново';
    $pageDescription = 'Заметки о технике, 1С и безопасности: что меняется, что стоит учесть и как не переплатить.';
}

$canonical = $post !== null
    ? 'https://ivu.ru/blog/' . $post['slug']
    : 'https://ivu.ru/blog/';
$ogImage = $post !== null && (string) $post['cover'] !== ''
    ? 'https://ivu.ru' . $uploadUrl . $post['cover']
    : 'https://ivu.ru/assets/og.png';
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
<meta name="theme-color" content="#3060a0">
<meta property="og:type" content="article">
<meta property="og:locale" content="ru_RU">
<meta property="og:site_name" content="Интер-Техника-Плюс">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="alternate" type="application/rss+xml" title="Блог Интер-Техника-Плюс" href="https://ivu.ru/blog/rss.xml">
<link rel="icon" href="/assets/favicon.ico" sizes="any">
<link rel="icon" href="/assets/favicon-32.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png" sizes="180x180">
<link rel="stylesheet" href="/assets/style.css?v=20261174">
<?php if ($post !== null): ?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BlogPosting",
  "headline": <?= json_encode($post['title'], JSON_UNESCAPED_UNICODE) ?>,
  "datePublished": <?= json_encode(date('c', strtotime((string) $post['created'])), JSON_UNESCAPED_UNICODE) ?>,
  "dateModified": <?= json_encode(date('c', strtotime((string) ($post['updated'] !== '' ? $post['updated'] : $post['created']))), JSON_UNESCAPED_UNICODE) ?>,
  "author": { "@type": "Organization", "name": "ООО «Интер-Техника-Плюс»" },
  "publisher": { "@type": "Organization", "name": "ООО «Интер-Техника-Плюс»", "logo": { "@type": "ImageObject", "url": "https://ivu.ru/assets/favicon-512.png" } },
  "mainEntityOfPage": <?= json_encode($canonical, JSON_UNESCAPED_UNICODE) ?>
  <?php if ((string) $post['cover'] !== ''): ?>, "image": <?= json_encode($ogImage, JSON_UNESCAPED_UNICODE) ?><?php endif; ?>
}
</script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Главная", "item": "https://ivu.ru/" },
    { "@type": "ListItem", "position": 2, "name": "Блог", "item": "https://ivu.ru/blog/" },
    { "@type": "ListItem", "position": 3, "name": <?= json_encode($post['title'], JSON_UNESCAPED_UNICODE) ?> }
  ]
}
</script>
<?php endif; ?>
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="/index.html" aria-label="Интер-Техника-Плюс — на главную">
      <img class="brand-logo" src="/assets/logo.png?v=20261174" alt="Интер-Техника-Плюс" width="112" height="44">
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
      <a href="/blog/" aria-current="page">Блог</a>
      <a href="/index.html#contacts">Контакты</a>
      <a class="nav__legal" href="/requisites.html">Реквизиты</a>
      <a class="btn btn--sm" href="tel:+74932353070">+7 4932 353070</a>
    </nav>
  </div>
</header>

<main class="container blog">
<?php if ($notFound): ?>
  <article class="prose">
    <p class="eyebrow">Блог</p>
    <h1>Запись не найдена</h1>
    <p>Возможно, ссылка устарела. Все записи — в <a href="/blog/">списке блога</a>.</p>
  </article>

<?php elseif ($post !== null): ?>
  <nav class="breadcrumbs" aria-label="Хлебные крошки">
    <a href="/index.html">Главная</a>
    <span aria-hidden="true">/</span>
    <a href="/blog/">Блог</a>
    <span aria-hidden="true">/</span>
    <span><?= e($post['title']) ?></span>
  </nav>

  <article class="post">
    <p class="post__date"><?= e(date('d.m.Y', strtotime((string) $post['created']))) ?></p>
    <h1 class="post__title"><?= e($post['title']) ?></h1>

    <?php if ((string) $post['cover'] !== ''): ?>
      <img class="post__cover" src="<?= e($uploadUrl . $post['cover']) ?>" alt="<?= e($post['title']) ?>" width="1200" height="630">
    <?php endif; ?>

    <div class="post__body">
      <?= formatBody((string) $post['body']) ?>
    </div>

    <section class="tovar__comments" id="comments">
      <h2 class="tovar__h2">Комментарии</h2>

      <div id="post-comments">
        <?php if (!$postComments): ?>
          <p class="tovar__empty">Пока комментариев нет. Будьте первым.</p>
        <?php else: ?>
          <?php foreach ($postComments as $comment): ?>
            <div class="tovar__comment">
              <p class="tovar__comment-head">
                <strong><?= e($comment['name']) ?></strong>
                <span><?= e(date('d.m.Y', strtotime((string) $comment['created']))) ?></span>
              </p>
              <p class="tovar__comment-text"><?= nl2br(e($comment['text'])) ?></p>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <?php if ($yandexReady && $loggedUser === null): ?>
        <p class="tovar__login-note">
          Комментировать могут только авторизованные пользователи — так меньше спама.
        </p>
        <p><a class="btn btn--sm" href="/auth.php?action=start">Войти через Яндекс</a></p>
      <?php else: ?>
        <form class="tovar__form" id="post-comment-form">
          <input type="text" name="hp" class="tovar__hp" tabindex="-1" autocomplete="off">
          <?php if ($yandexReady): ?>
            <p class="tovar__login-note">Вы вошли как <strong><?= e($loggedUser['name'] ?? '') ?></strong></p>
          <?php else: ?>
            <label class="tovar__label"><span>Ваше имя</span><input type="text" name="name" maxlength="60" required></label>
          <?php endif; ?>
          <label class="tovar__label"><span>Телефон или почта (необязательно)</span><input type="text" name="contact" maxlength="80"></label>
          <label class="tovar__label"><span>Комментарий</span><textarea name="text" maxlength="1500" required></textarea></label>
          <p><button class="btn" type="submit">Отправить</button></p>
          <p class="tovar__status" id="post-comment-status" role="status"></p>
        </form>
      <?php endif; ?>
    </section>

    <script>
    (function () {
      var form = document.getElementById("post-comment-form");
      if (!form) return;
      var status = document.getElementById("post-comment-status");
      form.addEventListener("submit", function (event) {
        event.preventDefault();
        var data = {
          type: "post",
          post: <?= (int) $post['id'] ?>,
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
            status.textContent = "Спасибо! Комментарий получили — опубликуем после проверки.";
            status.className = "tovar__status tovar__status--ok";
          })
          .catch(function (error) {
            status.textContent = error.message || "Не удалось отправить.";
            status.className = "tovar__status tovar__status--err";
          });
      });
    })();
    </script>

    <p class="post__back"><a class="link-arrow" href="/blog/">← Все записи</a></p>
  </article>

<?php else: ?>
  <header class="page-head">
    <p class="eyebrow">Блог</p>
    <h1 class="section-title">Заметки</h1>
    <p class="section-lead">
      О технике, 1С и безопасности — без пересказа новостей.
      Что меняется, что стоит учесть и где обычно переплачивают.
    </p>
  </header>

  <?php if (!$posts): ?>
    <p class="catalog-empty">Пока пусто. Первая запись появится здесь.</p>
  <?php else: ?>
    <div class="posts">
      <?php foreach ($posts as $index => $entry): ?>
        <article class="post-card<?= $index === 0 ? ' post-card--wide' : '' ?>">
          <?php if ((string) $entry['cover'] !== ''): ?>
            <a class="post-card__media" href="/blog/<?= e($entry['slug']) ?>">
              <img src="<?= e($uploadUrl . $entry['cover']) ?>" alt="" loading="lazy" width="640" height="360">
            </a>
          <?php endif; ?>
          <p class="post-card__date"><?= e(date('d.m.Y', strtotime((string) $entry['created']))) ?></p>
          <h2 class="post-card__title">
            <a href="/blog/<?= e($entry['slug']) ?>"><?= e($entry['title']) ?></a>
          </h2>
          <?php if ((string) $entry['excerpt'] !== ''): ?>
            <p class="post-card__excerpt"><?= e($entry['excerpt']) ?></p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <p class="blog__rss"><a href="/blog/rss.xml">RSS-лента блога</a></p>
<?php endif; ?>
</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <p class="footer-h">Интер-Техника+</p>
      <p>ООО «Интер-Техника-Плюс»<br>ИНН 3702008221</p>
      <p>© 1999–<span id="year">2026</span>. Компьютеры, сервис и 1С<br>в Иванове с 1999 года.</p>
    </div>

    <nav class="footer-links" aria-label="Разделы сайта">
      <p class="footer-h">Разделы</p>
      <a href="/index.html#services">Услуги</a>
      <a href="/new.html">Техника</a>
      <a href="/used.html">Барахолка</a>
      <a href="/blog/">Блог</a>
      <a href="/requisites.html">Реквизиты</a>
      <a href="/privacy.html">Политика конфиденциальности</a>
    </nav>

    <div class="footer-contact">
      <p class="footer-h">Контакты</p>
      <span class="phone-group">
        <a href="tel:+74932353070">+7 4932 353070</a>
      </span>
      <p>Иваново, ул. Степанова, 24<br>вход с левой стороны, 1 этаж</p>
      <p>Пн–Пт 11:00–18:00</p>
    </div>
  </div>
</footer>
<script src="/assets/app.js?v=20261174" defer></script>
<script src="/assets/hours.js?v=20261174" defer></script>
</body>
</html>
