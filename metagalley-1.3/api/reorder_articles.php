<?php
require_once __DIR__ . '/../src/functions.php';
ensure_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order'])) {
    $order = explode(',', $_POST['order']);
    $newArts = [];
    foreach ($order as $idx) {
        $idx = (int)$idx;
        if (isset($_SESSION['articles'][$idx])) {
            $newArts[] = $_SESSION['articles'][$idx];
        }
    }
    $_SESSION['articles'] = $newArts;
}

header('Location: ../pages/dashboard.php?reordered=1#articles');
exit;
