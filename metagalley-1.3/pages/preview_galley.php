<?php
require_once __DIR__ . '/../src/functions.php';
ensure_session();

$id = (int)($_GET['id'] ?? $_SESSION['current_article_id'] ?? 0);
$art = get_article_by_id($id);
if ($art === null) {
    echo '<p>Статья не найдена.</p>';
    exit;
}
// Миграция: refs как массив -> HTML
if (is_array($art['refs_ru'] ?? null)) {
    $art['refs_ru'] = implode('', array_map(fn($r) => '<p>' . h($r) . '</p>', $art['refs_ru']));
}
if (is_array($art['refs_en'] ?? null)) {
    $art['refs_en'] = implode('', array_map(fn($r) => '<p>' . h($r) . '</p>', $art['refs_en']));
}
$blocks = $art['blocks_order'] ?? get_canonical_blocks_order();
if (in_array('refs', $blocks, true)) {
    $blocks = array_map(fn($b) => $b === 'refs' ? ['refs_ru', 'refs_en'] : [$b], $blocks);
    $blocks = array_merge(...$blocks);
}
// Миграция: добавляем блоки «Информация об авторах» если их нет
if (!in_array('authors_info_en', $blocks, true) || !in_array('authors_info_ru', $blocks, true)) {
    $toAdd = [];
    if (!in_array('authors_info_en', $blocks, true)) $toAdd[] = 'authors_info_en';
    if (!in_array('authors_info_ru', $blocks, true)) $toAdd[] = 'authors_info_ru';
    if (!empty($toAdd)) {
        $idx = array_search('refs_en', $blocks);
        if ($idx !== false) {
            array_splice($blocks, $idx + 1, 0, $toAdd);
        } else {
            $blocks = array_merge($blocks, $toAdd);
        }
    }
}
$levels = $art['blocks_heading_levels'] ?? [];
$hTag = function($bid) use ($levels) { $l = (int)($levels[$bid] ?? 2); return $l === 3 ? 'h3' : 'h2'; };

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Предпросмотр гранки — MetaGalley <?= h(METAGALLEY_VERSION) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php $issueData = $_SESSION['issue'] ?? default_issue_metadata(); $blocksSeparator = ($issueData['galley_blocks_separator'] ?? '0') === '1'; $fontUrl = get_galley_font_url($issueData['galley_font_family'] ?? null) ?? 'https://fonts.googleapis.com/css2?family=PT+Serif:ital,wght@0,400;0,700;1,400&display=swap'; ?>
    <link href="<?= h($fontUrl) ?>" rel="stylesheet">
    <script src="https://www.wiris.net/demo/plugins/app/WIRISplugins.js?viewer=image" referrerpolicy="origin"></script>
    <style><?= build_galley_css($_SESSION['issue'] ?? default_issue_metadata()) ?></style>
    <?php $logoAlign = in_array($issueData['galley_logo_align'] ?? 'center', ['left','center','right'], true) ? ($issueData['galley_logo_align'] ?? 'center') : 'center'; ?>
    <style>.journal-header{text-align:<?= h($logoAlign) ?>;padding:1.2em 1.5em 0.8em;margin-bottom:0.5em}.journal-header img{height:<?= (int)($issueData['galley_logo_size'] ?? 88) ?>px;width:auto;max-width:800px;object-fit:contain;display:inline-block}body.no-logo #content{padding-top:2em}.galley-article-footer{margin-top:2.5em;padding-top:1.25em;border-top:1px solid rgba(0,0,0,0.12);text-align:center}.issue-imprint{font-size:0.9em;color:#555;opacity:0.95}.issue-imprint p{margin:0.4em 0;text-indent:0}.galley-credit{margin:0.75em 0 0;font-size:0.8em;color:#555;opacity:0.45;text-indent:0}.issue-imprint+.galley-credit{margin-top:0.85em}</style>
