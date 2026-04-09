<?php
declare(strict_types=1);

/** Версия инструмента (отображается в интерфейсе) */
const METAGALLEY_VERSION = '1.3';

if (!extension_loaded('simplexml')) {
    throw new RuntimeException('Требуется расширение PHP SimpleXML (php-xml)');
}
session_start();

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Проверяет, есть ли в HTML реальный текстовый контент (не только пустые теги, &nbsp;, <br> и т.п.) */
function html_has_content(string $html): bool {
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);
    return $text !== '';
}

/** Базовый путь приложения (для редиректов из pages/ и api/) */
function app_base(): string {
    $sn = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $d = dirname($sn);
    return (basename($d) === 'pages' || basename($d) === 'api') ? '../' : '';
}

function ensure_session(): void {
    if (!isset($_SESSION['articles']) || !is_array($_SESSION['articles'])) {
        header('Location: ' . app_base() . 'index.php');
        exit;
    }
}

/** Возвращает статью по индексу или null */
function get_article_by_id(int $id): ?array {
    $arts = $_SESSION['articles'] ?? [];
    return isset($arts[$id]) ? $arts[$id] : null;
}

/** Имя файла гранки: первые 3 слова английского названия (или русского) */
function galley_filename(array $art, string $ext = ''): string {
    $title = trim($art['title_en'] ?? '') ?: trim($art['title_ru'] ?? '') ?: 'article';
    $words = preg_split('/\s+/u', $title, 4, PREG_SPLIT_NO_EMPTY);
    $base = implode('-', array_slice($words, 0, 3));
    $base = preg_replace('/[^a-zA-Z0-9а-яА-ЯёЁ_-]+/u', '-', $base);
    $base = preg_replace('/-+/', '-', trim($base, '-'));
    $base = $base ?: 'article';
    return $base . ($ext ? '.' . ltrim($ext, '.') : '');
}

/** Преобразует URL лицензии Creative Commons в короткое имя (CC BY 4.0 и т.д.) */
function license_url_to_short_name(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    $map = [
        'creativecommons.org/licenses/by/4.0' => 'CC BY 4.0',
        'creativecommons.org/licenses/by/3.0' => 'CC BY 3.0',
        'creativecommons.org/licenses/by-sa/4.0' => 'CC BY-SA 4.0',
        'creativecommons.org/licenses/by-sa/3.0' => 'CC BY-SA 3.0',
        'creativecommons.org/licenses/by-nc/4.0' => 'CC BY-NC 4.0',
        'creativecommons.org/licenses/by-nc/3.0' => 'CC BY-NC 3.0',
        'creativecommons.org/licenses/by-nc-sa/4.0' => 'CC BY-NC-SA 4.0',
        'creativecommons.org/licenses/by-nc-sa/3.0' => 'CC BY-NC-SA 3.0',
        'creativecommons.org/licenses/by-nc-nd/4.0' => 'CC BY-NC-ND 4.0',
        'creativecommons.org/licenses/by-nc-nd/3.0' => 'CC BY-NC-ND 3.0',
        'creativecommons.org/licenses/by-nd/4.0' => 'CC BY-ND 4.0',
        'creativecommons.org/licenses/by-nd/3.0' => 'CC BY-ND 3.0',
    ];
    $norm = preg_replace('#^https?://#i', '', rtrim($url, '/'));
    foreach ($map as $pattern => $name) {
        if (stripos($norm, $pattern) !== false) return $name;
    }
    return '';
}

function default_issue_metadata(): array {
    return [
        'volume' => '',
        'issue' => '',
        'year' => '',
        'published' => '',
        'journal_title_ru' => '',
        'journal_title_en' => '',
        /** Порядок в гранке: family_given — фамилия имя; given_family — имя фамилия */
        'author_name_order_ru' => 'family_given',
        'author_name_order_en' => 'given_family',
        'galley_logo_url' => '',
        'galley_logo_size' => '88',
        'galley_logo_align' => 'center',
        // Оформление гранки (кастомизация HTML/EPUB)
        'galley_preset' => 'standard',
        'galley_font_family' => '',
        'galley_font_size' => '',
        'galley_line_height' => '',
        'galley_text_color' => '',
        'galley_bg_color' => '',
        'galley_max_width' => '',
        'galley_meta_bg' => '',
        'galley_links_color' => '',
        'galley_links_hover_color' => '',
        'galley_meta_radius' => '',
        'galley_meta_border_color' => '',
        'galley_meta_border_width' => '',
        'galley_refs_font_size' => '',
        'galley_refs_hanging_indent' => '',
        'galley_h2_underline' => '',
        'galley_h3_italic' => '',
        'galley_blocks_separator' => '',
        'galley_custom_css' => '',
        'blocks_labels' => [],  // Переименования блоков — применяются ко всем статьям выпуска
    ];
}

/** Google Fonts с поддержкой кириллицы (id => [name, family, url]) */
function get_galley_fonts(): array {
    $base = 'https://fonts.googleapis.com/css2?family=';
    return [
        'pt-serif' => ['PT Serif', '"PT Serif", "Times New Roman", Georgia, serif', $base . 'PT+Serif:ital,wght@0,400;0,700;1,400&display=swap'],
        'pt-sans' => ['PT Sans', '"PT Sans", Arial, sans-serif', $base . 'PT+Sans:wght@400;700&display=swap'],
        'merriweather' => ['Merriweather', '"Merriweather", Georgia, serif', $base . 'Merriweather:ital,wght@0,400;0,700;1,400&display=swap'],
        'source-serif' => ['Source Serif Pro', '"Source Serif Pro", Georgia, serif', $base . 'Source+Serif+Pro:ital,wght@0,400;0,700;1,400&display=swap'],
        'lora' => ['Lora', '"Lora", Georgia, serif', $base . 'Lora:ital,wght@0,400;0,700;1,400&display=swap'],
        'crimson-text' => ['Crimson Text', '"Crimson Text", Georgia, serif', $base . 'Crimson+Text:ital,wght@0,400;0,700;1,400&display=swap'],
        'ibm-plex-serif' => ['IBM Plex Serif', '"IBM Plex Serif", Georgia, serif', $base . 'IBM+Plex+Serif:ital,wght@0,400;0,700;1,400&display=swap'],
        'literata' => ['Literata', '"Literata", Georgia, serif', $base . 'Literata:ital,wght@0,400;0,700;1,400&display=swap'],
        'noto-serif' => ['Noto Serif', '"Noto Serif", Georgia, serif', $base . 'Noto+Serif:ital,wght@0,400;0,700;1,400&display=swap'],
        'eb-garamond' => ['EB Garamond', '"EB Garamond", Georgia, serif', $base . 'EB+Garamond:ital,wght@0,400;0,700;1,400&display=swap'],
        'playfair-display' => ['Playfair Display', '"Playfair Display", Georgia, serif', $base . 'Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap'],
        'libre-baskerville' => ['Libre Baskerville', '"Libre Baskerville", Georgia, serif', $base . 'Libre+Baskerville:ital,wght@0,400;0,700;1,400&display=swap'],
        'bitter' => ['Bitter', '"Bitter", Georgia, serif', $base . 'Bitter:ital,wght@0,400;0,700;1,400&display=swap'],
        'vollkorn' => ['Vollkorn', '"Vollkorn", Georgia, serif', $base . 'Vollkorn:ital,wght@0,400;0,700;1,400&display=swap'],
        'cormorant-garamond' => ['Cormorant Garamond', '"Cormorant Garamond", Georgia, serif', $base . 'Cormorant+Garamond:ital,wght@0,400;0,700;1,400&display=swap'],
        'spectral' => ['Spectral', '"Spectral", Georgia, serif', $base . 'Spectral:ital,wght@0,400;0,700;1,400&display=swap'],
        'alegreya' => ['Alegreya', '"Alegreya", Georgia, serif', $base . 'Alegreya:ital,wght@0,400;0,700;1,400&display=swap'],
        'cardo' => ['Cardo', '"Cardo", Georgia, serif', $base . 'Cardo:ital,wght@0,400;0,700;1,400&display=swap'],
        'old-standard' => ['Old Standard TT', '"Old Standard TT", Georgia, serif', $base . 'Old+Standard+TT:ital,wght@0,400;0,700;1,400&display=swap'],
        'roboto' => ['Roboto', '"Roboto", Arial, sans-serif', $base . 'Roboto:wght@400;700&display=swap'],
        'open-sans' => ['Open Sans', '"Open Sans", Arial, sans-serif', $base . 'Open+Sans:wght@400;700&display=swap'],
        'source-sans' => ['Source Sans Pro', '"Source Sans Pro", Arial, sans-serif', $base . 'Source+Sans+Pro:ital,wght@0,400;0,700;1,400&display=swap'],
        'montserrat' => ['Montserrat', '"Montserrat", Arial, sans-serif', $base . 'Montserrat:wght@400;700&display=swap'],
        'raleway' => ['Raleway', '"Raleway", Arial, sans-serif', $base . 'Raleway:wght@400;700&display=swap'],
        'ubuntu' => ['Ubuntu', '"Ubuntu", Arial, sans-serif', $base . 'Ubuntu:ital,wght@0,400;0,700;1,400&display=swap'],
        'inter' => ['Inter', '"Inter", Arial, sans-serif', $base . 'Inter:wght@400;700&display=swap'],
        'nunito-sans' => ['Nunito Sans', '"Nunito Sans", Arial, sans-serif', $base . 'Nunito+Sans:ital,wght@0,400;0,700;1,400&display=swap'],
        'work-sans' => ['Work Sans', '"Work Sans", Arial, sans-serif', $base . 'Work+Sans:ital,wght@0,400;0,700;1,400&display=swap'],
        'manrope' => ['Manrope', '"Manrope", Arial, sans-serif', $base . 'Manrope:wght@400;700&display=swap'],
        'fira-sans' => ['Fira Sans', '"Fira Sans", Arial, sans-serif', $base . 'Fira+Sans:ital,wght@0,400;0,700;1,400&display=swap'],
        'comfortaa' => ['Comfortaa', '"Comfortaa", Arial, sans-serif', $base . 'Comfortaa:wght@400;700&display=swap'],
    ];
}

/** URL для предзагрузки всех шрифтов гранки (для отображения в выпадающем списке) */
function get_galley_fonts_preload_url(): string {
    $parts = [
        'PT+Serif:ital,wght@0,400;0,700;1,400',
        'PT+Sans:wght@400;700',
        'Merriweather:ital,wght@0,400;0,700;1,400',
        'Source+Serif+Pro:ital,wght@0,400;0,700;1,400',
        'Lora:ital,wght@0,400;0,700;1,400',
        'Crimson+Text:ital,wght@0,400;0,700;1,400',
        'IBM+Plex+Serif:ital,wght@0,400;0,700;1,400',
        'Literata:ital,wght@0,400;0,700;1,400',
        'Noto+Serif:ital,wght@0,400;0,700;1,400',
        'EB+Garamond:ital,wght@0,400;0,700;1,400',
        'Playfair+Display:ital,wght@0,400;0,700;1,400',
        'Libre+Baskerville:ital,wght@0,400;0,700;1,400',
        'Bitter:ital,wght@0,400;0,700;1,400',
        'Vollkorn:ital,wght@0,400;0,700;1,400',
        'Cormorant+Garamond:ital,wght@0,400;0,700;1,400',
        'Spectral:ital,wght@0,400;0,700;1,400',
        'Alegreya:ital,wght@0,400;0,700;1,400',
        'Cardo:ital,wght@0,400;0,700;1,400',
        'Old+Standard+TT:ital,wght@0,400;0,700;1,400',
        'Roboto:wght@400;700',
        'Open+Sans:wght@400;700',
        'Source+Sans+Pro:ital,wght@0,400;0,700;1,400',
        'Montserrat:wght@400;700',
        'Raleway:wght@400;700',
        'Ubuntu:ital,wght@0,400;0,700;1,400',
        'Inter:wght@400;700',
        'Nunito+Sans:ital,wght@0,400;0,700;1,400',
        'Work+Sans:ital,wght@0,400;0,700;1,400',
        'Manrope:wght@400;700',
        'Fira+Sans:ital,wght@0,400;0,700;1,400',
        'Comfortaa:wght@400;700',
    ];
    return 'https://fonts.googleapis.com/css2?' . implode('&', array_map(fn($p) => 'family=' . $p, $parts)) . '&display=swap';
}

/** URL шрифта Google Fonts по id или null */
function get_galley_font_url(?string $fontId): ?string {
    if (empty($fontId)) return null;
    $fonts = get_galley_fonts();
    return $fonts[$fontId][2] ?? null;
}

