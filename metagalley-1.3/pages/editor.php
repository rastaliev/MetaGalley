<?php
require_once __DIR__ . '/../src/functions.php';
ensure_session();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$art = get_article_by_id($id);
if ($art === null) {
    header('Location: dashboard.php');
    exit;
}
$_SESSION['current_article_id'] = $id;
$total = count($_SESSION['articles'] ?? []);
$art['authors'] = $art['authors'] ?? [];
$art['keywords_ru'] = $art['keywords_ru'] ?? [];
$art['article_categories_ru'] = $art['article_categories_ru'] ?? [];
$art['article_categories_en'] = $art['article_categories_en'] ?? [];
$art['funding_ru'] = $art['funding_ru'] ?? [];
$art['funding_en'] = $art['funding_en'] ?? [];
$art['history'] = $art['history'] ?? ['received' => '', 'accepted' => '', 'revised' => ''];
$art['permissions'] = $art['permissions'] ?? ['copyright_year' => '', 'copyright_holder_ru' => '', 'copyright_holder_en' => '', 'license' => '', 'license_url' => ''];
$art['keywords_en'] = $art['keywords_en'] ?? [];
// Миграция: refs как массив -> HTML
$refsRu = $art['refs_ru'] ?? '';
$refsEn = $art['refs_en'] ?? '';
if (is_array($refsRu)) {
    $art['refs_ru'] = implode('', array_map(fn($r) => '<p>' . h($r) . '</p>', $refsRu));
} else {
    $art['refs_ru'] = $refsRu ?: '';
}
if (is_array($refsEn)) {
    $art['refs_en'] = implode('', array_map(fn($r) => '<p>' . h($r) . '</p>', $refsEn));
} else {
    $art['refs_en'] = $refsEn ?: '';
}
$art['extra_info_html'] = $art['extra_info_html'] ?? '';
$art['extra_info_title'] = $art['extra_info_title'] ?? '';
$art['authors_info_ru_html'] = $art['authors_info_ru_html'] ?? '';
$art['authors_info_ru_title'] = $art['authors_info_ru_title'] ?? '';
$art['authors_info_en_html'] = $art['authors_info_en_html'] ?? '';
$art['authors_info_en_title'] = $art['authors_info_en_title'] ?? '';
$art['blocks_order'] = $art['blocks_order'] ?? get_canonical_blocks_order();
$art['blocks_heading_levels'] = $art['blocks_heading_levels'] ?? [];
$art['blocks_labels'] = ($_SESSION['issue']['blocks_labels'] ?? []) + ($art['blocks_labels'] ?? []);
$art['blocks_groups'] = $art['blocks_groups'] ?? [];
// Миграция: refs -> refs_ru, refs_en
if (in_array('refs', $art['blocks_order'], true)) {
    $art['blocks_order'] = array_map(fn($b) => $b === 'refs' ? ['refs_ru', 'refs_en'] : [$b], $art['blocks_order']);
    $art['blocks_order'] = array_merge(...$art['blocks_order']);
}
// Миграция: добавляем meta блоки если их нет (meta_en перед meta_ru)
$toAdd = [];
if (!in_array('meta', $art['blocks_order'], true)) $toAdd[] = 'meta';
if (!in_array('meta_en', $art['blocks_order'], true)) $toAdd[] = 'meta_en';
if (!in_array('meta_ru', $art['blocks_order'], true)) $toAdd[] = 'meta_ru';
if (!empty($toAdd)) {
    $idx = array_search('title_en', $art['blocks_order']);
    if ($idx !== false) array_splice($art['blocks_order'], $idx + 1, 0, $toAdd);
    else array_splice($art['blocks_order'], 0, 0, $toAdd);
}
// Миграция: authors без authors_ru/authors_en — добавляем display-блоки
if (!in_array('authors_ru', $art['blocks_order'], true) && in_array('authors', $art['blocks_order'], true)) {
    $idx = array_search('authors', $art['blocks_order']);
    array_splice($art['blocks_order'], $idx + 1, 0, ['authors_en', 'authors_ru']);
}
// Миграция: authors_ru/authors_en без authors — добавляем блок редактирования
if (!in_array('authors', $art['blocks_order'], true) && (in_array('authors_ru', $art['blocks_order'], true) || in_array('authors_en', $art['blocks_order'], true))) {
    $idx = array_search('authors_ru', $art['blocks_order']);
    if ($idx === false) $idx = array_search('authors_en', $art['blocks_order']);
    if ($idx !== false) array_splice($art['blocks_order'], $idx, 0, ['authors']);
}
// Миграция: добавляем блок доп. информации если его нет
if (!in_array('extra_info', $art['blocks_order'], true)) {
    $idx = array_search('refs_ru', $art['blocks_order']);
    if ($idx !== false) array_splice($art['blocks_order'], $idx, 0, ['extra_info']);
    elseif (in_array('body', $art['blocks_order'], true)) {
        $idx = array_search('body', $art['blocks_order']);
        array_splice($art['blocks_order'], $idx + 1, 0, ['extra_info']);
    } else {
        $art['blocks_order'][] = 'extra_info';
    }
}
// Миграция: добавляем блоки «Информация об авторах» после списка литературы
if (!in_array('authors_info_ru', $art['blocks_order'], true) || !in_array('authors_info_en', $art['blocks_order'], true)) {
    $toAdd = [];
    if (!in_array('authors_info_en', $art['blocks_order'], true)) $toAdd[] = 'authors_info_en';
    if (!in_array('authors_info_ru', $art['blocks_order'], true)) $toAdd[] = 'authors_info_ru';
    if (!empty($toAdd)) {
        $idx = array_search('refs_en', $art['blocks_order']);
        if ($idx !== false) array_splice($art['blocks_order'], $idx + 1, 0, $toAdd);
        else $art['blocks_order'] = array_merge($art['blocks_order'], $toAdd);
    }
}

$blockLabels = [
    'title_ru' => 'Название (RU)',
    'title_en' => 'Название (EN)',
    'meta' => 'Метаданные статьи',
    'meta_ru' => 'Метаданные RU',
    'meta_en' => 'Метаданные EN',
    'authors' => 'Авторы (редактирование)',
    'authors_ru' => 'Авторы (RU)',
    'authors_en' => 'Авторы (EN)',
    'abstract_ru' => 'Аннотация (RU)',
    'abstract_en' => 'Аннотация (EN)',
    'keywords_ru' => 'Ключевые слова (RU)',
    'keywords_en' => 'Ключевые слова (EN)',
    'body' => 'Текст статьи',
    'extra_info' => 'Доп. информация',
    'refs_ru' => 'Список литературы (RU)',
    'refs_en' => 'Список литературы (EN)',
    'authors_info_ru' => 'Информация об авторах (RU)',
    'authors_info_en' => 'Информация об авторах (EN)',
];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Редактор гранки — MetaGalley <?= h(METAGALLEY_VERSION) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.5.0/tinymce.min.js" referrerpolicy="origin"></script>
    <script src="https://www.wiris.net/demo/plugins/app/WIRISplugins.js?viewer=image" referrerpolicy="origin"></script>
