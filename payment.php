<?php
session_start();
date_default_timezone_set('Asia/Manila');

require_once 'database/db_connection.php';
require_once __DIR__ . '/membership.php';
$plans = membershipPlans();
$paymentFields = paymentFieldRules();

// Redirect if user is not logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$currentUsername = $_SESSION['username'];
$currentUserId = $_SESSION['user_id'];

// Fetch user's current membership status from the database
$stmt = $conn->prepare("SELECT member_status FROM users WHERE id = ?");
if ($stmt === false) {
    error_log("Payment Page DB prepare error: " . $conn->error);
    $_SESSION['message'] = "Database error. Please try again later.";
    $_SESSION['msg_type'] = "danger";
    header("Location: main.php"); 
    exit();
}
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
$userDataFromDb = $result->fetch_assoc();
$stmt->close(); 

if (!$userDataFromDb) {
    $_SESSION['message'] = "User data not found for membership processing.";
    $_SESSION['msg_type'] = "danger";
    header("Location: login.php");
    exit();
}

// Check if the user is already an active member
if ($userDataFromDb['member_status'] === 'Active') {
    $_SESSION['message'] = "You are already an active member.";
    $_SESSION['msg_type'] = "info";
    header("Location: afterpayment.php"); // Redirect to membership info page
    exit();
}

// Display error messages from session
$paymentErrorMessages = $_SESSION['payment_error_messages'] ?? [];
unset($_SESSION['payment_error_messages']); // Clear them after display


// Handle form submission for membership
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedPlan = paymentScalar($_POST, 'selected_plan');
    $paymentMethod = paymentScalar($_POST, 'paymentMethod');
    $validationErrors = validateMembershipPayment($_POST);
    $isValid = empty($validationErrors);
    $startDate = date('Y-m-d');
    $amount = $plans[$selectedPlan]['amount'] ?? 0;
    $endDate = isset($plans[$selectedPlan]) ? date('Y-m-d', strtotime($plans[$selectedPlan]['duration'])) : null;

    if ($isValid) {
        // Generate a unique transaction ID
        $transactionId = uniqid('txn_');

        // Update membership status in the database using the existing 'member_status' column
        $updateStmt = $conn->prepare("UPDATE users SET
            member_status = 'Active',
            membership_plan = ?,
            membership_amount_paid = ?,
            membership_start_date = ?,
            membership_end_date = ?,
            membership_payment_method = ?,
            membership_transaction_id = ?
            WHERE id = ?"); // Ensure 'id' matches your primary key column name

        if ($updateStmt === false) {
            error_log("Payment Page DB update prepare error: " . $conn->error);
            $_SESSION['payment_error_messages'][] = "An internal error occurred during payment processing (statement prepare).";
            header("Location: payment.php");
            exit();
        }

        // 's' for string, 'd' for double (decimal), 's' for date (string), 's' for date (string), 's' for string, 's' for string, 'i' for integer
        $updateStmt->bind_param("sdssssi",
            $selectedPlan,
            $amount,
            $startDate,
            $endDate,
            $paymentMethod,
            $transactionId,
            $currentUserId
        );

        if ($updateStmt->execute()) {
            // Update the user's session data
            $_SESSION['users'][$currentUsername]['membership'] = [
                'active' => true,
                'plan' => $selectedPlan,
                'amount_paid' => $amount,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'payment_method' => $paymentMethod,
                'transaction_id' => $transactionId
            ];
            $_SESSION['member_status'] = 'Active'; 

            unset($_SESSION['order_details']);

            $_SESSION['payment_success_message'] = 'Congratulations! Your membership is now active.';

            $updateStmt->close(); 
            header("Location: afterpayment.php");
            exit();

        } else {
            error_log("Database update error on payment: " . $updateStmt->error);
            $_SESSION['payment_error_messages'][] = "An internal error occurred. Please try again later.";
            $updateStmt->close(); 
        }

    } else {
        $paymentErrorMessages = $validationErrors;
    }
}
$chosenPlan = paymentScalar($_POST, 'selected_plan');
$chosenMethod = paymentScalar($_POST, 'paymentMethod');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Arcane Membership</title>

    <link rel="icon" type="image/x-xicon" href="assets/arcane.ico" />

    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>

    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700" rel="stylesheet" />

    <link href="css/styles.css" rel="stylesheet" />
    <link href="css/login_style.css" rel="stylesheet" />
    
    <!-- CSS for the loading animation -->
    <style>
        .payment-button-container button {
            position: relative;
        }
        .button--loading .button-text {
            visibility: hidden;
            opacity: 0;
        }
        .button--loading::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            margin: auto;
            border: 4px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: button-loading-spinner 1s ease infinite;
        }
        @keyframes button-loading-spinner {
            from {
                transform: rotate(0turn);
            }
            to {
                transform: rotate(1turn);
            }
        }
    </style>

