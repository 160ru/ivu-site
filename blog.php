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
$uploadDir = __DIR__ . '/uploads/blog';

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
                $html .= '  <li>' . e(preg_replace('/^-\s*/', '', $item) ?? '') . '</li>' . "\n";
            }
            $html .= '</ul>' . "\n";
            continue;
        }
        $html .= '<p>' . nl2br(e($block)) . '</p>' . "\n";
    }
    return $html;
}

$pdo = null;
$posts = [];
$post = null;

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

if ($pdo !== null) {
    if ($slug !== '') {
        $stmt = $pdo->prepare('SELECT * FROM posts WHERE slug = :slug AND published = 1');
        $stmt->execute([':slug' => $slug]);
        $post = $stmt->fetch() ?: null;
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
<?php if ($post !== null): ?>
<link rel="alternate" type="application/rss+xml" title="Блог Интер-Техника-Плюс" href="https://ivu.ru/blog/rss.xml">
<?php endif; ?>
<link rel="icon" href="/assets/favicon.ico" sizes="any">
<link rel="icon" href="/assets/favicon-32.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png" sizes="180x180">
<link rel="stylesheet" href="/assets/style.css?v=20261156">
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
<?php endif; ?>
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="/index.html" aria-label="Интер-Техника-Плюс — на главную">
      <img class="brand-logo" src="/assets/logo.png?v=20261156" alt="Интер-Техника-Плюс" width="112" height="44">
      <span class="brand-name">Интер-Техника<span class="brand-plus">+</span></span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav">
      <span class="nav-toggle-bars" aria-hidden="true"></span>
      <span class="visually-hidden">Меню</span>
    </button>
    <nav class="nav" id="nav" aria-label="Основная навигация">
      <a href="/index.html#services">Услуги</a>
      <a href="/new.html">Новая техника</a>
      <a href="/used.html">Барахолка</a>
      <a href="/blog/" aria-current="page">Блог</a>
      <a href="/index.html#contacts">Контакты</a>
      <a href="/requisites.html">Реквизиты</a>
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
      <?php foreach ($posts as $entry): ?>
        <article class="post-card">
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
  <div class="container footer-inner">
    <p>© 1999–<?= date('Y') ?>, ООО «Интер-Техника-Плюс».</p>
    <nav aria-label="Служебные ссылки">
      <a href="/index.html">На главную</a>
      <a href="/blog/">Блог</a>
    </nav>
  </div>
</footer>
<script src="/assets/app.js?v=20261156" defer></script>
</body>
</html>
