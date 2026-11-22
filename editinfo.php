<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'database/db_connection.php'; 

$user_id = $_SESSION['user_id'];
$user_data = [];
$message = $_SESSION['message'] ?? '';
$msg_type = $_SESSION['msg_type'] ?? '';
unset($_SESSION['message']);
unset($_SESSION['msg_type']);


if ($user_id) {
    // Fetch user's current data using correct column names
    $stmt = $conn->prepare("SELECT username, email, name, address, contact, dob, gender, region_id, province_id, city_municipality_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    }
    $stmt->close();
}

require_once 'database/locations.php';
$regions = $conn->query('SELECT id, region_name, region_code FROM regions ORDER BY region_name ASC')->fetch_all(MYSQLI_ASSOC);
$regionCode = '';
foreach ($regions as $region) {
    if ($region['id'] == ($user_data['region_id'] ?? null)) $regionCode = $region['region_code'];
}
$isNcr = $regionCode === 'NCR';
$provinces = $isNcr ? [] : locationRows($conn, 'provinces', (int)($user_data['region_id'] ?? 0));
$cities_municipalities_data = locationRows($conn, $isNcr ? 'cities_by_region' : 'cities',
    (int)($user_data[$isNcr ? 'region_id' : 'province_id'] ?? 0));

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Edit Account Information</title>

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
                <form action="process_edit_info.php" method="post" class="form-box">
                    <h1>Edit Information</h1>
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($msg_type); ?>" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <div class="signup-columns">
                        <div class="left-column">
                            <label>Username</label>
                            <input type="text" name="username" placeholder="Enter your preferred username" value="<?php echo htmlspecialchars($user_data['username'] ?? ''); ?>" required>

                            <label>Name</label>
                            <input type="text" name="name" placeholder="e.g. Juan Dela Cruz" value="<?php echo htmlspecialchars($user_data['name'] ?? ''); ?>" required>

                            <label>Address</label>
                            <input type="text" name="address" placeholder="Street/Barangay" value="<?php echo htmlspecialchars($user_data['address'] ?? ''); ?>" required>

                            <label for="region">Region</label>
                            <select name="region_id" id="region" required>
                                <option value="">Select Region</option>
                                <?php foreach ($regions as $region): ?>
                                    <option value="<?php echo htmlspecialchars($region['id']); ?>" data-region-code="<?php echo htmlspecialchars($region['region_code']); ?>" <?php echo (isset($user_data['region_id']) && $user_data['region_id'] == $region['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($region['region_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="province">Province</label>
                            <select name="province_id" id="province" required <?php echo empty($provinces) ? 'disabled' : ''; ?>>
                                <?php if ($isNcr): ?>
                                    <option value="NA">Not Applicable</option>
                                <?php else: renderLocationOptions($provinces, 'provinces', $user_data['province_id'] ?? null); endif; ?>
                            </select>

                            <label for="city_municipality">City/Municipality</label>
                            <select name="city_municipality_id" id="city_municipality" required <?php echo empty($cities_municipalities_data) ? 'disabled' : ''; ?>>
                                <?php renderLocationOptions($cities_municipalities_data, 'cities', $user_data['city_municipality_id'] ?? null); ?>
                            </select>

                        </div>

                        <div class="right-column">
                            <label>Email Address</label>
                            <input type="email" name="email" placeholder="e.g. example@email.com" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                            
                            <label>Contact Number</label>
                            <input type="text" name="contact" placeholder="e.g. 09xx-xxx-xxxx" pattern="[0-9]+" value="<?php echo htmlspecialchars($user_data['contact'] ?? ''); ?>" required>

                            <label>Date of Birth</label>
                            <input type="date" name="dob" value="<?php echo htmlspecialchars($user_data['dob'] ?? ''); ?>" required class="uniform-input">

                            <label for="gender">Gender</label>
                            <select name="gender" id="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (isset($user_data['gender']) && $user_data['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($user_data['gender']) && $user_data['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Non-Binary" <?php echo (isset($user_data['gender']) && $user_data['gender'] == 'Non-Binary') ? 'selected' : ''; ?>>Non-Binary</option>
                            </select>

                            <label>New Password (leave blank if not changing)</label>
                            <input type="password" name="password" placeholder="Enter new password">

                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" placeholder="Re-enter new password">
                        </div>
                    </div>

                    <div class="form-footer">
                        <button type="submit">Save Changes</button>
                    </div>

                    <p class="text-link">Go back to <a href="user_management.php">Account Management</a></p>
                </form>
            </div>
        </main>
    </div>

    <?php include 'footer.php'; ?>

    <script src="js/locations.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>