/** Ключи оформления гранки для экспорта/импорта пресета */
function get_galley_preset_keys(): array {
    return [
        'galley_preset', 'galley_logo_url', 'galley_logo_size', 'galley_logo_align', 'galley_font_family', 'galley_font_size', 'galley_line_height',
        'galley_text_color', 'galley_bg_color', 'galley_max_width', 'galley_meta_bg',
        'galley_links_color', 'galley_links_hover_color', 'galley_meta_radius',
        'galley_meta_border_color', 'galley_meta_border_width', 'galley_refs_font_size',
        'galley_refs_hanging_indent', 'galley_h2_underline', 'galley_h3_italic', 'galley_blocks_separator', 'galley_custom_css',
    ];
}

/** Пресеты оформления гранки */
function get_galley_presets(): array {
    return [
        'standard' => [
            'font_family' => '"PT Serif", "Times New Roman", Georgia, serif',
            'font_size' => '12pt',
            'line_height' => '1.5',
            'text_color' => '#1a1a1a',
            'bg_color' => '#fff',
            'max_width' => '42em',
            'meta_bg' => '#f6f7f8',
            'links_color' => '#0066cc',
            'links_hover_color' => '#004499',
            'meta_radius' => '4px',
            'meta_border_color' => '',
            'meta_border_width' => '0',
            'refs_font_size' => '0.9em',
            'refs_hanging_indent' => '0',
            'paragraph_align' => 'justify',
            'h2_underline' => '1',
            'h3_italic' => '1',
        ],
        'academic' => [
            'font_family' => 'Georgia, "Times New Roman", serif',
            'font_size' => '12pt',
            'line_height' => '1.55',
            'text_color' => '#222',
            'bg_color' => '#fff',
            'max_width' => '42em',
            'meta_bg' => '#f0f0f0',
            'links_color' => '#0066cc',
            'links_hover_color' => '#004499',
            'meta_radius' => '0',
            'meta_border_color' => '#ddd',
            'meta_border_width' => '1px',
            'refs_font_size' => '0.9em',
            'refs_hanging_indent' => '1',
            'paragraph_align' => 'justify',
            'h2_underline' => '1',
            'h3_italic' => '0',
        ],
        'minimal' => [
            'font_family' => '"PT Serif", Georgia, serif',
            'font_size' => '12pt',
            'line_height' => '1.6',
            'text_color' => '#333',
            'bg_color' => '#fff',
            'max_width' => '42em',
            'meta_bg' => '#fff',
            'links_color' => '#333',
            'links_hover_color' => '#0066cc',
            'meta_radius' => '0',
            'meta_border_color' => '#eee',
            'meta_border_width' => '1px',
            'refs_font_size' => '0.9em',
            'refs_hanging_indent' => '0',
            'paragraph_align' => 'justify',
            'h2_underline' => '0',
            'h3_italic' => '0',
        ],
        'print' => [
            'font_family' => '"PT Serif", "Times New Roman", Georgia, serif',
            'font_size' => '14pt',
            'line_height' => '1.5',
            'text_color' => '#000',
            'bg_color' => '#fff',
            'max_width' => '42em',
            'meta_bg' => '#f6f7f8',
            'links_color' => '#0066cc',
            'links_hover_color' => '#004499',
            'meta_radius' => '4px',
            'meta_border_color' => '',
            'meta_border_width' => '0',
            'refs_font_size' => '0.9em',
            'refs_hanging_indent' => '1',
            'paragraph_align' => 'justify',
            'h2_underline' => '1',
            'h3_italic' => '1',
        ],
        'dark' => [
            'font_family' => '"PT Serif", Georgia, serif',
            'font_size' => '12pt',
            'line_height' => '1.5',
            'text_color' => '#e0e0e0',
            'bg_color' => '#1a1a1a',
            'max_width' => '42em',
            'meta_bg' => '#2a2a2a',
            'links_color' => '#7eb8ff',
            'links_hover_color' => '#a8d4ff',
            'meta_radius' => '4px',
            'meta_border_color' => '#444',
            'meta_border_width' => '1px',
            'refs_font_size' => '0.9em',
            'refs_hanging_indent' => '0',
            'paragraph_align' => 'justify',
            'h2_underline' => '1',
            'h3_italic' => '1',
        ],
    ];
}

/** Возвращает значения оформления гранки (пресет + переопределения) */
function get_galley_style_values(array $issue): array {
    $presets = get_galley_presets();
    $presetName = $issue['galley_preset'] ?? 'standard';
    $preset = $presets[$presetName] ?? $presets['standard'];
    $map = [
        'font_family' => 'galley_font_family',
        'font_size' => 'galley_font_size',
        'line_height' => 'galley_line_height',
        'text_color' => 'galley_text_color',
        'bg_color' => 'galley_bg_color',
        'max_width' => 'galley_max_width',
        'meta_bg' => 'galley_meta_bg',
        'links_color' => 'galley_links_color',
        'links_hover_color' => 'galley_links_hover_color',
        'meta_radius' => 'galley_meta_radius',
        'meta_border_color' => 'galley_meta_border_color',
        'meta_border_width' => 'galley_meta_border_width',
        'refs_font_size' => 'galley_refs_font_size',
        'refs_hanging_indent' => 'galley_refs_hanging_indent',
        'h2_underline' => 'galley_h2_underline',
        'h3_italic' => 'galley_h3_italic',
    ];
    $out = [];
    foreach ($map as $key => $issueKey) {
        $val = trim($issue[$issueKey] ?? '');
        $out[$key] = $val !== '' ? $val : ($preset[$key] ?? '');
    }
    // Разрешение шрифта: galley_font_family может быть id из get_galley_fonts()
    $fontId = trim($issue['galley_font_family'] ?? '');
    if ($fontId !== '') {
        $fonts = get_galley_fonts();
        if (isset($fonts[$fontId])) {
            $out['font_family'] = $fonts[$fontId][1];
        }
    }
    $out['paragraph_align'] = $preset['paragraph_align'] ?? 'justify';
    return $out;
}

