<?php
require_once __DIR__ . '/../src/functions.php';
ensure_session();

$issue = $_SESSION['issue'] ?? default_issue_metadata();
$arts = $_SESSION['articles'];

// Сохранение метаданных выпуска и оформления гранки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_issue'])) {
    $issue['volume'] = trim($_POST['volume'] ?? $issue['volume'] ?? '');
    $issue['issue'] = trim($_POST['issue'] ?? $issue['issue'] ?? '');
    $issue['year'] = trim($_POST['year'] ?? $issue['year'] ?? '');
    $issue['published'] = trim($_POST['published'] ?? $issue['published'] ?? '');
    $issue['journal_title_ru'] = trim($_POST['journal_title_ru'] ?? $issue['journal_title_ru'] ?? '');
    $issue['journal_title_en'] = trim($_POST['journal_title_en'] ?? $issue['journal_title_en'] ?? '');
    if (isset($_POST['author_name_order_ru'])) {
        $issue['author_name_order_ru'] = in_array($_POST['author_name_order_ru'], ['family_given', 'given_family'], true) ? $_POST['author_name_order_ru'] : 'family_given';
    }
    if (isset($_POST['author_name_order_en'])) {
        $issue['author_name_order_en'] = in_array($_POST['author_name_order_en'], ['family_given', 'given_family'], true) ? $_POST['author_name_order_en'] : 'given_family';
    }
    if (isset($_POST['galley_preset'])) {
        $issue['galley_preset'] = $_POST['galley_preset'] ?? 'standard';
        $issue['galley_logo_url'] = trim($_POST['galley_logo_url'] ?? '');
        $logoSize = trim($_POST['galley_logo_size'] ?? '');
        $logoSizeInt = (int)$logoSize;
        $issue['galley_logo_size'] = ($logoSize !== '' && $logoSizeInt >= 40 && $logoSizeInt <= 200) ? (string)$logoSizeInt : ($issue['galley_logo_size'] ?? '88');
        $issue['galley_logo_align'] = in_array($_POST['galley_logo_align'] ?? 'center', ['left','center','right'], true) ? $_POST['galley_logo_align'] : 'center';
        $issue['galley_font_family'] = trim($_POST['galley_font_family'] ?? '');
        $issue['galley_font_size'] = trim($_POST['galley_font_size'] ?? '');
        $issue['galley_line_height'] = trim($_POST['galley_line_height'] ?? '');
        $issue['galley_text_color'] = trim($_POST['galley_text_color'] ?? '');
        $issue['galley_bg_color'] = trim($_POST['galley_bg_color'] ?? '');
        $issue['galley_max_width'] = trim($_POST['galley_max_width'] ?? '');
        $issue['galley_meta_bg'] = trim($_POST['galley_meta_bg'] ?? '');
        $issue['galley_links_color'] = trim($_POST['galley_links_color'] ?? '');
        $issue['galley_links_hover_color'] = trim($_POST['galley_links_hover_color'] ?? '');
        $issue['galley_meta_radius'] = trim($_POST['galley_meta_radius'] ?? '');
        $issue['galley_meta_border_color'] = trim($_POST['galley_meta_border_color'] ?? '');
        $issue['galley_meta_border_width'] = trim($_POST['galley_meta_border_width'] ?? '');
        $issue['galley_refs_font_size'] = trim($_POST['galley_refs_font_size'] ?? '');
        $issue['galley_refs_hanging_indent'] = isset($_POST['galley_refs_hanging_indent']) ? '1' : '0';
        $issue['galley_h2_underline'] = isset($_POST['galley_h2_underline']) ? '1' : '0';
        $issue['galley_h3_italic'] = isset($_POST['galley_h3_italic']) ? '1' : '0';
        $issue['galley_blocks_separator'] = isset($_POST['galley_blocks_separator']) ? '1' : '0';
        $issue['galley_custom_css'] = trim($_POST['galley_custom_css'] ?? '');
    }
    $_SESSION['issue'] = $issue;
    issue_apply_to_articles($issue);
    header('Location: dashboard.php?saved=1');
    exit;
}
$curFontHead = $issue['galley_font_family'] ?? '';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Рабочий стол — MetaGalley <?= h(METAGALLEY_VERSION) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= h(get_galley_fonts_preload_url()) ?>" rel="stylesheet">
    <?php if ($curFontHead && ($fontUrl = get_galley_font_url($curFontHead))): ?><link href="<?= h($fontUrl) ?>" rel="stylesheet"><?php endif; ?>
