<?php
/**
 * RSS-лента блога — для Дзена, читалок и авто-репостов.
 * Адрес: /blog/rss.xml
 */

declare(strict_types=1);

header('Content-Type: application/rss+xml; charset=utf-8');

$base = dirname(__DIR__);
$dbFile = $base . '/data/catalog.db';

$posts = [];
if (is_file($dbFile)) {
    try {
        $pdo = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $posts = $pdo->query('SELECT slug, title, excerpt, body, created
            FROM posts WHERE published = 1 ORDER BY created DESC LIMIT 50')->fetchAll();
    } catch (Throwable $error) {
        $posts = [];
    }
}

function x(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel>
    <title>Блог — Интер-Техника-Плюс</title>
    <link>https://ivu.ru/blog/</link>
    <description>Заметки о технике, 1С и безопасности. Иваново.</description>
    <language>ru</language>
    <lastBuildDate><?= date(DATE_RSS) ?></lastBuildDate>
<?php foreach ($posts as $post): ?>
    <item>
      <title><?= x((string) $post['title']) ?></title>
      <link>https://ivu.ru/blog/<?= x((string) $post['slug']) ?></link>
      <guid isPermaLink="true">https://ivu.ru/blog/<?= x((string) $post['slug']) ?></guid>
      <pubDate><?= date(DATE_RSS, strtotime((string) $post['created'])) ?></pubDate>
      <description><?= x((string) $post['excerpt']) ?></description>
      <content:encoded><![CDATA[<?php
        $body = (string) $post['body'];
        $paragraphs = preg_split('/\n{2,}/', trim($body)) ?: [];
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            if (strpos($paragraph, '## ') === 0) {
                echo '<h2>' . htmlspecialchars(mb_substr($paragraph, 3), ENT_QUOTES, 'UTF-8') . '</h2>';
            } else {
                echo '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')) . '</p>';
            }
        }
      ?>]]></content:encoded>
    </item>
<?php endforeach; ?>
  </channel>
</rss>
