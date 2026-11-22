<?php session_start(); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Create an Account</title>

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
                <form action="registration_result.php" method="post" class="form-box">
                    <h1>Sign Up</h1>

                    <div class="signup-columns">
                        <div class="left-column">
                            <label>Username</label>
                            <input type="text" name="username" placeholder="Enter your preferred username" required>

                            <label>Name</label>
                            <input type="text" name="name" placeholder="e.g. Juan Dela Cruz" required>

                            <label>Address</label>
                            <input type="text" name="address" placeholder="Street/Barangay" required>

                            <label for="region">Region</label>
                            <select name="region_id" id="region" required>
                                <option value="">Select Region</option>
                                <?php
                                // IMPORTANT: Adjust path to db_connection.php
                                require_once 'database/db_connection.php'; // Path from signup.php to db_connection.php
                                $sql = "SELECT id, region_name, region_code FROM regions ORDER BY region_name ASC";
                                $result = $conn->query($sql);
                                if ($result->num_rows > 0) {
                                    while($row = $result->fetch_assoc()) {
                                        echo '<option value="' . htmlspecialchars($row['id']) . '" data-region-code="' . htmlspecialchars($row['region_code']) . '">' . htmlspecialchars($row['region_name']) . '</option>';
                                    }
                                }
                                $conn->close();
                                ?>
                            </select>

                            <label for="province">Province</label>
                            <select name="province_id" id="province" required disabled>
                                <option value="">Select Province</option>
                            </select>

                            <label for="city_municipality">City/Municipality</label>
                            <select name="city_municipality_id" id="city_municipality" required disabled>
                                <option value="">Select City/Municipality</option>
                            </select>

                        </div>

                        <div class="right-column">
                            <label>Email Address</label>
                            <input type="email" name="email" placeholder="e.g. example@email.com" required>
                            
                            <label>Contact Number</label>
                            <input type="text" name="contact" placeholder="e.g. 09xx-xxx-xxxx" pattern="[0-9]+" required>

                            <label>Date of Birth</label>
                            <input type="date" name="dob" required class="uniform-input">

                            <label for="gender">Gender</label>
                            <select name="gender" id="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Non-Binary">Non-Binary</option>
                            </select>

                            <label>Password</label>
                            <input type="password" name="password" placeholder="Enter your password" required>

                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" placeholder="Re-enter your password" required>
                        </div>
                    </div>

                    <div class="form-footer">
                        <button type="submit">Register</button>
                    </div>

                    <p class="text-link">Already have an account? <a href="login.php">Login here</a></p>
                </form>
            </div>
        </main>
    </div>

    <?php include 'footer.php'; ?>

    <script src="js/locations.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>