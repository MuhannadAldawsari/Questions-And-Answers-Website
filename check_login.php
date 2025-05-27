<?php
// Start session
session_start();

// Set response header to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
  echo json_encode([
    'logged_in' => true,
    'user_id' => $_SESSION['user_id'],
    'user_name' => $_SESSION['user_name']
  ]);
} else {
  echo json_encode([
    'logged_in' => false
  ]);
}
?>