</head>
<body>
<div class="wrap">
    <div class="breadcrumbs">
        <a href="../index.php">Главная</a>
        <span class="breadcrumbs-separator">→</span>
        <span>Рабочий стол</span>
    </div>

    <div class="topbar">
        <div class="topbar-group">
            <a href="../api/export_backup.php" class="btn btn-secondary">Резервная копия</a>
            <a href="../api/export_galley_all.php" class="btn btn-primary">Скачать все гранки</a>
        </div>
        <div class="topbar-group">
            <a href="../api/reset_session.php" class="btn btn-light btn-danger" onclick="return confirm('Сбросить сессию? Все данные будут потеряны.');">Сбросить сессию</a>
        </div>
        <div class="topbar-group">
            <a href="../manual.php" class="btn btn-manual-link">Методические рекомендации</a>
        </div>
    </div>

    <div class="dashboard-hero">
        <h1 class="dashboard-title">Рабочий стол MetaGalley</h1>
        <p class="hero-subtitle">Редактируйте метаданные, оформление гранок и управляйте статьями</p>
        <p class="muted" style="margin: 10px 0 0; font-size: 0.95rem;">Версия <?= h(METAGALLEY_VERSION) ?></p>
        <?php
        $journalTitle = trim($issue['journal_title_ru'] ?? '') ?: trim($issue['journal_title_en'] ?? '');
        $vol = trim($issue['volume'] ?? ''); $iss = trim($issue['issue'] ?? '');
        $volIssue = ($vol || $iss) ? ' Т. ' . $vol . ', № ' . $iss : '';
        if ($journalTitle || $volIssue): ?>
        <p class="hero-journal"><?= h($journalTitle ?: 'Журнал') ?><?= h($volIssue) ?></p>
        <?php endif; ?>
    </div>

    <nav class="dashboard-tabs" role="tablist">
        <a href="#meta" class="active" data-tab="meta" role="tab">Метаданные</a>
        <a href="#style" data-tab="style" role="tab">Оформление</a>
        <a href="#articles" data-tab="articles" role="tab">Статьи</a>
    </nav>

    <?php if (isset($_GET['saved'])): ?>
        <div class="success" style="margin-bottom: 16px;">Метаданные выпуска сохранены и применены ко всем статьям.</div>
    <?php endif; ?>
    <?php if (isset($_GET['restored'])): ?>
        <div class="success" style="margin-bottom: 16px;">Данные восстановлены из резервной копии.</div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="success" style="margin-bottom: 16px;">Удалено статей: <?= (int)$_GET['deleted'] ?>.</div>
    <?php endif; ?>
    <?php if (isset($_GET['added'])): ?>
        <div class="success" style="margin-bottom: 16px;">Статья добавлена.</div>
    <?php endif; ?>
    <?php if (isset($_GET['reordered'])): ?>
        <div class="success" style="margin-bottom: 16px;">Порядок статей сохранён.</div>
    <?php endif; ?>

    <!-- Панель: Метаданные -->
    <section class="dashboard-tab-panel active" id="panel-meta" role="tabpanel">
    <h2><span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg></span> Метаданные выпуска</h2>
    <p class="muted" style="margin-bottom: 16px;">Изменения применяются ко всем статьям при сохранении.</p>
    <form method="post" class="grid">
        <input type="hidden" name="save_issue" value="1">
        <div class="field-group">
            <label class="label-text">Том</label>
            <input type="text" name="volume" value="<?= h($issue['volume']) ?>">
        </div>
        <div class="field-group">
            <label class="label-text">Номер выпуска</label>
            <input type="text" name="issue" value="<?= h($issue['issue']) ?>">
        </div>
        <div class="field-group">
            <label class="label-text">Год</label>
            <input type="text" name="year" value="<?= h($issue['year']) ?>" placeholder="2026">
        </div>
        <div class="field-group">
            <label class="label-text">Опубликовано</label>
            <input type="text" name="published" value="<?= h($issue['published']) ?>" placeholder="YYYY-MM-DD">
        </div>
        <div class="field-group">
            <label class="label-text">Название журнала (RU)</label>
            <input type="text" name="journal_title_ru" value="<?= h($issue['journal_title_ru']) ?>">
        </div>
        <div class="field-group">
            <label class="label-text">Название журнала (EN)</label>
            <input type="text" name="journal_title_en" value="<?= h($issue['journal_title_en']) ?>">
        </div>
        <?php
        $ordRu = $issue['author_name_order_ru'] ?? 'family_given';
        $ordEn = $issue['author_name_order_en'] ?? 'given_family';
        if (!in_array($ordRu, ['family_given', 'given_family'], true)) {
            $ordRu = 'family_given';
        }
        if (!in_array($ordEn, ['family_given', 'given_family'], true)) {
            $ordEn = 'given_family';
        }
        ?>
        <div class="field-group" style="grid-column: 1 / -1;">
            <label class="label-text">Порядок ФИО авторов в гранке (русский блок)</label>
            <select name="author_name_order_ru" title="Как склеивать поля «Фамилия» и «Имя» в блоке авторов на русском">
                <option value="family_given"<?= $ordRu === 'family_given' ? ' selected' : '' ?>>Сначала фамилия, затем имя (классика)</option>
                <option value="given_family"<?= $ordRu === 'given_family' ? ' selected' : '' ?>>Сначала имя, затем фамилия</option>
            </select>
        </div>
        <div class="field-group" style="grid-column: 1 / -1;">
            <label class="label-text">Порядок ФИО авторов в гранке (английский блок)</label>
            <select name="author_name_order_en" title="Как склеивать поля в блоке авторов на английском">
                <option value="given_family"<?= $ordEn === 'given_family' ? ' selected' : '' ?>>Сначала имя (given), затем фамилия (western)</option>
                <option value="family_given"<?= $ordEn === 'family_given' ? ' selected' : '' ?>>Сначала фамилия, затем имя</option>
            </select>
        </div>
        <div class="row" style="grid-column: 1 / -1;">
            <button type="submit" class="btn btn-primary">Сохранить метаданные выпуска</button>
        </div>
    </form>
    </section>

    <!-- Панель: Оформление -->
    <section class="dashboard-tab-panel" id="panel-style" role="tabpanel">
    <h2><span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.648 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c.92 0 1.668.748 1.668 1.668 0 .473-.148.836-.437 1.125-.258.29-.437.688-.437 1.125 0 .902.722 1.648 1.648 1.648"/></svg></span> Оформление гранки</h2>
    <p class="muted" style="margin-bottom: 16px;">Настройки применяются к предпросмотру, экспорту HTML и EPUB. Сохраняйте форму своей кнопкой.</p>
    <div class="style-actions-top row" style="margin-bottom: 24px; flex-wrap: wrap; gap: 12px; align-items: center; padding: 16px; background: var(--bg); border-radius: var(--radius); border: 1px solid var(--border);">
        <button type="submit" form="galleyStyleForm" class="btn btn-primary">Сохранить оформление гранки</button>
        <a href="../api/export_galley_preset.php" class="btn btn-light">Сохранить пресет в файл</a>
        <form method="post" action="../api/import_galley_preset.php" enctype="multipart/form-data" style="display: inline-flex; gap: 8px; align-items: center; margin: 0;">
            <input type="file" name="preset_file" accept=".json,application/json" required style="font-size: 13px;">
            <button type="submit" class="btn btn-secondary">Загрузить пресет из файла</button>
        </form>
    </div>
    <?php
    $gf = get_galley_fonts();
    uasort($gf, fn($a, $b) => strcasecmp($a[0], $b[0]));
    $curFont = $issue['galley_font_family'] ?? '';
    $curSize = $issue['galley_font_size'] ?? '';
    $curLine = $issue['galley_line_height'] ?? '';
    $curText = $issue['galley_text_color'] ?? '#1a1a1a';
    $curBg = $issue['galley_bg_color'] ?? '#ffffff';
    $curMeta = $issue['galley_meta_bg'] ?? '#f6f7f8';
    $curLinks = $issue['galley_links_color'] ?? '#0066cc';
    $curLinksHover = $issue['galley_links_hover_color'] ?? '#004499';
    $curMetaRadius = $issue['galley_meta_radius'] ?? '';
    $curMetaBorderColor = $issue['galley_meta_border_color'] ?? '';
    $curMetaBorderWidth = $issue['galley_meta_border_width'] ?? '';
    $curRefsFontSize = $issue['galley_refs_font_size'] ?? '';
    $hex6 = function($c) { if (preg_match('/^#([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])$/', $c, $m)) return '#' . $m[1].$m[1].$m[2].$m[2].$m[3].$m[3]; return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : null; };
    $sizeVal = in_array($curSize, ['10pt','11pt','12pt','14pt','16pt','18pt']) ? $curSize : '12pt';
    $lineVal = in_array($curLine, ['1.3','1.4','1.5','1.6','1.7']) ? $curLine : '1.5';
    $curWidth = $issue['galley_max_width'] ?? '';
    $widthVal = in_array($curWidth, ['36em','42em','48em','56em']) ? $curWidth : '42em';
    ?>
    <form method="post" id="galleyStyleForm">
        <input type="hidden" name="save_issue" value="1">
        <input type="hidden" name="volume" value="<?= h($issue['volume']) ?>">
        <input type="hidden" name="issue" value="<?= h($issue['issue']) ?>">
        <input type="hidden" name="year" value="<?= h($issue['year']) ?>">
        <input type="hidden" name="published" value="<?= h($issue['published']) ?>">
        <input type="hidden" name="journal_title_ru" value="<?= h($issue['journal_title_ru']) ?>">
        <input type="hidden" name="journal_title_en" value="<?= h($issue['journal_title_en']) ?>">
        <input type="hidden" name="author_name_order_ru" value="<?= h(in_array($issue['author_name_order_ru'] ?? 'family_given', ['family_given', 'given_family'], true) ? ($issue['author_name_order_ru'] ?? 'family_given') : 'family_given') ?>">
        <input type="hidden" name="author_name_order_en" value="<?= h(in_array($issue['author_name_order_en'] ?? 'given_family', ['family_given', 'given_family'], true) ? ($issue['author_name_order_en'] ?? 'given_family') : 'given_family') ?>">
        <input type="hidden" name="galley_logo_url" value="<?= h($issue['galley_logo_url'] ?? '') ?>">

        <div class="dashboard-card">
            <h3 class="dashboard-card-title"><span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7V4h16v3M9 20h6M12 4v16"/></svg></span> Шрифт и размер</h3>
            <div class="field-group">
                <label class="label-text">Ссылка на логотип журнала</label>
                <input type="url" name="galley_logo_url" value="<?= h($issue['galley_logo_url'] ?? '') ?>" placeholder="https://example.com/logo.png">
            </div>
            <div class="field-group">
                <label class="label-text">Размер логотипа (px)</label>
                <input type="number" name="galley_logo_size" value="<?= h($issue['galley_logo_size'] ?? '88') ?>" min="40" max="200" step="4" style="width: 80px;">
            </div>
            <div class="field-group">
                <label class="label-text">Выравнивание логотипа</label>
                <select name="galley_logo_align">
                    <option value="left"<?= ($issue['galley_logo_align'] ?? 'center') === 'left' ? ' selected' : '' ?>>Слева</option>
                    <option value="center"<?= ($issue['galley_logo_align'] ?? 'center') === 'center' ? ' selected' : '' ?>>По центру</option>
                    <option value="right"<?= ($issue['galley_logo_align'] ?? 'center') === 'right' ? ' selected' : '' ?>>Справа</option>
                </select>
            </div>
            <div class="field-group">
                <label class="label-text">Пресет оформления</label>
            <select name="galley_preset" id="galleyPreset">
                <option value="standard"<?= ($issue['galley_preset'] ?? '') === 'standard' ? ' selected' : '' ?>>Стандарт (PT Serif)</option>
                <option value="academic"<?= ($issue['galley_preset'] ?? '') === 'academic' ? ' selected' : '' ?>>Академический (Georgia)</option>
                <option value="minimal"<?= ($issue['galley_preset'] ?? '') === 'minimal' ? ' selected' : '' ?>>Минималистичный</option>
                <option value="print"<?= ($issue['galley_preset'] ?? '') === 'print' ? ' selected' : '' ?>>Печатный (крупный шрифт)</option>
                <option value="dark"<?= ($issue['galley_preset'] ?? '') === 'dark' ? ' selected' : '' ?>>Тёмная тема</option>
            </select>
        </div>
        <div class="grid" style="margin-top: 16px;">
            <div class="field-group">
                <label class="label-text">Шрифт</label>
                <input type="hidden" name="galley_font_family" id="galleyFontFamily" value="<?= h($curFont) ?>">
                <div class="font-picker" id="fontPicker">
                    <button type="button" class="font-picker-btn" id="fontPickerBtn" aria-haspopup="listbox" aria-expanded="false">
                        <span id="fontPickerLabel" class="font-picker-label" style="<?= $curFont && isset($gf[$curFont]) ? 'font-family:' . h($gf[$curFont][1]) . ';' : '' ?>"><?= $curFont && isset($gf[$curFont]) ? h($gf[$curFont][0]) : '— по пресету —' ?></span>
                        <span aria-hidden="true">▼</span>
                    </button>
                    <ul class="font-picker-list" id="fontPickerList" role="listbox" aria-label="Выбор шрифта">
                        <li role="option" data-value="" id="fontOptDefault"<?= $curFont === '' ? ' aria-selected="true"' : '' ?>>— по пресету —</li>
                        <?php foreach ($gf as $id => $f): ?>
                        <li role="option" data-value="<?= h($id) ?>" style="font-family:<?= h($f[1]) ?>;"<?= $curFont === $id ? ' aria-selected="true"' : '' ?>><?= h($f[0]) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <style>
            .font-picker{position:relative;width:100%;max-width:280px;}
            .font-picker-btn{display:flex;justify-content:space-between;align-items:center;width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);background:var(--surface);font:inherit;cursor:pointer;text-align:left;color:var(--text);}
            .font-picker-btn:hover{border-color:var(--primary);}
            .font-picker-label{min-height:1.2em;display:inline-block;color:var(--text);}
            .font-picker-btn span:last-child{font-size:0.7em;opacity:0.7;margin-left:8px;}
            .font-picker-list{display:none;position:absolute;top:100%;left:0;right:0;margin:4px 0 0;padding:4px 0;max-height:280px;overflow-y:auto;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius-sm);box-shadow:var(--shadow-md);z-index:100;list-style:none;}
            .font-picker.open .font-picker-list{display:block;}
            .font-picker-list li{padding:10px 12px;cursor:pointer;font-size:15px;}
            .font-picker-list li:hover{background:var(--primary-light);}
            .font-picker-list li[aria-selected="true"]{background:var(--primary-light);color:var(--primary);}
            </style>
            <script>
            (function(){
                var btn=document.getElementById('fontPickerBtn');
                var list=document.getElementById('fontPickerList');
                var hidden=document.getElementById('galleyFontFamily');
                var label=document.getElementById('fontPickerLabel');
                var container=document.getElementById('fontPicker');
                var fonts=<?= json_encode(array_map(fn($f) => [$f[0], $f[1]], $gf)) ?>;
                function setVal(v){
                    hidden.value=v||'';
                    if(v&&fonts[v]){
                        label.textContent=fonts[v][0];
                        label.style.fontFamily=fonts[v][1];
                    }else{
                        label.textContent='\u2014 по пресету \u2014';
                        label.style.fontFamily='';
                    }
                    list.querySelectorAll('[role=option]').forEach(function(li){
                        li.setAttribute('aria-selected',li.dataset.value===v?'true':'false');
                    });
                    container.classList.remove('open');
                    btn.setAttribute('aria-expanded','false');
                }
                btn.onclick=function(e){e.stopPropagation();container.classList.toggle('open');btn.setAttribute('aria-expanded',container.classList.contains('open'));};
                list.querySelectorAll('li').forEach(function(li){li.onclick=function(){setVal(li.dataset.value);};});
                container.addEventListener('click',function(e){e.stopPropagation();});
                document.addEventListener('click',function(){container.classList.remove('open');btn.setAttribute('aria-expanded','false');});
                if(document.fonts&&document.fonts.ready){document.fonts.ready.then(function(){var l=document.getElementById('fontPickerLabel');if(l){l.style.fontFamily=l.style.fontFamily||'';l.offsetHeight;}});}
            })();
            </script>
            <div class="field-group">
                <label class="label-text">Размер шрифта <span id="fontSizeVal"><?= h($sizeVal) ?></span></label>
                <?php $sizeIdx = array_search($sizeVal, ['10pt','11pt','12pt','14pt','16pt','18pt']); $sizeIdx = $sizeIdx !== false ? $sizeIdx : 2; ?>
                <input type="hidden" name="galley_font_size" id="galleyFontSizeHidden" value="<?= h($sizeVal) ?>">
                <input type="range" id="galleyFontSizeRange" min="0" max="5" value="<?= $sizeIdx ?>" style="width:100%;max-width:200px;">
            </div>
            <script>
            (function(){
                var sizes=['10pt','11pt','12pt','14pt','16pt','18pt'];
                var r=document.getElementById('galleyFontSizeRange');
                var h=document.getElementById('galleyFontSizeHidden');
                var v=document.getElementById('fontSizeVal');
                if(r&&h&&v){
                    r.oninput=function(){var s=sizes[parseInt(this.value,10)];h.value=s;v.textContent=s;};
                }
            })();
            </script>
            <div class="field-group">
                <label class="label-text">Межстрочный интервал</label>
                <select name="galley_line_height">
                    <option value="">— по пресету —</option>
                    <option value="1.3"<?= $curLine === '1.3' ? ' selected' : '' ?>>1.3</option>
                    <option value="1.4"<?= $curLine === '1.4' ? ' selected' : '' ?>>1.4</option>
                    <option value="1.5"<?= $curLine === '1.5' ? ' selected' : '' ?>>1.5</option>
                    <option value="1.6"<?= $curLine === '1.6' ? ' selected' : '' ?>>1.6</option>
                    <option value="1.7"<?= $curLine === '1.7' ? ' selected' : '' ?>>1.7</option>
                </select>
            </div>
            <div class="field-group">
                <label class="label-text">Ширина контента <span id="widthVal"><?= h($widthVal) ?></span></label>
                <?php $widthIdx = array_search($widthVal, ['36em','42em','48em','56em']); $widthIdx = $widthIdx !== false ? $widthIdx : 1; ?>
                <input type="hidden" name="galley_max_width" id="galleyWidthHidden" value="<?= h($widthVal) ?>">
                <input type="range" id="galleyWidthRange" min="0" max="3" value="<?= $widthIdx ?>" style="width:100%;max-width:200px;">
            </div>
            <script>
            (function(){
                var widths=['36em','42em','48em','56em'];
                var r=document.getElementById('galleyWidthRange');
                var h=document.getElementById('galleyWidthHidden');
                var v=document.getElementById('widthVal');
                if(r&&h&&v){
                    r.oninput=function(){var w=widths[parseInt(this.value,10)];h.value=w;v.textContent=w;};
                }
            })();
            </script>
        </div>
        </div>

        <div class="dashboard-card">
            <h3 class="dashboard-card-title"><span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.648 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c.92 0 1.668.748 1.668 1.668 0 .473-.148.836-.437 1.125-.258.29-.437.688-.437 1.125 0 .902.722 1.648 1.648 1.648"/></svg></span> Цвета</h3>
            <div class="grid">
            <div class="field-group">
                <label class="label-text">Цвет текста</label>
                <div class="color-row">
                    <input type="color" name="galley_text_color" id="colorText" value="<?= h($hex6($curText) ?? '#1a1a1a') ?>" oninput="this.nextElementSibling.value=this.value">
                    <input type="text" value="<?= h($curText) ?>" placeholder="#1a1a1a" onchange="var c=this.value;if(/^#[0-9a-fA-F]{6}$/.test(c))document.getElementById('colorText').value=c;">
                </div>
            </div>
            <div class="field-group">
                <label class="label-text">Фон страницы</label>
                <div class="color-row">
                    <input type="color" name="galley_bg_color" id="colorBg" value="<?= h($hex6($curBg) ?? '#ffffff') ?>" oninput="this.nextElementSibling.value=this.value">
                    <input type="text" value="<?= h($curBg) ?>" placeholder="#fff" onchange="var c=this.value;if(/^#[0-9a-fA-F]{6}$/.test(c))document.getElementById('colorBg').value=c;">
                </div>
            </div>
            <div class="field-group">
                <label class="label-text">Фон блоков метаданных</label>
                <div class="color-row">
                    <input type="color" name="galley_meta_bg" id="colorMeta" value="<?= h($hex6($curMeta) ?? '#f6f7f8') ?>" oninput="this.nextElementSibling.value=this.value">
                    <input type="text" value="<?= h($curMeta) ?>" placeholder="#f6f7f8" onchange="var c=this.value;if(/^#[0-9a-fA-F]{6}$/.test(c))document.getElementById('colorMeta').value=c;">
                </div>
            </div>
            <div class="field-group">
                <label class="label-text">Цвет ссылок</label>
                <div class="color-row">
                    <input type="color" name="galley_links_color" id="colorLinks" value="<?= h($hex6($curLinks) ?? '#0066cc') ?>" oninput="this.nextElementSibling.value=this.value">
                    <input type="text" value="<?= h($curLinks) ?>" placeholder="#0066cc" onchange="var c=this.value;if(/^#[0-9a-fA-F]{6}$/.test(c))document.getElementById('colorLinks').value=c;">
                </div>
            </div>
            <div class="field-group">
                <label class="label-text">Цвет ссылок при наведении</label>
                <div class="color-row">
                    <input type="color" name="galley_links_hover_color" id="colorLinksHover" value="<?= h($hex6($curLinksHover) ?? '#004499') ?>" oninput="this.nextElementSibling.value=this.value">
                    <input type="text" value="<?= h($curLinksHover) ?>" placeholder="#004499" onchange="var c=this.value;if(/^#[0-9a-fA-F]{6}$/.test(c))document.getElementById('colorLinksHover').value=c;">
                </div>
            </div>
        </div>
        </div>

        <div class="dashboard-card">
            <h3 class="dashboard-card-title"><span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span> Макет</h3>
            <div class="grid">
            <div class="field-group">
                <label class="label-text">Скругление углов блоков</label>
                <select name="galley_meta_radius">
                    <option value="">— по пресету —</option>
                    <option value="0"<?= $curMetaRadius === '0' ? ' selected' : '' ?>>0</option>
                    <option value="4px"<?= $curMetaRadius === '4px' ? ' selected' : '' ?>>4px</option>
                    <option value="6px"<?= $curMetaRadius === '6px' ? ' selected' : '' ?>>6px</option>
                    <option value="8px"<?= $curMetaRadius === '8px' ? ' selected' : '' ?>>8px</option>
                </select>
            </div>
            <div class="field-group">
                <label class="label-text">Рамка блоков</label>
                <div class="color-row" style="flex-wrap:wrap;">
                    <select name="galley_meta_border_width" style="width:80px;">
                        <option value="">нет</option>
                        <option value="1px"<?= $curMetaBorderWidth === '1px' ? ' selected' : '' ?>>1px</option>
                        <option value="2px"<?= $curMetaBorderWidth === '2px' ? ' selected' : '' ?>>2px</option>
                    </select>
                    <input type="color" name="galley_meta_border_color" id="colorMetaBorder" value="<?= h($hex6($curMetaBorderColor) ?? '#dddddd') ?>" oninput="this.nextElementSibling.value=this.value">
                    <input type="text" value="<?= h($curMetaBorderColor) ?>" placeholder="#ddd" onchange="var c=this.value;if(/^#[0-9a-fA-F]{6}$/.test(c))document.getElementById('colorMetaBorder').value=c;">
                </div>
            </div>
            <div class="field-group">
                <label class="label-text">Размер шрифта списка литературы</label>
                <select name="galley_refs_font_size">
                    <option value="">— по пресету —</option>
                    <option value="0.85em"<?= $curRefsFontSize === '0.85em' ? ' selected' : '' ?>>0.85em</option>
                    <option value="0.9em"<?= $curRefsFontSize === '0.9em' ? ' selected' : '' ?>>0.9em</option>
                    <option value="1em"<?= $curRefsFontSize === '1em' ? ' selected' : '' ?>>1em</option>
                </select>
            </div>
        </div>
        </div>

        <div class="dashboard-card">
            <h3 class="dashboard-card-title"><span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></span> Типографика</h3>
            <div class="toggle-list">
            <div class="toggle-row">
                <span class="toggle-text">Заголовок h2 с подчёркиванием</span>
                <label class="toggle-switch">
                    <input type="checkbox" name="galley_h2_underline" value="1"<?= ($issue['galley_h2_underline'] ?? '1') === '1' ? ' checked' : '' ?>>
                </label>
            </div>
            <div class="toggle-row">
                <span class="toggle-text">Заголовок h3 курсивом</span>
                <label class="toggle-switch">
                    <input type="checkbox" name="galley_h3_italic" value="1"<?= ($issue['galley_h3_italic'] ?? '1') === '1' ? ' checked' : '' ?>>
                </label>
            </div>
            <div class="toggle-row">
                <span class="toggle-text">Висячий отступ в списке литературы</span>
                <label class="toggle-switch">
                    <input type="checkbox" name="galley_refs_hanging_indent" value="1"<?= ($issue['galley_refs_hanging_indent'] ?? '0') === '1' ? ' checked' : '' ?>>
                </label>
            </div>
            <div class="toggle-row">
                <span class="toggle-text">Визуально разделять блоки (после «Ключевые слова» / «Keywords»)</span>
                <label class="toggle-switch">
                    <input type="checkbox" name="galley_blocks_separator" value="1"<?= ($issue['galley_blocks_separator'] ?? '0') === '1' ? ' checked' : '' ?>>
                </label>
            </div>
            </div>
        </div>
        <div class="dashboard-card">
            <h3 class="dashboard-card-title"><span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span> Дополнительно</h3>
        <div class="field-group">
            <label class="label-text">Дополнительный CSS</label>
            <p class="muted" style="margin: 0 0 8px; font-size: 13px;">Для опытных верстальщиков: можно добавить произвольные CSS-правила. Убедитесь, что синтаксис корректен.</p>
            <textarea name="galley_custom_css" rows="4" placeholder="Например: .meta-block { border: 1px solid #ddd; }" style="font-family: monospace; font-size: 13px;"><?= h($issue['galley_custom_css'] ?? '') ?></textarea>
        </div>
        </div>
        <div class="row" style="margin-top: 16px; flex-wrap: wrap; gap: 12px; align-items: center;">
            <button type="submit" class="btn btn-primary">Сохранить оформление гранки</button>
            <a href="../api/export_galley_preset.php" class="btn btn-light">Сохранить пресет в файл</a>
            <span class="muted" style="font-size: 13px;">Пресеты можно сохранять, передавать коллегам и восстанавливать на другом компьютере.</span>
        </div>
    </form>
    <form method="post" action="../api/import_galley_preset.php" enctype="multipart/form-data" style="margin-top: 12px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
        <input type="file" name="preset_file" accept=".json,application/json" required style="font-size: 13px;">
        <button type="submit" class="btn btn-secondary">Загрузить пресет из файла</button>
    </form>

    <?php if (isset($_GET['import'])): ?>
    <div class="<?= $_GET['import'] === 'ok' ? 'success' : 'error' ?>" style="margin-top: 12px;">
        <?= $_GET['import'] === 'ok' ? 'Пресет оформления загружен.' : 'Ошибка загрузки пресета. Проверьте формат файла.' ?>
    </div>
    <?php endif; ?>
    </section>

    <!-- Панель: Статьи -->
    <section class="dashboard-tab-panel" id="panel-articles" role="tabpanel">
    <h2><span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg></span> Статьи (<?= count($arts) ?>)</h2>
    <div style="margin-bottom: 12px;">
        <form method="post" action="../api/add_article.php" style="display: inline; margin: 0;">
            <button type="submit" class="btn btn-small btn-primary">+ Добавить статью</button>
        </form>
    </div>
    <form method="post" action="../api/delete_article.php" id="bulkDeleteForm" onsubmit="return document.querySelectorAll('.article-checkbox:checked').length > 0 && confirm('Удалить выбранные статьи?');">
        <div class="articles-controls" style="margin-bottom: 12px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
            <button type="button" class="btn btn-small btn-light" id="selectAllBtn">Выбрать все</button>
            <button type="button" class="btn btn-small btn-light" id="deselectAllBtn">Снять выделение</button>
            <button type="submit" class="btn btn-small btn-danger" id="bulkDeleteBtn" disabled>Удалить выбранные</button>
        </div>
        <div class="table-wrapper">
            <table class="list reorder-table">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="selectAll" title="Выбрать все"></th>
                        <th style="width: 50px;">#</th>
                        <th style="width: 40px;">⇅</th>
                        <th>Название (RU)</th>
                        <th>Страницы</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody id="articles-tbody">
                    <?php foreach ($arts as $i => $a): ?>
                    <tr data-index="<?= $i ?>" id="article-<?= $i ?>" draggable="true">
                        <td><input type="checkbox" name="ids[]" value="<?= $i ?>" class="article-checkbox"></td>
                        <td class="num"><?= ($i + 1) ?></td>
                        <td class="drag-handle" title="Перетащите для изменения порядка">⇅</td>
                        <td><?= h(mb_strimwidth(($a['title_ru'] ?? $a['title_en'] ?? '—') ?: '—', 0, 80, '…', 'UTF-8')) ?></td>
                        <td><?= h(($a['fpage'] ?? '') . (($a['fpage'] ?? '') && ($a['lpage'] ?? '') ? '–' : '') . ($a['lpage'] ?? '')) ?></td>
                        <td>
                            <div class="article-row-actions">
                                <a href="editor.php?id=<?= $i ?>" class="btn btn-small btn-primary" title="Редактировать">Редактировать</a>
                                <div class="article-row-export">
                                    <a href="../api/export_galley.php?id=<?= $i ?>" class="btn btn-small btn-secondary" target="_blank" title="HTML">HTML</a>
                                    <a href="../api/export_galley_epub.php?id=<?= $i ?>" class="btn btn-small btn-secondary" target="_blank" title="EPUB">EPUB</a>
                                </div>
                                <form method="post" action="../api/delete_article.php" style="display:inline;margin:0;" onsubmit="return confirm('Удалить эту статью?');">
                                    <input type="hidden" name="id" value="<?= $i ?>">
                                    <button type="submit" class="btn btn-small btn-danger" title="Удалить">Удалить</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
    <form method="post" action="../api/reorder_articles.php" id="orderForm" style="margin-top: 16px;">
        <input type="hidden" name="order" id="orderInput">
        <div class="row" style="align-items: center;">
            <button type="submit" class="btn btn-secondary">Сохранить порядок статей</button>
            <span class="muted">Перетащите строки таблицы мышью для изменения порядка.</span>
        </div>
    </form>
    </section>

    <script>
    (function() {
        var tabs = document.querySelectorAll('.dashboard-tabs a[data-tab]');
        var panels = document.querySelectorAll('.dashboard-tab-panel');
        function showTab(id) {
            var target = id || (location.hash && location.hash.slice(1));
            if (!target) target = 'meta';
            tabs.forEach(function(t) {
                t.classList.toggle('active', t.dataset.tab === target);
            });
            panels.forEach(function(p) {
                p.classList.toggle('active', p.id === 'panel-' + target);
            });
            if (target && !location.hash) history.replaceState(null, '', '#' + target);
        }
        tabs.forEach(function(t) {
            t.addEventListener('click', function(e) {
                e.preventDefault();
                showTab(t.dataset.tab);
            });
        });
        window.addEventListener('hashchange', function() { showTab(); });
        showTab(location.hash ? location.hash.slice(1) : 'meta');
    })();
    </script>
    <script>
    (function() {
        const form = document.getElementById('bulkDeleteForm');
        const selectAll = document.getElementById('selectAll');
        const checkboxes = form.querySelectorAll('.article-checkbox');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const deselectAllBtn = document.getElementById('deselectAllBtn');

        function updateBulkDeleteBtn() {
            bulkDeleteBtn.disabled = !form.querySelectorAll('.article-checkbox:checked').length;
        }
        function updateSelectAllState() {
            selectAll.checked = checkboxes.length > 0 && checkboxes.length === form.querySelectorAll('.article-checkbox:checked').length;
        }

        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => { cb.checked = this.checked; });
            updateBulkDeleteBtn();
        });
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                updateBulkDeleteBtn();
                updateSelectAllState();
            });
        });
        selectAllBtn.addEventListener('click', function() {
            checkboxes.forEach(cb => { cb.checked = true; });
            selectAll.checked = true;
            updateBulkDeleteBtn();
        });
        deselectAllBtn.addEventListener('click', function() {
            checkboxes.forEach(cb => { cb.checked = false; });
            selectAll.checked = false;
            bulkDeleteBtn.disabled = true;
        });
    })();

    // Drag-and-drop для сортировки статей
    (function() {
        const tbody = document.getElementById('articles-tbody');
        const orderForm = document.getElementById('orderForm');
        const orderInput = document.getElementById('orderInput');
        if (!tbody || !orderForm || !orderInput) return;

        let draggedRow = null;

        tbody.addEventListener('dragstart', function(e) {
            const tr = e.target.closest('tr[data-index]');
            if (!tr) return;
            draggedRow = tr;
            tr.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', tr.dataset.index);
        });

        tbody.addEventListener('dragend', function() {
            if (draggedRow) {
                draggedRow.classList.remove('dragging');
                draggedRow = null;
            }
        });

        function updateRowNumbers() {
            tbody.querySelectorAll('tr[data-index]').forEach(function(tr, idx) {
                const numCell = tr.querySelector('td.num');
                if (numCell) numCell.textContent = idx + 1;
            });
        }

        tbody.addEventListener('dragover', function(e) {
            e.preventDefault();
            const tr = e.target.closest('tr[data-index]');
            if (!tr || tr === draggedRow) return;
            const rect = tr.getBoundingClientRect();
            const offset = e.clientY - rect.top;
            const halfway = rect.height / 2;
            if (offset > halfway) {
                tr.after(draggedRow);
            } else {
                tr.before(draggedRow);
            }
            updateRowNumbers();
        });

        orderForm.addEventListener('submit', function() {
            const order = [];
            tbody.querySelectorAll('tr[data-index]').forEach(function(tr) {
                order.push(tr.dataset.index);
            });
            orderInput.value = order.join(',');
        });
    })();
    </script>

    <div class="footer-info">
        <p class="muted">Версия <?= h(METAGALLEY_VERSION) ?> • 2026</p>
    </div>
</div>
</body>
</html>