/** Строит CSS для гранки на основе настроек выпуска. $layout: 'preview' | 'full' */
function build_galley_css(array $issue, string $layout = 'preview'): string {
    $v = get_galley_style_values($issue);
    $h2Border = ($v['h2_underline'] ?? '1') === '1' ? 'border-bottom: 1pt solid ' . ($v['text_color'] ?? '#333') . '; padding-bottom: 0.25em;' : '';
    $h3Style = ($v['h3_italic'] ?? '1') === '1' ? 'font-style: italic;' : '';
    $bodyExtra = $layout === 'preview'
        ? 'max-width: ' . ($v['max_width'] ?? '42em') . '; margin: 2.5em auto; padding: 0 1.5em;'
        : 'margin: 0; padding: 0;';
    $base = '
        body { font-family: ' . ($v['font_family'] ?? 'serif') . '; font-size: ' . ($v['font_size'] ?? '12pt') . '; line-height: ' . ($v['line_height'] ?? '1.5') . '; color: ' . ($v['text_color'] ?? '#1a1a1a') . '; ' . $bodyExtra . ' background: ' . ($v['bg_color'] ?? '#fff') . '; text-rendering: optimizeLegibility; }
        h1, h2, h3 { font-family: inherit; font-weight: 700; margin-top: 1.5em; margin-bottom: 0.5em; line-height: 1.3; }
        h1 { font-size: 1.5em; margin-top: 0; }
        h2 { font-size: 1.25em; ' . $h2Border . ' }
        h3 { font-size: 1.1em; ' . $h3Style . ' }
        p { text-align: ' . ($v['paragraph_align'] ?? 'justify') . '; margin: 0 0 1em; }
        .abstract p, .authors p { text-indent: 0; }
        .authors { padding: 0.6em 0; font-size: 0.9em; line-height: 1.45; margin: 0.5em 0; text-align: left; }
        .authors .author-entry { margin-bottom: 0.6em; }
        .authors .author-entry:last-child { margin-bottom: 0; }
        .authors .authors-corresp-note { margin: 0.5em 0 0; font-size: 0.85em; color: ' . ($v['text_color'] ?? '#666') . '; opacity: 0.85; }
        .authors p { text-align: left; }
        .body p { text-indent: 0; }
        ol, ul { margin: 0.5em 0 1em 1.5em; }
        .refs, .refs.refs-plain { padding: 0.75em 0; font-size: ' . ($v['refs_font_size'] ?? '0.9em') . '; line-height: 1.5; margin: 0.5em 0; list-style: none; text-align: left; }
        .refs-plain p { margin: 0 0 0.85em; ' . (($v['refs_hanging_indent'] ?? '0') === '1' ? 'text-indent: -2em; padding-left: 2em; ' : 'text-indent: 0; ') . 'text-align: left; }
        .refs-plain p:last-child { margin-bottom: 0; }
        table { border-collapse: collapse; margin: 1.5em auto; width: 100%; }
        table, th, td { border: 1pt solid ' . ($v['text_color'] ?? '#333') . '; padding: 0.4em 0.6em; }
        th { background: ' . ($v['meta_bg'] ?? '#f5f5f5') . '; }
        img { max-width: 100%; height: auto; }
        .meta-block { background: ' . ($v['meta_bg'] ?? '#f6f7f8') . '; padding: 0.75em 1em; border-radius: ' . ($v['meta_radius'] ?? '4px') . '; font-size: 0.9em; color: ' . ($v['text_color'] ?? '#444') . '; opacity: 0.95; ' . ((($v['meta_border_width'] ?? '0') !== '0' && ($v['meta_border_width'] ?? '') !== '') ? 'border: ' . ($v['meta_border_width'] ?? '1px') . ' solid ' . ($v['meta_border_color'] ?? '#ddd') . '; ' : '') . '}
        .meta-block p { margin: 0.3em 0; text-indent: 0; }
        .meta-rubric { font-variant: small-caps; }
        .meta-dates { font-style: italic; }
        .extra-info, .authors-info { background: ' . ($v['meta_bg'] ?? '#f6f7f8') . '; padding: 0.6em 1em; border-radius: ' . ($v['meta_radius'] ?? '4px') . '; font-size: 0.9em; margin: 0.5em 0; ' . ((($v['meta_border_width'] ?? '0') !== '0' && ($v['meta_border_width'] ?? '') !== '') ? 'border: ' . ($v['meta_border_width'] ?? '1px') . ' solid ' . ($v['meta_border_color'] ?? '#ddd') . '; ' : '') . '}
        a { color: ' . ($v['links_color'] ?? '#0066cc') . '; }
        a:hover { color: ' . ($v['links_hover_color'] ?? $v['links_color'] ?? '#004499') . '; }
        .block-separator { margin: 1em 0; border: none; border-top: 1pt solid ' . ($v['text_color'] ?? '#ccc') . '; opacity: 0.6; }
    ';
    $custom = trim($issue['galley_custom_css'] ?? '');
    return $base . ($custom !== '' ? "\n        /* Дополнительный CSS */\n        " . $custom : '');
}

/** Минимальный CSS для EPUB: шрифт и тема — у читалки; здесь только масштаб картинок и границы таблиц. */
function build_galley_epub_css(): string {
    return preg_replace('/\s+/', ' ', trim('
        body { margin: 0; }
        img, svg { max-width: 100%; height: auto; }
        table { border-collapse: collapse; width: 100%; max-width: 100%; }
        table, th, td { border: 1px solid #999; padding: 0.2em 0.4em; }
    '));
}

/** Строит полный CSS для экспорта HTML (с оглавлением). Изоляция для OJS: .metagalley-granka сбрасывает наследуемые стили. */
function build_galley_css_full(array $issue): string {
    $v = get_galley_style_values($issue);
    $maxWidth = $v['max_width'] ?? '42em';
    $bgColor = $v['bg_color'] ?? '#fff';
    $linksColor = $v['links_color'] ?? '#0066cc';
    $textColor = $v['text_color'] ?? '#1a1a1a';
    $fontFamily = $v['font_family'] ?? 'serif';
    $fontSize = $v['font_size'] ?? '12pt';
    $lineHeight = $v['line_height'] ?? '1.5';
    $metaBg = $v['meta_bg'] ?? '#f6f7f8';
    $metaRadius = $v['meta_radius'] ?? '4px';
    $h2Border = ($v['h2_underline'] ?? '1') === '1' ? 'border-bottom: 1pt solid ' . ($v['text_color'] ?? '#333') . '; padding-bottom: 0.25em;' : '';
    $h3Style = ($v['h3_italic'] ?? '1') === '1' ? 'font-style: italic;' : '';
    $base = build_galley_css($issue, 'full');
    $toc = '
        /* Изоляция при встраивании в OJS — сброс наследуемых стилей */
        .metagalley-granka { all: initial; display: block; box-sizing: border-box; font-family: ' . $fontFamily . ' !important; font-size: ' . $fontSize . ' !important; line-height: ' . $lineHeight . ' !important; color: ' . $textColor . ' !important; background: ' . $bgColor . ' !important; text-rendering: optimizeLegibility; }
        .metagalley-granka *, .metagalley-granka *::before, .metagalley-granka *::after { box-sizing: border-box; }
        .metagalley-granka h1, .metagalley-granka h2, .metagalley-granka h3 { font-family: inherit !important; font-weight: 700 !important; margin-top: 1.5em !important; margin-bottom: 0.5em !important; line-height: 1.3 !important; }
        .metagalley-granka h1 { font-size: 1.5em !important; margin-top: 0 !important; }
        .metagalley-granka h2 { font-size: 1.25em !important; ' . $h2Border . ' }
        .metagalley-granka h3 { font-size: 1.1em !important; ' . $h3Style . ' }
        .metagalley-granka p { font-size: inherit !important; line-height: inherit !important; text-align: ' . ($v['paragraph_align'] ?? 'justify') . '; margin: 0 0 1em !important; }
        .metagalley-granka .meta-block { background: ' . $metaBg . ' !important; padding: 0.75em 1em !important; border-radius: ' . $metaRadius . ' !important; font-size: 0.9em !important; color: ' . ($v['text_color'] ?? '#444') . ' !important; }
        .metagalley-granka .authors { font-size: 0.9em !important; line-height: 1.45 !important; }
        .metagalley-granka .abstract p, .metagalley-granka .authors p { text-indent: 0 !important; }
        .metagalley-granka .meta-block p { margin: 0.3em 0 !important; }
        .metagalley-granka .extra-info, .metagalley-granka .authors-info { background: ' . $metaBg . ' !important; padding: 0.6em 1em !important; border-radius: ' . $metaRadius . ' !important; font-size: 0.9em !important; }
        .metagalley-granka a { color: ' . $linksColor . ' !important; }
        .metagalley-granka a:hover { color: ' . ($v['links_hover_color'] ?? $linksColor) . ' !important; }
        .journal-header { text-align: ' . h(in_array($issue['galley_logo_align'] ?? 'center', ['left','center','right'], true) ? ($issue['galley_logo_align'] ?? 'center') : 'center') . '; padding: 1.2em 1.5em 0.8em; margin-bottom: 0.5em; }
        .journal-header img { height: ' . (int)($issue['galley_logo_size'] ?? 88) . 'px; width: auto; max-width: 800px; object-fit: contain; display: inline-block; }
        .metagalley-granka.no-logo #article-content { padding-top: 2em; }
        #article-content { max-width: ' . $maxWidth . '; margin: 0 auto; padding: 0 1.5em 3em; }
        #float-buttons { position: fixed; bottom: 24px; right: 24px; z-index: 1000; display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
        #float-buttons .btn-row { display: flex; flex-direction: row; gap: 10px; }
        #toc-panel { max-height: 0; overflow: hidden; transition: max-height 0.25s ease; border: none; padding: 0; background: transparent; box-shadow: none; }
        #toc-panel.toc-open { max-height: 50vh; overflow-y: auto; padding: 12px 16px; margin-bottom: 4px; background: ' . ($v['meta_bg'] ?? '#fafafa') . '; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); border: 1px solid rgba(0,0,0,0.08); }
        #toc-list { list-style: none; padding: 0; margin: 0; font-size: 0.88em; min-width: 180px; }
        #toc-list li { margin: 0.25em 0; }
        #toc-list a { color: ' . $linksColor . '; text-decoration: none; display: block; padding: 2px 0; }
        #toc-list a:hover { text-decoration: underline; }
        .float-btn { width: 48px; height: 48px; border-radius: 50%; border: 1px solid rgba(0,0,0,0.08); cursor: pointer; font-size: 1.2em; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 12px rgba(0,0,0,0.12); transition: transform 0.2s, box-shadow 0.2s, background 0.2s; background: ' . $bgColor . '; color: ' . $textColor . '; }
        .float-btn:hover { transform: scale(1.06); box-shadow: 0 4px 18px rgba(0,0,0,0.18); background: ' . ($v['meta_bg'] ?? '#f5f5f5') . '; }
        .float-btn.toc-btn { font-size: 1.35em; }
        .float-btn.back-btn { font-size: 1.25em; line-height: 1; }
        .galley-article-footer { margin-top: 2.5em !important; padding-top: 1.25em !important; border-top: 1px solid rgba(0,0,0,0.12) !important; text-align: center !important; }
        .issue-imprint { font-size: 0.9em !important; color: ' . ($v['text_color'] ?? '#555') . ' !important; opacity: 0.95 !important; }
        .issue-imprint p { text-align: center !important; margin: 0.4em 0 !important; text-indent: 0 !important; }
        .galley-credit { text-align: center !important; margin: 0.75em 0 0 !important; padding: 0 !important; text-indent: 0 !important; font-size: 0.8em !important; color: ' . ($v['text_color'] ?? '#555') . ' !important; opacity: 0.45 !important; }
        .issue-imprint + .galley-credit { margin-top: 0.85em !important; }
    ';
    return $base . $toc;
}

/** Извлекает метаданные выпуска из JATS XML (journal-meta + article-meta) */
function extract_jats_issue_metadata(string $xmlBytes): array {
    $issue = default_issue_metadata();
    libxml_use_internal_errors(true);
    $sx = @simplexml_load_string($xmlBytes);
    if ($sx === false) return $issue;

    $sx->registerXPathNamespace('j', 'http://www.ncbi.nlm.nih.gov/JATS1');
    $sx->registerXPathNamespace('xlink', 'http://www.w3.org/1999/xlink');
    $front = $sx->front ?? $sx->children('j', true)->front ?? null;
    if (!$front) return $issue;

    // journal-meta: journal-title-group/journal-title
    $jm = $front->{'journal-meta'} ?? ($front->children('j', true)->{'journal-meta'} ?? null);
    if ($jm) {
        $jtg = $jm->{'journal-title-group'} ?? null;
        if ($jtg) {
            foreach ($jtg->{'journal-title'} ?? [] as $jt) {
                $lang = (string)($jt['xml:lang'] ?? '');
                if ($lang === '') {
                    $attrs = $jt->attributes('http://www.w3.org/XML/1998/namespace');
                    $lang = ($attrs && isset($attrs['lang'])) ? (string)$attrs['lang'] : 'ru';
                }
                $val = trim((string)$jt);
                if ($val === '') continue;
                if ($lang === 'en' || $lang === 'eng') $issue['journal_title_en'] = $val;
                else $issue['journal_title_ru'] = $val;
            }
        }
    }

    // article-meta: volume, issue, pub-date
    $am = $front->{'article-meta'} ?? ($front->children('j', true)->{'article-meta'} ?? null);
    if ($am) {
        $issue['volume'] = trim((string)($am->volume ?? ''));
        $issue['issue'] = trim((string)($am->issue ?? ''));
        $pd = $am->{'pub-date'} ?? null;
        if ($pd) {
            $y = trim((string)($pd->year ?? ''));
            $m = trim((string)($pd->month ?? ''));
            $d = trim((string)($pd->day ?? ''));
            if ($y) $issue['year'] = $y;
            if ($y && $m && $d) {
                $issue['published'] = $y . '-' . str_pad($m, 2, '0', STR_PAD_LEFT) . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
            } elseif ($y) {
                $issue['published'] = $y . '-01-01';
            }
        }
    }

    return $issue;
}

/** Применяет метаданные выпуска ко всем статьям (том, выпуск, год, название журнала) */
function issue_apply_to_articles(array $issue): void {
    $arts = &$_SESSION['articles'];
    if (!is_array($arts)) return;
    foreach ($arts as $i => $art) {
        $arts[$i]['volume'] = $issue['volume'] ?? $arts[$i]['volume'] ?? '';
        $arts[$i]['issue'] = $issue['issue'] ?? $arts[$i]['issue'] ?? '';
        if (!empty(trim($issue['published'] ?? ''))) {
            $arts[$i]['pub_date'] = trim($issue['published']);
        } elseif (!empty($issue['year'])) {
            $arts[$i]['pub_date'] = $issue['year'] . '-01-01';
        }
        $arts[$i]['journal_title_ru'] = $issue['journal_title_ru'] ?? $arts[$i]['journal_title_ru'] ?? '';
        $arts[$i]['journal_title_en'] = $issue['journal_title_en'] ?? $arts[$i]['journal_title_en'] ?? '';
    }
}

/** Канонический порядок блоков: EN сначала, затем RU, текст, доп. информация, список литературы, редакторские */
function get_canonical_blocks_order(): array {
    return [
        // EN блоки (сначала): метаданные под authors
        'title_en', 'authors_en', 'meta_en', 'abstract_en', 'keywords_en',
        // RU блоки
        'title_ru', 'authors_ru', 'meta_ru', 'abstract_ru', 'keywords_ru',
        // Текст статьи
        'body',
        // Дополнительная информация для верстальщика
        'extra_info',
        // Список литературы (порядок без изменений)
        'refs_ru', 'refs_en',
        // Информация об авторах (парные RU/EN, отображаются только если заполнены)
        'authors_info_en', 'authors_info_ru',
        // Редакторские (только для редактирования, в HTML не выводятся)
        'meta', 'authors',
    ];
}

/** Возвращает пустую статью для режима «Начать с нуля» */
function get_empty_article(): array {
    return [
        'title_ru' => '',
        'title_en' => '',
        'abstract_ru' => '',
        'abstract_en' => '',
        'keywords_ru' => [],
        'keywords_en' => [],
        'authors' => [],
        'doi' => '',
        'edn' => '',
        'url' => '',
        'fpage' => '',
        'lpage' => '',
        'volume' => '',
        'issue' => '',
        'pub_date' => '',
        'history' => ['received' => '', 'accepted' => '', 'revised' => ''],
        'article_categories_ru' => [],
        'article_categories_en' => [],
        'funding_ru' => [],
        'funding_en' => [],
        'permissions' => ['copyright_year' => '', 'copyright_holder_ru' => '', 'copyright_holder_en' => '', 'license' => '', 'license_url' => ''],
        'body_html' => '',
        'extra_info_html' => '',
        'extra_info_title' => '',
        'authors_info_ru_html' => '',
        'authors_info_ru_title' => '',
        'authors_info_en_html' => '',
        'authors_info_en_title' => '',
        'refs_ru' => '',
        'refs_en' => '',
        'blocks_order' => get_canonical_blocks_order(),
        'blocks_labels' => [],
        'blocks_groups' => [],
        /** Обложка EPUB: только в сессии (base64), не отдельные файлы на диске */
        'epub_cover' => null,
    ];
}

/** Определяет формат XML и вызывает парсер JATS */
function parse_article_xml(string $xmlBytes): array {
    $pre = substr(trim($xmlBytes), 0, 2048);
    if (stripos($pre, '<article') !== false && (stripos($pre, 'article-type') !== false || stripos($pre, 'dtd-version') !== false)) {
        return parse_jats_xml($xmlBytes);
    }
    throw new Exception('Неизвестный формат XML. Поддерживается JATS XML (article).');
}

/** Парсинг JATS XML (одна статья) */
function parse_jats_xml(string $xmlBytes): array {
    libxml_use_internal_errors(true);
    $sx = @simplexml_load_string($xmlBytes);
    if ($sx === false) {
        $errs = array_map(fn($e) => trim($e->message), libxml_get_errors());
        throw new Exception('Невалидный JATS XML: ' . implode('; ', array_slice($errs, 0, 3)));
    }

    $ns = $sx->getNamespaces(true);
    $sx->registerXPathNamespace('j', 'http://www.ncbi.nlm.nih.gov/JATS1');
    $sx->registerXPathNamespace('xlink', 'http://www.w3.org/1999/xlink');

    $front = $sx->front ?? $sx->children('j', true)->front ?? null;
    $body = $sx->body ?? $sx->children('j', true)->body ?? null;
    $back = $sx->back ?? $sx->children('j', true)->back ?? null;

    $article = [
        'title_ru' => '',
        'title_en' => '',
        'abstract_ru' => '',
        'abstract_en' => '',
        'keywords_ru' => [],
        'keywords_en' => [],
        'authors' => [],
        'doi' => '',
        'edn' => '',
        'url' => '',
        'fpage' => '',
        'lpage' => '',
        'volume' => '',
        'issue' => '',
        'pub_date' => '',
        'history' => ['received' => '', 'accepted' => '', 'revised' => ''],
        'article_categories_ru' => [],
        'article_categories_en' => [],
        'funding_ru' => [],
        'funding_en' => [],
        'permissions' => ['copyright_year' => '', 'copyright_holder_ru' => '', 'copyright_holder_en' => '', 'license' => '', 'license_url' => ''],
        'body_html' => '',
        'extra_info_html' => '',
        'extra_info_title' => '',
        'authors_info_ru_html' => '',
        'authors_info_ru_title' => '',
        'authors_info_en_html' => '',
        'authors_info_en_title' => '',
        'refs_ru' => [],
        'refs_en' => [],
        'blocks_order' => get_canonical_blocks_order(),
    ];

    if (!$front) return $article;

    $am = $front->{'article-meta'} ?? $front->children('j', true)->{'article-meta'} ?? null;
    if (!$am) return $article;

    // DOI, URL
    foreach ($am->{'article-id'} ?? [] as $aid) {
        $type = (string)($aid['pub-id-type'] ?? '');
        $val = trim((string)$aid);
        $assigningAuthority = (string)($aid['assigning-authority'] ?? '');
        if ($type === 'doi') $article['doi'] = $val;
        if ($type === 'edn' || ($type === 'other' && strtolower($assigningAuthority) === 'edn')) $article['edn'] = $val;
        if ($type === 'uri') $article['url'] = $val;
    }
    $selfUri = $am->{'self-uri'} ?? null;
    if ($selfUri && $article['url'] === '') {
        $article['url'] = trim((string)$selfUri);
    }

    // Заголовки
    $tg = $am->{'title-group'} ?? null;
    if ($tg) {
        $at = $tg->{'article-title'} ?? null;
        if ($at) {
            $lang = (string)($at['xml:lang'] ?? 'ru');
            $article[$lang === 'en' ? 'title_en' : 'title_ru'] = trim((string)$at);
        }
        $ttg = $tg->{'trans-title-group'} ?? null;
        if ($ttg) {
            $tt = $ttg->{'trans-title'} ?? null;
            if ($tt) $article['title_en'] = trim((string)$tt);
        }
    }

    // Авторы
    $cg = $am->{'contrib-group'} ?? null;
    $affMap = [];
    if ($cg) {
        foreach ($cg->{'aff-alternatives'} ?? [] as $affAlt) {
            $rid = (string)($affAlt['id'] ?? '');
            foreach ($affAlt->aff ?? [] as $aff) {
                $inst = $aff->institution ?? null;
                if ($inst) {
                    $lang = (string)($inst['xml:lang'] ?? '');
                    if ($lang === '') {
                        $attrs = $inst->attributes('http://www.w3.org/XML/1998/namespace');
                        $lang = ($attrs && isset($attrs['lang'])) ? (string)$attrs['lang'] : 'ru';
                    }
                    $affMap[$rid][$lang === 'en' || $lang === 'eng' ? 'en' : 'ru'] = trim((string)$inst);
                }
            }
        }
        foreach ($cg->contrib ?? [] as $c) {
            $ct = (string)($c['contrib-type'] ?? 'author');
            if ($ct === 'reviewer') continue;
            $name = $c->name ?? null;
            $surname = $given = '';
            $nameStyle = $name ? (string)($name['name-style'] ?? '') : '';
            if ($name) {
                $surname = trim((string)($name->surname ?? ''));
                $given = trim((string)($name->{'given-names'} ?? ''));
            }
            $nameAlt = $c->{'name-alternatives'} ?? null;
            $surnameRu = $surname; $givenRu = $given;
            $surnameEn = ''; $givenEn = '';
            if ($nameAlt) {
                foreach ($nameAlt->name ?? [] as $n) {
                    $sr = trim((string)($n->surname ?? ''));
                    $gr = trim((string)($n->{'given-names'} ?? ''));
                    $style = (string)($n['name-style'] ?? '');
                    $l = (string)($n['xml:lang'] ?? '');
                    $isEn = ($l === 'en' || $style === 'western' || (strlen($sr) > 0 && !preg_match('/\p{Cyrillic}/u', $sr)));
                    if ($isEn) {
                        $surnameEn = $sr; $givenEn = $gr;
                    } else {
                        $surnameRu = $sr; $givenRu = $gr;
                    }
                }
            }
            if ($surnameRu === '' && $nameStyle === 'eastern') {
                $surnameRu = $surname; $givenRu = $given;
            }
            $email = trim((string)($c->email ?? ''));
            $orcid = '';
            foreach ($c->{'contrib-id'} ?? [] as $cid) {
                if ((string)($cid['contrib-id-type'] ?? '') === 'orcid') {
                    $orcid = trim((string)$cid);
                    break;
                }
            }
            $xref = $c->xref ?? null;
            $affRu = $affEn = '';
            if ($xref) {
                $rid = (string)($xref['rid'] ?? '');
                if (isset($affMap[$rid])) {
                    $affRu = $affMap[$rid]['ru'] ?? '';
                    $affEn = $affMap[$rid]['en'] ?? '';
                }
            }
            $corresp = strtolower(trim((string)($c['corresp'] ?? ''))) === 'yes';
            $article['authors'][] = [
                'surname_ru' => $surnameRu, 'given_ru' => $givenRu,
                'surname_en' => $surnameEn, 'given_en' => $givenEn,
                'email' => $email, 'orcid' => $orcid,
                'aff_ru' => $affRu, 'aff_en' => $affEn,
                'city_country_ru' => '', 'city_country_en' => '',
                'corresp' => $corresp,
            ];
        }
    }

    // Дата публикации
    $pd = $am->{'pub-date'} ?? null;
    if ($pd) {
        $y = (string)($pd->year ?? '');
        $m = (string)($pd->month ?? '01');
        $d = (string)($pd->day ?? '01');
        if ($y) $article['pub_date'] = $y . '-' . str_pad($m, 2, '0', STR_PAD_LEFT) . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
    }

    // Страницы, том, выпуск
    $article['fpage'] = trim((string)($am->fpage ?? ''));
    $article['lpage'] = trim((string)($am->lpage ?? ''));
    $article['volume'] = trim((string)($am->volume ?? ''));
    $article['issue'] = trim((string)($am->issue ?? ''));

    // Рубрики (article-categories / subj-group) — по кириллице или xml:lang
    foreach ($am->{'article-categories'} ?? [] as $ac) {
        foreach ($ac->{'subj-group'} ?? [] as $sg) {
            foreach ($sg->subject ?? [] as $subj) {
                $txt = trim((string)$subj);
                if ($txt === '') continue;
                $lang = (string)($subj['xml:lang'] ?? '');
                if ($lang === '') {
                    $attrs = $subj->attributes('http://www.w3.org/XML/1998/namespace');
                    $lang = ($attrs && isset($attrs['lang'])) ? (string)$attrs['lang'] : '';
                }
                $key = ($lang === 'en' || $lang === 'eng') ? 'article_categories_en' : (preg_match('/\p{Cyrillic}/u', $txt) ? 'article_categories_ru' : 'article_categories_en');
                $article[$key][] = $txt;
            }
        }
    }

    // История статьи
    $history = $am->history ?? null;
    if ($history) {
        foreach ($history->date ?? [] as $d) {
            $type = (string)($d['date-type'] ?? '');
            $iso = (string)($d['iso-8601-date'] ?? '');
            if ($iso === '') {
                $y = (string)($d->year ?? '');
                $m = (string)($d->month ?? '01');
                $day = (string)($d->day ?? '01');
                if ($y) $iso = $y . '-' . str_pad($m, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
            }
            if ($type === 'received') $article['history']['received'] = $iso;
            elseif ($type === 'accepted') $article['history']['accepted'] = $iso;
            elseif ($type === 'rev-recd' || $type === 'revised') $article['history']['revised'] = $iso;
        }
    }

    // Финансирование
    foreach ($am->{'funding-group'} ?? [] as $fg) {
        foreach ($fg->{'funding-statement'} ?? [] as $fs) {
            $lang = (string)($fs['xml:lang'] ?? '');
            if ($lang === '') {
                $attrs = $fs->attributes('http://www.w3.org/XML/1998/namespace');
                $lang = ($attrs && isset($attrs['lang'])) ? (string)$attrs['lang'] : 'ru';
            }
            $txt = trim((string)$fs);
            if ($txt !== '') {
                $key = ($lang === 'en' || $lang === 'eng') ? 'funding_en' : 'funding_ru';
                $article[$key][] = $txt;
            }
        }
    }

    // Права и лицензия
    $perm = $am->permissions ?? null;
    if ($perm) {
        $article['permissions']['copyright_year'] = trim((string)($perm->{'copyright-year'} ?? ''));
        $licEl = $perm->license ?? null;
        $licText = '';
        $licUrl = '';
        if ($licEl) {
            $licText = trim((string)($licEl->{'license-p'} ?? $licEl));
            $xlink = $licEl->attributes('http://www.w3.org/1999/xlink');
            $licUrl = ($xlink && isset($xlink['href'])) ? trim((string)$xlink['href']) : '';
            if ($licUrl === '' && isset($licEl->children('http://www.niso.org/schemas/ali/1.0/')->license_ref)) {
                $aliRef = $licEl->children('http://www.niso.org/schemas/ali/1.0/')->license_ref;
                $licUrl = trim((string)$aliRef);
            }
        }
        if ($licUrl) $article['permissions']['license_url'] = $licUrl;
        $shortFromUrl = license_url_to_short_name($licUrl);
        if ($licText) {
            $article['permissions']['license'] = (strlen($licText) > 80 && $shortFromUrl !== '') ? $shortFromUrl : $licText;
        } elseif ($shortFromUrl !== '') {
            $article['permissions']['license'] = $shortFromUrl;
        }
        foreach ($perm->{'copyright-holder'} ?? [] as $ch) {
            $lang = (string)($ch['xml:lang'] ?? '');
            if ($lang === '') {
                $attrs = $ch->attributes('http://www.w3.org/XML/1998/namespace');
                $lang = ($attrs && isset($attrs['lang'])) ? (string)$attrs['lang'] : 'ru';
            }
            $txt = trim((string)$ch);
            if ($lang === 'en' || $lang === 'eng') $article['permissions']['copyright_holder_en'] = $txt;
            else $article['permissions']['copyright_holder_ru'] = $txt;
        }
    }

    // Аннотации
    foreach ($am->abstract ?? [] as $abs) {
        $lang = (string)($abs['xml:lang'] ?? 'ru');
        $p = $abs->p ?? $abs;
        $article[$lang === 'en' ? 'abstract_en' : 'abstract_ru'] = trim((string)$p);
    }
    foreach ($am->{'trans-abstract'} ?? [] as $abs) {
        $article['abstract_en'] = trim((string)($abs->p ?? $abs));
    }

    // Ключевые слова (xml:lang может быть в namespace)
    foreach ($am->{'kwd-group'} ?? [] as $kg) {
        $lang = (string)($kg['xml:lang'] ?? '');
        if ($lang === '') {
            $attrs = $kg->attributes('http://www.w3.org/XML/1998/namespace');
            $lang = ($attrs && isset($attrs['lang'])) ? (string)$attrs['lang'] : 'ru';
        }
        $key = ($lang === 'en' || $lang === 'eng') ? 'keywords_en' : 'keywords_ru';
        foreach ($kg->kwd ?? [] as $k) {
            $article[$key][] = trim((string)$k);
        }
    }

    // Тело статьи (body)
    if ($body && count($body->children()) > 0) {
        $article['body_html'] = jats_body_to_html($body);
    }

    // Список литературы (back)
    if ($back) {
        $rl = $back->{'ref-list'} ?? $back->children('j', true)->{'ref-list'} ?? null;
        if ($rl) {
            foreach ($rl->ref ?? [] as $ref) {
                $mc = $ref->{'mixed-citation'} ?? null;
                if ($mc) {
                    $lang = (string)($mc['xml:lang'] ?? '');
                    if ($lang === '') {
                        $attrs = $mc->attributes('http://www.w3.org/XML/1998/namespace');
                        $lang = ($attrs && isset($attrs['lang'])) ? (string)$attrs['lang'] : 'ru';
                    }
                    $txt = trim((string)$mc);
                    if ($lang === 'en' || $lang === 'eng') $article['refs_en'][] = $txt;
                    else $article['refs_ru'][] = $txt;
                }
            }
        }
    }

    if (!isset($article['epub_cover'])) {
        $article['epub_cover'] = null;
    }
    return $article;
}

/** Конвертирует JATS body в HTML */
function jats_body_to_html(SimpleXMLElement $body): string {
    $html = '';
    foreach ($body->children() as $child) {
        $name = $child->getName();
        $inner = '';
        foreach ($child->children() as $c) {
            $inner .= jats_element_to_html($c);
        }
        $text = trim((string)$child);
        if ($name === 'sec') {
            $title = $child->title ?? null;
            $t = $title ? '<h2>' . h((string)$title) . '</h2>' : '';
            $html .= '<section>' . $t . $inner . '</section>';
        } elseif ($name === 'p') {
            $html .= '<p>' . ($inner ?: h($text)) . '</p>';
        } elseif ($name === 'title') {
            $html .= '<h3>' . h($text) . '</h3>';
        } else {
            $html .= $inner ?: '<p>' . h($text) . '</p>';
        }
    }
    return $html ?: '';
}

function jats_element_to_html(SimpleXMLElement $el): string {
    $name = $el->getName();
    $text = (string)$el;
    if ($name === 'p') return '<p>' . h($text) . '</p>';
    if ($name === 'bold' || $name === 'b') return '<strong>' . h($text) . '</strong>';
    if ($name === 'italic' || $name === 'i') return '<em>' . h($text) . '</em>';
    if ($name === 'sub') return '<sub>' . h($text) . '</sub>';
    if ($name === 'sup') return '<sup>' . h($text) . '</sup>';
    return h($text);
}

/** Нормализует порядок частей ФИО из метаданных выпуска: family_given | given_family */
function issue_author_name_order(bool $isEn, array $issue): string {
    $key = $isEn ? 'author_name_order_en' : 'author_name_order_ru';
    $v = $issue[$key] ?? ($isEn ? 'given_family' : 'family_given');
    return in_array($v, ['family_given', 'given_family'], true) ? $v : ($isEn ? 'given_family' : 'family_given');
}

/** Собирает отображаемое ФИО автора по правилам выпуска (поля surname_* / given_* — как в JATS). */
function format_author_display_name(array $a, bool $isEn, ?array $issue = null): string {
    $issue = $issue ?? $_SESSION['issue'] ?? default_issue_metadata();
    $order = issue_author_name_order($isEn, $issue);
    if ($isEn) {
        $s = trim($a['surname_en'] ?? '');
        $g = trim($a['given_en'] ?? '');
    } else {
        $s = trim($a['surname_ru'] ?? '');
        $g = trim($a['given_ru'] ?? '');
    }
    if ($order === 'given_family') {
        return trim($g . ' ' . $s);
    }
    return trim($s . ' ' . $g);
}

/** Форматирует одного автора: имя — строка (со звёздочкой для корреспондирующего), под ним аффилиация, затем Email и ORCID. В EPUB ($epubPlainOrcid) email и ORCID — только текст, без ссылок и иконки ORCID. $issue — порядок ФИО (из метаданных выпуска). */
function format_author_block(array $a, bool $isEn, bool $epubPlainOrcid = false, ?array $issue = null): string {
    $issue = $issue ?? $_SESSION['issue'] ?? default_issue_metadata();
    $name = format_author_display_name($a, $isEn, $issue);
    $corresp = !empty($a['corresp']);
    if ($name && $corresp) $name .= ' *';
    $aff = $isEn ? trim($a['aff_en'] ?? '') : trim($a['aff_ru'] ?? '');
    $cc = $isEn ? trim($a['city_country_en'] ?? '') : trim($a['city_country_ru'] ?? '');
    if ($cc) $aff = $aff ? $aff . ' ' . $cc : $cc;
    $email = trim($a['email'] ?? '');
    $orcid = trim($a['orcid'] ?? '');
    $line2 = $aff ? h($aff) . '.' : '';
    $line3 = '';
    if ($email) {
        $line3 .= 'Email: ' . ($epubPlainOrcid ? h($email) : (strpos($email, '@') !== false ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : h($email)));
    }
    if ($orcid) {
        if ($epubPlainOrcid) {
            $line3 .= ($line3 ? ' ' : '') . h($orcid);
        } else {
            $orcidLogo = '<img src="https://orcid.org/sites/default/files/images/orcid_16x16.png" alt="ORCID" width="16" height="16" style="vertical-align:middle">';
            $line3 .= ($line3 ? ' ' : '') . '<a href="https://orcid.org/' . h($orcid) . '" target="_blank" rel="noopener">' . $orcidLogo . ' ' . h($orcid) . '</a>';
        }
    }
    $html = '';
    if ($name) $html .= '<strong>' . h($name) . '</strong>';
    if ($line2) $html .= ($html ? '<br>' : '') . $line2;
    if ($line3) $html .= ($html ? '<br>' : '') . $line3;
    return $html ? '<div class="author-entry">' . $html . '</div>' : '';
}

/** Рендерит блок метаданных для гранки (RU или EN) */
function render_meta_block(array $art, string $lang): string {
    $isRu = ($lang === 'ru');
    $parts = [];
    $cat = $isRu ? ($art['article_categories_ru'] ?? []) : ($art['article_categories_en'] ?? []);
    if (!empty($cat)) $parts[] = '<p class="meta-rubric"><strong>' . ($isRu ? 'РУБРИКА:' : 'SECTION:') . '</strong> ' . h(implode('; ', $cat)) . '</p>';
    $doi = trim($art['doi'] ?? '');
    if ($doi) $parts[] = '<p class="meta-doi"><strong>DOI:</strong> <a href="https://doi.org/' . h($doi) . '" target="_blank">' . h($doi) . '</a></p>';
    $edn = trim($art['edn'] ?? '');
    if ($edn) $parts[] = '<p class="meta-edn"><strong>EDN:</strong> <a href="https://elibrary.ru/' . rawurlencode($edn) . '" target="_blank" rel="noopener">' . h($edn) . '</a></p>';
    $vol = trim($art['volume'] ?? ''); $iss = trim($art['issue'] ?? ''); $fp = trim($art['fpage'] ?? ''); $lp = trim($art['lpage'] ?? '');
    if ($vol || $iss || $fp) {
        $v = []; if ($vol) $v[] = ($isRu ? 'Т.' : 'Vol.') . ' ' . $vol; if ($iss) $v[] = ($isRu ? '№' : 'No.') . ' ' . $iss; if ($fp && $lp) $v[] = ($isRu ? 'с.' : 'pp.') . ' ' . $fp . '–' . $lp; elseif ($fp) $v[] = ($isRu ? 'с.' : 'p.') . ' ' . $fp;
        $parts[] = '<p class="meta-vol">' . implode(', ', $v) . '</p>';
    }
    $h = $art['history'] ?? []; $pd = trim($art['pub_date'] ?? '');
    $dates = [];
    if (!empty($h['received'])) $dates[] = ($isRu ? 'Поступила:' : 'Received:') . ' ' . format_date_display($h['received']);
    if (!empty($h['revised'])) $dates[] = ($isRu ? 'Исправлена:' : 'Revised:') . ' ' . format_date_display($h['revised']);
    if (!empty($h['accepted'])) $dates[] = ($isRu ? 'Принята:' : 'Accepted:') . ' ' . format_date_display($h['accepted']);
    if ($pd) $dates[] = ($isRu ? 'Опубликована:' : 'Published:') . ' ' . format_date_display($pd);
    if (!empty($dates)) $parts[] = '<p class="meta-dates">' . implode(' | ', $dates) . '</p>';
    $fund = $isRu ? ($art['funding_ru'] ?? []) : ($art['funding_en'] ?? []);
    if (!empty($fund)) $parts[] = '<p class="meta-funding"><strong>' . ($isRu ? 'Финансирование:' : 'Funding:') . '</strong> ' . h(implode(' ', $fund)) . '</p>';
    $perm = $art['permissions'] ?? [];
    $cy = trim($perm['copyright_year'] ?? ''); $ch = $isRu ? trim($perm['copyright_holder_ru'] ?? '') : trim($perm['copyright_holder_en'] ?? '');
    $lic = trim($perm['license'] ?? ''); $licUrl = trim($perm['license_url'] ?? '');
    if ($cy || $ch || $lic) {
        $p = []; if ($cy && $ch) $p[] = '© ' . $cy . ' ' . h($ch); elseif ($ch) $p[] = '© ' . h($ch);
        if ($lic) $p[] = $licUrl ? '<a href="' . h($licUrl) . '" target="_blank">' . h($lic) . '</a>' : h($lic);
        $parts[] = '<p class="meta-permissions">' . implode('. ', $p) . '</p>';
    }
    return implode('', $parts) ?: '<p class="muted">' . ($isRu ? 'Нет данных' : 'No data') . '</p>';
}

/** Форматирует ISO-дату для отображения (DD.MM.YYYY) */
function format_date_display(string $iso): string {
    if ($iso === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) return '';
    return $m[3] . '.' . $m[2] . '.' . $m[1];
}

/** Возвращает название блока для вывода: пользовательское (из issue или статьи) или значение по умолчанию. */
function get_block_label(array $art, string $bid, string $default): string {
    $issueLabels = $_SESSION['issue']['blocks_labels'] ?? [];
    $artLabels = $art['blocks_labels'] ?? [];
    $effective = $issueLabels + $artLabels;  // issue перекрывает article
    $custom = trim($effective[$bid] ?? '');
    return $custom !== '' ? $custom : $default;
}

/** Строит HTML-контент статьи для экспорта (HTML, EPUB). $issue — для опции galley_blocks_separator. */
function build_article_content_html(array $art, ?array $issue = null, array $options = []): string {
    $issue = $issue ?? $_SESSION['issue'] ?? default_issue_metadata();
    $forEpub = !empty($options['for_epub']);
    $blocksSeparator = ($issue['galley_blocks_separator'] ?? '0') === '1';
    $blocks = $art['blocks_order'] ?? get_canonical_blocks_order();
    if (in_array('refs', $blocks, true)) {
        $blocks = array_map(fn($b) => $b === 'refs' ? ['refs_ru', 'refs_en'] : [$b], $blocks);
        $blocks = array_merge(...$blocks);
    }
    if (!in_array('authors_info_en', $blocks, true) || !in_array('authors_info_ru', $blocks, true)) {
        $toAdd = [];
        if (!in_array('authors_info_en', $blocks, true)) $toAdd[] = 'authors_info_en';
        if (!in_array('authors_info_ru', $blocks, true)) $toAdd[] = 'authors_info_ru';
        if (!empty($toAdd)) {
            $idx = array_search('refs_en', $blocks);
            if ($idx !== false) array_splice($blocks, $idx + 1, 0, $toAdd);
            else $blocks = array_merge($blocks, $toAdd);
        }
    }
    $levels = $art['blocks_heading_levels'] ?? [];
    $h = function($bid) use ($levels) { $l = (int)($levels[$bid] ?? 2); return $l === 3 ? 'h3' : 'h2'; };
    $contentHtml = '';
    foreach ($blocks as $bid) {
        if ($forEpub && in_array($bid, ['meta_ru', 'meta_en'], true)) {
            continue;
        }
        if ($bid === 'title_ru' && trim($art['title_ru'] ?? '') !== '') {
            $contentHtml .= '<h1>' . h($art['title_ru']) . '</h1>';
        } elseif ($bid === 'title_en' && trim($art['title_en'] ?? '') !== '') {
            $contentHtml .= '<h1>' . h($art['title_en']) . '</h1>';
        } elseif ($bid === 'meta_ru') { $m = render_meta_block($art, 'ru'); if (strpos($m, 'class="muted"') === false) $contentHtml .= "<div class=\"meta-block\">$m</div>"; }
        elseif ($bid === 'meta_en') { $m = render_meta_block($art, 'en'); if (strpos($m, 'class="muted"') === false) $contentHtml .= "<div class=\"meta-block\">$m</div>"; }
        elseif ($bid === 'authors_ru' && !empty($art['authors'])) {
            $authorsHtml = '';
            foreach ($art['authors'] as $a) {
                $authorsHtml .= format_author_block($a, false, $forEpub, $issue);
            }
            if (html_has_content($authorsHtml)) {
                $contentHtml .= '<div class="authors authors-compact">' . $authorsHtml;
                if (array_filter($art['authors'], fn($a) => !empty($a['corresp']))) {
                    $contentHtml .= '<p class="authors-corresp-note">* — корреспондирующий автор</p>';
                }
                $contentHtml .= '</div>';
            }
        }
        elseif ($bid === 'authors_en' && !empty($art['authors'])) {
            $authorsHtml = '';
            foreach ($art['authors'] as $a) {
                $authorsHtml .= format_author_block($a, true, $forEpub, $issue);
            }
            if (html_has_content($authorsHtml)) {
                $contentHtml .= '<div class="authors authors-compact">' . $authorsHtml;
                if (array_filter($art['authors'], fn($a) => !empty($a['corresp']))) {
                    $contentHtml .= '<p class="authors-corresp-note">* — corresponding author</p>';
                }
                $contentHtml .= '</div>';
            }
        }
        elseif ($bid === 'authors') { }
        elseif ($bid === 'abstract_ru' && html_has_content($art['abstract_ru'] ?? '')) { $tag = $h('abstract_ru'); $lbl = get_block_label($art, 'abstract_ru', 'Аннотация'); $contentHtml .= "<div class=\"abstract\"><$tag>" . h($lbl) . "</$tag>" . $art['abstract_ru'] . '</div>'; }
        elseif ($bid === 'abstract_en' && html_has_content($art['abstract_en'] ?? '')) { $tag = $h('abstract_en'); $lbl = get_block_label($art, 'abstract_en', 'Abstract'); $contentHtml .= "<div class=\"abstract\"><$tag>" . h($lbl) . "</$tag>" . $art['abstract_en'] . '</div>'; }
        elseif ($bid === 'keywords_ru') {
            $kw = array_filter(array_map('trim', is_array($art['keywords_ru'] ?? null) ? $art['keywords_ru'] : []));
            if (!empty($kw)) { $tag = $h('keywords_ru'); $lbl = get_block_label($art, 'keywords_ru', 'Ключевые слова'); $contentHtml .= "<$tag>" . h($lbl) . "</$tag><p>" . h(implode(', ', $kw)) . '</p>' . ($blocksSeparator ? '<hr class="block-separator">' : ''); }
        }
        elseif ($bid === 'keywords_en') {
            $kw = array_filter(array_map('trim', is_array($art['keywords_en'] ?? null) ? $art['keywords_en'] : []));
            if (!empty($kw)) { $tag = $h('keywords_en'); $lbl = get_block_label($art, 'keywords_en', 'Keywords'); $contentHtml .= "<$tag>" . h($lbl) . "</$tag><p>" . h(implode(', ', $kw)) . '</p>' . ($blocksSeparator ? '<hr class="block-separator">' : ''); }
        }
        elseif ($bid === 'body' && html_has_content($art['body_html'] ?? '')) $contentHtml .= '<div class="body">' . $art['body_html'] . '</div>';
        elseif ($bid === 'extra_info' && html_has_content($art['extra_info_html'] ?? '')) {
            $tit = trim($art['extra_info_title'] ?? '');
            $tag = $h('extra_info');
            if ($tit !== '') $contentHtml .= "<$tag>" . h($tit) . "</$tag>";
            $contentHtml .= '<div class="extra-info">' . $art['extra_info_html'] . '</div>';
        }
        elseif ($bid === 'refs_ru') {
            $refs = $art['refs_ru'] ?? null;
            $refsHtml = is_array($refs) ? implode('', array_map(fn($r) => '<p>' . h($r) . '</p>', array_filter($refs, fn($r) => trim((string)$r) !== ''))) : (string)($refs ?? '');
            if (html_has_content($refsHtml)) {
                $tag = $h('refs_ru');
                $lbl = get_block_label($art, 'refs_ru', 'Список литературы');
                $contentHtml .= "<$tag>" . h($lbl) . "</$tag><div class=\"refs refs-plain\">$refsHtml</div>";
            }
        }
        elseif ($bid === 'refs_en') {
            $refs = $art['refs_en'] ?? null;
            $refsHtml = is_array($refs) ? implode('', array_map(fn($r) => '<p>' . h($r) . '</p>', array_filter($refs, fn($r) => trim((string)$r) !== ''))) : (string)($refs ?? '');
            if (html_has_content($refsHtml)) {
                $tag = $h('refs_en');
                $lbl = get_block_label($art, 'refs_en', 'References');
                $contentHtml .= "<$tag>" . h($lbl) . "</$tag><div class=\"refs refs-plain\">$refsHtml</div>";
            }
        }
        elseif ($bid === 'authors_info_ru' && html_has_content($art['authors_info_ru_html'] ?? '')) {
            $tit = trim($art['authors_info_ru_title'] ?? '');
            $tag = $h('authors_info_ru');
            if ($tit !== '') $contentHtml .= "<$tag>" . h($tit) . "</$tag>";
            $contentHtml .= '<div class="authors-info">' . $art['authors_info_ru_html'] . '</div>';
        }
        elseif ($bid === 'authors_info_en' && html_has_content($art['authors_info_en_html'] ?? '')) {
            $tit = trim($art['authors_info_en_title'] ?? '');
            $tag = $h('authors_info_en');
            if ($tit !== '') $contentHtml .= "<$tag>" . h($tit) . "</$tag>";
            $contentHtml .= '<div class="authors-info">' . $art['authors_info_en_html'] . '</div>';
        }
    }
    return $contentHtml;
}

/** Выходные данные выпуска для колофона (одна строка): название, том, номер, год. */
function build_issue_imprint_line(array $issue, string $lang): string {
    $lang = $lang === 'en' ? 'en' : 'ru';
    $title = trim($lang === 'en' ? ($issue['journal_title_en'] ?? '') : ($issue['journal_title_ru'] ?? ''));
    $vol = trim($issue['volume'] ?? '');
    $iss = trim($issue['issue'] ?? '');
    $year = trim($issue['year'] ?? '');
    $parts = [];
    if ($title !== '') {
        $parts[] = $title;
    }
    $vi = [];
    if ($vol !== '') {
        $vi[] = ($lang === 'en' ? 'Vol. ' : 'Т. ') . $vol;
    }
    if ($iss !== '') {
        $vi[] = ($lang === 'en' ? 'No. ' : '№ ') . $iss;
    }
    if ($vi !== []) {
        $parts[] = implode(', ', $vi);
    }
    if ($year !== '') {
        $parts[] = $year;
    }
    return implode('. ', array_filter($parts, fn($p) => $p !== ''));
}

/** HTML колофона: по центру сначала строка по-русски, затем по-английски (если есть данные). */
function build_issue_imprint_html(array $issue): string {
    $lineRu = build_issue_imprint_line($issue, 'ru');
    $lineEn = build_issue_imprint_line($issue, 'en');
    if ($lineRu === '' && $lineEn === '') {
        return '';
    }
    $out = '<footer class="issue-imprint" role="contentinfo">';
    if ($lineRu !== '') {
        $out .= '<p class="issue-imprint-line issue-imprint-ru">' . h($lineRu) . '</p>';
    }
    if ($lineEn !== '' && $lineEn !== $lineRu) {
        $out .= '<p class="issue-imprint-line issue-imprint-en">' . h($lineEn) . '</p>';
    } elseif ($lineEn !== '' && $lineRu === '') {
        $out .= '<p class="issue-imprint-line issue-imprint-en">' . h($lineEn) . '</p>';
    }
    $out .= '</footer>';
    return $out;
}

/** Полупрозрачная строка о создании гранки в MetaGalley (по центру, под колофоном выпуска). */
function build_galley_credit_html(): string {
    return '<p class="galley-credit">This HTML was created using MetaGalley ' . h(METAGALLEY_VERSION) . '</p>';
}

/** Колофон выпуска + строка MetaGalley для конца статьи в HTML. */
function build_galley_article_footer_html(array $issue): string {
    return '<div class="galley-article-footer">' . build_issue_imprint_html($issue) . build_galley_credit_html() . '</div>';
}

/** Строит полный HTML-документ гранки */
function build_galley_html_full(array $art): string {
    $issue = $_SESSION['issue'] ?? default_issue_metadata();
    $contentHtml = build_article_content_html($art, $issue);
    $footerHtml = build_galley_article_footer_html($issue);
    $title = $art['title_ru'] ?: $art['title_en'] ?: 'Статья';
    $galleyCss = build_galley_css_full($issue);
    return '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>' . h($title) . '</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="' . h(get_galley_font_url($issue['galley_font_family'] ?? null) ?? 'https://fonts.googleapis.com/css2?family=PT+Serif:ital,wght@0,400;0,700;1,400&display=swap') . '" rel="stylesheet">
    <script src="https://www.wiris.net/demo/plugins/app/WIRISplugins.js?viewer=image" referrerpolicy="origin"></script>
    <style>' . $galleyCss . '</style>
</head>
<body>
<div class="metagalley-granka' . (($logoUrl = trim($issue['galley_logo_url'] ?? '')) !== '' ? '' : ' no-logo') . '" id="metagalley-root">
' . ($logoUrl !== '' ? '<header class="journal-header"><img src="' . h($logoUrl) . '" alt="' . h(trim($issue['journal_title_en'] ?? '') ?: trim($issue['journal_title_ru'] ?? '')) . '"></header>' : '') . '
<main id="article-content">
' . $contentHtml . "\n" . $footerHtml . '
</main>

<div id="float-buttons">
    <div id="toc-panel">
        <ul id="toc-list"></ul>
    </div>
    <div class="btn-row">
        <button id="toc-toggle" class="float-btn toc-btn" type="button" aria-expanded="false" title="Содержание">≡</button>
        <button id="backToTop" class="float-btn back-btn" title="Наверх" aria-label="Наверх">↑</button>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var tocList = document.getElementById("toc-list");
    var tocPanel = document.getElementById("toc-panel");
    var tocBtn = document.getElementById("toc-toggle");
    var backBtn = document.getElementById("backToTop");
    var headers = document.querySelectorAll("#article-content h1, #article-content h2, #article-content h3");
    headers.forEach(function(header, index) {
        if (!header.id) header.id = "header-" + index;
        var li = document.createElement("li");
        li.style.marginLeft = (parseInt(header.tagName.substring(1)) - 1) * 10 + "px";
        var a = document.createElement("a");
        a.href = "#" + header.id;
        a.textContent = header.textContent;
        a.addEventListener("click", function(e) {
            tocPanel.classList.remove("toc-open");
            tocBtn.setAttribute("aria-expanded", "false");
        });
        li.appendChild(a);
        tocList.appendChild(li);
    });
    tocBtn.addEventListener("click", function() {
        var open = tocPanel.classList.toggle("toc-open");
        this.setAttribute("aria-expanded", open);
    });
    backBtn.style.display = "none";
    window.addEventListener("scroll", function() { backBtn.style.display = window.scrollY > 200 ? "flex" : "none"; });
    backBtn.addEventListener("click", function() { window.scrollTo({ top: 0, behavior: "smooth" }); });
});
</script>
</div>
</body>
</html>';
}

/** Исправляет неэкранированные & в HTML для встраивания в XML (XHTML) */
function fix_html_for_xml(string $html): string {
    return preg_replace('/&(?![a-zA-Z0-9#]+;)/u', '&amp;', $html);
}

/** Убирает выравнивание по центру/вправо из разметки тела статьи для EPUB (inline style и align). */
function epub_strip_text_alignment_markup(string $html): string {
    if (trim($html) === '') {
        return $html;
    }
    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $wrapped = '<?xml encoding="UTF-8"?><div xmlns="http://www.w3.org/1999/xhtml">' . $html . '</div>';
    if (!@$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
        libxml_clear_errors();
        return $html;
    }
    libxml_clear_errors();
    $root = $dom->documentElement;
    if ($root === null) {
        return $html;
    }
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//*[@style]') as $el) {
        if (!$el instanceof DOMElement) {
            continue;
        }
        $s = $el->getAttribute('style');
        $s = preg_replace('/\btext-align\s*:\s*[^;]+;?/iu', '', $s);
        $s = preg_replace('/\bmargin\s*:\s*(0(?:px)?|[0-9.]+(?:px|em|rem|%)?)\s+auto\b(?:\s*!important)?\s*;?/iu', 'margin: \\1 0;', $s);
        $s = preg_replace('/\bmargin-(?:left|right)\s*:\s*auto\s*;?/iu', '', $s);
        $s = trim(preg_replace('/\s*;\s*;/', ';', $s), '; ');
        if ($s === '') {
            $el->removeAttribute('style');
        } else {
            $el->setAttribute('style', $s);
        }
    }
    foreach ($xpath->query('//*[@align]') as $el) {
        if ($el instanceof DOMElement) {
            $el->removeAttribute('align');
        }
    }
    $inner = '';
    foreach ($root->childNodes as $child) {
        $inner .= $dom->saveHTML($child);
    }
    return $inner;
}

/** Первое непустое название по порядку блоков (для &lt;title&gt; главы EPUB и пр.) */
function epub_primary_title_plain(array $art): string {
    $blocks = $art['blocks_order'] ?? get_canonical_blocks_order();
    if (in_array('refs', $blocks, true)) {
        $blocks = array_map(fn($b) => $b === 'refs' ? ['refs_ru', 'refs_en'] : [$b], $blocks);
        $blocks = array_merge(...$blocks);
    }
    foreach ($blocks as $bid) {
        if ($bid === 'title_ru') {
            $t = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($art['title_ru'] ?? ''))));
            if ($t !== '') {
                return $t;
            }
        } elseif ($bid === 'title_en') {
            $t = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($art['title_en'] ?? ''))));
            if ($t !== '') {
                return $t;
            }
        }
    }
    $tr = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($art['title_ru'] ?? ''))));
    $te = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($art['title_en'] ?? ''))));
    return $tr !== '' ? $tr : ($te !== '' ? $te : 'Статья');
}

/** Добавляет id к h1–h6 без id; возвращает HTML и список заголовков для оглавления */
function epub_inject_heading_ids(string $html): array {
    $html = trim($html);
    if ($html === '') {
        return ['html' => '', 'headings' => []];
    }
    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $wrapped = '<?xml encoding="UTF-8"?><div xmlns="http://www.w3.org/1999/xhtml">' . $html . '</div>';
    if (!@$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
        libxml_clear_errors();
        return epub_inject_heading_ids_regex($html);
    }
    libxml_clear_errors();
    $root = $dom->documentElement;
    if ($root === null) {
        return epub_inject_heading_ids_regex($html);
    }
    $xp = new DOMXPath($dom);
    $heads = $xp->query('//h1|//h2|//h3|//h4|//h5|//h6');
    $headings = [];
    $n = 0;
    if ($heads !== false) {
        foreach ($heads as $h) {
            if (!$h instanceof DOMElement) {
                continue;
            }
            if ($h->getAttribute('id') === '') {
                $h->setAttribute('id', 'mg-h-' . $n++);
            }
            $id = $h->getAttribute('id');
            $level = (int)substr($h->tagName, 1);
            $text = trim(preg_replace('/\s+/u', ' ', $h->textContent ?? ''));
            if ($text !== '') {
                $headings[] = ['level' => $level, 'text' => $text, 'id' => $id];
            }
        }
    }
    $inner = '';
    foreach ($root->childNodes as $child) {
        $inner .= $dom->saveHTML($child);
    }
    return ['html' => $inner, 'headings' => $headings];
}

/** Резерв: id через regexp, текст заголовков — упрощённо */
function epub_inject_heading_ids_regex(string $html): array {
    $n = 0;
    $headings = [];
    $out = preg_replace_callback(
        '/<(h[1-6])(\s[^>]*)?>/iu',
        function (array $m) use (&$n, &$headings): string {
            $tag = strtolower($m[1]);
            $attrs = $m[2] ?? '';
            if (preg_match('/\sid\s*=\s*["\'][^"\']*["\']/iu', $attrs)) {
                return $m[0];
            }
            $id = 'mg-h-' . $n++;
            $level = (int)substr($tag, 1);
            $headings[] = ['level' => $level, 'text' => '', 'id' => $id];
            return '<' . $tag . rtrim(' ' . $attrs) . ' id="' . $id . '">';
        },
        $html
    );
    if ($out === null) {
        return ['html' => $html, 'headings' => []];
    }
    foreach ($headings as $i => $_) {
        if (preg_match('/<h[1-6][^>]*\sid\s*=\s*["\']' . preg_quote($headings[$i]['id'], '/') . '["\'][^>]*>(.*?)<\/h[1-6]>/ius', $out, $mm)) {
            $headings[$i]['text'] = trim(preg_replace('/\s+/u', ' ', strip_tags($mm[1])));
        }
    }
    $headings = array_values(array_filter($headings, fn($h) => ($h['text'] ?? '') !== ''));
    return ['html' => $out, 'headings' => $headings];
}

/** Текст аннотации для dc:description */
function epub_plain_description(array $art): string {
    $parts = [];
    $a1 = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($art['abstract_ru'] ?? ''))));
    $a2 = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($art['abstract_en'] ?? ''))));
    if ($a1 !== '') {
        $parts[] = $a1;
    }
    if ($a2 !== '') {
        $parts[] = $a2;
    }
    return implode("\n\n", $parts);
}

