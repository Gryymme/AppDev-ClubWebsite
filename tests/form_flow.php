<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(__DIR__ . '/..');
require 'database/db_connection.php';
$user = $conn->query("SELECT * FROM users WHERE gender IN ('Male', 'Female', 'Non-Binary') AND province_id IS NOT NULL LIMIT 1")->fetch_assoc();
if (!$user) { fwrite(STDERR, "A user with a provincial address and a recognized gender is required.\n"); exit(1); }
$session = ['user_id' => $user['id']];
ini_set('session.serialize_handler', 'php_serialize');
session_set_save_handler(fn() => true, fn() => true, fn($id) => serialize($session), fn($id, $data) => true, fn($id) => true, fn($lifetime) => 0);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = $user;
$_POST['password'] = '';
$_POST['confirm_password'] = '';
$mode = $argv[1] ?? '';
if (!in_array($mode, ['edit', 'invalid-edit', 'registration'], true)) {
    fwrite(STDERR, "Usage: php tests/form_flow.php edit|invalid-edit|registration\n");
    exit(1);
}
if ($mode === 'registration') {
    $_POST['username'] = 'verification_' . bin2hex(random_bytes(6));
    $_POST['email'] = $_POST['username'] . '@example.invalid';
    $_POST['password'] = $_POST['confirm_password'] = 'TemporaryVerification123';
}
if ($mode === 'invalid-edit') $_POST['region_id'] = 999999;
// Both handlers use the required connection and never commit. Closing rolls back.
$conn->begin_transaction();
register_shutdown_function(function () use ($mode) {
    $ok = $mode === 'registration' ? !empty($_SESSION['registration_success']) :
        ($mode === 'invalid-edit' ? ($_SESSION['msg_type'] ?? '') === 'danger' : ($_SESSION['msg_type'] ?? '') === 'success');
    if (!$ok) { fwrite(STDERR, "FAIL form flow: $mode\n"); exit(1); }
    echo "PASS transactional form flow: $mode\n";
});
require $mode === 'registration' ? 'registration_result.php' : 'process_edit_info.php';
