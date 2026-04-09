<?php
require_once __DIR__ . '/../src/functions.php';
ensure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/dashboard.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : ($_SESSION['current_article_id'] ?? 0);
$art = get_article_by_id($id);
$prevEpubCover = is_array($art['epub_cover'] ?? null) ? $art['epub_cover'] : null;
if ($art === null) {
    if (!empty($_POST['ajax_save'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false]);
        exit;
    }
    header('Location: ../pages/dashboard.php');
    exit;
}

$art['title_ru'] = trim($_POST['title_ru'] ?? '');
$art['title_en'] = trim($_POST['title_en'] ?? '');
$art['abstract_ru'] = trim($_POST['abstract_ru'] ?? '');
$art['abstract_en'] = trim($_POST['abstract_en'] ?? '');
$art['body_html'] = trim($_POST['body_html'] ?? '');
$art['extra_info_html'] = trim($_POST['extra_info_html'] ?? '');
$art['extra_info_title'] = trim($_POST['extra_info_title'] ?? '');
$art['authors_info_ru_html'] = trim($_POST['authors_info_ru_html'] ?? '');
$art['authors_info_ru_title'] = trim($_POST['authors_info_ru_title'] ?? '');
$art['authors_info_en_html'] = trim($_POST['authors_info_en_html'] ?? '');
$art['authors_info_en_title'] = trim($_POST['authors_info_en_title'] ?? '');

$kwRu = trim(strip_tags($_POST['keywords_ru'] ?? ''));
$kwEn = trim(strip_tags($_POST['keywords_en'] ?? ''));
$art['keywords_ru'] = array_values(array_filter(array_map('trim', preg_split('/[,;]+/u', $kwRu))));
$art['keywords_en'] = array_values(array_filter(array_map('trim', preg_split('/[,;]+/u', $kwEn))));

// Авторы из JSON
$authorsJson = trim($_POST['authors_json'] ?? '');
if ($authorsJson !== '') {
    $decoded = json_decode($authorsJson, true);
    if (is_array($decoded)) {
        $art['authors'] = [];
        foreach ($decoded as $au) {
            if (!is_array($au)) continue;
            $art['authors'][] = [
                'surname_ru' => trim($au['surname_ru'] ?? ''),
                'given_ru' => trim($au['given_ru'] ?? ''),
                'surname_en' => trim($au['surname_en'] ?? ''),
                'given_en' => trim($au['given_en'] ?? ''),
                'email' => trim($au['email'] ?? ''),
                'orcid' => trim($au['orcid'] ?? ''),
                'aff_ru' => trim($au['aff_ru'] ?? ''),
                'aff_en' => trim($au['aff_en'] ?? ''),
                'city_country_ru' => trim($au['city_country_ru'] ?? ''),
                'city_country_en' => trim($au['city_country_en'] ?? ''),
                'corresp' => !empty($au['corresp']),
            ];
        }
    }
}

// Список литературы — храним как HTML (редактируемый блок)
$art['refs_ru'] = trim($_POST['refs_ru'] ?? '');
$art['refs_en'] = trim($_POST['refs_en'] ?? '');

// Порядок блоков — применяется ко всем статьям выпуска
$order = trim($_POST['blocks_order'] ?? '');
if ($order !== '') {
    $blocksOrder = array_values(array_filter(explode(',', $order)));
    $art['blocks_order'] = $blocksOrder;
    foreach ($_SESSION['articles'] ?? [] as $i => $a) {
        $_SESSION['articles'][$i]['blocks_order'] = $blocksOrder;
    }
}