/** Строка dc:rights из разрешений */
function epub_build_rights_string(array $art): string {
    $perm = $art['permissions'] ?? [];
    $cy = trim($perm['copyright_year'] ?? '');
    $ch = trim($perm['copyright_holder_ru'] ?? '') ?: trim($perm['copyright_holder_en'] ?? '');
    $lic = trim($perm['license'] ?? '');
    $p = [];
    if ($cy && $ch) {
        $p[] = '© ' . $cy . ' ' . $ch;
    } elseif ($ch) {
        $p[] = '© ' . $ch;
    } elseif ($cy) {
        $p[] = '© ' . $cy;
    }
    if ($lic !== '') {
        $p[] = $lic;
    }
    return implode('. ', array_filter($p));
}

/** Список имён авторов для метаданных EPUB (порядок — по правилам выпуска) */
function epub_metadata_creator_strings(array $art, array $issue): array {
    $out = [];
    foreach ($art['authors'] ?? [] as $a) {
        if (!is_array($a)) {
            continue;
        }
        $lineEn = trim(format_author_display_name($a, true, $issue));
        $lineRu = trim(format_author_display_name($a, false, $issue));
        $line = $lineEn !== '' ? $lineEn : $lineRu;
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return $out;
}

/** Дата публикации для dc:date (YYYY-MM-DD или год) */
function epub_dc_date_string(array $art, array $issue): string {
    $pd = trim($art['pub_date'] ?? '');
    if ($pd !== '' && preg_match('/^(\d{4}-\d{2}-\d{2})/', $pd, $m)) {
        return $m[1];
    }
    if ($pd !== '' && preg_match('/^(\d{4})/', $pd, $m)) {
        return $m[1] . '-01-01';
    }
    $y = trim($issue['year'] ?? '');
    if ($y !== '' && preg_match('/^\d{4}$/', $y)) {
        return $y . '-01-01';
    }
    return '';
}

/** Язык публикации для dc:language и атрибутов XHTML (совпадает с логикой dc:language в метаданных). */
function epub_article_xml_lang(array $art): string {
    $titleRu = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($art['title_ru'] ?? ''))));
    $titleEn = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($art['title_en'] ?? ''))));
    $primary = epub_primary_title_plain($art);
    if ($titleRu === '' && $titleEn !== '') {
        return 'en';
    }
    if ($titleRu !== '' && $titleEn !== '' && $primary === $titleEn) {
        return 'en';
    }
    return 'ru';
}

