<?php
require_once __DIR__ . '/../src/functions.php';
ensure_session();

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false]);
    exit;
}

$id = (int)($_POST['id'] ?? $_SESSION['current_article_id'] ?? 0);
$art = get_article_by_id($id);
if ($art === null) {
    echo json_encode(['ok' => false]);
    exit;
}

$order = trim($_POST['blocks_order'] ?? '');
if ($order !== '') {
    $blocksOrder = array_values(array_filter(explode(',', $order)));
    // Применяем новый порядок ко ВСЕМ статьям выпуска
    foreach ($_SESSION['articles'] ?? [] as $i => $a) {
        $_SESSION['articles'][$i]['blocks_order'] = $blocksOrder;
    }
}
echo json_encode(['ok' => true]);
exit;
