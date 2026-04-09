<?php
require_once __DIR__ . '/../src/functions.php';
ensure_session();

$data = [
    'version' => '1.0',
    'export_date' => date('Y-m-d H:i:s'),
    'issue' => $_SESSION['issue'] ?? default_issue_metadata(),
    'articles' => $_SESSION['articles'] ?? [],
];

$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$filename = 'Backup-' . date('Y-m-d-His') . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $json;
exit;
