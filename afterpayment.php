<?php
session_start();
date_default_timezone_set('Asia/Manila');

require_once 'database/db_connection.php'; 

// Redirect if user is not logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$currentUsername = $_SESSION['username'];
$currentUserId = $_SESSION['user_id'];

// Fetch user's details and membership status from the database
$stmt = $conn->prepare("SELECT
    u.name,
    u.address,
    r.region_name,
    p.province_name,
    cm.city_municipality_name,
    u.contact,
    u.member_status,
    u.membership_plan,
    u.membership_amount_paid,
    u.membership_start_date,
    u.membership_end_date,
    u.membership_payment_method,
    u.membership_transaction_id
FROM users u
LEFT JOIN regions r ON u.region_id = r.id
LEFT JOIN provinces p ON u.province_id = p.id
LEFT JOIN cities_municipalities cm ON u.city_municipality_id = cm.id
WHERE u.id = ?"); // Ensure 'id' matches your primary key column name

if ($stmt === false) {
    error_log("AfterPayment Page DB prepare error: " . $conn->error);
    $_SESSION['message'] = "Database error. Please try again later.";
    $_SESSION['msg_type'] = "danger";
    header("Location: main.php");
    exit();
}
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
$userDataFromDb = $result->fetch_assoc();
$stmt->close(); // Close the statement

// If user data not found or membership is not active in DB, redirect
if (!$userDataFromDb || $userDataFromDb['member_status'] !== 'Active') {
    $_SESSION['message'] = "Your membership is not active or data not found.";
    $_SESSION['msg_type'] = "info";
    header("Location: payment.php");
    exit();
}

// Get membership details from the database data
$plan = $userDataFromDb['membership_plan'] ?? 'N/A';
$amountPaid = $userDataFromDb['membership_amount_paid'] ?? 'N/A';
$startDate = $userDataFromDb['membership_start_date'] ?? 'N/A';
$endDate = $userDataFromDb['membership_end_date'] ?? 'N/A';
$paymentMethod = $userDataFromDb['membership_payment_method'] ?? 'N/A';
$transactionId = $userDataFromDb['membership_transaction_id'] ?? 'N/A';


// Format billing text
$billingText = '';
if ($plan === 'monthly') {
    $billingText = 'P 20 /month';
} elseif ($plan === 'annual') {
    $billingText = 'P 200 /year';
}

// Format start date and time
$formattedStartDate = 'N/A';
if ($startDate !== 'N/A') {
    $formattedStartDate = date('m/d/Y', strtotime($startDate));
}
$formattedEndDate = 'N/A';
if ($endDate !== 'N/A') {
    $formattedEndDate = date('m/d/Y', strtotime($endDate));
}


// Get user details from database
$username = $currentUsername;
$name = $userDataFromDb['name'] ?? 'N/A';

// Construct full address: <address>, <city/municipality>, <province>, <region>
$address_parts = [];
if (!empty($userDataFromDb['address'])) {
    $address_parts[] = htmlspecialchars($userDataFromDb['address']);
}
if (!empty($userDataFromDb['city_municipality_name'])) {
    $address_parts[] = htmlspecialchars($userDataFromDb['city_municipality_name']);
}
if (!empty($userDataFromDb['province_name'])) {
    $address_parts[] = htmlspecialchars($userDataFromDb['province_name']);
}
if (!empty($userDataFromDb['region_name'])) {
    $address_parts[] = htmlspecialchars($userDataFromDb['region_name']);
}
$fullAddress = implode(', ', $address_parts);
if (empty($fullAddress)) {
    $fullAddress = 'N/A';
}

$contact = $userDataFromDb['contact'] ?? 'N/A';

// Handle End Membership action
if (isset($_GET['action']) && $_GET['action'] === 'end_membership') {
    // Update membership status in the database to 'Inactive' and clear details
    $updateStmt = $conn->prepare("UPDATE users SET
        member_status = 'Inactive',
        membership_plan = NULL,
        membership_amount_paid = NULL,
        membership_start_date = NULL,
        membership_end_date = NULL,
        membership_payment_method = NULL,
        membership_transaction_id = NULL
        WHERE id = ?"); // Ensure 'id' matches your primary key column name

    if ($updateStmt === false) {
        error_log("End Membership DB prepare error: " . $conn->error);
        $_SESSION['message'] = "An error occurred while preparing to end your membership.";
        $_SESSION['msg_type'] = "danger";
        header("Location: afterpayment.php");
        exit();
    }
    $updateStmt->bind_param("i", $currentUserId);

    if ($updateStmt->execute()) {
        // Update session variables after successful database update
        $_SESSION['member_status'] = 'Not a member';
        unset($_SESSION['users'][$currentUsername]['membership']);

        $_SESSION['message'] = 'Your membership has been successfully ended.';
        $_SESSION['msg_type'] = 'info';
        $updateStmt->close();
        header("Location: main.php");
        exit();
    } else {
        error_log("Database update error ending membership: " . $updateStmt->error);
        $_SESSION['message'] = "An error occurred while ending your membership. Please try again.";
        $_SESSION['msg_type'] = "danger";
        $updateStmt->close();
        header("Location: afterpayment.php");
        exit();
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
    <title>Membership Confirmation</title>

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
                <h1>Club Membership Active</h1>
                <hr class="afterpayment-divider" />
                <div class="membership-details">
                    <div class="membership-details-item">
                        <span><strong>Billing:</strong></span>
                        <span><?php echo htmlspecialchars($billingText); ?></span>
                    </div>
                    <div class="membership-details-item">
                        <span><strong>Date of Membership:</strong></span>
                        <span><?php echo htmlspecialchars($formattedStartDate); ?></span>
                    </div>
                    <div class="membership-details-item">
                        <span><strong>Membership End Date:</strong></span>
                        <span><?php echo htmlspecialchars($formattedEndDate); ?></span>
                    </div>
                    <div class="membership-details-item">
                        <span><strong>Payment Option:</strong></span>
                        <span><?php echo htmlspecialchars($paymentMethod === 'credit_debit' ? 'Credit/Debit Card' : 'GCash'); ?></span>
                    </div>
                    <div class="membership-details-item">
                        <span><strong>Transaction ID:</strong></span>
                        <span><?php echo htmlspecialchars($transactionId); ?></span>
                    </div>
                    <div class="membership-details-item">
                        <span><strong>Membership Status:</strong></span>
                        <span>Active</span>
                    </div>
                    <div class="membership-details-item">
                        <span><strong>Name:</strong></span>
                        <span><?php echo htmlspecialchars($name); ?></span>
                    </div>
                    <div class="membership-details-item">
                        <span><strong>Username:</strong></span>
                        <span><?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <div class="membership-details-item">
                        <span><strong>Address:</strong></span>
                        <span><?php echo $fullAddress; ?></span>
                    </div>
                    <div class="membership-details-item">
                        <span><strong>Contact Number:</strong></span>
                        <span><?php echo htmlspecialchars($contact); ?></span>
                    </div>
                </div>

                <hr class="afterpayment-divider" />
                <div class="button-group">
                    <button type="button" class="close-button" onclick="window.location.href='main.php'">Close Window</button>
                    <button type="button" class="end-membership-button" onclick="confirmEndMembership()">End Membership</button>
                </div>
            </div>
        </main>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        function confirmEndMembership() {
            if (confirm("Are you sure you want to end your membership? This action cannot be undone.")) {
                window.location.href = "afterpayment.php?action=end_membership";
            }
        }
    </script>
</body>
</html>