/** Расширенные dc:* для content.opf */
function epub_build_metadata_xml(array $art, array $issue): string {
    $titleRu = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($art['title_ru'] ?? ''))));
    $titleEn = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($art['title_en'] ?? ''))));
    $primary = epub_primary_title_plain($art);
    $lines = [];
    $lines[] = '    <dc:title id="title-main">' . h($primary) . '</dc:title>';
    if ($titleRu !== '' && $titleEn !== '' && $titleRu !== $titleEn) {
        $alt = ($primary === $titleRu) ? $titleEn : $titleRu;
        if ($alt !== '' && $alt !== $primary) {
            $altLang = ($alt === $titleEn) ? 'en' : 'ru';
            $lines[] = '    <dc:title id="title-alt" xml:lang="' . h($altLang) . '">' . h($alt) . '</dc:title>';
        }
    }
    foreach (epub_metadata_creator_strings($art, $issue) as $creator) {
        $lines[] = '    <dc:creator>' . h($creator) . '</dc:creator>';
    }
    $pub = trim($issue['journal_title_ru'] ?? '') ?: trim($issue['journal_title_en'] ?? '');
    if ($pub !== '') {
        $lines[] = '    <dc:publisher>' . h($pub) . '</dc:publisher>';
    }
    $d = epub_dc_date_string($art, $issue);
    if ($d !== '') {
        $lines[] = '    <dc:date>' . h($d) . '</dc:date>';
    }
    $rights = epub_build_rights_string($art);
    if ($rights !== '') {
        $lines[] = '    <dc:rights>' . h($rights) . '</dc:rights>';
    }
    $desc = epub_plain_description($art);
    if ($desc !== '') {
        $lines[] = '    <dc:description>' . h($desc) . '</dc:description>';
    }
    $kwAll = array_unique(array_merge($art['keywords_ru'] ?? [], $art['keywords_en'] ?? []));
    foreach ($kwAll as $kw) {
        $kw = trim((string)$kw);
        if ($kw !== '') {
            $lines[] = '    <dc:subject>' . h($kw) . '</dc:subject>';
        }
    }
    $doi = trim($art['doi'] ?? '');
    $edn = trim($art['edn'] ?? '');
    $idVal = $doi !== '' ? 'doi:' . $doi : ($edn !== '' ? 'edn:' . $edn : 'urn:uuid:' . sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0x0fff) | 0x4000, random_int(0, 0x3fff) | 0x8000, random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)));
    $lines[] = '    <dc:identifier id="book-id">' . h($idVal) . '</dc:identifier>';
    $lang = epub_article_xml_lang($art);
    $lines[] = '    <dc:language>' . h($lang) . '</dc:language>';
    return implode("\n", $lines);
}

