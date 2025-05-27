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
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Validate inputs
if (empty($email) || empty($password)) {
  echo json_encode(['success' => false, 'message' => 'Email and password are required']);
  exit;
}

// Query the database for the user
$stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
  exit;
}

$user = $result->fetch_assoc();

// Verify password
if (!($password == $user['password'])) {
  echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
  exit;
}

// Set session variables
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['logged_in'] = true;

// Return success response
echo json_encode([
  'success' => true,
  'message' => 'Login successful',
  'user_id' => $user['id'],
  'user_name' => $user['name']
]);

// Close statement and connection
$stmt->close();
$conn->close();
?>
