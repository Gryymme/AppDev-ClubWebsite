<?php
session_start();
require_once 'database/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    // Redirect if not logged in
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'])) {
    $userId = $_SESSION['user_id'];
    $eventId = $_POST['event_id'];

    // Check if the user is already signed up
    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM signed_up_events WHERE user_id = ? AND event_id = ?");
    $check_stmt->bind_param("ii", $userId, $eventId);
    $check_stmt->execute();
    $check_stmt->bind_result($count);
    $check_stmt->fetch();
    $check_stmt->close();

    if ($count > 0) {
        $_SESSION['message'] = "You are already signed up for this event.";
        $_SESSION['msg_type'] = "info";
        header('Location: user_management.php');
        exit();
    }

    // Fetch event details from the 'events' table
    $event_stmt = $conn->prepare("SELECT title, event_date, event_time FROM events WHERE id = ?");
    $event_stmt->bind_param("i", $eventId);
    $event_stmt->execute();
    $event_stmt->bind_result($eventTitle, $eventDate, $eventTime);
    
    if (!$event_stmt->fetch()) {
        $_SESSION['message'] = "Event not found.";
        $_SESSION['msg_type'] = "danger";
        header('Location: events.php');
        exit();
    }
    $event_stmt->close();

    // Format the date to ex: "July 7, 2025"
    $eventDateFormatted = date("F j, Y", strtotime($eventDate));

    // Insert fetched details into the signed_up_events table
    $insert_stmt = $conn->prepare("INSERT INTO signed_up_events (user_id, event_id, event_title, event_date, event_time) VALUES (?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("iisss", $userId, $eventId, $eventTitle, $eventDateFormatted, $eventTime);

    if ($insert_stmt->execute()) {
        $_SESSION['message'] = "Successfully reserved ticket for " . htmlspecialchars($eventTitle) . "!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Error signing up for event: " . $insert_stmt->error;
        $_SESSION['msg_type'] = "danger";
    }
    $insert_stmt->close();

    $conn->close();
    header('Location: user_management.php');
    exit();
} else {
    $_SESSION['message'] = "Invalid request.";
    $_SESSION['msg_type'] = "danger";
    header('Location: events.php');
    exit();
}
?>