<?php
require_once __DIR__ . '/../src/functions.php';
ensure_session();

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false]);
    exit;
}

$size = trim($_POST['galley_logo_size'] ?? '');
$sizeInt = (int)$size;
if ($size !== '' && $sizeInt >= 40 && $sizeInt <= 200) {
    $_SESSION['issue'] = $_SESSION['issue'] ?? default_issue_metadata();
    $_SESSION['issue']['galley_logo_size'] = (string)$sizeInt;
    issue_apply_to_articles($_SESSION['issue']);
}
echo json_encode(['ok' => true]);
