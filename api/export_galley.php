<?php
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

$html = build_galley_html_full($art);
$filename = galley_filename($art, 'html');

header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $html;
exit;