</head>
<body>
<div class="wrap wrap-wide">
    <div class="breadcrumbs">
        <a href="../index.php">Главная</a>
        <span class="breadcrumbs-separator">→</span>
        <a href="dashboard.php#articles">Рабочий стол</a>
        <span class="breadcrumbs-separator">→</span>
        <span>Редактор гранки #<?= ($id + 1) ?></span>
    </div>

    <div class="topbar">
        <a class="btn btn-light" href="dashboard.php#articles">← К рабочему столу</a>
        <a class="btn btn-manual-link" href="../manual.php">Методические рекомендации</a>
        <button type="submit" form="galleyForm" class="btn btn-primary">Сохранить</button>
        <a class="btn btn-primary" href="#" id="exportHtmlBtn">Скачать HTML</a>
        <a class="btn btn-primary" href="#" id="exportEpubBtn">Скачать EPUB</a>
        <a href="../api/export_backup.php" class="btn btn-secondary">Резервная копия</a>
        <a class="btn btn-light btn-danger" href="../api/reset_session.php" onclick="return confirm('Сбросить сессию? Все данные будут потеряны.');">Сбросить сессию</a>
    </div>

    <?php if (isset($_GET['saved'])): ?>
        <div class="success" style="margin-bottom: 16px;">Изменения сохранены.</div>
    <?php endif; ?>

    <h1>Редактор гранки #<?= ($id + 1) ?> из <?= $total ?></h1>

    <div class="article-nav">
        <?php if ($id > 0): ?>
            <a href="editor.php?id=<?= ($id - 1) ?>" class="btn btn-light">← Предыдущая статья</a>
        <?php endif; ?>
        <select onchange="if(this.value) window.location.href='editor.php?id=' + this.value">
            <option value="">Перейти к статье...</option>
            <?php foreach ($_SESSION['articles'] as $i => $a): ?>
                <option value="<?= $i ?>" <?= $i === $id ? ' selected' : '' ?>>
                    Статья <?= ($i + 1) ?>: <?= h(mb_strimwidth(($a['title_ru'] ?? $a['title_en'] ?? 'Без названия') ?: 'Без названия', 0, 60, '…', 'UTF-8')) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($id < $total - 1): ?>
            <a href="editor.php?id=<?= ($id + 1) ?>" class="btn btn-light">Следующая статья →</a>
        <?php endif; ?>
    </div>

    <p class="muted">Перетаскивайте блоки для изменения порядка (сохраняется автоматически). Редактируйте текст напрямую. Кнопки форматирования — у каждого блока.</p>

    <div class="edit-layout">
        <div class="edit-left">
            <form method="post" action="../api/save_article.php?id=<?= $id ?>" id="galleyForm">
                <input type="hidden" name="title_ru" id="hid_title_ru">
                <input type="hidden" name="title_en" id="hid_title_en">
                <input type="hidden" name="abstract_ru" id="hid_abstract_ru">
                <input type="hidden" name="abstract_en" id="hid_abstract_en">
                <input type="hidden" name="keywords_ru" id="hid_keywords_ru">
                <input type="hidden" name="keywords_en" id="hid_keywords_en">
                <input type="hidden" name="body_html" id="hid_body_html">
                <input type="hidden" name="extra_info_html" id="hid_extra_info_html">
                <input type="hidden" name="extra_info_title" id="hid_extra_info_title">
                <input type="hidden" name="authors_json" id="hid_authors_json">
                <input type="hidden" name="refs_ru" id="hid_refs_ru">
                <input type="hidden" name="refs_en" id="hid_refs_en">
                <input type="hidden" name="authors_info_ru_html" id="hid_authors_info_ru_html">
                <input type="hidden" name="authors_info_ru_title" id="hid_authors_info_ru_title">
                <input type="hidden" name="authors_info_en_html" id="hid_authors_info_en_html">
                <input type="hidden" name="authors_info_en_title" id="hid_authors_info_en_title">
                <input type="hidden" name="blocks_heading_levels" id="hid_blocks_heading_levels">
                <input type="hidden" name="meta_json" id="hid_meta_json">
                <input type="hidden" name="epub_cover_json" id="hid_epub_cover_json" value="">
                <input type="hidden" name="epub_cover_clear" id="hid_epub_cover_clear" value="0">

                <?php $issueData = $_SESSION['issue'] ?? default_issue_metadata(); $logoUrl = trim($issueData['galley_logo_url'] ?? ''); $logoSize = (int)($issueData['galley_logo_size'] ?? 88); $logoSize = $logoSize >= 40 && $logoSize <= 200 ? $logoSize : 88; ?>
                <div class="galley-block galley-block-logo" id="logoBlock" data-block="logo">
                    <div class="block-header"><span class="block-label">Логотип журнала</span></div>
                    <div class="block-body">
                        <?php if ($logoUrl !== ''): ?>
                        <p class="muted" style="margin-bottom: 8px; font-size: 0.9em;">URL логотипа задаётся в <a href="dashboard.php">Dashboard</a> → Оформление гранки. Здесь можно менять только размер.</p>
                        <div class="logo-preview-wrap" style="margin-bottom: 12px;">
                            <img src="<?= h($logoUrl) ?>" alt="Логотип" class="logo-preview-img" style="height: <?= $logoSize ?>px; width: auto; max-width: 800px; object-fit: contain;">
                        </div>
                        <?php else: ?>
                        <p class="muted" style="margin-bottom: 12px;">Логотип не задан. Укажите URL в <a href="dashboard.php">Dashboard</a> → Оформление гранки.</p>
                        <?php endif; ?>
                        <div class="field-row" style="align-items: center; gap: 12px;">
                            <label class="label-text" for="logoSizeSlider">Размер: <span id="logoSizeValue"><?= $logoSize ?></span> px</label>
                            <input type="range" id="logoSizeSlider" min="40" max="200" step="4" value="<?= $logoSize ?>" style="flex: 1; min-width: 120px;">
                        </div>
                    </div>
                </div>

                <div class="galley-block collapsed" id="epubCoverBlock" data-block="epub_cover">
                    <div class="block-header"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть" aria-label="Свернуть">▼</button><span class="block-label">Обложка EPUB</span></div>
                    <div class="block-body">
                        <p class="muted" style="font-size: 0.9em; margin-bottom: 10px;">Для электронной книги: вертикальная обложка в пропорции <strong>3∶4</strong> (например <strong>900×1200</strong> или <strong>1200×1600</strong> px). Форматы: JPEG, PNG, GIF, WebP; до 6 МБ.</p>
                        <div id="epubCoverPreviewWrap" style="margin-bottom: 10px; display: none;">
                            <img id="epubCoverPreview" src="" alt="Обложка EPUB" style="max-width: 220px; max-height: 293px; border-radius: 8px; border: 1px solid var(--border, #ddd); object-fit: contain; background: #f5f5f5;">
                        </div>
                        <div class="field-row" style="flex-wrap: wrap; gap: 10px; align-items: center;">
                            <input type="file" id="epubCoverFile" accept="image/jpeg,image/png,image/gif,image/webp" style="max-width: 100%;">
                            <button type="button" class="btn btn-small btn-secondary" id="epubCoverRemove" style="display: none;">Убрать обложку</button>
                        </div>
                    </div>
                </div>

                <div id="blocksContainer">
                    <?php foreach ($art['blocks_order'] as $bid): ?>
                        <?php if ($bid === 'title_ru'): ?>
                        <div class="galley-block collapsed" data-block="title_ru" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть" aria-label="Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="title_ru" value="<?= h($art['blocks_labels']['title_ru'] ?? '') ?>" placeholder="<?= h($blockLabels['title_ru']) ?>" title="Переименовать блок"></div>
                            <div class="block-body">
                            <div class="block-toolbar">
                                <button type="button" class="btn btn-small btn-light" data-cmd="bold" title="Жирный">B</button>
                                <button type="button" class="btn btn-small btn-light" data-cmd="italic" title="Курсив">I</button>
                                <button type="button" class="btn btn-small btn-light" data-cmd="formatBlock" data-value="h2">H2</button>
                                <button type="button" class="btn btn-small btn-light" data-cmd="formatBlock" data-value="p">P</button>
                            </div>
                            <div contenteditable="true" name="title_ru" class="block-content"><?= $art['title_ru'] ?></div>
                            </div>
                        </div>
                        <?php elseif ($bid === 'title_en'): ?>
                        <div class="galley-block collapsed" data-block="title_en" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть" aria-label="Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="title_en" value="<?= h($art['blocks_labels']['title_en'] ?? '') ?>" placeholder="<?= h($blockLabels['title_en']) ?>" title="Переименовать блок"></div>
                            <div class="block-body">
                            <div class="block-toolbar">
                                <button type="button" class="btn btn-small btn-light" data-cmd="bold">B</button>
                                <button type="button" class="btn btn-small btn-light" data-cmd="italic">I</button>
                                <button type="button" class="btn btn-small btn-light" data-cmd="formatBlock" data-value="h2">H2</button>
                                <button type="button" class="btn btn-small btn-light" data-cmd="formatBlock" data-value="p">P</button>
                            </div>
                            <div contenteditable="true" name="title_en" class="block-content"><?= $art['title_en'] ?></div>
                            </div>
                        </div>
                        <?php elseif ($bid === 'meta'): ?>
                        <div class="galley-block galley-block-meta collapsed" data-block="meta" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="meta" value="<?= h($art['blocks_labels']['meta'] ?? '') ?>" placeholder="<?= h($blockLabels['meta']) ?>" title="Переименовать блок"></div>
                            <div class="block-body">
                            <div class="meta-fields">
                                <div class="meta-row"><label>DOI:</label> <input type="text" name="meta_doi" value="<?= h($art['doi'] ?? '') ?>" placeholder="10.1234/example"> <label>EDN:</label> <input type="text" name="meta_edn" value="<?= h($art['edn'] ?? '') ?>" placeholder="ELibrary ID"></div>
                                <div class="meta-row"><label>URL:</label> <input type="url" name="meta_url" value="<?= h($art['url'] ?? '') ?>" placeholder="https://..."></div>
                                <div class="meta-row"><label>Том:</label> <input type="text" name="meta_volume" value="<?= h($art['volume'] ?? '') ?>"> <label>Выпуск:</label> <input type="text" name="meta_issue" value="<?= h($art['issue'] ?? '') ?>"> <label>Стр.:</label> <input type="text" name="meta_fpage" value="<?= h($art['fpage'] ?? '') ?>" style="width:50px">–<input type="text" name="meta_lpage" value="<?= h($art['lpage'] ?? '') ?>" style="width:50px"></div>
                                <div class="meta-row"><label>Поступила:</label> <input type="text" name="meta_history_received" value="<?= h($art['history']['received'] ?? '') ?>" placeholder="YYYY-MM-DD"> <label>Исправлена:</label> <input type="text" name="meta_history_revised" value="<?= h($art['history']['revised'] ?? '') ?>"> <label>Принята:</label> <input type="text" name="meta_history_accepted" value="<?= h($art['history']['accepted'] ?? '') ?>"> <label>Опубликована:</label> <input type="text" name="meta_pub_date" value="<?= h($art['pub_date'] ?? '') ?>"></div>
                                <div class="meta-row"><label>Рубрика (RU):</label> <input type="text" name="meta_categories_ru" value="<?= h(implode('; ', $art['article_categories_ru'] ?? [])) ?>" placeholder="через ;" style="flex:1"></div>
                                <div class="meta-row"><label>Рубрика (EN):</label> <input type="text" name="meta_categories_en" value="<?= h(implode('; ', $art['article_categories_en'] ?? [])) ?>" placeholder="separated by ;" style="flex:1"></div>
                                <div class="meta-row"><label>Финансирование (RU):</label> <textarea name="meta_funding_ru" rows="2" placeholder="каждая строка — отдельное"><?= h(implode("\n", $art['funding_ru'] ?? [])) ?></textarea></div>
                                <div class="meta-row"><label>Финансирование (EN):</label> <textarea name="meta_funding_en" rows="2"><?= h(implode("\n", $art['funding_en'] ?? [])) ?></textarea></div>
                                <div class="meta-row"><label>© Год:</label> <input type="text" name="meta_copyright_year" value="<?= h($art['permissions']['copyright_year'] ?? '') ?>"> <label>Правообладатель (RU):</label> <input type="text" name="meta_copyright_ru" value="<?= h($art['permissions']['copyright_holder_ru'] ?? '') ?>"> <label>(EN):</label> <input type="text" name="meta_copyright_en" value="<?= h($art['permissions']['copyright_holder_en'] ?? '') ?>"></div>
                                <div class="meta-row"><label>Лицензия (быстрый выбор):</label> <select id="meta_license_select" onchange="updateLicenseFromSelect(this.value)" style="width: auto; max-width: 280px;">
                                    <option value="">— выберите или введите вручную ниже —</option>
                                    <option value="CC BY 4.0"<?= (($art['permissions']['license'] ?? '') === 'CC BY 4.0') ? ' selected' : '' ?>>CC BY 4.0</option>
                                    <option value="CC BY 3.0"<?= (($art['permissions']['license'] ?? '') === 'CC BY 3.0') ? ' selected' : '' ?>>CC BY 3.0</option>
                                    <option value="CC BY-SA 4.0"<?= (($art['permissions']['license'] ?? '') === 'CC BY-SA 4.0') ? ' selected' : '' ?>>CC BY-SA 4.0</option>
                                    <option value="CC BY-SA 3.0"<?= (($art['permissions']['license'] ?? '') === 'CC BY-SA 3.0') ? ' selected' : '' ?>>CC BY-SA 3.0</option>
                                    <option value="CC BY-NC 4.0"<?= (($art['permissions']['license'] ?? '') === 'CC BY-NC 4.0') ? ' selected' : '' ?>>CC BY-NC 4.0</option>
                                    <option value="CC BY-NC 3.0"<?= (($art['permissions']['license'] ?? '') === 'CC BY-NC 3.0') ? ' selected' : '' ?>>CC BY-NC 3.0</option>
                                    <option value="CC BY-NC-SA 4.0"<?= (($art['permissions']['license'] ?? '') === 'CC BY-NC-SA 4.0') ? ' selected' : '' ?>>CC BY-NC-SA 4.0</option>
                                    <option value="CC BY-NC-SA 3.0"<?= (($art['permissions']['license'] ?? '') === 'CC BY-NC-SA 3.0') ? ' selected' : '' ?>>CC BY-NC-SA 3.0</option>
                                    <option value="CC BY-NC-ND 4.0"<?= (($art['permissions']['license'] ?? '') === 'CC BY-NC-ND 4.0') ? ' selected' : '' ?>>CC BY-NC-ND 4.0</option>
                                    <option value="CC BY-NC-ND 3.0"<?= (($art['permissions']['license'] ?? '') === 'CC BY-NC-ND 3.0') ? ' selected' : '' ?>>CC BY-NC-ND 3.0</option>
                                    <option value="CC BY-ND 4.0"<?= (($art['permissions']['license'] ?? '') === 'CC BY-ND 4.0') ? ' selected' : '' ?>>CC BY-ND 4.0</option>
                                    <option value="CC BY-ND 3.0"<?= (($art['permissions']['license'] ?? '') === 'CC BY-ND 3.0') ? ' selected' : '' ?>>CC BY-ND 3.0</option>
                                    <option value="custom">Иное</option>
                                </select></div>
                                <div class="meta-row"><label>Текст лицензии:</label> <input type="text" id="meta_license" name="meta_license" value="<?= h($art['permissions']['license'] ?? '') ?>" placeholder="CC BY 4.0"> <label>URL:</label> <input type="url" id="meta_license_url" name="meta_license_url" value="<?= h($art['permissions']['license_url'] ?? '') ?>"></div>
                            </div>
                            </div>
                        </div>
                        <?php elseif ($bid === 'meta_ru'): ?>
                        <?php $hl = $art['blocks_heading_levels'][$bid] ?? '2'; ?>
                        <div class="galley-block galley-block-display collapsed" data-block="meta_ru" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="meta_ru" value="<?= h($art['blocks_labels']['meta_ru'] ?? '') ?>" placeholder="<?= h($blockLabels['meta_ru']) ?>" title="Переименовать блок"><select class="block-heading-level" data-block="meta_ru"><option value="2"<?= ($art['blocks_heading_levels'][$bid] ?? '2')==='2'?' selected':''?>>H2</option><option value="3"<?= ($art['blocks_heading_levels'][$bid] ?? '2')==='3'?' selected':''?>>H3</option></select></div>
                            <div class="block-body">
                            <div class="block-content block-meta-display" data-lang="ru"><?php
                                echo render_meta_block($art, 'ru');
                            ?></div>
                            </div>
                        </div>
                        <?php elseif ($bid === 'meta_en'): ?>
                        <?php $hl = $art['blocks_heading_levels'][$bid] ?? '2'; ?>
                        <div class="galley-block galley-block-display collapsed" data-block="meta_en" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="meta_en" value="<?= h($art['blocks_labels']['meta_en'] ?? '') ?>" placeholder="<?= h($blockLabels['meta_en']) ?>" title="Переименовать блок"><select class="block-heading-level" data-block="meta_en"><option value="2"<?= ($art['blocks_heading_levels'][$bid] ?? '2')==='2'?' selected':''?>>H2</option><option value="3"<?= ($art['blocks_heading_levels'][$bid] ?? '2')==='3'?' selected':''?>>H3</option></select></div>
                            <div class="block-body">
                            <div class="block-content block-meta-display" data-lang="en"><?php
                                echo render_meta_block($art, 'en');
                            ?></div>
                            </div>
                        </div>
                        <?php elseif ($bid === 'authors'): ?>
                        <div class="galley-block galley-block-authors collapsed" data-block="authors" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="authors" value="<?= h($art['blocks_labels']['authors'] ?? '') ?>" placeholder="<?= h($blockLabels['authors']) ?>" title="Переименовать блок"><button type="button" class="btn btn-small btn-secondary add-author-btn">+ Добавить автора</button></div>
                            <div class="block-body">
                            <div class="authors-list" id="authorsList">
                                <?php foreach ($art['authors'] as $i => $a): ?>
                                <div class="author-card" data-index="<?= $i ?>">
                                    <div class="author-fields">
                                        <div class="author-row"><label>ФИО (RU):</label> <input type="text" name="authors[<?= $i ?>][surname_ru]" value="<?= h($a['surname_ru'] ?? '') ?>" placeholder="Фамилия"> <input type="text" name="authors[<?= $i ?>][given_ru]" value="<?= h($a['given_ru'] ?? '') ?>" placeholder="Имя Отчество"> <label class="author-corresp"><input type="checkbox" name="authors[<?= $i ?>][corresp]" value="1"<?= !empty($a['corresp']) ? ' checked' : '' ?>> * корресп.</label></div>
                                        <div class="author-row"><label>ФИО (EN):</label> <input type="text" name="authors[<?= $i ?>][given_en]" value="<?= h($a['given_en'] ?? '') ?>" placeholder="Given names"> <input type="text" name="authors[<?= $i ?>][surname_en]" value="<?= h($a['surname_en'] ?? '') ?>" placeholder="Surname"></div>
                                        <div class="author-row"><label>Email:</label> <input type="text" name="authors[<?= $i ?>][email]" value="<?= h($a['email'] ?? '') ?>" style="min-width:220px"></div>
                                        <div class="author-row"><label>ORCID:</label> <input type="text" name="authors[<?= $i ?>][orcid]" value="<?= h($a['orcid'] ?? '') ?>" placeholder="0000-0002-1234-5678"></div>
                                        <div class="author-row"><label>Аффилиация (RU):</label> <input type="text" name="authors[<?= $i ?>][aff_ru]" value="<?= h($a['aff_ru'] ?? '') ?>" style="flex:1"></div>
                                        <div class="author-row"><label>Аффилиация (EN):</label> <input type="text" name="authors[<?= $i ?>][aff_en]" value="<?= h($a['aff_en'] ?? '') ?>" style="flex:1"></div>
                                        <div class="author-row"><label>Город, страна (RU):</label> <input type="text" name="authors[<?= $i ?>][city_country_ru]" value="<?= h($a['city_country_ru'] ?? '') ?>" placeholder="(Москва, Россия)" style="flex:1"></div>
                                        <div class="author-row"><label>Город, страна (EN):</label> <input type="text" name="authors[<?= $i ?>][city_country_en]" value="<?= h($a['city_country_en'] ?? '') ?>" placeholder="(Moscow, Russia)" style="flex:1"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php if (empty($art['authors'])): ?>
                                <div class="author-card" data-index="0">
                                    <div class="author-fields">
                                        <div class="author-row"><label>ФИО (RU):</label> <input type="text" name="authors[0][surname_ru]" placeholder="Фамилия"> <input type="text" name="authors[0][given_ru]" placeholder="Имя Отчество"> <label class="author-corresp"><input type="checkbox" name="authors[0][corresp]" value="1"> * корресп.</label></div>
                                        <div class="author-row"><label>ФИО (EN):</label> <input type="text" name="authors[0][given_en]" placeholder="Given names"> <input type="text" name="authors[0][surname_en]" placeholder="Surname"></div>
                                        <div class="author-row"><label>Email:</label> <input type="text" name="authors[0][email]" style="min-width:220px"></div>
                                        <div class="author-row"><label>ORCID:</label> <input type="text" name="authors[0][orcid]" placeholder="0000-0002-1234-5678"></div>
                                        <div class="author-row"><label>Аффилиация (RU):</label> <input type="text" name="authors[0][aff_ru]" style="flex:1"></div>
                                        <div class="author-row"><label>Аффилиация (EN):</label> <input type="text" name="authors[0][aff_en]" style="flex:1"></div>
                                        <div class="author-row"><label>Город, страна (RU):</label> <input type="text" name="authors[0][city_country_ru]" placeholder="(Москва, Россия)" style="flex:1"></div>
                                        <div class="author-row"><label>Город, страна (EN):</label> <input type="text" name="authors[0][city_country_en]" placeholder="(Moscow, Russia)" style="flex:1"></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            </div>
                        </div>
                        <?php elseif ($bid === 'authors_ru'): ?>
                        <?php $hl = $art['blocks_heading_levels'][$bid] ?? '2'; ?>
                        <div class="galley-block galley-block-display collapsed" data-block="authors_ru" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="authors_ru" value="<?= h($art['blocks_labels']['authors_ru'] ?? '') ?>" placeholder="<?= h($blockLabels['authors_ru']) ?>" title="Переименовать блок"><select class="block-heading-level" data-block="authors_ru" title="Уровень заголовка"><option value="2"<?= $hl==='2'?' selected':''?>>H2</option><option value="3"<?= $hl==='3'?' selected':''?>>H3</option></select></div>
                            <div class="block-body">
                            <div class="block-content block-authors-display" data-lang="ru">
                                <?php
                                $out = '';
                                foreach ($art['authors'] as $a) {
                                    $out .= format_author_block($a, false, false, $issueData);
                                }
                                echo $out ?: '<p class="muted">Нет данных</p>';
                                ?>
                            </div>
                            </div>
                        </div>
                        <?php elseif ($bid === 'authors_en'): ?>
                        <?php $hl = $art['blocks_heading_levels'][$bid] ?? '2'; ?>
                        <div class="galley-block galley-block-display collapsed" data-block="authors_en" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="authors_en" value="<?= h($art['blocks_labels']['authors_en'] ?? '') ?>" placeholder="<?= h($blockLabels['authors_en']) ?>" title="Переименовать блок"><select class="block-heading-level" data-block="authors_en" title="Уровень заголовка"><option value="2"<?= $hl==='2'?' selected':''?>>H2</option><option value="3"<?= $hl==='3'?' selected':''?>>H3</option></select></div>
                            <div class="block-body">
                            <div class="block-content block-authors-display" data-lang="en">
                                <?php
                                $out = '';
                                foreach ($art['authors'] as $a) {
                                    $out .= format_author_block($a, true, false, $issueData);
                                }
                                echo $out ?: '<p class="muted">No data</p>';
                                ?>
                            </div>
                            </div>
                        </div>
                        <?php elseif ($bid === 'abstract_ru'): ?>
                        <?php $hl = $art['blocks_heading_levels'][$bid] ?? '2'; ?>
                        <div class="galley-block collapsed" data-block="abstract_ru" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="abstract_ru" value="<?= h($art['blocks_labels']['abstract_ru'] ?? '') ?>" placeholder="<?= h($blockLabels['abstract_ru']) ?>" title="Переименовать блок"><select class="block-heading-level" data-block="abstract_ru" title="Уровень заголовка"><option value="2"<?= $hl==='2'?' selected':''?>>H2</option><option value="3"<?= $hl==='3'?' selected':''?>>H3</option></select></div>
                            <div class="block-body">
                            <textarea id="abstractRuEditor" class="block-content" style="min-height:300px;width:100%;padding:8px;border:1px dashed #ccc;border-radius:4px;font-family:inherit;"><?= str_replace('</textarea>', '</te&#8203;xtarea>', ($art['abstract_ru'] ?? '') ?: '<p></p>') ?></textarea>
                            </div>
                        </div>
                        <?php elseif ($bid === 'abstract_en'): ?>
                        <?php $hl = $art['blocks_heading_levels'][$bid] ?? '2'; ?>
                        <div class="galley-block collapsed" data-block="abstract_en" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="abstract_en" value="<?= h($art['blocks_labels']['abstract_en'] ?? '') ?>" placeholder="<?= h($blockLabels['abstract_en']) ?>" title="Переименовать блок"><select class="block-heading-level" data-block="abstract_en" title="Уровень заголовка"><option value="2"<?= $hl==='2'?' selected':''?>>H2</option><option value="3"<?= $hl==='3'?' selected':''?>>H3</option></select></div>
                            <div class="block-body">
                            <textarea id="abstractEnEditor" class="block-content" style="min-height:300px;width:100%;padding:8px;border:1px dashed #ccc;border-radius:4px;font-family:inherit;"><?= str_replace('</textarea>', '</te&#8203;xtarea>', ($art['abstract_en'] ?? '') ?: '<p></p>') ?></textarea>
                            </div>
                        </div>
                        <?php elseif ($bid === 'keywords_ru'): ?>
                        <?php $hl = $art['blocks_heading_levels'][$bid] ?? '2'; ?>
                        <div class="galley-block collapsed" data-block="keywords_ru" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="keywords_ru" value="<?= h($art['blocks_labels']['keywords_ru'] ?? '') ?>" placeholder="<?= h($blockLabels['keywords_ru']) ?>" title="Переименовать блок"><select class="block-heading-level" data-block="keywords_ru" title="Уровень заголовка"><option value="2"<?= $hl==='2'?' selected':''?>>H2</option><option value="3"<?= $hl==='3'?' selected':''?>>H3</option></select></div>
                            <div class="block-body">
                            <div contenteditable="true" name="keywords_ru" class="block-content block-keywords"><?= implode(', ', array_map('h', $art['keywords_ru'])) ?: '' ?></div>
                            </div>
                        </div>
                        <?php elseif ($bid === 'keywords_en'): ?>
                        <?php $hl = $art['blocks_heading_levels'][$bid] ?? '2'; ?>
                        <div class="galley-block collapsed" data-block="keywords_en" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="keywords_en" value="<?= h($art['blocks_labels']['keywords_en'] ?? '') ?>" placeholder="<?= h($blockLabels['keywords_en']) ?>" title="Переименовать блок"><select class="block-heading-level" data-block="keywords_en" title="Уровень заголовка"><option value="2"<?= $hl==='2'?' selected':''?>>H2</option><option value="3"<?= $hl==='3'?' selected':''?>>H3</option></select></div>
                            <div class="block-body">
                            <div contenteditable="true" name="keywords_en" class="block-content block-keywords"><?= implode(', ', array_map('h', $art['keywords_en'])) ?: '' ?></div>
                            </div>
                        </div>
                        <?php elseif ($bid === 'body'): ?>
                        <div class="galley-block collapsed" data-block="body" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="body" value="<?= h($art['blocks_labels']['body'] ?? '') ?>" placeholder="<?= h($blockLabels['body']) ?>" title="Переименовать блок"></div>
                            <div class="block-body">
                            <textarea id="bodyEditor" class="block-content block-body" style="min-height:200px;width:100%;padding:8px;border:1px dashed #ccc;border-radius:4px;font-family:inherit;"><?= str_replace('</textarea>', '</te&#8203;xtarea>', $art['body_html'] ?: '<p></p>') ?></textarea>
                            </div>
                        </div>
                        <?php elseif ($bid === 'extra_info'): ?>
                        <?php $hl = $art['blocks_heading_levels'][$bid] ?? '2'; ?>
                        <div class="galley-block collapsed" data-block="extra_info" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="extra_info" value="<?= h($art['blocks_labels']['extra_info'] ?? '') ?>" placeholder="<?= h($blockLabels['extra_info']) ?>" title="Переименовать блок"><select class="block-heading-level" data-block="extra_info" title="Уровень заголовка"><option value="2"<?= $hl==='2'?' selected':''?>>H2</option><option value="3"<?= $hl==='3'?' selected':''?>>H3</option></select></div>
                            <div class="block-body">
                            <div class="meta-row" style="margin-bottom:8px;"><label>Название блока:</label> <input type="text" name="extra_info_title" id="extra_info_title" value="<?= h($art['extra_info_title']) ?>" placeholder="Дополнительная информация, Заявления автора, Заявления авторов…" style="flex:1;min-width:200px"></div>
                            <textarea id="extraInfoEditor" class="block-content" style="min-height:300px;width:100%;padding:8px;border:1px dashed #ccc;border-radius:4px;font-family:inherit;"><?= str_replace('</textarea>', '</te&#8203;xtarea>', $art['extra_info_html'] ?: '<p></p>') ?></textarea>
                            </div>
                        </div>
                        <?php elseif ($bid === 'refs_ru'): ?>
                        <?php $hl = $art['blocks_heading_levels'][$bid] ?? '2'; ?>
                        <div class="galley-block collapsed" data-block="refs_ru" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="refs_ru" value="<?= h($art['blocks_labels']['refs_ru'] ?? '') ?>" placeholder="<?= h($blockLabels['refs_ru']) ?>" title="Переименовать блок"><select class="block-heading-level" data-block="refs_ru" title="Уровень заголовка"><option value="2"<?= $hl==='2'?' selected':''?>>H2</option><option value="3"<?= $hl==='3'?' selected':''?>>H3</option></select></div>
                            <div class="block-body">
                            <textarea id="refsRuEditor" class="block-content" style="min-height:300px;width:100%;padding:8px;border:1px dashed #ccc;border-radius:4px;font-family:inherit;"><?= str_replace('</textarea>', '</te&#8203;xtarea>', $art['refs_ru'] ?: '<p></p>') ?></textarea>
                            </div>
                        </div>
                        <?php elseif ($bid === 'refs_en'): ?>
                        <?php $hl = $art['blocks_heading_levels'][$bid] ?? '2'; ?>
                        <div class="galley-block collapsed" data-block="refs_en" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="refs_en" value="<?= h($art['blocks_labels']['refs_en'] ?? '') ?>" placeholder="<?= h($blockLabels['refs_en']) ?>" title="Переименовать блок"><select class="block-heading-level" data-block="refs_en" title="Уровень заголовка"><option value="2"<?= $hl==='2'?' selected':''?>>H2</option><option value="3"<?= $hl==='3'?' selected':''?>>H3</option></select></div>
                            <div class="block-body">
                            <textarea id="refsEnEditor" class="block-content" style="min-height:300px;width:100%;padding:8px;border:1px dashed #ccc;border-radius:4px;font-family:inherit;"><?= str_replace('</textarea>', '</te&#8203;xtarea>', $art['refs_en'] ?: '<p></p>') ?></textarea>
                            </div>
                        </div>
                        <?php elseif ($bid === 'authors_info_ru'): ?>
                        <?php $hl = $art['blocks_heading_levels'][$bid] ?? '2'; ?>
                        <div class="galley-block collapsed" data-block="authors_info_ru" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="authors_info_ru" value="<?= h($art['blocks_labels']['authors_info_ru'] ?? '') ?>" placeholder="<?= h($blockLabels['authors_info_ru']) ?>" title="Переименовать блок"><select class="block-heading-level" data-block="authors_info_ru" title="Уровень заголовка"><option value="2"<?= $hl==='2'?' selected':''?>>H2</option><option value="3"<?= $hl==='3'?' selected':''?>>H3</option></select></div>
                            <div class="block-body">
                            <div class="meta-row" style="margin-bottom:8px;"><label>Название блока:</label> <input type="text" name="authors_info_ru_title" id="authors_info_ru_title" value="<?= h($art['authors_info_ru_title']) ?>" placeholder="Информация об авторах" style="flex:1;min-width:200px"></div>
                            <textarea id="authorsInfoRuEditor" class="block-content" style="min-height:300px;width:100%;padding:8px;border:1px dashed #ccc;border-radius:4px;font-family:inherit;"><?= str_replace('</textarea>', '</te&#8203;xtarea>', $art['authors_info_ru_html'] ?: '<p></p>') ?></textarea>
                            </div>
                        </div>
                        <?php elseif ($bid === 'authors_info_en'): ?>
                        <?php $hl = $art['blocks_heading_levels'][$bid] ?? '2'; ?>
                        <div class="galley-block collapsed" data-block="authors_info_en" draggable="true">
                            <div class="block-header" title="Блок можно перемещать (≡) и сворачивать (▼)"><button type="button" class="block-collapse-btn" title="Развернуть/Свернуть">▼</button><span class="block-handle" title="Перетащите для изменения порядка">≡</span><input type="text" class="block-label-input" data-block="authors_info_en" value="<?= h($art['blocks_labels']['authors_info_en'] ?? '') ?>" placeholder="<?= h($blockLabels['authors_info_en']) ?>" title="Переименовать блок"><select class="block-heading-level" data-block="authors_info_en" title="Уровень заголовка"><option value="2"<?= $hl==='2'?' selected':''?>>H2</option><option value="3"<?= $hl==='3'?' selected':''?>>H3</option></select></div>
                            <div class="block-body">
                            <div class="meta-row" style="margin-bottom:8px;"><label>Название блока:</label> <input type="text" name="authors_info_en_title" id="authors_info_en_title" value="<?= h($art['authors_info_en_title']) ?>" placeholder="Author information" style="flex:1;min-width:200px"></div>
                            <textarea id="authorsInfoEnEditor" class="block-content" style="min-height:300px;width:100%;padding:8px;border:1px dashed #ccc;border-radius:4px;font-family:inherit;"><?= str_replace('</textarea>', '</te&#8203;xtarea>', $art['authors_info_en_html'] ?: '<p></p>') ?></textarea>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <input type="hidden" name="blocks_order" id="blocksOrderInput" value="<?= h(implode(',', $art['blocks_order'])) ?>">
                <input type="hidden" name="blocks_labels" id="blocksLabelsInput" value="<?= h(json_encode($art['blocks_labels'])) ?>">
                <input type="hidden" name="blocks_groups" id="blocksGroupsInput" value="<?= h(json_encode($art['blocks_groups'])) ?>">
                <div class="row" style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>

        <div class="edit-preview">
            <div class="field-row" style="align-items: center; margin-bottom: 8px;">
                <span class="block-label">Предпросмотр гранки</span>
            </div>
            <iframe id="previewFrame" src="preview_galley.php?id=<?= $id ?>" frameborder="0"></iframe>
        </div>
    </div>

    <div class="footer-info">
        <p class="muted">Версия <?= h(METAGALLEY_VERSION) ?> • 2026</p>
    </div>
