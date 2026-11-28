<?php
/**
 * Карта сайта: статические страницы + все товары каталога.
 * Отдаётся по адресу /sitemap.xml (правило в .htaccess).
 */

declare(strict_types=1);

header('Content-Type: application/xml; charset=utf-8');

$base = dirname(__DIR__);
$dbFile = $base . '/data/catalog.db';

$static = [
    ['loc' => 'https://ivu.ru/',               'file' => 'index.html',      'priority' => '1.0', 'changefreq' => 'weekly'],
    ['loc' => 'https://ivu.ru/new.html',       'file' => 'new.html',        'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => 'https://ivu.ru/used.html',      'file' => 'used.html',       'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => 'https://ivu.ru/blog/',          'file' => 'blog.php',        'priority' => '0.8', 'changefreq' => 'weekly'],
    ['loc' => 'https://ivu.ru/requisites.html','file' => 'requisites.html', 'priority' => '0.5', 'changefreq' => 'yearly'],
    ['loc' => 'https://ivu.ru/privacy.html',   'file' => 'privacy.html',    'priority' => '0.2', 'changefreq' => 'yearly'],
];

$items = [];
$blogPosts = [];
if (is_file($dbFile)) {
    try {
        $pdo = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $items = $pdo->query('SELECT id, created FROM items ORDER BY created DESC')->fetchAll();
        $blogPosts = $pdo->query('SELECT slug, created, updated FROM posts WHERE published = 1 ORDER BY created DESC')->fetchAll();
    } catch (Throwable $error) {
        $items = [];
$blogPosts = [];
    }
}

$today = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($static as $page) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($page['loc'], ENT_QUOTES, 'UTF-8') . "</loc>\n";
    $filePath = __DIR__ . '/' . $page['file'];
    $stamp = is_file($filePath) ? date('Y-m-d', (int) filemtime($filePath)) : $today;
    echo '    <lastmod>' . $stamp . "</lastmod>\n";
    echo '    <changefreq>' . $page['changefreq'] . "</changefreq>\n";
    echo '    <priority>' . $page['priority'] . "</priority>\n";
    echo "  </url>\n";
}

foreach ($items as $item) {
    $lastmod = $item['created'] ? date('Y-m-d', strtotime((string) $item['created'])) : $today;
    echo "  <url>\n";
    echo '    <loc>https://ivu.ru/tovar/' . (int) $item['id'] . "</loc>\n";
    echo '    <lastmod>' . $lastmod . "</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.7</priority>\n";
    echo "  </url>\n";
}

foreach ($blogPosts as $entry) {
    $lastmod = (string) $entry['updated'] !== ''
        ? date('Y-m-d', strtotime((string) $entry['updated']))
        : date('Y-m-d', strtotime((string) $entry['created']));
    echo "  <url>\n";
    echo '    <loc>https://ivu.ru/blog/' . htmlspecialchars((string) $entry['slug'], ENT_QUOTES, 'UTF-8') . "</loc>\n";
    echo '    <lastmod>' . $lastmod . "</lastmod>\n";
    echo "    <changefreq>monthly</changefreq>\n";
    echo "    <priority>0.6</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>' . "\n";