/** EPUB 3: отдельный документ навигации (оглавление в меню читалки, не в теле главы). */
function epub_build_nav_xhtml(array $tocEntries, string $navTitle, string $xmlLang): string {
    $lis = [];
    foreach ($tocEntries as $e) {
        $text = $e['text'] ?? '';
        $id = $e['id'] ?? '';
        if ($text === '' || $id === '') {
            continue;
        }
        $lis[] = '      <li class="epub-nav-l' . (int)($e['level'] ?? 1) . '"><a href="chapter.xhtml#' . h($id) . '">' . h($text) . '</a></li>';
    }
    if ($lis === []) {
        $lis[] = '      <li><a href="chapter.xhtml">' . h($navTitle) . '</a></li>';
    }
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<!DOCTYPE html>' . "\n"
        . '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" lang="' . h($xmlLang) . '" xml:lang="' . h($xmlLang) . '">' . "\n"
        . '<head><meta charset="utf-8"/><title>' . h($navTitle) . '</title></head>' . "\n"
        . '<body>' . "\n"
        . '  <nav epub:type="toc" id="toc">' . "\n"
        . '    <h1>' . h($navTitle) . '</h1>' . "\n"
        . '    <ol>' . "\n" . implode("\n", $lis) . "\n    </ol>\n"
        . '  </nav>' . "\n"
        . '</body></html>';
}

