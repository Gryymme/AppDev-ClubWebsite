<?php
session_start();

require_once 'database/db_connection.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['username'] ?? ''); 
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = "Please enter both username/email and password.";
    } else {
        // Determine if the identifier is an email or a username
        $is_email = filter_var($identifier, FILTER_VALIDATE_EMAIL);

        // Prepare the SQL query to find a user by username or email
        if ($is_email) {
            $stmt = $conn->prepare("SELECT id, username, email, password, member_status FROM users WHERE email = ?");
            $stmt->bind_param("s", $identifier);
        } else {
            $stmt = $conn->prepare("SELECT id, username, email, password, member_status FROM users WHERE username = ?");
            $stmt->bind_param("s", $identifier);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verify the hashed password
            if (password_verify($password, $user['password'])) {
                // Login successful
                date_default_timezone_set('Asia/Manila'); 

                $_SESSION['user_id'] = $user['id']; // Store the database ID
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                
                // Set $_SESSION['member_status'] directly from the database
                $_SESSION['member_status'] = $user['member_status'];

                header("Location: user_management.php");
                exit();
            } else {
                $error = "Invalid username/email or password.";
            }
        } else {
            $error = "Invalid username/email or password.";
        }
        $stmt->close();
    }
}
// Close the database connection
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Login</title>

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
                <form method="post" class="form-box">
                    <h1>Login</h1>

                    <?php if ($error): ?>
                        <p class="error"><?= htmlspecialchars($error) ?></p>
                    <?php endif; ?>

                    <label>Username or Email</label>
                    <input type="text" name="username" placeholder="Type your username or email" required>

                    <label>Password</label>
                    <input type="password" name="password" placeholder="Type your password" required>

                    <button type="submit">Login</button>

                    <p class="text-link">No account? <a href="signup.php">Sign up here</a></p>
                </form>
            </div>
        </main>
    </div>

    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>