</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="page-wrapper">
        <main class="main-content">
            <div class="login-container">
                <h1>Become a member</h1>

                <?php if (!empty($paymentErrorMessages)): ?>
                    <ul class="validation-error-message">
                        <?php foreach ($paymentErrorMessages as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form id="membershipForm" method="POST">
                    <input type="hidden" id="selected_plan" name="selected_plan" value="<?= isset($plans[$chosenPlan]) ? htmlspecialchars($chosenPlan) : '' ?>">

                    <div class="membership-options-container">
                        <?php foreach ($plans as $planId => $plan): ?>
                        <div class="membership-plan" id="<?= htmlspecialchars($planId) ?>Plan">
                            <div>
                                <h2>P <?= (int)$plan['amount'] ?></h2>
                                <p><?= htmlspecialchars($plan['billing']) ?></p>
                                <ul class="features">
                                    <?php foreach ($plan['features'] as $feature): ?>
                                    <li><?= htmlspecialchars($feature) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <button type="button" class="select-button<?= $chosenPlan === $planId ? ' selected' : '' ?>" data-plan="<?= htmlspecialchars($planId) ?>" aria-pressed="<?= $chosenPlan === $planId ? 'true' : 'false' ?>">Select</button>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <h2 style="margin-top: 40px; text-align: center;">Payment Options</h2>
                    <div class="input-group">
                        <label for="paymentMethod">Select Payment Method:</label>
                        <select id="paymentMethod" name="paymentMethod" required>
                            <option value="">-- Select --</option>
                            <option value="credit_debit" <?= $chosenMethod === 'credit_debit' ? 'selected' : '' ?>>Credit/Debit Card</option>
                            <option value="gcash" <?= $chosenMethod === 'gcash' ? 'selected' : '' ?>>GCash</option>
                        </select>
                        <div id="paymentMethodError" class="error-message"></div>
                    </div>

                    <div id="creditCardDetails" data-payment-method="credit_debit" style="display: none;">
                        <div class="input-group">
                            <label for="cardName">Card Name:</label>
                            <input <?= paymentInputAttributes('cardName', $paymentFields['credit_debit']['cardName']) ?> placeholder="Name on Card">
                            <div id="cardNameError" class="error-message" aria-live="polite"></div>
                        </div>
                        <div class="input-group">
                            <label for="cardNumber">Card Number:</label>
                            <input <?= paymentInputAttributes('cardNumber', $paymentFields['credit_debit']['cardNumber']) ?> placeholder="Enter card number" inputmode="numeric">
                            <div id="cardNumberError" class="error-message" aria-live="polite"></div>
                        </div>
                        <div class="card-details-row" style="display:flex; gap:15px;">
                            <div class="input-group" style="flex:1;">
                                <label for="expiryDate">Expiry Date (MM/YY):</label>
                                <input <?= paymentInputAttributes('expiryDate', $paymentFields['credit_debit']['expiryDate']) ?> placeholder="MM/YY" inputmode="numeric">
                                <div id="expiryDateError" class="error-message" aria-live="polite"></div>
                            </div>
                            <div class="input-group" style="flex:1;">
                                <label for="cvv">CVV:</label>
                                <input <?= paymentInputAttributes('cvv', $paymentFields['credit_debit']['cvv']) ?> placeholder="CVV" inputmode="numeric">
                                <div id="cvvError" class="error-message" aria-live="polite"></div>
                            </div>
                        </div>
                    </div>

                    <div id="gcashDetails" data-payment-method="gcash" style="display: none;">
                        <div class="input-group">
                            <label for="gcashPhoneNumber">Mobile Number:</label>
                            <div class="gcash-mobile-input-group">
                                <span>+63</span>
                                <input <?= paymentInputAttributes('gcashPhoneNumber', $paymentFields['gcash']['gcashPhoneNumber']) ?> placeholder="e.g., 9171234567" inputmode="tel">
                            </div>
                            <div id="gcashPhoneNumberError" class="error-message" aria-live="polite"></div>
                        </div>
                    </div>

                    <div class="payment-button-container">
                        <!-- Modified Button -->
                        <button type="submit" id="payNowButton" disabled>
                            <span class="button-text">Pay Now</span>
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <?php include 'footer.php'; ?>

    <script src="js/payment.js" defer></script>
</body>
</html>