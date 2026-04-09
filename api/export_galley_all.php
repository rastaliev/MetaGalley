<?php
require_once __DIR__ . '/../src/functions.php';
ensure_session();

$arts = $_SESSION['articles'] ?? [];
if (empty($arts)) {
    header('Location: ../pages/dashboard.php?error=' . urlencode('Нет статей для экспорта.'));
    exit;
}

$issue = $_SESSION['issue'] ?? default_issue_metadata();
$zip = new ZipArchive();
$tmpFile = tempnam(sys_get_temp_dir(), 'galley_all_');
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    header('Location: ../pages/dashboard.php?error=' . urlencode('Не удалось создать ZIP архив.'));
    exit;
}

$usedNames = [];
foreach ($arts as $i => $art) {
    $art = prepare_article_for_export($art);
    $base = galley_filename($art, '');
    if (isset($usedNames[$base])) {
        $usedNames[$base]++;
        $base = $base . '-' . $usedNames[$base];
    } else {
        $usedNames[$base] = 0;
    }
    $num = str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
    $nameWithNum = $num . '-' . $base;

    try {
        $zip->addFromString('html/' . $nameWithNum . '.html', build_galley_html_full($art));
        $zip->addFromString('epub/' . $nameWithNum . '.epub', build_galley_epub_bytes($art));
    } catch (Throwable $e) {
        $zip->close();
        unlink($tmpFile);
        header('Location: ../pages/dashboard.php?error=' . urlencode('Ошибка при создании гранки #' . ($i + 1) . ': ' . $e->getMessage()));
        exit;
    }
}

$zip->close();

$journal = preg_replace('/[^a-zA-Z0-9а-яА-ЯёЁ_-]/u', '-', trim($issue['journal_title_ru'] ?: $issue['journal_title_en'] ?: 'galleys', " \t\n\r"));
$journal = preg_replace('/-+/', '-', $journal) ?: 'galleys';
$year = trim($issue['year'] ?? '') ?: date('Y');
$issueNum = trim($issue['issue'] ?? '') ?: '1';
$filename = $journal . '-' . $year . '-' . $issueNum . '-galleys.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
readfile($tmpFile);
unlink($tmpFile);
exit;

function prepare_article_for_export(array $art): array {
    if (is_array($art['refs_ru'] ?? null)) {
        $art['refs_ru'] = implode('', array_map(fn($r) => '<p>' . h($r) . '</p>', $art['refs_ru']));
    }
    if (is_array($art['refs_en'] ?? null)) {
        $art['refs_en'] = implode('', array_map(fn($r) => '<p>' . h($r) . '</p>', $art['refs_en']));
    }
    return $art;
}
