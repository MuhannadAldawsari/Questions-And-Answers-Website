<?php
// get_my_answers.php - Get answers for the current user

require_once 'db_connection.php';

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'You must be logged in to view your answers']);
  exit;
}

$user_id = $_SESSION['user_id'];

// Get all answers by the current user with question titles
$query = "SELECT a.*, q.title as question_title
          FROM answers a
          JOIN questions q ON a.question_id = q.id
          WHERE a.user_id = ?
          ORDER BY a.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$answers = [];
while ($row = $result->fetch_assoc()) {
  $answers[] = $row;
}

echo json_encode(['success' => true, 'answers' => $answers]);

$stmt->close();
$conn->close();
?>
