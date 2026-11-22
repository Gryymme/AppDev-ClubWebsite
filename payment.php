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
    $selectedPlan = $_POST['selected_plan'] ?? null;
    $paymentMethod = $_POST['paymentMethod'] ?? null;
    $isValid = true;
    $validationErrors = [];

    // Basic validation for selected plan
    if (!in_array($selectedPlan, ['monthly', 'annual'])) {
        $validationErrors[] = "Please select a membership plan.";
        $isValid = false;
    }

    // Basic validation for payment method
    if (!in_array($paymentMethod, ['credit_debit', 'gcash'])) {
        $validationErrors[] = "Please select a valid payment method.";
        $isValid = false;
    }

    $amount = 0;
    $endDate = null;
    $startDate = date('Y-m-d'); // Payment date

    if ($selectedPlan === 'monthly') {
        $amount = 20;
        $endDate = date('Y-m-d', strtotime('+1 month'));
    } elseif ($selectedPlan === 'annual') {
        $amount = 200;
        $endDate = date('Y-m-d', strtotime('+1 year'));
    }

    // Validate payment details based on method
    if ($paymentMethod === 'credit_debit') {
        $cardName = trim($_POST['cardName'] ?? '');
        $cardNumber = trim(str_replace(' ', '', $_POST['cardNumber'] ?? ''));
        $expiryDate = trim($_POST['expiryDate'] ?? '');
        $cvv = trim($_POST['cvv'] ?? '');

        if (empty($cardName) || !preg_match('/^[a-zA-Z\s.-]+$/', $cardName)) {
            $validationErrors[] = "Card Name must contain only letters, spaces, hyphens, and periods.";
            $isValid = false;
        }
        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19 || !ctype_digit($cardNumber)) {
            $validationErrors[] = "Invalid Card Number length (13-19 digits) or characters.";
            $isValid = false;
        }
        if (!preg_match('/^(0[1-9]|1[0-2])\/?([0-9]{2})$/', $expiryDate, $matches)) {
            $validationErrors[] = "Invalid Expiry Date format (MM/YY).";
            $isValid = false;
        } else {
            $expMonth = (int)$matches[1];
            $expYear = (int)$matches[2];
            $currentYear = (int)date('y');
            $currentMonth = (int)date('m');

            if ($expYear < $currentYear || ($expYear === $currentYear && $expMonth < $currentMonth)) {
                $validationErrors[] = "Credit Card has expired.";
                $isValid = false;
            }
        }
        if (!ctype_digit($cvv) || (strlen($cvv) !== 3 && strlen($cvv) !== 4)) {
            $validationErrors[] = "CVV must be 3 or 4 digits.";
            $isValid = false;
        }

    } elseif ($paymentMethod === 'gcash') {
        $mobileNumber = trim($_POST['gcashPhoneNumber'] ?? '');
        if (!ctype_digit($mobileNumber) || (strlen($mobileNumber) !== 10 && strlen($mobileNumber) !== 11)) {
            $validationErrors[] = "Mobile Number must be 10 or 11 digits (numerical only).";
            $isValid = false;
        }
    }

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
        $_SESSION['payment_error_messages'] = $validationErrors;
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
                    <input type="hidden" id="selected_plan" name="selected_plan" value="">

                    <div class="membership-options-container">
                        <div class="membership-plan" id="monthlyPlan">
                            <div>
                                <h2>P 20</h2>
                                <p>Monthly Billing</p>
                                <ul class="features">
                                    <li>First month free</li>
                                    <li>Access to Club Newsletter</li>
                                    <li>Access to Exclusive Events</li>
                                </ul>
                            </div>
                            <button type="button" class="select-button" data-plan="monthly" onclick="selectMembershipPlan('monthly')">Select</button>
                        </div>

                        <div class="membership-plan" id="annualPlan">
                            <div>
                                <h2>P 200</h2>
                                <p>Annual Billing</p>
                                <ul class="features">
                                    <li>Access to Club Newsletter</li>
                                    <li>Access to Exclusive Events</li>
                                </ul>
                            </div>
                            <button type="button" class="select-button" data-plan="annual" onclick="selectMembershipPlan('annual')">Select</button>
                        </div>
                    </div>

                    <h2 style="margin-top: 40px; text-align: center;">Payment Options</h2>
                    <div class="input-group">
                        <label for="paymentMethod">Select Payment Method:</label>
                        <select id="paymentMethod" name="paymentMethod" onchange="togglePaymentFields(); validateForm()">
                            <option value="">-- Select --</option>
                            <option value="credit_debit">Credit/Debit Card</option>
                            <option value="gcash">GCash</option>
                        </select>
                        <div id="paymentMethodError" class="error-message"></div>
                    </div>

                    <div id="creditCardDetails" style="display: none;">
                        <div class="input-group">
                            <label for="cardName">Card Name:</label>
                            <input type="text" id="cardName" name="cardName" placeholder="Name on Card" oninput="this.value = this.value.replace(/[^a-zA-Z\s.-]/g, ''); validateCardName(); validateForm()">
                            <div id="cardNameError" class="error-message"></div>
                        </div>
                        <div class="input-group">
                            <label for="cardNumber">Card Number:</label>
                            <input type="text" id="cardNumber" name="cardNumber" placeholder="Enter card number" pattern="[0-9]*" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, ''); validateCardNumber(); validateForm()" maxlength="19">
                            <div id="cardNumberError" class="error-message"></div>
                        </div>
                        <div class="card-details-row" style="display:flex; gap:15px;">
                            <div class="input-group" style="flex:1;">
                                <label for="expiryDate">Expiry Date (MM/YY):</label>
                                <input type="text" id="expiryDate" name="expiryDate" placeholder="MM/YY" pattern="[0-9/]*" inputmode="numeric" oninput="formatAndValidateExpiryDate(this); validateForm()" maxlength="5">
                                <div id="expiryDateError" class="error-message"></div>
                            </div>
                            <div class="input-group" style="flex:1;">
                                <label for="cvv">CVV:</label>
                                <input type="text" id="cvv" name="cvv" placeholder="CVV" pattern="[0-9]*" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4); validateCVV(); validateForm()" maxlength="4">
                                <div id="cvvError" class="error-message"></div>
                            </div>
                        </div>
                    </div>

                    <div id="gcashDetails" style="display: none;">
                        <div class="input-group">
                            <label for="gcashPhoneNumber">Mobile Number:</label>
                            <div class="gcash-mobile-input-group">
                                <span>+63</span>
                                <input type="text" id="gcashPhoneNumber" name="gcashPhoneNumber" placeholder="e.g., 9171234567" pattern="[0-9]*" inputmode="tel" oninput="this.value = this.value.replace(/[^0-9]/g, ''); validateGCashPhoneNumber(); validateForm()" maxlength="11">
                            </div>
                            <div id="gcashPhoneNumberError" class="error-message"></div>
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

    <script>
        let selectedPlan = null; // Store the selected plan type

        document.addEventListener('DOMContentLoaded', function() {
            // Restore selected plan from hidden input if form was submitted with errors
            const hiddenPlanInput = document.getElementById('selected_plan');
            if (hiddenPlanInput.value) {
                selectedPlan = hiddenPlanInput.value;
                const selectedButton = document.querySelector(`.select-button[data-plan="${selectedPlan}"]`);
                if (selectedButton) {
                    selectedButton.classList.add('selected');
                }
            }

            togglePaymentFields(); // Initialize visibility on page load
            validateForm(); // Initial validation for button state
        });

        function selectMembershipPlan(planType) {
            selectedPlan = planType;

            // Remove 'selected' class from all buttons
            document.querySelectorAll('.select-button').forEach(button => {
                button.classList.remove('selected');
            });

            // Add 'selected' class to the clicked button
            const selectedButton = document.querySelector(`.select-button[data-plan="${planType}"]`);
            if (selectedButton) {
                selectedButton.classList.add('selected');
            }

            // Update the hidden input field for form submission
            document.getElementById('selected_plan').value = planType;

            validateForm(); // Re-validate form state after selecting plan
        }

        function togglePaymentFields() {
            const paymentMethod = document.getElementById('paymentMethod').value;
            const creditCardDetails = document.getElementById('creditCardDetails');
            const gcashDetails = document.getElementById('gcashDetails');

            // Hide all payment details first
            creditCardDetails.style.display = 'none';
            gcashDetails.style.display = 'none';

            // Clear previous error messages when switching payment methods
            clearAllErrors();
            clearPaymentFields(); // Clear values when switching

            // Show relevant fields based on selection
            if (paymentMethod === 'credit_debit') {
                creditCardDetails.style.display = 'block';
            } else if (paymentMethod === 'gcash') {
                gcashDetails.style.display = 'block';
            }
            validateForm(); // Re-validate form state after changing payment method
        }

        function clearPaymentFields() {
            // Clear Credit Card fields
            document.getElementById('cardName').value = '';
            document.getElementById('cardNumber').value = '';
            document.getElementById('expiryDate').value = '';
            document.getElementById('cvv').value = '';

            // Clear GCash field
            document.getElementById('gcashPhoneNumber').value = '';
        }

        function clearError(elementId) {
            const errorElement = document.getElementById(elementId);
            if (errorElement) {
                errorElement.textContent = '';
            }
        }

        function displayError(elementId, message) {
            const errorElement = document.getElementById(elementId);
            if (errorElement) {
                errorElement.textContent = message;
            }
        }

        function clearAllErrors() {
            const errorElements = document.querySelectorAll('.error-message');
            errorElements.forEach(el => el.textContent = '');
        }

        // Validation functions (return true if valid, false if invalid)
        function validateCardName() {
            const cardNameInput = document.getElementById('cardName');
            const cardName = cardNameInput.value.trim();
            clearError('cardNameError');
            if (cardName.length === 0) {
                displayError('cardNameError', 'Card Name is required.');
                return false;
            }
            if (!/^[a-zA-Z\s.-]+$/.test(cardName)) {
                displayError('cardNameError', 'Card Name can only contain letters, spaces, hyphens, and periods.');
                return false;
            }
            return true;
        }

        function validateCardNumber() {
            const cardNumberInput = document.getElementById('cardNumber');
            const cardNumber = cardNumberInput.value.replace(/\s/g, ''); // Remove spaces
            clearError('cardNumberError');

            if (cardNumber.length === 0) {
                displayError('cardNumberError', 'Card Number is required.');
                return false;
            }
            if (cardNumber.length < 13 || cardNumber.length > 19 || !/^\d+$/.test(cardNumber)) {
                displayError('cardNumberError', 'Invalid card number length (13-19 digits).');
                return false;
            }
            return true;
        }

        function formatAndValidateExpiryDate(input) {
            let value = input.value.replace(/\D/g, ''); // Remove non-digits
            clearError('expiryDateError');

            if (value.length > 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            input.value = value;

            if (value.length === 5) { // MM/YY
                const [monthStr, yearStr] = value.split('/');
                const month = parseInt(monthStr, 10);
                const year = 2000 + parseInt(yearStr, 10); // Assume 20xx for YY

                if (isNaN(month) || isNaN(year) || month < 1 || month > 12) {
                    displayError('expiryDateError', 'Invalid month (MM).');
                    return false;
                }

                const today = new Date();
                const currentYear = today.getFullYear();
                const currentMonth = today.getMonth() + 1; 

                if (year < currentYear || (year === currentYear && month < currentMonth)) {
                    displayError('expiryDateError', 'Expired date.');
                    return false;
                }
                return true;
            } else if (value.length > 0 && value.length < 5) {
                return false; 
            } else if (value.length === 0) {
                displayError('expiryDateError', 'Expiry Date is required.');
                return false;
            }
            return false;
        }

        function validateCVV() {
            const cvvInput = document.getElementById('cvv');
            const cvv = cvvInput.value;
            clearError('cvvError');

            if (cvv.length === 0) {
                displayError('cvvError', 'CVV is required.');
                return false;
            }
            if (!/^\d{3,4}$/.test(cvv)) {
                displayError('cvvError', 'CVV must be 3 or 4 digits.');
                return false;
            }
            return true;
        }

        function validateGCashPhoneNumber() {
            const phoneNumberInput = document.getElementById('gcashPhoneNumber');
            const phoneNumber = phoneNumberInput.value;
            clearError('gcashPhoneNumberError');

            if (phoneNumber.length === 0) {
                displayError('gcashPhoneNumberError', 'Mobile Number is required.');
                return false;
            }
            if (!/^\d{10,11}$/.test(phoneNumber)) {
                displayError('gcashPhoneNumberError', 'Phone number must be 10 or 11 digits.');
                return false;
            }
            return true;
        }

        // Master validation function to enable/disable Pay Now button
        function validateForm() {
            const payNowButton = document.getElementById('payNowButton');
            let formIsValid = true;

            // Check if a plan is selected
            if (!selectedPlan) {
                formIsValid = false;
            }

            // Check if payment method is selected
            const paymentMethod = document.getElementById('paymentMethod').value;
            if (paymentMethod === '') {
                formIsValid = false;
            }

            // Validate specific payment fields based on selected method
            if (paymentMethod === 'credit_debit') {
                if (!(validateCardName() && validateCardNumber() && formatAndValidateExpiryDate(document.getElementById('expiryDate')) && validateCVV())) {
                    formIsValid = false;
                }
            } else if (paymentMethod === 'gcash') {
                if (!validateGCashPhoneNumber()) {
                    formIsValid = false;
                }
            }

            payNowButton.disabled = !formIsValid;
        }

        // Attach validateForm to all relevant input events 
        document.querySelectorAll('#membershipForm input, #membershipForm select').forEach(element => {
            if (element.id !== 'payNowButton' && element.id !== 'selected_plan') {
                element.addEventListener('input', validateForm);
                element.addEventListener('change', validateForm);
            }
        });

        // Add an event listener to the form to handle submission with loading animation
        document.getElementById('membershipForm').addEventListener('submit', function(event) {
            // Prevent the form from submitting immediately
            event.preventDefault();

            // Run final validation
            validateForm();

            const payNowButton = document.getElementById('payNowButton');
            const form = this; // 'this' refers to the form element

            // Check if the form is valid (i.e., the button is not disabled)
            if (!payNowButton.disabled) {
                // Add the loading class to the button
                payNowButton.classList.add('button--loading');
                // Disable the button to prevent multiple clicks
                payNowButton.disabled = true;

                // Set a timeout for 3 seconds (3000 milliseconds)
                setTimeout(function() {
                    // After 3 seconds, submit the form programmatically
                    form.submit();
                }, 3000);
            }
        });
    </script>
</body>
</html>