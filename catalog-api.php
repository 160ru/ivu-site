<?php
/**
 * Публичный API каталога: отдаёт товары JSON-ом.
 *
 * GET catalog-api.php?cat=new|used&page=1&per=24&q=поиск
 *
 * Данные лежат в SQLite рядом с сайтом, вне public_html:
 *   /home/<логин>/<домен>/data/catalog.db
 * Фотографии — в public_html/uploads/products/
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$base = dirname(__DIR__);
$dbFile = $base . '/data/catalog.db';

if (!is_file($dbFile)) {
    echo json_encode(['total' => 0, 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$cat = isset($_GET['cat']) && $_GET['cat'] === 'used' ? 'used' : 'new';
$per = isset($_GET['per']) ? (int) $_GET['per'] : 24;
$per = max(1, min(60, $per));
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$q = mb_substr($q, 0, 80);
$priceMin = isset($_GET['price_min']) ? max(0, (int) $_GET['price_min']) : 0;
$priceMax = isset($_GET['price_max']) ? max(0, (int) $_GET['price_max']) : 0;
$stock = isset($_GET['stock']) && $_GET['stock'] === '1';
$sort = (string) ($_GET['sort'] ?? 'new');

try {
    $pdo = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $where = 'category = :cat';
    $params = [':cat' => $cat];

    if ($q !== '') {
        // ищем по заранее приведённому к нижнему регистру тексту:
        // SQLite не умеет сравнивать кириллицу без учёта регистра
        $where .= ' AND search_text LIKE :q';
        $params[':q'] = '%' . mb_strtolower($q, 'UTF-8') . '%';
    }
    if ($priceMin > 0) {
        $where .= ' AND price >= :pmin';
        $params[':pmin'] = $priceMin;
    }
    if ($priceMax > 0) {
        $where .= ' AND price > 0 AND price <= :pmax';
        $params[':pmax'] = $priceMax;
    }
    if ($stock) {
        $where .= ' AND sold = 0';
    }

    $order = 'sold ASC, created DESC';
    if ($sort === 'price_asc') {
        $order = 'sold ASC, CASE WHEN price > 0 THEN price ELSE 999999999 END ASC, created DESC';
    } elseif ($sort === 'price_desc') {
        $order = 'sold ASC, price DESC, created DESC';
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE ' . $where);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = 'SELECT id, title, price, condition, shown_date, description, avito, sold,
            (SELECT COUNT(*) FROM comments c WHERE c.item_id = items.id AND c.approved = 1) AS comments_count
            FROM items WHERE ' . $where . '
            ORDER BY ' . $order . '
            LIMIT :lim OFFSET :off';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':lim', $per, PDO::PARAM_INT);
    $stmt->bindValue(':off', ($page - 1) * $per, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $photoStmt = $pdo->prepare('SELECT file FROM photos WHERE item_id = :id ORDER BY ord ASC, id ASC');
    $items = [];
    foreach ($rows as $row) {
        $photoStmt->execute([':id' => $row['id']]);
        $photos = array_map(static function ($p) { return $p['file']; }, $photoStmt->fetchAll());
        $items[] = [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'price' => (int) $row['price'],
            'condition' => $row['condition'],
            'date' => $row['shown_date'],
            'desc' => $row['description'],
            'avito' => $row['avito'],
            'sold' => (bool) $row['sold'],
            'photos' => $photos,
            'comments' => (int) $row['comments_count'],
        ];
    }

    echo json_encode([
        'total' => $total,
        'page' => $page,
        'per' => $per,
        'pages' => (int) ceil($total / $per),
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['error' => 'catalog unavailable'], JSON_UNESCAPED_UNICODE);
}
