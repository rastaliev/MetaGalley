<?php
ob_start();
require_once __DIR__ . '/../src/functions.php';
ensure_session();

$id = (int)($_GET['id'] ?? $_SESSION['current_article_id'] ?? 0);
$art = get_article_by_id($id);
if ($art === null) {
    header('Location: ../pages/dashboard.php');
    exit;
}
if (is_array($art['refs_ru'] ?? null)) {
    $art['refs_ru'] = implode('', array_map(fn($r) => '<p>' . h($r) . '</p>', $art['refs_ru']));
}
if (is_array($art['refs_en'] ?? null)) {
    $art['refs_en'] = implode('', array_map(fn($r) => '<p>' . h($r) . '</p>', $art['refs_en']));
}

$bytes = build_galley_epub_bytes($art);
$filename = galley_filename($art, 'epub');

ob_end_clean();
header('Content-Type: application/epub+zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($bytes));
echo $bytes;
exit;
