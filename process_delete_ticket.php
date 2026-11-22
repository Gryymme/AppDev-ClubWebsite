<?php
session_start();
require_once 'database/db_connection.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['event_id_to_delete'])) {
    $userId = $_SESSION['user_id'];
    $eventIdToDelete = $_POST['event_id_to_delete'];

    // Delete the event sign-up from the database
    $stmt = $conn->prepare("DELETE FROM signed_up_events WHERE user_id = ? AND event_id = ?");
    if ($stmt === false) {
        $_SESSION['message'] = "Database error (prepare delete): " . $conn->error;
        $_SESSION['msg_type'] = "danger";
        $conn->close();
        header("Location: user_management.php");
        exit();
    }
    $stmt->bind_param("ii", $userId, $eventIdToDelete); // "ii" for two integers

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['message'] = "Reserved ticket has been successfully cancelled!";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Reserved ticket not found or already cancelled.";
            $_SESSION['msg_type'] = "warning";
        }
    } else {
        // Handle database delete error
        $_SESSION['message'] = "Error cancelling reserved ticket: " . $stmt->error;
        $_SESSION['msg_type'] = "danger";
    }
    $stmt->close();
    $conn->close(); 
} else {
    $_SESSION['message'] = "Invalid request to delete ticket.";
    $_SESSION['msg_type'] = "danger";
}

header("Location: user_management.php"); // Redirect back to user management page
exit();
?>