<?php
if (!extension_loaded('simplexml')) {
    die('<h1>MetaGalley</h1><p>Требуется расширение PHP SimpleXML (php-xml). Установите: <code>sudo apt install php-xml</code></p>');
}
require_once __DIR__ . '/src/functions.php';

$error = '';
$success = '';

// Восстановление из резервной копии
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    if (!isset($_FILES['backup']) || !is_uploaded_file($_FILES['backup']['tmp_name'] ?? '')) {
        $error = 'Выберите файл резервной копии (JSON).';
    } else {
    $json = file_get_contents($_FILES['backup']['tmp_name']);
    $data = json_decode($json, true);
    if (is_array($data) && isset($data['articles']) && is_array($data['articles'])) {
        $_SESSION['issue'] = array_merge(default_issue_metadata(), $data['issue'] ?? []);
        $_SESSION['articles'] = $data['articles'];
        header('Location: pages/dashboard.php?restored=1');
        exit;
    }
    $error = 'Неверный формат файла резервной копии.';
    }
}

// Загрузка JATS (один XML или архив ZIP с несколькими XML)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['jats']) && is_uploaded_file($_FILES['jats']['tmp_name'])) {
    $articles = [];
    $firstXml = null;
    $ext = strtolower(pathinfo($_FILES['jats']['name'], PATHINFO_EXTENSION));
    if ($ext === 'zip') {
        if (!class_exists('ZipArchive')) {
            $error = 'Требуется расширение PHP ZipArchive (php-zip). Установите: sudo apt install php-zip';
        } else {
        $zip = new ZipArchive();
        if ($zip->open($_FILES['jats']['tmp_name'], ZipArchive::RDONLY) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!preg_match('/\.xml$/i', $name)) continue;
                $bytes = $zip->getFromIndex($i);
                if ($bytes && strlen($bytes) > 10) {
                    if ($firstXml === null) $firstXml = $bytes;
                    try {
                        $article = parse_jats_xml($bytes);
                        $articles[] = $article;
                    } catch (Throwable $e) {
                        $error = 'Ошибка в файле ' . basename($name) . ': ' . $e->getMessage();
                        break;
                    }
                }
            }
            $zip->close();
        } else {
            $error = 'Не удалось открыть ZIP-архив.';
        }
        }
    } else {
        $bytes = file_get_contents($_FILES['jats']['tmp_name']);
        if (strlen($bytes) < 10) {
            $error = 'Файл пуст или повреждён.';
        } else {
            $firstXml = $bytes;
            try {
                $article = parse_jats_xml($bytes);
                $articles[] = $article;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
    if (!empty($articles) && $error === '') {
        $_SESSION['issue'] = ($firstXml && strlen($firstXml) > 10)
            ? extract_jats_issue_metadata($firstXml)
            : default_issue_metadata();
        $_SESSION['articles'] = $articles;
        header('Location: pages/dashboard.php');
        exit;
    }
}

// Начать с нуля — пустая статья
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_from_scratch'])) {
    $_SESSION['issue'] = default_issue_metadata();
    $_SESSION['articles'] = [get_empty_article()];
    header('Location: pages/dashboard.php');
    exit;
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>MetaGalley <?= h(METAGALLEY_VERSION) ?> — Умный редактор HTML-гранок и EPUB</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="wrap">
    <div class="header-box">
        <h1>MetaGalley</h1>
        <p class="subtitle">Умный редактор HTML-гранок и EPUB научных статей. Загрузите JATS XML или начните с нуля — отредактируйте блоки, экспортируйте готовую HTML-гранку или EPUB.</p>
    </div>

    <div class="topbar topbar-index" style="justify-content: space-between;">
        <span class="muted" style="font-size: 0.95rem;">Версия <?= h(METAGALLEY_VERSION) ?></span>
        <a href="manual.php" class="btn btn-manual-link">Методические рекомендации</a>
    </div>

    <ul class="tabs-nav">
        <li><a href="#tab-jats" class="active" data-tab="jats">
            <svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M10 13l2 2 4-4"/></svg>
            JATS XML
        </a></li>
        <li><a href="#tab-empty" data-tab="empty">
            <svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            С нуля
        </a></li>
        <li><a href="#tab-restore" data-tab="restore">
            <svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            Восстановить
        </a></li>
    </ul>

    <div class="tab-pane active" id="tab-jats">
        <div class="upload-section" style="margin-top: 0;">
            <h3 style="margin-top: 0;">Загрузить JATS XML</h3>
            <p class="muted">Можно загрузить один XML-файл или архив ZIP с несколькими JATS XML. Каждый XML — одна статья. Метаданные выпуска извлекаются из первого файла.</p>
            <form method="post" enctype="multipart/form-data">
                <div class="field-group">
                    <label>
                        <span class="label-text">Файл XML или архив ZIP</span>
                        <input type="file" name="jats" accept=".xml,application/xml,.zip,application/zip" required>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">Загрузить</button>
            </form>
        </div>
    </div>

    <div class="tab-pane" id="tab-empty">
        <div class="upload-section" style="margin-top: 0;">
            <h3 style="margin-top: 0;">Начать с нуля</h3>
            <p class="muted">Создайте выпуск с одной пустой статьёй. Заполните метаданные и контент вручную на рабочем столе и в редакторе гранки.</p>
            <form method="post">
                <input type="hidden" name="start_from_scratch" value="1">
                <button type="submit" class="btn btn-secondary">Создать пустой выпуск</button>
            </form>
        </div>
    </div>

    <div class="tab-pane" id="tab-restore">
        <div class="upload-section" style="margin-top: 0;">
            <h3 style="margin-top: 0;">Восстановить из резервной копии</h3>
            <p class="muted">Загрузите JSON-файл, созданный кнопкой «Резервная копия» на рабочем столе MetaGalley.</p>
            <?php if ($error && isset($_POST['restore_backup'])): ?>
                <div class="error" style="margin-bottom: 16px;"><?= h($error) ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="restore_backup" value="1">
                <div class="field-group">
                    <label>
                        <span class="label-text">Файл JSON</span>
                        <input type="file" name="backup" accept=".json,application/json" required>
                    </label>
                </div>
                <button type="submit" class="btn btn-secondary">Восстановить проект</button>
            </form>
        </div>
    </div>

    <script>
    (function() {
        var links = document.querySelectorAll('.tabs-nav a[data-tab]');
        var panes = document.querySelectorAll('.tab-pane');
        function activate(tab) {
            links.forEach(function(l) {
                l.classList.toggle('active', l.getAttribute('data-tab') === tab);
            });
            panes.forEach(function(p) {
                p.classList.toggle('active', p.id === 'tab-' + tab);
            });
        }
        links.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var tab = link.getAttribute('data-tab');
                activate(tab);
                if (history.replaceState) history.replaceState(null, '', '#' + tab);
            });
        });
        var hash = (window.location.hash || '').replace(/^#/, '');
        if (hash && ['jats','empty','restore'].indexOf(hash) >= 0) activate(hash);
        if (hash === 'html') { activate('jats'); if (history.replaceState) history.replaceState(null, '', '#jats'); }
    })();
    </script>

    <?php if ($error && !isset($_POST['restore_backup'])): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="intro-box">
        <h3>Возможности MetaGalley</h3>
        <ul class="features-list">
            <li>• Загрузка статей из <strong>JATS XML</strong> (один файл или ZIP)</li>
            <li>• Создание выпуска с нуля и ручное заполнение</li>
            <li>• Редактор блоков гранки с предпросмотром и автосохранением</li>
            <li>• Настройка оформления: пресеты, шрифты, цвета, логотип</li>
            <li>• Экспорт <strong>HTML</strong> и <strong>EPUB</strong> по каждой статье или архивом</li>
            <li>• Резервная копия и восстановление (JSON)</li>
        </ul>
    </div>

    <div class="intro-box">
        <h3>Сессия и хранение данных</h3>
        <ul class="features-list">
            <li>• Данные выпуска хранятся в сессии браузера</li>
            <li>• При длительном бездействии сессия PHP может истечь (часто около 24 минут по умолчанию)</li>
            <li>• Пока вы работаете и сохраняете изменения, сессия обновляется</li>
            <li>• Регулярно сохраняйте резервную копию (JSON) на диск</li>
        </ul>
    </div>

    <div class="footer-info">
        <p><strong>Разработчик:</strong> к.и.н. Растям Туктарович Алиев</p>
        <p class="muted">Версия <?= h(METAGALLEY_VERSION) ?> • 2026</p>
    </div>
</div>
</body>
</html>
