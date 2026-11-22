<?php
session_start();
header('Content-Type: application/json');

require_once '../database/db_connection.php'; 

// Checks if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['username'] !== 'admin' || $_SESSION['email'] !== 'admin@email.com') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}
$conn->set_charset("utf8mb4");

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action.'];

switch ($action) {
    case 'add':
        $user_id = $_POST['user_id'] ?? '';
        $event_id = $_POST['event_id'] ?? '';
        $event_title = $_POST['event_title'] ?? '';
        $event_date = $_POST['event_date'] ?? '';
        $event_time = $_POST['event_time'] ?? '';

        $user_id_int = (int)$user_id;
        $event_id_int = (int)$event_id;

        $errors = [];
        if (empty($user_id) || empty($event_id) || empty($event_title) || empty($event_date) || empty($event_time)) {
            $errors[] = "All event fields are required for new entries.";
        }
        if (!empty($errors)) {
            $response = ['success' => false, 'message' => implode("<br>", $errors)];
            break;
        }

        $user_exists = false;
        $stmt_check_user = $conn->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
        if ($stmt_check_user) {
            $stmt_check_user->bind_param("i", $user_id_int);
            $stmt_check_user->execute();
            $stmt_check_user->bind_result($count_user);
            $stmt_check_user->fetch();
            $stmt_check_user->close();
            if ($count_user > 0) {
                $user_exists = true;
            }
        } else {
            $response = ['success' => false, 'message' => 'Database error during user existence check: ' . $conn->error];
            break;
        }

        if (!$user_exists) {
             $response = ['success' => false, 'message' => 'User with the provided ID does not exist.'];
             break;
        }

        $stmt_check_duplicate = $conn->prepare("SELECT COUNT(*) FROM signed_up_events WHERE user_id = ? AND event_id = ?");
        if ($stmt_check_duplicate) {
            $stmt_check_duplicate->bind_param("ii", $user_id_int, $event_id_int);
            $stmt_check_duplicate->execute();
            $stmt_check_duplicate->bind_result($duplicate_count);
            $stmt_check_duplicate->fetch();
            $stmt_check_duplicate->close();
            if ($duplicate_count > 0) {
                $response = ['success' => false, 'message' => 'This user is already signed up for this event.'];
                break;
            }
        } else {
            $response = ['success' => false, 'message' => 'Database error during duplicate sign-up check: ' . $conn->error];
            break;
        }

        $stmt = $conn->prepare("INSERT INTO signed_up_events (user_id, event_id, event_title, event_date, event_time, signup_date) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($stmt === false) {
            $response = ['success' => false, 'message' => 'Prepare failed: ' . $conn->error];
            break;
        }
        $stmt->bind_param("iisss", $user_id_int, $event_id_int, $event_title, $event_date, $event_time);

        if ($stmt->execute()) {
            $new_event_db_id = $stmt->insert_id;
            $fetch_stmt = $conn->prepare("SELECT id, user_id, event_id, event_title, event_date, event_time, signup_date FROM signed_up_events WHERE id = ?");
            $fetch_stmt->bind_param("i", $new_event_db_id);
            $fetch_stmt->execute();
            $result = $fetch_stmt->get_result();
            $new_event_data = $result->fetch_assoc();
            $fetch_stmt->close();

            $response = ['success' => true, 'message' => 'Event sign-up added successfully!', 'event' => $new_event_data];
        } else {
            $response = ['success' => false, 'message' => 'Error adding event sign-up: ' . $stmt->error];
        }
        $stmt->close();
        break;

    case 'edit':
        $id = $_POST['id'] ?? null; 
        if (!$id) {
            $response = ['success' => false, 'message' => 'Event sign-up ID is required for editing.'];
            break;
        }

        $update_fields = [];
        $update_params = [];
        $types = "";

        if (isset($_POST['user_id'])) { 
            $update_fields[] = "user_id = ?"; 
            $update_params[] = (int)$_POST['user_id']; 
            $types .= "i"; 
        }
        if (isset($_POST['event_id'])) { 
            $update_fields[] = "event_id = ?"; 
            $update_params[] = (int)$_POST['event_id']; 
            $types .= "i"; 
        }
        if (isset($_POST['event_title'])) { $update_fields[] = "event_title = ?"; $update_params[] = $_POST['event_title']; $types .= "s"; }
        if (isset($_POST['event_date'])) { $update_fields[] = "event_date = ?"; $update_params[] = $_POST['event_date']; $types .= "s"; }
        if (isset($_POST['event_time'])) { $update_fields[] = "event_time = ?"; $update_params[] = $_POST['event_time']; $types .= "s"; }

        if (empty($update_fields)) {
            $response = ['success' => false, 'message' => 'No fields to update.'];
            break;
        }

        $sql = "UPDATE signed_up_events SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            $response = ['success' => false, 'message' => 'Prepare failed: ' . $conn->error];
            break;
        }

        $update_params[] = $id;
        $types .= "i";

        $stmt->bind_param($types, ...$update_params);

        if ($stmt->execute()) {
            $fetch_stmt = $conn->prepare("SELECT id, user_id, event_id, event_title, event_date, event_time, signup_date FROM signed_up_events WHERE id = ?");
            $fetch_stmt->bind_param("i", $id);
            $fetch_stmt->execute();
            $result = $fetch_stmt->get_result();
            $updated_event_data = $result->fetch_assoc();
            $fetch_stmt->close();

            $response = ['success' => true, 'message' => 'Event sign-up updated successfully!', 'event' => $updated_event_data];
        } else {
            $response = ['success' => false, 'message' => 'Error updating event sign-up: ' . $stmt->error];
        }
        $stmt->close();
        break;

    case 'delete':
        $id = $_POST['id'] ?? null; 
        if (!$id) {
            $response = ['success' => false, 'message' => 'Event sign-up ID is required for deletion.'];
            break;
        }

        $stmt = $conn->prepare("DELETE FROM signed_up_events WHERE id = ?");
        if ($stmt === false) {
            $response = ['success' => false, 'message' => 'Prepare failed: ' . $conn->error];
            break;
        }
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response = ['success' => true, 'message' => 'Event sign-up deleted successfully!'];
            } else {
                $response = ['success' => false, 'message' => 'Event sign-up not found.'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Error deleting event sign-up: ' . $stmt->error];
        }
        $stmt->close();
        break;

    default:
        break;
}

$conn->close();
echo json_encode($response);
?>