<?php
require_once __DIR__ . '/../src/functions.php';
ensure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['preset_file']['tmp_name'])) {
    header('Location: ../pages/dashboard.php?import=error');
    exit;
}

$tmp = $_FILES['preset_file']['tmp_name'];
$raw = @file_get_contents($tmp);
if ($raw === false) {
    header('Location: ../pages/dashboard.php?import=error');
    exit;
}

$data = @json_decode($raw, true);
if (!is_array($data)) {
    header('Location: ../pages/dashboard.php?import=error');
    exit;
}

$keys = get_galley_preset_keys();
$issue = $_SESSION['issue'] ?? default_issue_metadata();
foreach ($keys as $k) {
    if (array_key_exists($k, $data) && is_string($data[$k])) {
        $issue[$k] = $data[$k];
    }
}
$_SESSION['issue'] = $issue;
header('Location: ../pages/dashboard.php?import=ok');
exit;