</head>
<body<?= ($logoUrl = trim($issueData['galley_logo_url'] ?? '')) === '' ? ' class="no-logo"' : '' ?>>
<?php if ($logoUrl !== ''): $logoAlt = trim($issueData['journal_title_en'] ?? '') ?: trim($issueData['journal_title_ru'] ?? ''); ?>
<header class="journal-header"><img src="<?= h($logoUrl) ?>" alt="<?= h($logoAlt) ?>"></header>
<?php endif; ?>
<div id="content">
    <?php
    foreach ($blocks as $bid) {
        if ($bid === 'title_ru' && trim($art['title_ru'] ?? '') !== '') echo '<h1>' . h($art['title_ru']) . '</h1>';
        elseif ($bid === 'title_en' && trim($art['title_en'] ?? '') !== '') echo '<h1>' . h($art['title_en']) . '</h1>';
        elseif ($bid === 'meta_ru') {
            $metaHtml = render_meta_block($art, 'ru');
            if (strpos($metaHtml, 'class="muted"') === false) echo "<div class=\"meta-block\">$metaHtml</div>";
        }
        elseif ($bid === 'meta_en') {
            $metaHtml = render_meta_block($art, 'en');
            if (strpos($metaHtml, 'class="muted"') === false) echo "<div class=\"meta-block\">$metaHtml</div>";
        }
        elseif ($bid === 'authors_ru' && !empty($art['authors'])) {
            $authorsHtml = '';
            foreach ($art['authors'] as $a) {
                $authorsHtml .= format_author_block($a, false, false, $issueData);
            }
            if (html_has_content($authorsHtml)) {
                echo '<div class="authors authors-ru authors-compact">' . $authorsHtml;
                if (array_filter($art['authors'], fn($a) => !empty($a['corresp']))) {
                    echo '<p class="authors-corresp-note">* — корреспондирующий автор</p>';
                }
                echo '</div>';
            }
        }
        elseif ($bid === 'authors_en' && !empty($art['authors'])) {
            $authorsHtml = '';
            foreach ($art['authors'] as $a) {
                $authorsHtml .= format_author_block($a, true, false, $issueData);
            }
            if (html_has_content($authorsHtml)) {
                echo '<div class="authors authors-en authors-compact">' . $authorsHtml;
                if (array_filter($art['authors'], fn($a) => !empty($a['corresp']))) {
                    echo '<p class="authors-corresp-note">* — corresponding author</p>';
                }
                echo '</div>';
            }
        }
        elseif ($bid === 'authors') {
            // Блок «Авторы (редактирование)» — только для редактора, в HTML не выводим
        }
        elseif ($bid === 'abstract_ru' && html_has_content($art['abstract_ru'] ?? '')) { $tag = $hTag('abstract_ru'); $lbl = get_block_label($art, 'abstract_ru', 'Аннотация'); echo "<div class=\"abstract\"><$tag>" . h($lbl) . "</$tag>" . $art['abstract_ru'] . '</div>'; }
        elseif ($bid === 'abstract_en' && html_has_content($art['abstract_en'] ?? '')) { $tag = $hTag('abstract_en'); $lbl = get_block_label($art, 'abstract_en', 'Abstract'); echo "<div class=\"abstract\"><$tag>" . h($lbl) . "</$tag>" . $art['abstract_en'] . '</div>'; }
        elseif ($bid === 'keywords_ru') {
            $kw = array_filter(array_map('trim', is_array($art['keywords_ru'] ?? null) ? $art['keywords_ru'] : []));
            if (!empty($kw)) { $tag = $hTag('keywords_ru'); $lbl = get_block_label($art, 'keywords_ru', 'Ключевые слова'); echo "<$tag>" . h($lbl) . "</$tag><p>" . h(implode(', ', $kw)) . '</p>' . ($blocksSeparator ? '<hr class="block-separator">' : ''); }
        }
        elseif ($bid === 'keywords_en') {
            $kw = array_filter(array_map('trim', is_array($art['keywords_en'] ?? null) ? $art['keywords_en'] : []));
            if (!empty($kw)) { $tag = $hTag('keywords_en'); $lbl = get_block_label($art, 'keywords_en', 'Keywords'); echo "<$tag>" . h($lbl) . "</$tag><p>" . h(implode(', ', $kw)) . '</p>' . ($blocksSeparator ? '<hr class="block-separator">' : ''); }
        }
        elseif ($bid === 'body' && html_has_content($art['body_html'] ?? '')) echo '<div class="body">' . $art['body_html'] . '</div>';
        elseif ($bid === 'extra_info' && html_has_content($art['extra_info_html'] ?? '')) {
            $tit = trim($art['extra_info_title'] ?? '');
            $tag = $hTag('extra_info');
            if ($tit !== '') echo "<$tag>" . h($tit) . "</$tag>";
            echo '<div class="extra-info">' . $art['extra_info_html'] . '</div>';
        }
        elseif ($bid === 'refs_ru') {
            $refs = $art['refs_ru'] ?? null;
            $refsHtml = is_array($refs) ? implode('', array_map(fn($r) => '<p>' . h($r) . '</p>', array_filter($refs, fn($r) => trim((string)$r) !== ''))) : (string)($refs ?? '');
            if (html_has_content($refsHtml)) {
                $tag = $hTag('refs_ru');
                $lbl = get_block_label($art, 'refs_ru', 'Список литературы');
                echo "<$tag>" . h($lbl) . "</$tag><div class=\"refs refs-plain\">" . $refsHtml . '</div>';
            }
        }
        elseif ($bid === 'refs_en') {
            $refs = $art['refs_en'] ?? null;
            $refsHtml = is_array($refs) ? implode('', array_map(fn($r) => '<p>' . h($r) . '</p>', array_filter($refs, fn($r) => trim((string)$r) !== ''))) : (string)($refs ?? '');
            if (html_has_content($refsHtml)) {
                $tag = $hTag('refs_en');
                $lbl = get_block_label($art, 'refs_en', 'References');
                echo "<$tag>" . h($lbl) . "</$tag><div class=\"refs refs-plain\">" . $refsHtml . '</div>';
            }
        }
        elseif ($bid === 'authors_info_ru' && html_has_content($art['authors_info_ru_html'] ?? '')) {
            $tit = trim($art['authors_info_ru_title'] ?? '');
            $tag = $hTag('authors_info_ru');
            if ($tit !== '') echo "<$tag>" . h($tit) . "</$tag>";
            echo '<div class="authors-info">' . $art['authors_info_ru_html'] . '</div>';
        }
        elseif ($bid === 'authors_info_en' && html_has_content($art['authors_info_en_html'] ?? '')) {
            $tit = trim($art['authors_info_en_title'] ?? '');
            $tag = $hTag('authors_info_en');
            if ($tit !== '') echo "<$tag>" . h($tit) . "</$tag>";
            echo '<div class="authors-info">' . $art['authors_info_en_html'] . '</div>';
        }
    }
    echo build_galley_article_footer_html($issueData);
    ?>
