<?php
require_once __DIR__ . '/../src/functions.php';
ensure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/dashboard.php#articles');
    exit;
}

$arts = &$_SESSION['articles'];
$arts[] = get_empty_article();

header('Location: ../pages/dashboard.php?added=1#articles');
exit;
