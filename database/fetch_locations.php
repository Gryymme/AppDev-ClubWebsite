<?php
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/locations.php';

$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$rows = locationRows($conn, $type, $id);
$conn->close();

// Preserve the original JSON contract; forms request escaped HTML options.
if (($_GET['format'] ?? '') === 'options') {
    header('Content-Type: text/html; charset=utf-8');
    renderLocationOptions($rows, $type);
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($rows);
}
