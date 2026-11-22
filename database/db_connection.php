<?php
// Directory: database/db_connection.php
$servername = "localhost";
$username = "root"; // Your phpMyAdmin username
$password = "";     // Your phpMyAdmin password (often empty for root with XAMPP/WAMP)
$dbname = "user_registration_db"; // The database name you created

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

?>