</div>
<script>
window.galleyBlocksSeparator = <?= $blocksSeparator ? 'true' : 'false' ?>;
window.galleyBlockLabels = <?= json_encode($art['blocks_labels'] ?? []) ?>;
window.authorNameOrderRu = <?= json_encode(issue_author_name_order(false, $issueData)) ?>;
window.authorNameOrderEn = <?= json_encode(issue_author_name_order(true, $issueData)) ?>;
window.galleyFooterHtml = <?= json_encode(build_galley_article_footer_html($issueData)) ?>;
function fmtDate(iso) {
    if (!iso || !/^(\d{4})-(\d{2})-(\d{2})/.test(iso)) return '';
    return iso.replace(/^(\d{4})-(\d{2})-(\d{2}).*/, '$3.$2.$1');
}
function renderMetaBlock(meta, lang) {
    if (!meta) return '<p class="muted">' + (lang === 'ru' ? 'Нет данных' : 'No data') + '</p>';
    const isRu = (lang === 'ru');
    const parts = [];
    const catKey = isRu ? 'categories_ru' : 'categories_en';
    const catVal = (meta[catKey] || '').split(';').map(s => s.trim()).filter(Boolean);
    if (catVal.length) parts.push('<p class="meta-rubric"><strong>' + (isRu ? 'РУБРИКА:' : 'SECTION:') + '</strong> ' + catVal.join('; ') + '</p>');
    if (meta.doi) parts.push('<p class="meta-doi"><strong>DOI:</strong> <a href="https://doi.org/' + meta.doi + '" target="_blank">' + meta.doi + '</a></p>');
    if (meta.edn) { const edn = (meta.edn || '').trim(); const ednEsc = edn.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); parts.push('<p class="meta-edn"><strong>EDN:</strong> <a href="https://elibrary.ru/' + encodeURIComponent(edn) + '" target="_blank" rel="noopener">' + ednEsc + '</a></p>'); }
    const v = []; if (meta.volume) v.push((isRu ? 'Т.' : 'Vol.') + ' ' + meta.volume); if (meta.issue) v.push((isRu ? '№' : 'No.') + ' ' + meta.issue); if (meta.fpage && meta.lpage) v.push((isRu ? 'с.' : 'pp.') + ' ' + meta.fpage + '–' + meta.lpage); else if (meta.fpage) v.push((isRu ? 'с.' : 'p.') + ' ' + meta.fpage);
    if (v.length) parts.push('<p class="meta-vol">' + v.join(', ') + '</p>');
    const dates = []; if (meta.history_received) dates.push((isRu ? 'Поступила:' : 'Received:') + ' ' + fmtDate(meta.history_received)); if (meta.history_revised) dates.push((isRu ? 'Исправлена:' : 'Revised:') + ' ' + fmtDate(meta.history_revised)); if (meta.history_accepted) dates.push((isRu ? 'Принята:' : 'Accepted:') + ' ' + fmtDate(meta.history_accepted)); if (meta.pub_date) dates.push((isRu ? 'Опубликована:' : 'Published:') + ' ' + fmtDate(meta.pub_date));
    if (dates.length) parts.push('<p class="meta-dates">' + dates.join(' | ') + '</p>');
    const fund = (isRu ? (meta.funding_ru || '') : (meta.funding_en || '')).split(/\n/).map(s => s.trim()).filter(Boolean);
    if (fund.length) parts.push('<p class="meta-funding"><strong>' + (isRu ? 'Финансирование:' : 'Funding:') + '</strong> ' + fund.join(' ') + '</p>');
    const cy = meta.copyright_year || ''; const ch = isRu ? (meta.copyright_ru || '') : (meta.copyright_en || ''); const lic = meta.license || ''; const licUrl = meta.license_url || '';
    if (cy || ch || lic) { const p = []; if (cy && ch) p.push('© ' + cy + ' ' + ch); else if (ch) p.push('© ' + ch); if (lic) p.push(licUrl ? '<a href="' + licUrl + '" target="_blank">' + lic + '</a>' : lic); parts.push('<p class="meta-permissions">' + p.join('. ') + '</p>'); }
    return parts.length ? parts.join('') : '<p class="muted">' + (isRu ? 'Нет данных' : 'No data') + '</p>';
}

