<?php
require_once __DIR__ . '/../src/functions.php';
ensure_session();

$issue = $_SESSION['issue'] ?? default_issue_metadata();
$keys = get_galley_preset_keys();
$preset = ['version' => '1.0', 'export_date' => date('Y-m-d H:i:s'), 'name' => ''];
foreach ($keys as $k) {
    $preset[$k] = $issue[$k] ?? '';
}

$json = json_encode($preset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$filename = 'galley-preset-' . date('Y-m-d-His') . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $json;
exit;
