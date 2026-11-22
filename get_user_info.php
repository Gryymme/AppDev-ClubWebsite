<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Ensure the user is logged in to access this feature
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

require_once 'database/db_connection.php';

$response = ['success' => false, 'message' => 'Invalid request.'];

if (isset($_GET['user_id'])) {
    $user_id_to_fetch = (int)$_GET['user_id'];

    if ($user_id_to_fetch > 0) {
        // Fetch user data: 'id', 'name', 'username', and 'created_at' columns.
        $stmt = $conn->prepare("SELECT name, username, created_at FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id_to_fetch);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            $response['success'] = true;
            $response['user'] = $user;
            $response['message'] = 'User found.';
        } else {
            $response['message'] = 'User not found.';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Invalid user ID.';
    }
}

$conn->close();
echo json_encode($response);
?>