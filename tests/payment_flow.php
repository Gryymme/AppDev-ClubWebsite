<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$mode = $argv[1] ?? '';
if (!in_array($mode, ['gcash', 'card', 'invalid'], true)) {
    fwrite(STDERR, "Usage: php tests/payment_flow.php gcash|card|invalid\n");
    exit(1);
}
chdir(__DIR__ . '/..');
require 'database/db_connection.php';
$user = $conn->query("SELECT * FROM users WHERE member_status <> 'Active' LIMIT 1")->fetch_assoc();
if (!$user) { fwrite(STDERR, "An existing inactive user is required.\n"); exit(1); }
$session = ['user_id' => $user['id'], 'username' => $user['username'], 'email' => $user['email'], 'member_status' => $user['member_status']];
ini_set('session.serialize_handler', 'php_serialize');
session_set_save_handler(fn() => true, fn() => true, fn($id) => serialize($session), fn($id, $data) => true, fn($id) => true, fn($lifetime) => 0);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['selected_plan' => 'monthly', 'paymentMethod' => 'gcash', 'gcashPhoneNumber' => '9171234567'];
if ($mode !== 'gcash') {
    $_POST = ['selected_plan' => 'annual', 'paymentMethod' => 'credit_debit', 'cardName' => 'Test Person', 'cardNumber' => '4111111111111111', 'expiryDate' => date('m/y', strtotime('+1 year')), 'cvv' => $mode === 'invalid' ? 'xx' : '123'];
}
// payment.php uses this required connection without committing the transaction.
$conn->begin_transaction();
ob_start();
register_shutdown_function(function () use ($conn, $user, $mode) {
    $html = ob_get_clean();
    $row = $conn->query('SELECT * FROM users WHERE id = ' . (int)$user['id'])->fetch_assoc();
    $ok = $mode === 'invalid'
        ? $row === $user && strpos($html, 'CVV must be 3 or 4 digits.') !== false && strpos($html, 'select-button selected') !== false
        : $row['member_status'] === 'Active' && $row['membership_plan'] === ($mode === 'card' ? 'annual' : 'monthly')
            && (float)$row['membership_amount_paid'] === ($mode === 'card' ? 200.0 : 20.0) && ($_SESSION['member_status'] ?? '') === 'Active';
    $conn->rollback();
    $restored = $conn->query('SELECT * FROM users WHERE id = ' . (int)$user['id'])->fetch_assoc();
    if (!$ok || $restored !== $user) { fwrite(STDERR, "FAIL payment flow: $mode\n"); exit(1); }
    echo "PASS transactional payment flow: $mode (original row restored)\n";
});
require 'payment.php';