/** HTTP(S): загрузка бинарных данных (для иллюстраций в EPUB) */
function epub_http_get_binary(string $url): ?string {
    $url = trim($url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return null;
    }
    $max = 5 * 1024 * 1024;
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 30,
            'follow_location' => 1,
            'max_redirects' => 10,
            'header' => "User-Agent: MetaGalley/" . METAGALLEY_VERSION . " EPUB\r\nAccept: image/*,*/*;q=0.8\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data !== false && strlen($data) <= $max) {
        return $data;
    }
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'MetaGalley/' . METAGALLEY_VERSION . ' EPUB',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $data = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($data === false || $code < 200 || $code >= 400 || strlen($data) > $max) {
        return null;
    }
    return $data;
}

/** MIME изображения по содержимому */
function epub_guess_image_mime(string $binary, string $urlHint): string {
    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        if ($f !== false) {
            $m = finfo_buffer($f, $binary);
            finfo_close($f);
            if (is_string($m) && strpos($m, 'image/') === 0) {
                return $m;
            }
        }
    }
    if (strncmp($binary, "\xFF\xD8\xFF", 3) === 0) {
        return 'image/jpeg';
    }
    if (strncmp($binary, "\x89PNG\r\n\x1a\n", 8) === 0) {
        return 'image/png';
    }
    if (strncmp($binary, 'GIF87a', 6) === 0 || strncmp($binary, 'GIF89a', 6) === 0) {
        return 'image/gif';
    }
    if (strncmp($binary, 'RIFF', 4) === 0 && substr($binary, 8, 4) === 'WEBP') {
        return 'image/webp';
    }
    $trim = ltrim($binary);
    if (strpos($trim, '<svg') !== false || strpos($trim, '<?xml') === 0) {
        return 'image/svg+xml';
    }
    if (preg_match('/\.svgz?(\?|#|$)/i', $urlHint)) {
        return 'image/svg+xml';
    }
    return 'application/octet-stream';
}

/** Расширение файла по MIME */
function epub_image_ext_from_mime(string $mime): string {
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];
    return $map[$mime] ?? 'img';
}

/**
 * Скачивает картинки по http(s), сохраняет в $imagesDirAbs, подставляет относительные пути images/….
 * @return array{html: string, manifest_lines: string[], zip_pairs: array<int, array{zip: string, path: string}>}
 */
