# MetaGalley

Редактор HTML-гранок и EPUB научных статей.

## Возможности

- **Загрузка JATS XML** — один файл или архив ZIP с несколькими статьями
- **Начать с нуля** — создание выпуска с пустой статьёй без XML
- **Автоматический разбор** полей: заголовки, авторы, аннотации, ключевые слова, текст, список литературы, DOI, EDN
- **Редактирование** с визуальным HTML-форматированием (TinyMCE)
- **Перемещение блоков** — drag-and-drop, порядок единый для всех статей выпуска
- **Логотип журнала** — URL и регулируемый размер (ползунок), применяется ко всем статьям
- **Оформление** — пресеты, шрифты, цвета, экспорт/импорт настроек
- **Изоляция стилей** — при встраивании в OJS типографика сохраняется
- **Экспорт** HTML-гранки и EPUB

## Требования

- PHP 7.4+ с расширениями: **SimpleXML** (php-xml), **session**, **mbstring**, **ZipArchive** (php-zip) для ZIP-архивов

```bash
# Ubuntu/Debian
sudo apt install php-xml php-zip

# Fedora
sudo dnf install php-xml php-zip
```

## Запуск

```bash
cd metagalley-1.3
php -S localhost:8765
```

Откройте http://localhost:8765 в браузере.

## Использование

1. На главной: загрузите JATS XML (или ZIP), начните с нуля или восстановите из резервной копии
2. В Dashboard отредактируйте метаданные выпуска и оформление
3. Откройте редактор статьи — измените блоки, порядок (перетаскивание), сохраните
4. Скачайте HTML или EPUB (по одной или все в ZIP)

## Структура проекта

```
metagalley-1.3/
├── index.php              # Главная — загрузка JATS, «Начать с нуля», восстановление
├── src/
│   └── functions.php      # Парсер JATS, рендеринг, экспорт
├── pages/
│   ├── dashboard.php      # Управление выпуском и статьями
│   ├── editor.php         # Редактор гранки с блоками
│   └── preview_galley.php # Предпросмотр в iframe
├── api/
│   ├── save_article.php
│   ├── save_blocks_order.php
│   ├── save_galley_logo_size.php
│   ├── export_galley.php
│   ├── export_galley_epub.php
│   ├── export_galley_all.php
│   ├── export_backup.php
│   ├── export_galley_preset.php
│   ├── import_galley_preset.php
│   └── reset_session.php
├── assets/
│   └── style.css
└── docs/
    └── manual.html        # Справка
```
