<?php
session_start();
header('Content-Type: application/json');

require_once '../database/db_connection.php'; 

// Check if the user logged in is an admin
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
        // Collect all fields from POST based on the user table structure
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $name = $_POST['name'] ?? '';
        $address = $_POST['address'] ?? '';
        $region_id = $_POST['region_id'] ?? null;
        $province_id = $_POST['province_id'] ?? null;
        $city_municipality_id = $_POST['city_municipality_id'] ?? null;
        $contact = $_POST['contact'] ?? '';
        $dob = $_POST['dob'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $member_status = $_POST['member_status'] ?? 'Pending';
        $membership_plan = $_POST['membership_plan'] ?? null;
        $membership_amount_paid = $_POST['membership_amount_paid'] ?? 0.00;
        $membership_start_date = $_POST['membership_start_date'] ?? null;
        $membership_end_date = $_POST['membership_end_date'] ?? null;
        $membership_payment_method = $_POST['membership_payment_method'] ?? null;
        $membership_transaction_id = $_POST['membership_transaction_id'] ?? null;

        $errors = [];
        if (empty($username) || empty($email) || empty($name) || empty($address) || empty($contact) || empty($dob) || empty($gender)) {
            $errors[] = "Username, Email, Name, Address, Contact, DOB, and Gender are required.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
        // Contact number validation
        if (!preg_match('/^\d{10,11}$/', $contact)) {
            $errors[] = "Contact number must be 10-11 digits.";
        }
        // Check for existing username/email to prevent duplicates
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("ss", $username, $email);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors[] = "Username or Email already exists.";
            }
            $stmt_check->close();
        } else {
            $errors[] = "Database error during uniqueness check: " . $conn->error;
        }

        if (!empty($errors)) {
            $response = ['success' => false, 'message' => implode("<br>", $errors)];
            break;
        }

        $default_hashed_password = password_hash("DefaultPass123", PASSWORD_DEFAULT); 

        $region_id = ($region_id === 'NULL' || empty($region_id)) ? NULL : (int)$region_id;
        $province_id = ($province_id === 'NULL' || empty($province_id)) ? NULL : (int)$province_id;
        $city_municipality_id = ($city_municipality_id === 'NULL' || empty($city_municipality_id)) ? NULL : (int)$city_municipality_id;
        $membership_plan = ($membership_plan === 'NULL' || empty($membership_plan)) ? NULL : $membership_plan;
        $membership_start_date = ($membership_start_date === 'NULL' || empty($membership_start_date)) ? NULL : $membership_start_date;
        $membership_end_date = ($membership_end_date === 'NULL' || empty($membership_end_date)) ? NULL : $membership_end_date;
        $membership_payment_method = ($membership_payment_method === 'NULL' || empty($membership_payment_method)) ? NULL : $membership_payment_method;
        $membership_transaction_id = ($membership_transaction_id === 'NULL' || empty($membership_transaction_id)) ? NULL : $membership_transaction_id;


        $stmt = $conn->prepare("INSERT INTO users (username, password, name, address, region_id, province_id, city_municipality_id, email, contact, dob, gender, member_status, created_at, membership_plan, membership_amount_paid, membership_start_date, membership_end_date, membership_payment_method, membership_transaction_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            $response = ['success' => false, 'message' => 'Prepare failed: ' . $conn->error];
            break;
        }
        // bind parameter (s=string, i=integer), ssssiiissssisdssss
        $stmt->bind_param("ssssiiissssisdssss",
            $username, $default_hashed_password, $name, $address, $region_id, $province_id, $city_municipality_id,
            $email, $contact, $dob, $gender, $member_status, $membership_plan, $membership_amount_paid,
            $membership_start_date, $membership_end_date, $membership_payment_method, $membership_transaction_id
        );

        if ($stmt->execute()) {
            $new_user_id = $stmt->insert_id;
            // Fetch added user with display names for location to send back to client
            $fetch_stmt = $conn->prepare("
                SELECT 
                    u.id, u.username, u.name, u.address, 
                    u.region_id, r.region_name AS region_name_display,
                    u.province_id, p.province_name AS province_name_display,
                    u.city_municipality_id, cm.city_municipality_name AS city_municipality_name_display,
                    u.email, u.contact, u.dob, u.gender, u.member_status, u.created_at,
                    u.membership_plan, u.membership_amount_paid, u.membership_start_date, u.membership_end_date,
                    u.membership_payment_method, u.membership_transaction_id
                FROM users u
                LEFT JOIN regions r ON u.region_id = r.id
                LEFT JOIN provinces p ON u.province_id = p.id
                LEFT JOIN cities_municipalities cm ON u.city_municipality_id = cm.id
                WHERE u.id = ?
            ");
            $fetch_stmt->bind_param("i", $new_user_id);
            $fetch_stmt->execute();
            $result = $fetch_stmt->get_result();
            $new_user_data = $result->fetch_assoc();
            $fetch_stmt->close();

            // Adjust display names to 'N/A' if null
            $new_user_data['region_name_display'] = $new_user_data['region_name_display'] ?? 'N/A';
            $new_user_data['province_name_display'] = $new_user_data['province_name_display'] ?? 'N/A';
            $new_user_data['city_municipality_name_display'] = $new_user_data['city_municipality_name_display'] ?? 'N/A';

            $response_user = [
                'id' => $new_user_data['id'],
                'username' => $new_user_data['username'],
                'email' => $new_user_data['email'],
                'name' => $new_user_data['name'],
                'address' => $new_user_data['address'],
                'region_id' => $new_user_data['region_id'],
                'region' => $new_user_data['region_name_display'], 
                'province_id' => $new_user_data['province_id'],
                'province' => $new_user_data['province_name_display'], 
                'city_municipality_id' => $new_user_data['city_municipality_id'],
                'city_municipality' => $new_user_data['city_municipality_name_display'], 
                'contact' => $new_user_data['contact'],
                'dob' => $new_user_data['dob'],
                'gender' => $new_user_data['gender'],
                'member_status' => $new_user_data['member_status'],
                'created_at' => $new_user_data['created_at'],
                'membership_plan' => $new_user_data['membership_plan'],
                'membership_amount_paid' => $new_user_data['membership_amount_paid'],
                'membership_start_date' => $new_user_data['membership_start_date'],
                'membership_end_date' => $new_user_data['membership_end_date'],
                'membership_payment_method' => $new_user_data['membership_payment_method'],
                'membership_transaction_id' => $new_user_data['membership_transaction_id']
            ];


            $response = ['success' => true, 'message' => 'User added successfully!', 'user' => $response_user];
        } else {
            $response = ['success' => false, 'message' => 'Error adding user: ' . $stmt->error];
        }
        $stmt->close();
        break;

    case 'edit':
        $id = $_POST['id'] ?? null;
        if (!$id) {
            $response = ['success' => false, 'message' => 'User ID is required for editing.'];
            break;
        }

        $update_fields = [];
        $update_params = [];
        $types = "";

        // Update query based on provided fields in $_POST
        if (isset($_POST['username'])) { $update_fields[] = "username = ?"; $update_params[] = $_POST['username']; $types .= "s"; }
        if (isset($_POST['email'])) { $update_fields[] = "email = ?"; $update_params[] = $_POST['email']; $types .= "s"; }
        if (isset($_POST['name'])) { $update_fields[] = "name = ?"; $update_params[] = $_POST['name']; $types .= "s"; }
        if (isset($_POST['address'])) { $update_fields[] = "address = ?"; $update_params[] = $_POST['address']; $types .= "s"; }
        
        // Handle nullable integer IDs for location fields
        if (isset($_POST['region_id'])) { 
            $val = ($_POST['region_id'] === 'NULL' || empty($_POST['region_id'])) ? NULL : (int)$_POST['region_id'];
            $update_fields[] = "region_id = ?"; $update_params[] = $val; $types .= "i"; 
        }
        if (isset($_POST['province_id'])) { 
            $val = ($_POST['province_id'] === 'NULL' || empty($_POST['province_id'])) ? NULL : (int)$_POST['province_id'];
            $update_fields[] = "province_id = ?"; $update_params[] = $val; $types .= "i"; 
        }
        if (isset($_POST['city_municipality_id'])) { 
            $val = ($_POST['city_municipality_id'] === 'NULL' || empty($_POST['city_municipality_id'])) ? NULL : (int)$_POST['city_municipality_id'];
            $update_fields[] = "city_municipality_id = ?"; $update_params[] = $val; $types .= "i"; 
        }

        if (isset($_POST['contact'])) { $update_fields[] = "contact = ?"; $update_params[] = $_POST['contact']; $types .= "s"; }
        if (isset($_POST['dob'])) { $update_fields[] = "dob = ?"; $update_params[] = $_POST['dob']; $types .= "s"; }
        if (isset($_POST['gender'])) { $update_fields[] = "gender = ?"; $update_params[] = $_POST['gender']; $types .= "s"; }
        if (isset($_POST['member_status'])) { $update_fields[] = "member_status = ?"; $update_params[] = $_POST['member_status']; $types .= "s"; }
        if (isset($_POST['membership_plan'])) { $update_fields[] = "membership_plan = ?"; $update_params[] = $_POST['membership_plan']; $types .= "s"; }
        if (isset($_POST['membership_amount_paid'])) { $update_fields[] = "membership_amount_paid = ?"; $update_params[] = $_POST['membership_amount_paid']; $types .= "d"; }
        if (isset($_POST['membership_start_date'])) { $update_fields[] = "membership_start_date = ?"; $update_params[] = $_POST['membership_start_date']; $types .= "s"; }
        if (isset($_POST['membership_end_date'])) { $update_fields[] = "membership_end_date = ?"; $update_params[] = $_POST['membership_end_date']; $types .= "s"; }
        if (isset($_POST['membership_payment_method'])) { $update_fields[] = "membership_payment_method = ?"; $update_params[] = $_POST['membership_payment_method']; $types .= "s"; }
        if (isset($_POST['membership_transaction_id'])) { $update_fields[] = "membership_transaction_id = ?"; $update_params[] = $_POST['membership_transaction_id']; $types .= "s"; }
        // 'password' and 'created_at' cannot be edited via admin tools.

        if (empty($update_fields)) {
            $response = ['success' => false, 'message' => 'No fields to update.'];
            break;
        }

        $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            $response = ['success' => false, 'message' => 'Prepare failed: ' . $conn->error];
            break;
        }

        $update_params[] = $id;
        $types .= "i";

        $stmt->bind_param($types, ...$update_params);

        if ($stmt->execute()) {
            // Fetch updated user data with display names for location to send back to client
            $fetch_stmt = $conn->prepare("
                SELECT 
                    u.id, u.username, u.name, u.address, 
                    u.region_id, r.region_name AS region_name_display,
                    u.province_id, p.province_name AS province_name_display,
                    u.city_municipality_id, cm.city_municipality_name AS city_municipality_name_display,
                    u.email, u.contact, u.dob, u.gender, u.member_status, u.created_at,
                    u.membership_plan, u.membership_amount_paid, u.membership_start_date, u.membership_end_date,
                    u.membership_payment_method, u.membership_transaction_id
                FROM users u
                LEFT JOIN regions r ON u.region_id = r.id
                LEFT JOIN provinces p ON u.province_id = p.id
                LEFT JOIN cities_municipalities cm ON u.city_municipality_id = cm.id
                WHERE u.id = ?
            ");
            $fetch_stmt->bind_param("i", $id);
            $fetch_stmt->execute();
            $result = $fetch_stmt->get_result();
            $updated_user_data = $result->fetch_assoc();
            $fetch_stmt->close();

            // Adjust display names to 'N/A' if null
            $updated_user_data['region_name_display'] = $updated_user_data['region_name_display'] ?? 'N/A';
            $updated_user_data['province_name_display'] = $updated_user_data['province_name_display'] ?? 'N/A';
            $updated_user_data['city_municipality_name_display'] = $updated_user_data['city_municipality_name_display'] ?? 'N/A';
            
            $response_user = [
                'id' => $updated_user_data['id'],
                'username' => $updated_user_data['username'],
                'email' => $updated_user_data['email'],
                'name' => $updated_user_data['name'],
                'address' => $updated_user_data['address'],
                'region_id' => $updated_user_data['region_id'],
                'region' => $updated_user_data['region_name_display'], // Display name
                'province_id' => $updated_user_data['province_id'],
                'province' => $updated_user_data['province_name_display'], // Display name
                'city_municipality_id' => $updated_user_data['city_municipality_id'],
                'city_municipality' => $updated_user_data['city_municipality_name_display'], // Display name
                'contact' => $updated_user_data['contact'],
                'dob' => $updated_user_data['dob'],
                'gender' => $updated_user_data['gender'],
                'member_status' => $updated_user_data['member_status'],
                'created_at' => $updated_user_data['created_at'],
                'membership_plan' => $updated_user_data['membership_plan'],
                'membership_amount_paid' => $updated_user_data['membership_amount_paid'],
                'membership_start_date' => $updated_user_data['membership_start_date'],
                'membership_end_date' => $updated_user_data['membership_end_date'],
                'membership_payment_method' => $updated_user_data['membership_payment_method'],
                'membership_transaction_id' => $updated_user_data['membership_transaction_id']
            ];

            $response = ['success' => true, 'message' => 'User updated successfully!', 'user' => $response_user];
        } else {
            $response = ['success' => false, 'message' => 'Error updating user: ' . $stmt->error];
        }
        $stmt->close();
        break;

    case 'delete':
        $id = $_POST['id'] ?? null;
        if (!$id) {
            $response = ['success' => false, 'message' => 'User ID is required for deletion.'];
            break;
        }

        // Prevent admin from deleting themselves
        if ($id == $_SESSION['user_id'] && $_SESSION['username'] === 'admin') {
            $response = ['success' => false, 'message' => 'Cannot delete the currently logged-in admin user.'];
            break;
        }

        $conn->begin_transaction();
        try {
            $stmt_events = $conn->prepare("DELETE FROM signed_up_events WHERE user_id = ?");
            if ($stmt_events === false) {
                throw new Exception('Prepare events delete failed: ' . $conn->error);
            }
            $stmt_events->bind_param("i", $id);
            $stmt_events->execute();
            $stmt_events->close();

            $stmt_user = $conn->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt_user === false) {
                throw new Exception('Prepare user delete failed: ' . $conn->error);
            }
            $stmt_user->bind_param("i", $id);
            if ($stmt_user->execute()) {
                if ($stmt_user->affected_rows > 0) {
                    $response = ['success' => true, 'message' => 'User and associated events deleted successfully!'];
                    $conn->commit(); // Commit the transaction if both operations succeed
                } else {
                    $response = ['success' => false, 'message' => 'User not found.'];
                    $conn->rollback(); // Rollback if user not found (nothing to delete)
                }
            } else {
                throw new Exception('Error deleting user: ' . $stmt_user->error);
            }
            $stmt_user->close();
        } catch (Exception $e) {
            $conn->rollback(); // Rollback on any exception
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
        break;

    default:
        break;
}

$conn->close();
echo json_encode($response);
?>