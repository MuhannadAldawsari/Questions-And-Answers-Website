<?php
// db_connection.php - Database connection file
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "qa_forum";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
?>
