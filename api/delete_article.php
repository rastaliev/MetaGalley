<?php
require_once __DIR__ . '/../src/functions.php';
ensure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/dashboard.php#articles');
    exit;
}

$arts = &$_SESSION['articles'];
$ids = [];

if (isset($_POST['id']) && $_POST['id'] !== '') {
    $ids[] = (int)$_POST['id'];
} elseif (!empty($_POST['ids']) && is_array($_POST['ids'])) {
    foreach ($_POST['ids'] as $id) {
        $id = (int)$id;
        if ($id >= 0 && $id < count($arts)) $ids[] = $id;
    }
}

if (empty($ids)) {
    header('Location: ../pages/dashboard.php#articles');
    exit;
}

$ids = array_unique($ids);
rsort($ids);

foreach ($ids as $id) {
    array_splice($arts, $id, 1);
}

$cur = (int)($_SESSION['current_article_id'] ?? 0);
$deletedCount = count(array_filter($ids, fn($i) => $i <= $cur));
$_SESSION['current_article_id'] = empty($arts) ? 0 : max(0, min($cur - $deletedCount, count($arts) - 1));

header('Location: ../pages/dashboard.php?deleted=' . count($ids) . '#articles');
exit;
