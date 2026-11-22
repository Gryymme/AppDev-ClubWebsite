<?php
session_start();
require_once 'database/db_connection.php'; 

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs from signup.php
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $region_id = $_POST['region_id'] ?? '';
    $city_municipality_id = $_POST['city_municipality_id'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $dob = $_POST['dob'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Handle province_id separately, it can be NULL or an integer
    $province_id = $_POST['province_id'] ?? null;
    if ($province_id === 'NA' || empty($province_id)) {
        $province_id = NULL;
    } else {
        $province_id = (int)$province_id;
    }

    require_once 'database/locations.php';
    if (!validLocation($conn, $region_id, $province_id, $city_municipality_id)) {
        $errors[] = 'Please select a city and province belonging to the selected region.';
    }

    // Basic Validation (re-validate server-side)
    if (empty($username) || empty($name) || empty($address) || empty($region_id) || empty($city_municipality_id) || empty($email) || empty($contact) || empty($dob) || empty($gender) || empty($password) || empty($confirm_password)) {
        $errors[] = "All fields are required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (!preg_match('/^\d{10,11}$/', $contact)) {
        $errors[] = "Contact number must be numeric and 10-11 digits long (e.g., 09xx-xxx-xxxx).";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check if username or email already exists in the database
    if (empty($errors)) {
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        if ($stmt_check === false) {
            $errors[] = "Database error during username/email check: " . $conn->error;
        } else {
            $stmt_check->bind_param("ss", $username, $email);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors[] = "Username or Email already exists.";
            }
            $stmt_check->close();
        }
    }

    if (empty($errors)) {
        // Custom User ID Generation & Uniqueness Check
        date_default_timezone_set('Asia/Manila');
        $userId = null;
        $idExists = true;
        $maxTries = 10; 
        $attempt = 0;

        do {
            // Get current date/time components
            $seconds = date('s'); // Seconds (0-59)
            $minutes = date('i'); // Minutes (00-59)
            $hour = date('H');    // Hour in 24-hour format (00-23)
            $day = date('d');     // Day of the month (01-31)

            // Generate a random number from 0 to 9
            $randomNumber = rand(0, 9);

            // Concatenate to form unique ID based on the new format:
            // ss (seconds) mm (minutes) HH (hour) dd (day) R (random)
            $generatedId = $seconds . $minutes . $hour . $day . $randomNumber;

            // Check if ID already exists in the database
            $stmt_id_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
            $stmt_id_check->bind_param("s", $generatedId);
            $stmt_id_check->execute();
            $stmt_id_check->store_result();
            
            if ($stmt_id_check->num_rows === 0) {
                // If the ID does not exist, id is unique
                $userId = $generatedId;
                $idExists = false;
            }
            $stmt_id_check->close();

            $attempt++;
        } while ($idExists && $attempt < $maxTries);

        if ($idExists) {
            $errors[] = "Error encountered when trying to create account. Please try again.";
        }
    }


    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (id, username, password, name, address, region_id, province_id, city_municipality_id, email, contact, dob, gender, member_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Inactive')");

        if ($stmt === false) {
            $errors[] = "Database error during registration: " . $conn->error;
        } else {
            $stmt->bind_param("sssssiiissss", $userId, $username, $hashed_password, $name, $address, $region_id, $province_id, $city_municipality_id, $email, $contact, $dob, $gender);

            if ($stmt->execute()) {
                $_SESSION['registration_success'] = true;
                $_SESSION['registration_data'] = [
                    'id' => $userId, 
                    'username' => $username,
                    'name' => $name,
                    'address' => $address,
                    'region_id' => $region_id,
                    'province_id' => $province_id,
                    'city_municipality_id' => $city_municipality_id,
                    'email' => $email,
                    'contact' => $contact,
                    'dob' => $dob,
                    'gender' => $gender,
                    'password' => $password, 
                ];
                // Redirect to self to show summary
                header("Location: registration_result.php");
                exit();
            } else {
                $errors[] = "Registration failed. Please try again. Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
} elseif (!isset($_SESSION['registration_success'])) {
    header("Location: signup.php");
    exit();
}

if (isset($conn) && $conn->ping()) {
    $conn->close();
}


// For displaying summary after successful registration
$display_data = [];
if (isset($_SESSION['registration_success']) && $_SESSION['registration_success'] === true && isset($_SESSION['registration_data'])) {
    $display_data = $_SESSION['registration_data'];

    // Reconnect to DB for fetching names for display
    require 'database/db_connection.php';

    $region_name = '';
    $stmt_region = $conn->prepare("SELECT region_name FROM regions WHERE id = ?");
    if ($stmt_region) {
        $stmt_region->bind_param("i", $display_data['region_id']);
        $stmt_region->execute();
        $stmt_region->bind_result($region_name);
        $stmt_region->fetch();
        $stmt_region->close();
    }
    $display_data['region_name'] = $region_name;

    $province_name = 'N/A';
    if (($display_data['province_id'] !== NULL) && ($display_data['province_id'] !== 'NA') && !empty($display_data['province_id'])) {
        $stmt_province = $conn->prepare("SELECT province_name FROM provinces WHERE id = ?");
        if ($stmt_province) {
            $stmt_province->bind_param("i", $display_data['province_id']);
            $stmt_province->execute();
            $stmt_province->bind_result($province_name);
            $stmt_province->fetch();
            $stmt_province->close();
        }
    }
    $display_data['province_name'] = $province_name;

    $city_municipality_name = '';
    $stmt_city = $conn->prepare("SELECT city_municipality_name FROM cities_municipalities WHERE id = ?");
    if ($stmt_city) {
        $stmt_city->bind_param("i", $display_data['city_municipality_id']);
        $stmt_city->execute();
        $stmt_city->bind_result($city_municipality_name);
        $stmt_city->fetch();
        $stmt_city->close();
    }
    $display_data['city_municipality_name'] = $city_municipality_name;

    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Registration Summary</title>

    <link rel="icon" type="image/x-xicon" href="assets/arcane.ico" />
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />
    <link href="css/login_style.css" rel="stylesheet" />
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="page-wrapper">
        <main class="main-content">

            <div class="login-container">
                <div class="form-box">
                    <h1>Registration Summary</h1>

                    <?php if (!empty($errors)): ?>
                        <div class="error">
                            <ul>
                                <?php foreach ($errors as $err): ?>
                                    <li><?= htmlspecialchars($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <p><a href="signup.php">Go back to fix</a></p>
                        </div>
                    <?php elseif (isset($_SESSION['registration_success']) && $_SESSION['registration_success'] === true): ?>
                        <p>Your account has been successfully registered!</p>
                        <ul>
                            <!-- Display the new User ID -->
                            <li><strong>User ID:</strong> <?= htmlspecialchars($display_data['id'] ?? '') ?></li>
                            <li><strong>Username:</strong> <?= htmlspecialchars($display_data['username'] ?? '') ?></li>
                            <li><strong>Name:</strong> <?= htmlspecialchars($display_data['name'] ?? '') ?></li>
                            <li><strong>Address:</strong> <?= htmlspecialchars($display_data['address'] ?? '') ?></li>
                            <li><strong>Region:</strong> <?= htmlspecialchars($display_data['region_name'] ?? '') ?></li>
                            <li><strong>Province:</strong> <?= htmlspecialchars($display_data['province_name'] ?? '') ?></li>
                            <li><strong>City/Municipality:</strong> <?= htmlspecialchars($display_data['city_municipality_name'] ?? '') ?></li>
                            <li><strong>Email:</strong> <?= htmlspecialchars($display_data['email'] ?? '') ?></li>
                            <li><strong>Contact:</strong> <?= htmlspecialchars($display_data['contact'] ?? '') ?></li>
                            <li><strong>Date of Birth:</strong> <?= htmlspecialchars($display_data['dob'] ?? '') ?></li>
                            <li><strong>Gender:</strong> <?= htmlspecialchars($display_data['gender'] ?? '') ?></li>
                            <li><strong>Password:</strong> <?= str_repeat('*', strlen($display_data['password'] ?? '')) ?></li>
                        </ul>

                        <form method="post" action="login.php">
                            <div class="form-footer">
                                <button type="submit">Go to Login</button>
                            </div>
                        </form>
                        <?php
                            // Clear registration data from session after display
                            unset($_SESSION['registration_success']);
                            unset($_SESSION['registration_data']);
                        ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>