</div>

<script>
function updateLicenseFromSelect(value) {
    const licenseText = document.getElementById('meta_license');
    const licenseUrl = document.getElementById('meta_license_url');
    if (!licenseText || !licenseUrl) return;
    if (!value || value === 'custom') return;
    const licenses = {
        'CC BY 3.0': 'https://creativecommons.org/licenses/by/3.0/',
        'CC BY 4.0': 'https://creativecommons.org/licenses/by/4.0/',
        'CC BY-NC 3.0': 'https://creativecommons.org/licenses/by-nc/3.0/',
        'CC BY-NC 4.0': 'https://creativecommons.org/licenses/by-nc/4.0/',
        'CC BY-NC-ND 3.0': 'https://creativecommons.org/licenses/by-nc-nd/3.0/',
        'CC BY-NC-ND 4.0': 'https://creativecommons.org/licenses/by-nc-nd/4.0/',
        'CC BY-NC-SA 3.0': 'https://creativecommons.org/licenses/by-nc-sa/3.0/',
        'CC BY-NC-SA 4.0': 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
        'CC BY-ND 3.0': 'https://creativecommons.org/licenses/by-nd/3.0/',
        'CC BY-ND 4.0': 'https://creativecommons.org/licenses/by-nd/4.0/',
        'CC BY-SA 3.0': 'https://creativecommons.org/licenses/by-sa/3.0/',
        'CC BY-SA 4.0': 'https://creativecommons.org/licenses/by-sa/4.0/'
    };
    if (licenses[value]) {
        licenseText.value = value;
        licenseUrl.value = licenses[value];
        licenseText.dispatchEvent(new Event('input'));
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('blocksContainer');

    let epubCoverState = <?= json_encode($art['epub_cover'] ?? null, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    let epubCoverClearPending = false;
    function syncEpubCoverUI() {
        const wrap = document.getElementById('epubCoverPreviewWrap');
        const img = document.getElementById('epubCoverPreview');
        const rm = document.getElementById('epubCoverRemove');
        if (!wrap || !img) return;
        if (epubCoverClearPending || !epubCoverState || !epubCoverState.data) {
            wrap.style.display = 'none';
            img.removeAttribute('src');
            if (rm) rm.style.display = 'none';
            return;
        }
        img.src = 'data:' + epubCoverState.mime + ';base64,' + epubCoverState.data;
        wrap.style.display = 'block';
        if (rm) rm.style.display = 'inline-block';
    }
    function prepareEpubCoverForSave() {
        const hidJson = document.getElementById('hid_epub_cover_json');
        const hidClear = document.getElementById('hid_epub_cover_clear');
        if (!hidJson || !hidClear) return;
        if (epubCoverClearPending) {
            hidClear.value = '1';
            hidJson.value = '';
        } else if (epubCoverState && epubCoverState.data && epubCoverState.mime) {
            hidClear.value = '0';
            hidJson.value = JSON.stringify({ mime: epubCoverState.mime, data: epubCoverState.data, filename: epubCoverState.filename || 'cover' });
        } else {
            hidClear.value = '0';
            hidJson.value = '';
        }
    }
    document.getElementById('epubCoverFile')?.addEventListener('change', function() {
        const f = this.files && this.files[0];
        if (!f) return;
        const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowed.includes(f.type)) {
            alert('Допустимы JPEG, PNG, GIF или WebP.');
            this.value = '';
            return;
        }
        if (f.size > 6 * 1024 * 1024) {
            alert('Файл больше 6 МБ.');
            this.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function() {
            const d = String(reader.result || '');
            const i = d.indexOf(',');
            const b64 = i >= 0 ? d.slice(i + 1) : d;
            epubCoverState = { mime: f.type, data: b64, filename: f.name.replace(/[^a-zA-Z0-9._-]/g, '_') };
            epubCoverClearPending = false;
            syncEpubCoverUI();
        };
        reader.readAsDataURL(f);
    });
    document.getElementById('epubCoverRemove')?.addEventListener('click', function() {
        epubCoverState = null;
        epubCoverClearPending = true;
        const fi = document.getElementById('epubCoverFile');
        if (fi) fi.value = '';
        syncEpubCoverUI();
    });
    syncEpubCoverUI();

    // Логотип: ползунок размера — сохраняет в issue и применяется ко всем статьям
    const logoSlider = document.getElementById('logoSizeSlider');
    const logoSizeValue = document.getElementById('logoSizeValue');
    const logoPreviewImg = document.querySelector('.logo-preview-img');
    if (logoSlider) {
        logoSlider.addEventListener('input', function() {
            const val = this.value;
            if (logoSizeValue) logoSizeValue.textContent = val;
            if (logoPreviewImg) logoPreviewImg.style.height = val + 'px';
        });
        logoSlider.addEventListener('change', function() {
            const val = this.value;
            const fd = new FormData();
            fd.append('galley_logo_size', val);
            fetch('../api/save_galley_logo_size.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.ok) {
                        const iframe = document.getElementById('previewFrame');
                        if (iframe) {
                            const url = new URL(iframe.src);
                            url.searchParams.set('_', Date.now());
                            iframe.src = url.toString();
                        }
                    }
                });
        });
    }

    // Format toolbar — привязать к блоку, в котором находится (mousedown чтобы не терять selection)
    container.querySelectorAll('.block-toolbar [data-cmd]').forEach(btn => {
        btn.addEventListener('mousedown', function(e) {
            e.preventDefault();
            const block = this.closest('.galley-block');
            const content = block?.querySelector('.block-content');
            if (!content) return;
            content.focus();
            const cmd = this.dataset.cmd;
            const val = this.dataset.value || '';
            if (cmd === 'insertTable') {
                const html = '<table><thead><tr><th></th><th></th><th></th></tr></thead><tbody><tr><td></td><td></td><td></td></tr><tr><td></td><td></td><td></td></tr></tbody></table><p></p>';
                document.execCommand('insertHTML', false, html);
            } else if (cmd === 'insertImage') {
                const url = prompt('URL изображения:', 'https://');
                if (url && url !== 'https://') {
                    document.execCommand('insertHTML', false, '<img src="' + url.replace(/"/g, '&quot;') + '" alt="" style="max-width:100%;height:auto;"><p></p>');
                }
            } else {
                document.execCommand(cmd, false, val);
            }
        });
    });

    // Collapse/expand blocks (по умолчанию свёрнуты). Форма включает блоки вне #blocksContainer (напр. обложка EPUB).
    const collapseRoot = document.getElementById('galleyForm') || container;
    collapseRoot.querySelectorAll('.block-collapse-btn').forEach(btn => {
        const block = btn.closest('.galley-block');
        if (block?.classList.contains('collapsed')) {
            btn.textContent = '▶';
            btn.setAttribute('aria-label', 'Развернуть');
            btn.setAttribute('title', 'Развернуть');
        } else {
            btn.setAttribute('title', 'Свернуть');
        }
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const blk = this.closest('.galley-block');
            if (blk) {
                blk.classList.toggle('collapsed');
                this.textContent = blk.classList.contains('collapsed') ? '▶' : '▼';
                const expanded = !blk.classList.contains('collapsed');
                this.setAttribute('aria-label', expanded ? 'Свернуть' : 'Развернуть');
                this.setAttribute('title', expanded ? 'Свернуть' : 'Развернуть');
            }
        });
    });

    // Группы блоков и drag-and-drop
    let blocksGroups = [];
    try {
        const gi = document.getElementById('blocksGroupsInput');
        if (gi && gi.value) blocksGroups = JSON.parse(gi.value) || [];
    } catch (e) {}
    let dragged = null;
    let saveOrderTimer;

    function getBlocksInOrder() {
        const list = [];
        const walk = (node) => {
            if (node.classList && node.classList.contains('galley-block')) list.push(node);
            else if (node.classList && node.classList.contains('block-group')) Array.from(node.children).forEach(walk);
            else if (node.children) Array.from(node.children).forEach(walk);
        };
        walk(container);
        return list;
    }
    function updateBlocksOrder() {
        const order = getBlocksInOrder().map(b => b.dataset.block).filter(Boolean);
        document.getElementById('blocksOrderInput').value = order.join(',');
    }
    function saveBlocksGroups() {
        const inp = document.getElementById('blocksGroupsInput');
        if (inp) inp.value = JSON.stringify(blocksGroups);
    }
    function wrapGroups() {
        container.querySelectorAll('.block-group').forEach(w => {
            while (w.firstChild) w.parentNode.insertBefore(w.firstChild, w);
            w.remove();
        });
        const blocks = Array.from(container.querySelectorAll('.galley-block'));
        const order = blocks.map(b => b.dataset.block).filter(Boolean);
        blocksGroups.forEach(group => {
            if (group.length < 2) return;
            const indices = group.map(bid => order.indexOf(bid)).filter(i => i >= 0).sort((a,b) => a - b);
            if (indices.length !== group.length) return;
            for (let i = 1; i < indices.length; i++) if (indices[i] !== indices[i-1] + 1) return;
            const toWrap = indices.map(i => blocks[i]);
            const firstBlock = toWrap[0];
            if (!firstBlock || firstBlock.closest('.block-group')) return;
            const wrapper = document.createElement('div');
            wrapper.className = 'block-group';
            wrapper.dataset.group = group.join(',');
            wrapper.draggable = true;
            const parent = firstBlock.parentNode;
            parent.insertBefore(wrapper, firstBlock);
            toWrap.forEach(b => wrapper.appendChild(b));
        });
    }
    function applyGroupsFromDOM() {
        blocksGroups = [];
        container.querySelectorAll('.block-group').forEach(w => {
            const ids = (w.dataset.group || '').split(',').filter(Boolean);
            if (ids.length >= 2) blocksGroups.push(ids);
        });
        saveBlocksGroups();
    }

    container.querySelectorAll('.block-label-input').forEach(inp => {
        inp.addEventListener('mousedown', e => e.stopPropagation());
    });

    container.addEventListener('dragstart', e => {
        if (e.target.closest('.block-label-input')) { e.preventDefault(); return; }
        const blk = e.target.closest('.galley-block');
        const grp = e.target.closest('.block-group');
        if (blk || grp) {
            dragged = grp || blk.closest('.block-group') || blk;
            dragged.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            const blocks = dragged.classList.contains('block-group') ? Array.from(dragged.querySelectorAll('.galley-block')) : [dragged];
            e.dataTransfer.setData('text/plain', blocks.map(b => b.dataset.block).join(','));
        }
    });
    container.addEventListener('dragend', e => {
        if (e.target.closest('.galley-block') || e.target.closest('.block-group')) {
            if (dragged) dragged.classList.remove('dragging');
            dragged = null;
            updateBlocksOrder();
            applyGroupsFromDOM();
            saveBlocksOrder();
        }
    });
    container.addEventListener('dragover', e => {
        e.preventDefault();
        if (!dragged) return;
        const target = (e.target.closest('.galley-block') || e.target.closest('.block-group'));
        if (!target || target === dragged || (dragged.contains && dragged.contains(target))) return;
        const rect = target.getBoundingClientRect();
        const mid = rect.top + rect.height / 2;
        const parent = target.parentNode;
        if (e.clientY < mid) parent.insertBefore(dragged, target);
        else parent.insertBefore(dragged, target.nextSibling);
    });
    container.querySelectorAll('.galley-block').forEach(block => {
        block.addEventListener('contextmenu', e => {
            e.preventDefault();
            const blk = block.closest('.galley-block');
            if (!blk) return;
            const bid = blk.dataset.block;
            const allBlocks = getBlocksInOrder();
            const idx = allBlocks.indexOf(blk);
            const prevBlock = idx > 0 ? allBlocks[idx - 1] : null;
            const nextBlock = idx < allBlocks.length - 1 ? allBlocks[idx + 1] : null;
            const groupOf = (b) => blocksGroups.find(g => g.includes(b?.dataset?.block));
            const myGroup = groupOf(blk);
            const prevGroup = groupOf(prevBlock);
            const nextGroup = groupOf(nextBlock);
            const items = [];
            if (prevBlock && (!myGroup || !prevGroup || myGroup !== prevGroup)) items.push({ text: 'Сгруппировать с предыдущим', action: () => groupWithPrev(bid, prevBlock.dataset.block) });
            if (nextBlock && (!myGroup || !nextGroup || myGroup !== nextGroup)) items.push({ text: 'Сгруппировать со следующим', action: () => groupWithNext(bid, nextBlock.dataset.block) });
            if (myGroup) items.push({ text: 'Разгруппировать', action: () => ungroup(bid) });
            if (items.length === 0) return;
            const menu = document.createElement('div');
            menu.className = 'block-context-menu';
            menu.style.cssText = 'position:fixed;left:' + e.clientX + 'px;top:' + e.clientY + 'px;background:#fff;border:1px solid #ccc;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:9999;min-width:180px;padding:4px 0;';
            items.forEach(it => {
                const a = document.createElement('div');
                a.textContent = it.text;
                a.style.cssText = 'padding:8px 14px;cursor:pointer;font-size:14px;';
                a.addEventListener('mouseenter', () => a.style.background = '#f0f0f0');
                a.addEventListener('mouseleave', () => a.style.background = '');
                a.addEventListener('click', () => { it.action(); document.body.removeChild(menu); });
                menu.appendChild(a);
            });
            document.body.appendChild(menu);
            const close = () => { if (menu.parentNode) document.body.removeChild(menu); document.removeEventListener('click', close); };
            setTimeout(() => document.addEventListener('click', close), 0);
        });
    });
    container.addEventListener('dragstart', e => {
        const grp = e.target.closest('.block-group');
        if (grp) {
            if (e.target.closest('.block-label-input')) { e.preventDefault(); return; }
            dragged = grp;
            grp.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', grp.dataset.group || '');
        }
    });
    container.addEventListener('dragend', e => {
        if (e.target.closest('.block-group') || e.target.closest('.galley-block')) {
            if (dragged) dragged.classList.remove('dragging');
            dragged = null;
            updateBlocksOrder();
            applyGroupsFromDOM();
            saveBlocksOrder();
        }
    });
    container.addEventListener('dragover', e => {
        e.preventDefault();
        if (!dragged) return;
        const target = (e.target.closest('.galley-block') || e.target.closest('.block-group'));
        if (!target || target === dragged || (dragged.contains && dragged.contains(target))) return;
        const rect = target.getBoundingClientRect();
        const mid = rect.top + rect.height / 2;
        const parent = target.parentNode;
        if (e.clientY < mid) parent.insertBefore(dragged, target);
        else parent.insertBefore(dragged, target.nextSibling);
    });

    function groupWithPrev(bid, prevBid) {
        const order = (document.getElementById('blocksOrderInput').value || '').split(',').filter(Boolean);
        const bidIdx = order.indexOf(bid);
        const prevIdx = order.indexOf(prevBid);
        if (bidIdx < 0 || prevIdx < 0 || bidIdx !== prevIdx + 1) return;
        const g = blocksGroups.find(gr => gr.includes(prevBid));
        if (g) {
            if (!g.includes(bid)) { const pi = g.indexOf(prevBid); g.splice(pi + 1, 0, bid); }
        } else blocksGroups.push([prevBid, bid]);
        saveBlocksGroups();
        rebuildGroupsInDOM();
    }
    function groupWithNext(bid, nextBid) {
        const order = (document.getElementById('blocksOrderInput').value || '').split(',').filter(Boolean);
        const bidIdx = order.indexOf(bid);
        const nextIdx = order.indexOf(nextBid);
        if (bidIdx < 0 || nextIdx < 0 || bidIdx !== nextIdx - 1) return;
        const g = blocksGroups.find(gr => gr.includes(nextBid));
        if (g) {
            if (!g.includes(bid)) g.unshift(bid);
        } else blocksGroups.push([bid, nextBid]);
        saveBlocksGroups();
        rebuildGroupsInDOM();
    }
    function ungroup(bid) {
        blocksGroups = blocksGroups.map(g => g.filter(b => b !== bid)).filter(g => g.length >= 2);
        saveBlocksGroups();
        rebuildGroupsInDOM();
    }
    function rebuildGroupsInDOM() {
        container.querySelectorAll('.block-group').forEach(w => {
            while (w.firstChild) w.parentNode.insertBefore(w.firstChild, w);
            w.remove();
        });
        wrapGroups();
        updateBlocksOrder();
        saveBlocksOrder();
    }
    setTimeout(wrapGroups, 50);

    function saveBlocksOrder() {
        const order = document.getElementById('blocksOrderInput').value;
        const fd = new FormData();
        fd.append('blocks_order', order);
        fd.append('id', '<?= $id ?>');
        fetch('../api/save_blocks_order.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if (d.ok) updatePreview(); });
    }

    document.querySelector('.add-author-btn')?.addEventListener('click', function() {
        const list = document.getElementById('authorsList');
        const count = list.querySelectorAll('.author-card').length;
        const card = document.createElement('div');
        card.className = 'author-card';
        card.dataset.index = count;
        card.innerHTML = '<div class="author-fields">' +
            '<div class="author-row"><label>ФИО (RU):</label> <input type="text" name="authors[' + count + '][surname_ru]" placeholder="Фамилия"> <input type="text" name="authors[' + count + '][given_ru]" placeholder="Имя Отчество"> <label class="author-corresp"><input type="checkbox" name="authors[' + count + '][corresp]" value="1"> * корресп.</label></div>' +
            '<div class="author-row"><label>ФИО (EN):</label> <input type="text" name="authors[' + count + '][given_en]" placeholder="Given names"> <input type="text" name="authors[' + count + '][surname_en]" placeholder="Surname"></div>' +
            '<div class="author-row"><label>Email:</label> <input type="text" name="authors[' + count + '][email]" style="min-width:220px"></div>' +
            '<div class="author-row"><label>ORCID:</label> <input type="text" name="authors[' + count + '][orcid]" placeholder="0000-0002-1234-5678"></div>' +
            '<div class="author-row"><label>Аффилиация (RU):</label> <input type="text" name="authors[' + count + '][aff_ru]" style="flex:1"></div>' +
            '<div class="author-row"><label>Аффилиация (EN):</label> <input type="text" name="authors[' + count + '][aff_en]" style="flex:1"></div>' +
            '<div class="author-row"><label>Город, страна (RU):</label> <input type="text" name="authors[' + count + '][city_country_ru]" placeholder="(Москва, Россия)" style="flex:1"></div>' +
            '<div class="author-row"><label>Город, страна (EN):</label> <input type="text" name="authors[' + count + '][city_country_en]" placeholder="(Moscow, Russia)" style="flex:1"></div>' +
            '</div>';
        list.appendChild(card);
    });

    // Очистка вставки из Word: оставляем только заголовки, жирный, курсив, подчёркнутый; URL → ссылки
    function cleanPasteContent(html) {
        if (!html || typeof html !== 'string') return html;
        const allowedTags = ['h1','h2','h3','h4','h5','h6','p','br','strong','b','em','i','u','a','ul','ol','li','blockquote','sup'];
        const urlRe = /(https?:\/\/[^\s<>"']+|www\.[^\s<>"']+)/gi;
        const doc = new DOMParser().parseFromString('<body>' + html + '</body>', 'text/html');
        const body = doc.body;

        function linkifyText(text) {
            const parts = text.split(urlRe);
            if (parts.length <= 1) return null;
            const frag = doc.createDocumentFragment();
            for (let i = 0; i < parts.length; i++) {
                if (i % 2 === 1) {
                    const a = doc.createElement('a');
                    a.href = /^www\./i.test(parts[i]) ? 'https://' + parts[i] : parts[i];
                    a.textContent = parts[i];
                    frag.appendChild(a);
                } else {
                    frag.appendChild(doc.createTextNode(parts[i]));
                }
            }
            return frag;
        }

        function cleanNode(node) {
            if (node.nodeType === Node.TEXT_NODE) {
                const txt = node.textContent;
                const frag = linkifyText(txt);
                if (frag) {
                    node.parentNode.replaceChild(frag, node);
                }
                return;
            }
            if (node.nodeType !== Node.ELEMENT_NODE) return;
            const tag = node.tagName.toLowerCase();
            if (tag === 'a') {
                const href = node.getAttribute('href');
                node.removeAttribute('style');
                node.removeAttribute('class');
                if (!href || (!/^https?:\/\//i.test(href) && !/^#/.test(href))) node.removeAttribute('href');
                const children = Array.from(node.childNodes);
                for (const c of children) cleanNode(c);
                return;
            }
            if (!allowedTags.includes(tag)) {
                const style = (node.getAttribute('style') || '').toLowerCase();
                const cls = (node.getAttribute('class') || '').toLowerCase();
                const hasBlockIndent = /margin-left\s*:[^;]+/.test(style) && /margin-right\s*:[^;]+/.test(style);
                const hasIndent = /margin-left|margin-right|padding-left|padding-right|margin\s*:\s*[^;]*\d/.test(style);
                const hasSmallerFont = /font-size\s*:\s*(0\.\d+em|0\.\d+rem|[89]pt|1[0-1]pt|smaller)/.test(style);
                const isQuote = (tag === 'div' || tag === 'p') && (
                    /quote|cite|msoquote|blockquote|msoblocktext/.test(cls) ||
                    hasBlockIndent ||
                    (hasIndent && hasSmallerFont)
                );
                if (isQuote && (tag === 'div' || tag === 'p')) {
                    const bq = doc.createElement('blockquote');
                    const origStyle = node.getAttribute('style') || '';
                    const keep = [];
                    if (/margin-left\s*:[^;]+/i.test(origStyle)) keep.push(origStyle.match(/margin-left\s*:[^;]+/i)[0]);
                    if (/margin-right\s*:[^;]+/i.test(origStyle)) keep.push(origStyle.match(/margin-right\s*:[^;]+/i)[0]);
                    if (/font-size\s*:[^;]+/i.test(origStyle)) keep.push(origStyle.match(/font-size\s*:[^;]+/i)[0]);
                    if (/margin\s*:[^;]+/i.test(origStyle) && !/margin-left|margin-right/.test(origStyle)) keep.push(origStyle.match(/margin\s*:[^;]+/i)[0]);
                    if (keep.length) bq.setAttribute('style', keep.join('; '));
                    const kids = Array.from(node.childNodes);
                    for (const k of kids) cleanNode(k);
                    while (node.firstChild) bq.appendChild(node.firstChild);
                    node.parentNode.replaceChild(bq, node);
                    cleanNode(bq);
                    return;
                }
                const parent = node.parentNode;
                const kids = Array.from(node.childNodes);
                for (const k of kids) cleanNode(k);
                while (node.firstChild) parent.insertBefore(node.firstChild, node);
                parent.removeChild(node);
                return;
            }
            if (tag !== 'blockquote') {
                const style = node.getAttribute('style') || '';
                const textAlignMatch = style.match(/text-align\s*:\s*[^;]+/i);
                const keep = [];
                if (textAlignMatch) keep.push(textAlignMatch[0]);
                if (keep.length) node.setAttribute('style', keep.join('; '));
                else node.removeAttribute('style');
                node.removeAttribute('class');
                node.removeAttribute('id');
            } else {
                const st = (node.getAttribute('style') || '').match(/(margin|padding|font-size)\s*:[^;]+/gi);
                if (st) node.setAttribute('style', st.join('; ')); else node.removeAttribute('style');
                node.removeAttribute('class');
                node.removeAttribute('id');
            }
            const children = Array.from(node.childNodes);
            for (const c of children) cleanNode(c);
        }

        const children = Array.from(body.childNodes);
        for (const c of children) cleanNode(c);
        return body.innerHTML;
    }

    // TinyMCE — полная конфигурация со всеми функциями (включая MathType для формул)
    const tinyConfig = (sel, h) => ({
        selector: sel,
        base_url: 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.5.0',
        width: '100%',
        height: h || 200,
        menubar: 'file edit insert view format table tools',
        plugins: 'lists link code paste image table charmap fullscreen searchreplace codesample advlist autolink quickbars wordcount',
        external_plugins: { tiny_mce_wiris: 'https://cdn.jsdelivr.net/npm/@wiris/mathtype-tinymce6@8.15.2/plugin.min.js' },
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | tiny_mce_wiris_formulaEditor tiny_mce_wiris_formulaEditorChemistry | charmap codesample hr | searchreplace | removeformat | code fullscreen',
        toolbar_mode: 'wrap',
        quickbars_selection_toolbar: 'bold italic | quicklink h2 h3 blockquote',
        quickbars_insert_toolbar: 'quicktable image',
        contextmenu: 'link image table',
        block_formats: 'Параграф=p; Заголовок 2=h2; Заголовок 3=h3; Заголовок 4=h4; Цитата=blockquote; Код=pre',
        content_style: 'body { font-family: inherit; font-size: 14px; line-height: 1.5; } blockquote { margin: 1em 2em; padding: 0.5em 0; font-size: 0.95em; font-style: italic; color: #333; border-left: 3pt solid #999; padding-left: 1em; }',
        language: 'ru',
        draggable_modal: true,
        extended_valid_elements: '*[.*]',
        paste_as_text: false,
        paste_data_images: true,
        image_advtab: true,
        codesample_languages: [{ text: 'HTML/XML', value: 'markup' }, { text: 'JavaScript', value: 'javascript' }, { text: 'CSS', value: 'css' }, { text: 'PHP', value: 'php' }, { text: 'Python', value: 'python' }],
        statusbar: true,
        elementpath: true,
        setup: function(ed) {
            ed.on('PastePreProcess', function(e) {
                e.content = cleanPasteContent(e.content);
            });
            ed.on('change keyup', function() {
                clearTimeout(previewTimer);
                previewTimer = setTimeout(updatePreview, 100);
            });
            ed.on('init', function() {
                setTimeout(updatePreview, 50);
            });
        }
    });
    if (typeof tinymce !== 'undefined') {
        tinymce.init(tinyConfig('#abstractRuEditor', 300));
        tinymce.init(tinyConfig('#abstractEnEditor', 300));
        tinymce.init(tinyConfig('#bodyEditor', 300));
        tinymce.init(tinyConfig('#extraInfoEditor', 300));
        const refsStyle = 'body { font-family: inherit; font-size: 14px; line-height: 1.5; text-align: left; }';
        tinymce.init({ ...tinyConfig('#refsRuEditor', 300), content_style: refsStyle });
        tinymce.init({ ...tinyConfig('#refsEnEditor', 300), content_style: refsStyle });
        if (document.getElementById('authorsInfoRuEditor')) tinymce.init(tinyConfig('#authorsInfoRuEditor', 300));
        if (document.getElementById('authorsInfoEnEditor')) tinymce.init(tinyConfig('#authorsInfoEnEditor', 300));
    }

    // Очистка вставки и для contenteditable-блоков
    container.querySelectorAll('[contenteditable="true"]').forEach(el => {
        el.addEventListener('paste', function(e) {
            e.preventDefault();
            const html = e.clipboardData?.getData('text/html') || e.clipboardData?.getData('text/plain');
            const cleaned = cleanPasteContent(html || '');
            document.execCommand('insertHTML', false, cleaned || e.clipboardData?.getData('text/plain') || '');
            clearTimeout(previewTimer);
            previewTimer = setTimeout(updatePreview, 300);
        });
        el.addEventListener('input', () => {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(updatePreview, 300);
        });
    });
    container.querySelectorAll('.block-heading-level').forEach(sel => {
        sel.addEventListener('change', () => { updatePreview(); });
    });
    container.querySelectorAll('.galley-block-meta input, .galley-block-meta textarea').forEach(inp => {
        inp.addEventListener('input', () => { clearTimeout(previewTimer); previewTimer = setTimeout(updatePreview, 300); });
        inp.addEventListener('change', () => { updatePreview(); });
    });
    document.getElementById('extra_info_title')?.addEventListener('input', () => { clearTimeout(previewTimer); previewTimer = setTimeout(updatePreview, 300); });
    document.getElementById('authors_info_ru_title')?.addEventListener('input', () => { clearTimeout(previewTimer); previewTimer = setTimeout(updatePreview, 300); });
    document.getElementById('authors_info_en_title')?.addEventListener('input', () => { clearTimeout(previewTimer); previewTimer = setTimeout(updatePreview, 300); });
    container.querySelectorAll('.galley-block-authors input').forEach(inp => {
        inp.addEventListener('input', () => { clearTimeout(previewTimer); previewTimer = setTimeout(updatePreview, 300); });
        inp.addEventListener('change', () => { updatePreview(); });
    });

    function updatePreview() {
        const iframe = document.getElementById('previewFrame');
        if (!iframe?.contentWindow) return;
        try {
            if (iframe.contentWindow.updateFromEditor) {
                iframe.contentWindow.updateFromEditor(getArticleData());
            }
        } catch (e) { console.warn('Preview update:', e); }
    }
    document.getElementById('previewFrame')?.addEventListener('load', function() {
        setTimeout(updatePreview, 100);
    });
    // Обновление предпросмотра каждую секунду при фокусе на странице (режим реального времени)
    setInterval(function() {
        if (document.hasFocus()) updatePreview();
    }, 1000);
    // Автосохранение каждые 30 с при наличии изменений
    let lastSavedData = '';
    setInterval(function() {
        if (!document.hasFocus()) return;
        prepareFormForSave();
        const form = document.getElementById('galleyForm');
        const fd = new FormData(form);
        const str = JSON.stringify([...fd.entries()].sort((a,b) => a[0].localeCompare(b[0])).map(([k,v]) => k + '=' + (typeof v === 'string' ? v : '').slice(0, 200)));
        if (str !== lastSavedData) {
            lastSavedData = str;
            fetch('../api/save_article.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => { if (!d.ok) lastSavedData = ''; })
                .catch(() => { lastSavedData = ''; });
        }
    }, 30000);

    function getArticleData() {
        const data = {};
        const tinyMceMap = { abstractRuEditor: 'abstract_ru', abstractEnEditor: 'abstract_en', bodyEditor: 'body_html', extraInfoEditor: 'extra_info_html', refsRuEditor: 'refs_ru', refsEnEditor: 'refs_en', authorsInfoRuEditor: 'authors_info_ru_html', authorsInfoEnEditor: 'authors_info_en_html' };
        if (typeof tinymce !== 'undefined') {
            Object.keys(tinyMceMap).forEach(id => {
                const ed = tinymce.get(id);
                if (ed) data[tinyMceMap[id]] = ed.getContent();
            });
        }
        container.querySelectorAll('.block-content').forEach(el => {
            const name = el.getAttribute('name');
            if (!name) return;
            if (Object.values(tinyMceMap).includes(name) || ['body_html','extra_info_html','refs_ru','refs_en','abstract_ru','abstract_en','authors_info_ru_html','authors_info_en_html'].includes(name)) return;
            if (el.contentEditable === 'true') {
                data[name] = el.innerHTML;
            } else if (el.tagName === 'TEXTAREA') {
                data[name] = el.value || '';
            }
        });
        data.blocks_order = document.getElementById('blocksOrderInput').value;
        data.blocks_labels = {};
        container.querySelectorAll('.block-label-input').forEach(inp => {
            const v = (inp.value || '').trim();
            if (v) data.blocks_labels[inp.dataset.block] = v;
        });
        const groupsInp = document.getElementById('blocksGroupsInput');
        data.blocks_groups = groupsInp && groupsInp.value ? (JSON.parse(groupsInp.value) || []) : [];
        const levels = {};
        container.querySelectorAll('.block-heading-level').forEach(sel => { levels[sel.dataset.block] = sel.value; });
        data.blocks_heading_levels = levels;
        data.meta = {};
        container.querySelectorAll('.galley-block-meta input, .galley-block-meta textarea').forEach(inp => {
            const n = inp.name || inp.id;
            if (n && n.startsWith('meta_')) data.meta[n.replace('meta_', '')] = inp.value;
        });
        data.extra_info_title = (document.getElementById('extra_info_title') || {}).value || '';
        data.authors_info_ru_title = (document.getElementById('authors_info_ru_title') || {}).value || '';
        data.authors_info_en_title = (document.getElementById('authors_info_en_title') || {}).value || '';
        data.authors = [];
        container.querySelectorAll('.author-card').forEach(card => {
            const inputs = card.querySelectorAll('input');
            const au = {};
            inputs.forEach(inp => {
                const m = inp.name.match(/authors\[\d+\]\[(\w+)\]/);
                if (m) au[m[1]] = inp.type === 'checkbox' ? (inp.checked ? '1' : '') : inp.value;
            });
            data.authors.push(au);
        });
        return data;
    }

    function prepareFormForSave() {
        prepareEpubCoverForSave();
        // TinyMCE-редакторы без name — явно копируем в hidden
        if (typeof tinymce !== 'undefined') {
            const map = { abstractRuEditor: 'hid_abstract_ru', abstractEnEditor: 'hid_abstract_en', bodyEditor: 'hid_body_html', extraInfoEditor: 'hid_extra_info_html', refsRuEditor: 'hid_refs_ru', refsEnEditor: 'hid_refs_en', authorsInfoRuEditor: 'hid_authors_info_ru_html', authorsInfoEnEditor: 'hid_authors_info_en_html' };
            Object.keys(map).forEach(id => {
                const ed = tinymce.get(id);
                const hid = document.getElementById(map[id]);
                if (ed && hid) hid.value = ed.getContent();
            });
        }
        const nameToId = { title_ru: 'hid_title_ru', title_en: 'hid_title_en', abstract_ru: 'hid_abstract_ru', abstract_en: 'hid_abstract_en', keywords_ru: 'hid_keywords_ru', keywords_en: 'hid_keywords_en', body_html: 'hid_body_html', extra_info_html: 'hid_extra_info_html', refs_ru: 'hid_refs_ru', refs_en: 'hid_refs_en', authors_info_ru_html: 'hid_authors_info_ru_html', authors_info_en_html: 'hid_authors_info_en_html' };
        const levels = {};
        container.querySelectorAll('.block-heading-level').forEach(sel => { levels[sel.dataset.block] = sel.value; });
        document.getElementById('hid_blocks_heading_levels').value = JSON.stringify(levels);
        const labels = {};
        container.querySelectorAll('.block-label-input').forEach(inp => {
            const v = (inp.value || '').trim();
            if (v) labels[inp.dataset.block] = v;
        });
        document.getElementById('blocksLabelsInput').value = JSON.stringify(labels);
        applyGroupsFromDOM();
        const titleInp = document.getElementById('extra_info_title');
        if (titleInp) document.getElementById('hid_extra_info_title').value = titleInp.value;
        const hidRu = document.getElementById('hid_authors_info_ru_title');
        if (hidRu) hidRu.value = (document.getElementById('authors_info_ru_title') || {}).value || '';
        const hidEn = document.getElementById('hid_authors_info_en_title');
        if (hidEn) hidEn.value = (document.getElementById('authors_info_en_title') || {}).value || '';
        const meta = {};
        container.querySelectorAll('.galley-block-meta input, .galley-block-meta textarea').forEach(inp => {
            const n = inp.name || inp.id;
            if (n && n.startsWith('meta_')) meta[n.replace('meta_', '')] = inp.value;
        });
        document.getElementById('hid_meta_json').value = JSON.stringify(meta);
        container.querySelectorAll('.block-content').forEach(el => {
            const name = el.getAttribute('name');
            if (!name) return;
            const hid = document.getElementById(nameToId[name] || 'hid_' + name);
            if (!hid) return;
            if (['abstractRuEditor','abstractEnEditor','bodyEditor','extraInfoEditor','refsRuEditor','refsEnEditor','authorsInfoRuEditor','authorsInfoEnEditor'].includes(el.id) && typeof tinymce !== 'undefined') {
                const ed = tinymce.get(el.id);
                hid.value = ed ? ed.getContent() : el.value || '';
            } else if (el.contentEditable === 'true') {
                hid.value = el.innerHTML;
            } else if (el.tagName === 'TEXTAREA') {
                hid.value = el.value || '';
            }
        });
        const authors = [];
        container.querySelectorAll('.author-card').forEach(card => {
            const au = {};
            card.querySelectorAll('input').forEach(inp => {
                const m = inp.name.match(/authors\[\d+\]\[(\w+)\]/);
                if (m) au[m[1]] = inp.type === 'checkbox' ? (inp.checked ? '1' : '') : inp.value;
            });
            authors.push(au);
        });
        document.getElementById('hid_authors_json').value = JSON.stringify(authors);
    }

    document.getElementById('galleyForm').addEventListener('submit', function() {
        prepareFormForSave();
    });

    function doExport(url) {
        prepareFormForSave();
        const form = document.getElementById('galleyForm');
        const fd = new FormData(form);
        fd.append('ajax_save', '1');
        fetch(form.action || ('../api/save_article.php?id=<?= $id ?>'), { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { window.open(url, '_blank'); })
            .catch(() => window.open(url, '_blank'));
    }
    document.getElementById('exportHtmlBtn').addEventListener('click', function(e) { e.preventDefault(); doExport('../api/export_galley.php?id=<?= $id ?>'); });
    document.getElementById('exportEpubBtn').addEventListener('click', function(e) { e.preventDefault(); doExport('../api/export_galley_epub.php?id=<?= $id ?>'); });
});
</script>
</body>
</html>
