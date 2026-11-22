<?php
session_start();
require_once 'database/db_connection.php'; 

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Please log in to edit your information.";
    $_SESSION['msg_type'] = "danger";
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $contact = trim($_POST['contact']); 
    $dob = trim($_POST['dob']);         
    $gender = trim($_POST['gender']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    $region_id = $_POST['region_id'] ?? null;
    $province_id = $_POST['province_id'] ?? null;
    $city_municipality_id = $_POST['city_municipality_id'] ?? null;

    $errors = [];

    // Basic validation
    if (empty($username) || empty($name) || empty($email) || empty($address) || empty($contact) || empty($dob) || empty($gender) || empty($region_id) || empty($city_municipality_id)) {
        $errors[] = "All required fields must be filled.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Password validation
    if (!empty($password)) {
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        }
        if ($password !== $confirm_password) {
            $errors[] = "New password and confirm password do not match.";
        }
    }

    // Check if username or email already exists for another user
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $stmt_check->bind_param("ssi", $username, $email, $user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows > 0) {
        $existing_user = $result_check->fetch_assoc();
        if ($existing_user['id'] != $user_id) { // Ensure it's not the current user's existing data
            $errors[] = "Username or email already taken by another account.";
        }
    }
    $stmt_check->close();

    if (empty($errors)) {
        $sql = "UPDATE users SET username = ?, name = ?, email = ?, address = ?, contact = ?, dob = ?, gender = ?, region_id = ?, province_id = ?, city_municipality_id = ?";
        $params = "ssssssiiii";
        $param_values = [$username, $name, $email, $address, $contact, $dob, $gender, $region_id, $province_id, $city_municipality_id];

        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql .= ", password = ?";
            $params .= "s";
            $param_values[] = $hashed_password;
        }

        $sql .= " WHERE id = ?";
        $params .= "i";
        $param_values[] = $user_id;

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param($params, ...$param_values);

            if ($stmt->execute()) {
                $_SESSION['message'] = "Account information updated successfully!";
                $_SESSION['msg_type'] = "success";

                // Update session variables if username or email changed
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;

            } else {
                $_SESSION['message'] = "Error updating account information: " . $stmt->error;
                $_SESSION['msg_type'] = "danger";
            }
            $stmt->close();
        } else {
            $_SESSION['message'] = "Database error: " . $conn->error;
            $_SESSION['msg_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = implode("<br>", $errors);
        $_SESSION['msg_type'] = "danger";
    }

    $conn->close();
    header("Location: editinfo.php"); 
    exit();
} else {
    header("Location: editinfo.php"); 
    exit();
}
?>