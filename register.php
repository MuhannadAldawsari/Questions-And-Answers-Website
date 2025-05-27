<?php
// Start session
session_start();

// Include database connection
require_once 'db_connection.php';

// Set response header to JSON
header('Content-Type: application/json');

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Invalid request method']);
  exit;
}

// Get form data
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Validate inputs
if (empty($name) || empty($email) || empty($password)) {
  echo json_encode(['success' => false, 'message' => 'All fields are required']);
  exit;
}

if (strlen($password) < 8) {
  echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
  exit;
}

// Check if email is already registered
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  echo json_encode(['success' => false, 'message' => 'Email is already registered']);
  $stmt->close();
  exit;
}
$stmt->close();

// Insert new user into the database
$stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $name, $email, $password);

// Execute the query
if ($stmt->execute()) {
  // Get the inserted user ID
  $user_id = $stmt->insert_id;

  // Set session variables
  $_SESSION['user_id'] = $user_id;
  $_SESSION['user_name'] = $name;
  $_SESSION['logged_in'] = true;

  // Return success response
  echo json_encode([
    'success' => true,
    'message' => 'Registration successful',
    'user_id' => $user_id,
    'user_name' => $name
  ]);
} else {
  echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $stmt->error]);
}

// Close statement and connection
$stmt->close();
$conn->close();
?>
