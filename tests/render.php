<?php
// CLI-only visual QA helper. Never provides an HTTP authentication bypass.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$page = $argv[1] ?? '';
if (!in_array($page, ['admintools.php', 'discussions.php', 'editinfo.php', 'user_management.php'], true)) {
    fwrite(STDERR, "Usage: php tests/render.php <page>\n");
    exit(1);
}
chdir(__DIR__ . '/..');
require 'database/db_connection.php';
$query = $page === 'admintools.php'
    ? "SELECT * FROM users WHERE username = 'admin' AND email = 'admin@email.com' LIMIT 1"
    : "SELECT * FROM users WHERE member_status = 'Active' ORDER BY id LIMIT 1";
$user = $conn->query($query)->fetch_assoc();
if (!$user) { fwrite(STDERR, "A suitable existing user is required.\n"); exit(1); }
// A memory-only session avoids touching existing sessions or user data.
$session = ['user_id' => $user['id'], 'username' => $user['username'], 'email' => $user['email'], 'member_status' => $user['member_status']];
ini_set('session.serialize_handler', 'php_serialize');
session_set_save_handler(
    fn() => true, fn() => true, fn($id) => serialize($session),
    fn($id, $data) => true, fn($id) => true, fn($lifetime) => 0
);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/' . $page;
require $page;