function formatAuthorBlockHtml(au, isEn) {
    const order = isEn ? (window.authorNameOrderEn || 'given_family') : (window.authorNameOrderRu || 'family_given');
    const s = isEn ? (au.surname_en || '').trim() : (au.surname_ru || '').trim();
    const g = isEn ? (au.given_en || '').trim() : (au.given_ru || '').trim();
    let name = order === 'given_family' ? (g + ' ' + s).trim() : (s + ' ' + g).trim();
    if (name && (au.corresp === true || au.corresp === '1' || au.corresp === 1)) name += ' *';
    let aff = (isEn ? au.aff_en : au.aff_ru) || '';
    const cc = (isEn ? au.city_country_en : au.city_country_ru) || '';
    if (cc) aff = aff ? aff + ' ' + cc : cc;
    const email = (au.email || '').trim();
    const orcid = (au.orcid || '').trim();
    const esc = s => (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const line2 = aff ? esc(aff) + '.' : '';
    let line3 = '';
    if (email) line3 += 'Email: ' + (email.indexOf('@') !== -1 ? '<a href="mailto:' + esc(email) + '">' + esc(email) + '</a>' : esc(email));
    if (orcid) line3 += (line3 ? ' ' : '') + '<a href="https://orcid.org/' + orcid + '" target="_blank" rel="noopener"><img src="https://orcid.org/sites/default/files/images/orcid_16x16.png" alt="ORCID" width="16" height="16" style="vertical-align:middle"> ' + esc(orcid) + '</a>';
    let html = '';
    if (name) html += '<strong>' + name.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</strong>';
    if (line2) html += (html ? '<br>' : '') + line2;
    if (line3) html += (html ? '<br>' : '') + line3;
    return html ? '<div class="author-entry">' + html + '</div>' : '';
}

function htmlHasContent(html) {
    let t = (html || '').replace(/<[^>]+>/g, '');
    t = t.replace(/&nbsp;/gi, ' ').replace(/\u00A0/g, ' ');
    t = t.replace(/\s+/g, ' ').trim();
    return t !== '';
}
function updateFromEditor(data) {
    const content = document.getElementById('content');
    if (!content || !data) return;
    const order = (data.blocks_order || '').split(',').filter(Boolean);
    if (order.length === 0) order.push('title_en', 'authors_en', 'meta_en', 'abstract_en', 'keywords_en', 'title_ru', 'authors_ru', 'meta_ru', 'abstract_ru', 'keywords_ru', 'body', 'extra_info', 'refs_ru', 'refs_en', 'authors_info_en', 'authors_info_ru', 'meta', 'authors');
    const levels = data.blocks_heading_levels || {};
    const labels = data.blocks_labels || (typeof window.galleyBlockLabels !== 'undefined' ? window.galleyBlockLabels : {});
    const lbl = (bid, def) => { const c = (labels[bid] || '').trim(); return c !== '' ? c : def; };
    const h = (bid) => { const l = parseInt(levels[bid] || '2', 10); return l === 3 ? 'h3' : 'h2'; };

    let html = '';
    order.forEach(bid => {
        if (bid === 'title_ru' && (data.title_ru || '').trim()) html += '<h1>' + (data.title_ru || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') + '</h1>';
        else if (bid === 'title_en' && (data.title_en || '').trim()) html += '<h1>' + (data.title_en || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') + '</h1>';
        else if (bid === 'meta_ru' && data.meta) { const m = renderMetaBlock(data.meta, 'ru'); if (m.indexOf('class="muted"') === -1) html += '<div class="meta-block">' + m + '</div>'; }
        else if (bid === 'meta_en' && data.meta) { const m = renderMetaBlock(data.meta, 'en'); if (m.indexOf('class="muted"') === -1) html += '<div class="meta-block">' + m + '</div>'; }
        else if (bid === 'authors_ru' && data.authors && data.authors.length) {
            let authorsHtml = '';
            data.authors.forEach(au => { authorsHtml += formatAuthorBlockHtml(au, false); });
            if (htmlHasContent(authorsHtml)) {
                const hasCorresp = data.authors.some(au => au.corresp === true || au.corresp === '1' || au.corresp === 1);
                html += '<div class="authors authors-ru authors-compact">' + authorsHtml;
                if (hasCorresp) html += '<p class="authors-corresp-note">* — корреспондирующий автор</p>';
                html += '</div>';
            }
        }
        else if (bid === 'authors_en' && data.authors && data.authors.length) {
            let authorsHtml = '';
            data.authors.forEach(au => { authorsHtml += formatAuthorBlockHtml(au, true); });
            if (htmlHasContent(authorsHtml)) {
                const hasCorresp = data.authors.some(au => au.corresp === true || au.corresp === '1' || au.corresp === 1);
                html += '<div class="authors authors-en authors-compact">' + authorsHtml;
                if (hasCorresp) html += '<p class="authors-corresp-note">* — corresponding author</p>';
                html += '</div>';
            }
        }
        else if (bid === 'authors') {
            // Блок «Авторы (редактирование)» — только для редактора, в HTML не выводим
        }
        else if (bid === 'abstract_ru' && htmlHasContent(data.abstract_ru)) { const t = h('abstract_ru'); const s = (lbl('abstract_ru', 'Аннотация') || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); html += '<div class="abstract"><' + t + '>' + s + '</' + t + '>' + data.abstract_ru + '</div>'; }
        else if (bid === 'abstract_en' && htmlHasContent(data.abstract_en)) { const t = h('abstract_en'); const s = (lbl('abstract_en', 'Abstract') || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); html += '<div class="abstract"><' + t + '>' + s + '</' + t + '>' + data.abstract_en + '</div>'; }
        else if (bid === 'keywords_ru') {
            const raw = data.keywords_ru;
            const kw = (Array.isArray(raw) ? raw : (raw || '').split(/[,;]/)).map(s => (typeof s === 'string' ? s : String(s)).trim()).filter(Boolean);
            if (kw.length) { const t = h('keywords_ru'); const s = (lbl('keywords_ru', 'Ключевые слова') || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); const sep = (typeof window.galleyBlocksSeparator !== 'undefined' && window.galleyBlocksSeparator) ? '<hr class="block-separator">' : ''; html += '<' + t + '>' + s + '</' + t + '><p>' + kw.join(', ').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') + '</p>' + sep; }
        }
        else if (bid === 'keywords_en') {
            const raw = data.keywords_en;
            const kw = (Array.isArray(raw) ? raw : (raw || '').split(/[,;]/)).map(s => (typeof s === 'string' ? s : String(s)).trim()).filter(Boolean);
            if (kw.length) { const t = h('keywords_en'); const s = (lbl('keywords_en', 'Keywords') || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); const sep = (typeof window.galleyBlocksSeparator !== 'undefined' && window.galleyBlocksSeparator) ? '<hr class="block-separator">' : ''; html += '<' + t + '>' + s + '</' + t + '><p>' + kw.join(', ').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') + '</p>' + sep; }
        }
        else if (bid === 'body' && htmlHasContent(data.body_html)) html += '<div class="body">' + data.body_html + '</div>';
        else if (bid === 'extra_info' && htmlHasContent(data.extra_info_html)) {
            const tit = (data.extra_info_title || '').trim();
            if (tit) {
                const tag = h('extra_info');
                const safe = tit.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                html += '<' + tag + '>' + safe + '</' + tag + '>';
            }
            html += '<div class="extra-info">' + data.extra_info_html + '</div>';
        }
        else if (bid === 'refs_ru' && htmlHasContent(data.refs_ru)) { const t = h('refs_ru'); const s = (lbl('refs_ru', 'Список литературы') || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); html += '<' + t + '>' + s + '</' + t + '><div class="refs refs-plain">' + data.refs_ru + '</div>'; }
        else if (bid === 'refs_en' && htmlHasContent(data.refs_en)) { const t = h('refs_en'); const s = (lbl('refs_en', 'References') || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); html += '<' + t + '>' + s + '</' + t + '><div class="refs refs-plain">' + data.refs_en + '</div>'; }
        else if (bid === 'authors_info_ru' && htmlHasContent(data.authors_info_ru_html)) {
            const tit = (data.authors_info_ru_title || '').trim();
            if (tit) { const tag = h('authors_info_ru'); const safe = tit.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); html += '<' + tag + '>' + safe + '</' + tag + '>'; }
            html += '<div class="authors-info">' + data.authors_info_ru_html + '</div>';
        }
        else if (bid === 'authors_info_en' && htmlHasContent(data.authors_info_en_html)) {
            const tit = (data.authors_info_en_title || '').trim();
            if (tit) { const tag = h('authors_info_en'); const safe = tit.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); html += '<' + tag + '>' + safe + '</' + tag + '>'; }
            html += '<div class="authors-info">' + data.authors_info_en_html + '</div>';
        }
    });
    if (typeof window.galleyFooterHtml === 'string' && window.galleyFooterHtml) {
        html += window.galleyFooterHtml;
    }
    content.innerHTML = html || '<p>Нет контента</p>';
}
</script>
</body>
</html>
