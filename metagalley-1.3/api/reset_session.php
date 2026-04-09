<?php
/**
 * Сброс рабочих данных. Обложки EPUB не лежат отдельными файлами на сервере:
 * они только в $_SESSION['articles'][*]['epub_cover'] (base64). Вместе с
 * session_destroy() эти данные исчезают; отдельно чистить каталоги не нужно.
 */
session_start();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: ../index.php');
exit;