function epub_embed_remote_images(string $html, string $imagesDirAbs): array {
    $manifestLines = [];
    $zipPairs = [];
    $seenUrls = [];
    $seq = 0;
    if (trim($html) === '') {
        return ['html' => $html, 'manifest_lines' => [], 'zip_pairs' => []];
    }
    if (!is_dir($imagesDirAbs) && !@mkdir($imagesDirAbs, 0777, true)) {
        return ['html' => $html, 'manifest_lines' => [], 'zip_pairs' => []];
    }
    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $wrapped = '<?xml encoding="UTF-8"?><div xmlns="http://www.w3.org/1999/xhtml">' . $html . '</div>';
    if (!@$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
        libxml_clear_errors();
        return epub_embed_remote_images_regex($html, $imagesDirAbs);
    }
    libxml_clear_errors();
    $root = $dom->documentElement;
    if ($root === null) {
        return epub_embed_remote_images_regex($html, $imagesDirAbs);
    }
    $imgs = $root->getElementsByTagName('img');
    for ($i = 0; $i < $imgs->length; $i++) {
        $img = $imgs->item($i);
        if (!$img instanceof DOMElement) {
            continue;
        }
        $src = trim($img->getAttribute('src'));
        if ($src === '' || preg_match('#^data:#i', $src)) {
            continue;
        }
        if (!preg_match('#^https?://#i', $src)) {
            continue;
        }
        if (isset($seenUrls[$src])) {
            $img->setAttribute('src', $seenUrls[$src]);
            continue;
        }
        $bin = epub_http_get_binary($src);
        if ($bin === null) {
            continue;
        }
        $mime = epub_guess_image_mime($bin, $src);
        if (strpos($mime, 'image/') !== 0) {
            continue;
        }
        $seq++;
        $ext = epub_image_ext_from_mime($mime);
        $fname = 'img' . str_pad((string)$seq, 3, '0', STR_PAD_LEFT) . '.' . $ext;
        $rel = 'images/' . $fname;
        $abs = $imagesDirAbs . '/' . $fname;
        if (@file_put_contents($abs, $bin) === false) {
            continue;
        }
        $id = 'epub-img-' . $seq;
        $manifestLines[] = '    <item id="' . h($id) . '" href="' . h($rel) . '" media-type="' . h($mime) . '"/>';
        $zipPairs[] = ['zip' => 'OEBPS/' . $rel, 'path' => $abs];
        $img->setAttribute('src', $rel);
        $seenUrls[$src] = $rel;
    }
    $inner = '';
    foreach ($root->childNodes as $child) {
        $inner .= $dom->saveHTML($child);
    }
    return ['html' => $inner, 'manifest_lines' => $manifestLines, 'zip_pairs' => $zipPairs];
}

/** Упрощённая подстановка src, если DOM не разобрал фрагмент */
function epub_embed_remote_images_regex(string $html, string $imagesDirAbs): array {
    if (!is_dir($imagesDirAbs) && !@mkdir($imagesDirAbs, 0777, true)) {
        return ['html' => $html, 'manifest_lines' => [], 'zip_pairs' => []];
    }
    $manifestLines = [];
    $zipPairs = [];
    $seenUrls = [];
    $seq = 0;
    $out = preg_replace_callback(
        '/<img\b([^>]*?)\bsrc\s*=\s*(["\'])(https?:\/\/[^"\']+)\2([^>]*)>/iu',
        function (array $m) use ($imagesDirAbs, &$manifestLines, &$zipPairs, &$seenUrls, &$seq): string {
            $src = $m[3];
            if (isset($seenUrls[$src])) {
                return '<img' . $m[1] . 'src=' . $m[2] . $seenUrls[$src] . $m[2] . $m[4] . '>';
            }
            $bin = epub_http_get_binary($src);
            if ($bin === null) {
                return $m[0];
            }
            $mime = epub_guess_image_mime($bin, $src);
            if (strpos($mime, 'image/') !== 0) {
                return $m[0];
            }
            $seq++;
            $ext = epub_image_ext_from_mime($mime);
            $fname = 'img' . str_pad((string)$seq, 3, '0', STR_PAD_LEFT) . '.' . $ext;
            $rel = 'images/' . $fname;
            $abs = $imagesDirAbs . '/' . $fname;
            if (@file_put_contents($abs, $bin) === false) {
                return $m[0];
            }
            $manifestLines[] = '    <item id="epub-img-' . $seq . '" href="' . h($rel) . '" media-type="' . h($mime) . '"/>';
            $zipPairs[] = ['zip' => 'OEBPS/' . $rel, 'path' => $abs];
            $seenUrls[$src] = $rel;
            return '<img' . $m[1] . 'src=' . $m[2] . $rel . $m[2] . $m[4] . '>';
        },
        $html
    );
    return ['html' => $out ?? $html, 'manifest_lines' => $manifestLines, 'zip_pairs' => $zipPairs];
}

/** Имя файла обложки по MIME */
function epub_cover_href_from_mime(string $mime): string {
    $map = [
        'image/jpeg' => 'cover.jpg',
        'image/png' => 'cover.png',
        'image/gif' => 'cover.gif',
        'image/webp' => 'cover.webp',
    ];
    return $map[$mime] ?? 'cover.jpg';
}

/** Строит EPUB как бинарную строку */
function build_galley_epub_bytes(array $art): string {
    if (is_array($art['refs_ru'] ?? null)) {
        $art['refs_ru'] = implode('', array_map(fn($r) => '<p>' . h($r) . '</p>', $art['refs_ru']));
    }
    if (is_array($art['refs_en'] ?? null)) {
        $art['refs_en'] = implode('', array_map(fn($r) => '<p>' . h($r) . '</p>', $art['refs_en']));
    }
    $issue = $_SESSION['issue'] ?? default_issue_metadata();
    $contentHtml = build_article_content_html($art, $issue, ['for_epub' => true]);
    $contentHtml = fix_html_for_xml($contentHtml);
    $contentHtml = epub_strip_text_alignment_markup($contentHtml);
    $contentHtml = fix_html_for_xml($contentHtml);
    $injected = epub_inject_heading_ids($contentHtml);
    $contentHtml = $injected['html'];
    $tmpDir = sys_get_temp_dir() . '/epub_' . uniqid();
    if (!@mkdir($tmpDir . '/OEBPS/images', 0777, true)) {
        throw new RuntimeException('Не удалось создать временную директорию для EPUB');
    }
    $embedded = epub_embed_remote_images($contentHtml, $tmpDir . '/OEBPS/images');
    $contentHtml = fix_html_for_xml($embedded['html']);
    $imageManifestLines = $embedded['manifest_lines'];
    $imageZipPairs = $embedded['zip_pairs'];
    $headingsFromBody = $injected['headings'];
    $tocEntries = $headingsFromBody;
    $docTitle = epub_primary_title_plain($art);
    $xmlLang = epub_article_xml_lang($art);
    $navLabel = $xmlLang === 'en' ? 'Contents' : 'Содержание';
    $navXhtml = epub_build_nav_xhtml($tocEntries, $navLabel, $xmlLang);
    $epubCss = build_galley_epub_css();
    $chapterXhtml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<!DOCTYPE html>' . "\n"
        . '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" lang="' . h($xmlLang) . '" xml:lang="' . h($xmlLang) . '">' . "\n"
        . '<head><meta charset="utf-8"/><title>' . h($docTitle) . '</title>' . "\n"
        . '<style>' . $epubCss . '</style></head>' . "\n"
        . '<body>' . $contentHtml . '</body></html>';

    $metaXml = epub_build_metadata_xml($art, $issue);
    $metaXml .= "\n    <meta property=\"dcterms:modified\">" . gmdate('Y-m-d\TH:i:s\Z') . '</meta>';
    $cover = $art['epub_cover'] ?? null;
    $coverHref = '';
    $coverBytes = null;
    $coverMime = '';
    if (is_array($cover) && !empty($cover['data']) && !empty($cover['mime'])) {
        $raw = base64_decode((string)$cover['data'], true);
        if ($raw !== false && strlen($raw) < 6 * 1024 * 1024) {
            $coverMime = (string)$cover['mime'];
            $coverHref = epub_cover_href_from_mime($coverMime);
            $coverBytes = $raw;
        }
    }

    $manifestItems = array_merge(
        [
            '    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>',
            '    <item id="chapter1" href="chapter.xhtml" media-type="application/xhtml+xml"/>',
        ],
        $imageManifestLines
    );
    $spineItems = [];
    if ($coverHref !== '' && $coverBytes !== null) {
        $manifestItems[] = '    <item id="cover-image" href="' . h($coverHref) . '" media-type="' . h($coverMime) . '" properties="cover-image"/>';
        $manifestItems[] = '    <item id="cover-page" href="cover.xhtml" media-type="application/xhtml+xml"/>';
        array_unshift($spineItems, '<itemref idref="cover-page" linear="no"/>');
    }
    $spineItems[] = '<itemref idref="chapter1"/>';

    $contentOpf = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<package xmlns="http://www.idpf.org/2007/opf" unique-identifier="book-id" version="3.0" xml:lang="' . h($xmlLang) . '" prefix="dcterms: http://purl.org/dc/terms/">' . "\n"
        . '  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n"
        . $metaXml . "\n"
        . '  </metadata>' . "\n"
        . '  <manifest>' . "\n" . implode("\n", $manifestItems) . "\n  </manifest>\n"
        . '  <spine>' . "\n    " . implode("\n    ", $spineItems) . "\n  </spine>\n"
        . '</package>';

    $coverXhtml = '';
    if ($coverHref !== '' && $coverBytes !== null) {
        $coverXhtml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<!DOCTYPE html>' . "\n"
            . '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" lang="' . h($xmlLang) . '" xml:lang="' . h($xmlLang) . '">' . "\n"
            . '<head><meta charset="utf-8"/><title>Cover</title>'
            . '<style type="text/css">body{margin:0}img{max-width:100%;height:auto}</style></head>'
            . '<body epub:type="cover"><img src="' . h($coverHref) . '" alt=""/></body></html>';
    }

    $container = '<?xml version="1.0" encoding="UTF-8"?>
<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <rootfiles>
    <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>';

    if (!@mkdir($tmpDir . '/META-INF') && !is_dir($tmpDir . '/META-INF')) {
        epub_cleanup_tmp($tmpDir);
        throw new RuntimeException('Не удалось создать временную директорию для EPUB');
    }

    file_put_contents($tmpDir . '/mimetype', 'application/epub+zip', LOCK_EX);
    file_put_contents($tmpDir . '/META-INF/container.xml', $container, LOCK_EX);
    file_put_contents($tmpDir . '/OEBPS/content.opf', $contentOpf, LOCK_EX);
    file_put_contents($tmpDir . '/OEBPS/nav.xhtml', $navXhtml, LOCK_EX);
    file_put_contents($tmpDir . '/OEBPS/chapter.xhtml', $chapterXhtml, LOCK_EX);
    if ($coverHref !== '' && $coverBytes !== null) {
        file_put_contents($tmpDir . '/OEBPS/' . $coverHref, $coverBytes, LOCK_EX);
        file_put_contents($tmpDir . '/OEBPS/cover.xhtml', $coverXhtml, LOCK_EX);
    }

    $zipPath = $tmpDir . '.epub';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        epub_cleanup_tmp($tmpDir);
        throw new RuntimeException('Не удалось создать EPUB');
    }
    $zip->addFile($tmpDir . '/mimetype', 'mimetype');
    $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);
    $zip->addFile($tmpDir . '/META-INF/container.xml', 'META-INF/container.xml');
    $zip->addFile($tmpDir . '/OEBPS/content.opf', 'OEBPS/content.opf');
    $zip->addFile($tmpDir . '/OEBPS/nav.xhtml', 'OEBPS/nav.xhtml');
    $zip->addFile($tmpDir . '/OEBPS/chapter.xhtml', 'OEBPS/chapter.xhtml');
    if ($coverHref !== '' && $coverBytes !== null) {
        $zip->addFile($tmpDir . '/OEBPS/' . $coverHref, 'OEBPS/' . $coverHref);
        $zip->addFile($tmpDir . '/OEBPS/cover.xhtml', 'OEBPS/cover.xhtml');
    }
    foreach ($imageZipPairs as $pair) {
        if (is_file($pair['path'] ?? '')) {
            $zip->addFile($pair['path'], $pair['zip']);
        }
    }
    $zip->close();

    $bytes = file_get_contents($zipPath);
    @unlink($zipPath);
    epub_cleanup_tmp($tmpDir);
    return $bytes;
}

function epub_cleanup_tmp(string $tmpDir): void {
    foreach (glob($tmpDir . '/OEBPS/images/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($tmpDir . '/OEBPS/images');
    foreach (glob($tmpDir . '/OEBPS/*') ?: [] as $f) {
        @unlink($f);
    }
    foreach (glob($tmpDir . '/META-INF/*') ?: [] as $f) {
        @unlink($f);
    }
    @unlink($tmpDir . '/mimetype');
    @rmdir($tmpDir . '/OEBPS');
    @rmdir($tmpDir . '/META-INF');
    @rmdir($tmpDir);
}

/** Нормализует список строк */
function normalize_string_list($value): array {
    if (is_array($value)) return array_values(array_filter(array_map('trim', $value)));
    $value = trim((string)$value);
    if ($value === '') return [];
    return array_values(array_filter(preg_split('/\R+/u', $value)));
}