// Метаданные
$metaJson = trim($_POST['meta_json'] ?? '');
if ($metaJson !== '') {
    $m = json_decode($metaJson, true);
    if (is_array($m)) {
        $art['doi'] = trim($m['doi'] ?? '');
        $art['edn'] = trim($m['edn'] ?? '');
        $art['url'] = trim($m['url'] ?? '');
        $art['volume'] = trim($m['volume'] ?? '');
        $art['issue'] = trim($m['issue'] ?? '');
        $art['fpage'] = trim($m['fpage'] ?? '');
        $art['lpage'] = trim($m['lpage'] ?? '');
        $art['pub_date'] = trim($m['pub_date'] ?? '');
        $art['history'] = [
            'received' => trim($m['history_received'] ?? ''),
            'accepted' => trim($m['history_accepted'] ?? ''),
            'revised' => trim($m['history_revised'] ?? ''),
        ];
        $art['article_categories_ru'] = array_values(array_filter(array_map('trim', preg_split('/[;]+/u', $m['categories_ru'] ?? ''))));
        $art['article_categories_en'] = array_values(array_filter(array_map('trim', preg_split('/[;]+/u', $m['categories_en'] ?? ''))));
        $art['funding_ru'] = normalize_string_list($m['funding_ru'] ?? '');
        $art['funding_en'] = normalize_string_list($m['funding_en'] ?? '');
        $art['permissions'] = [
            'copyright_year' => trim($m['copyright_year'] ?? ''),
            'copyright_holder_ru' => trim($m['copyright_ru'] ?? ''),
            'copyright_holder_en' => trim($m['copyright_en'] ?? ''),
            'license' => trim($m['license'] ?? ''),
            'license_url' => trim($m['license_url'] ?? ''),
        ];
    }
}

// Уровни заголовков блоков (H2/H3)
$levelsJson = trim($_POST['blocks_heading_levels'] ?? '');
if ($levelsJson !== '') {
    $decoded = json_decode($levelsJson, true);
    if (is_array($decoded)) {
        $art['blocks_heading_levels'] = $decoded;
    }
}

// Пользовательские названия блоков — применяются ко всем статьям выпуска
$labelsJson = trim($_POST['blocks_labels'] ?? '');
if ($labelsJson !== '') {
    $decoded = json_decode($labelsJson, true);
    if (is_array($decoded)) {
        $newLabels = array_filter($decoded, fn($v) => is_string($v) && $v !== '');
        $art['blocks_labels'] = $newLabels;
        $_SESSION['issue']['blocks_labels'] = $newLabels;
        foreach ($_SESSION['articles'] ?? [] as $i => $a) {
            $_SESSION['articles'][$i]['blocks_labels'] = $newLabels;
        }
    }
}

// Группы блоков (для перетаскивания группами)
$groupsJson = trim($_POST['blocks_groups'] ?? '');
if ($groupsJson !== '') {
    $decoded = json_decode($groupsJson, true);
    if (is_array($decoded)) {
        $art['blocks_groups'] = array_values(array_filter($decoded, fn($g) => is_array($g) && count($g) >= 2));
    }
}

// Обложка EPUB (base64 в JSON или сброс)
if (($_POST['epub_cover_clear'] ?? '') === '1') {
    $art['epub_cover'] = null;
} else {
    $coverRaw = trim($_POST['epub_cover_json'] ?? '');
    if ($coverRaw !== '') {
        $c = json_decode($coverRaw, true);
        if (is_array($c) && !empty($c['data']) && !empty($c['mime'])) {
            $bin = base64_decode((string)$c['data'], true);
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if ($bin !== false && strlen($bin) < 6 * 1024 * 1024 && in_array((string)$c['mime'], $allowed, true)) {
                $fn = preg_replace('/[^a-zA-Z0-9._-]/u', '', (string)($c['filename'] ?? 'cover'));
                $art['epub_cover'] = [
                    'mime' => (string)$c['mime'],
                    'data' => base64_encode($bin),
                    'filename' => $fn !== '' ? $fn : 'cover',
                ];
            }
        }
    } else {
        $art['epub_cover'] = $prevEpubCover;
    }
}

$_SESSION['articles'][$id] = $art;

if (!empty($_POST['ajax_save'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}
header('Location: ../pages/editor.php?id=' . $id . '&saved=1');
exit;
