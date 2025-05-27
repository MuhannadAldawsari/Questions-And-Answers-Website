<?php
// answer_handler.php - Handles adding and editing answers

require_once 'db_connection.php';

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'You must be logged in to perform this action']);
  exit;
}

// Get the user ID from the session
$user_id = $_SESSION['user_id'];

// Process form data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Check if we have required fields
  if (!isset($_POST['question_id']) || !isset($_POST['body']) || empty(trim($_POST['body']))) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
  }

  $question_id = $_POST['question_id'];
  $body = trim($_POST['body']);

  // Check if this is an edit (answer_id is provided) or a new answer
  if (isset($_POST['answer_id']) && !empty($_POST['answer_id'])) {
    // Edit existing answer
    $answer_id = $_POST['answer_id'];

    // Check if the user owns this answer
    $stmt = $conn->prepare("SELECT user_id FROM answers WHERE id = ?");
    $stmt->bind_param("i", $answer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      echo json_encode(['success' => false, 'message' => 'Answer not found']);
      exit;
    }

    $answer = $result->fetch_assoc();

    if ($answer['user_id'] != $user_id) {
      echo json_encode(['success' => false, 'message' => 'You can only edit your own answers']);
      exit;
    }

    // Update the answer
    $stmt = $conn->prepare("UPDATE answers SET body = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("si", $body, $answer_id);

    if ($stmt->execute()) {
      echo json_encode(['success' => true, 'message' => 'Answer updated successfully', 'answer_id' => $answer_id]);
    } else {
      echo json_encode(['success' => false, 'message' => 'Error updating answer: ' . $conn->error]);
    }

  } else {
    // Add new answer
    $stmt = $conn->prepare("INSERT INTO answers (body, user_id, question_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sii", $body, $user_id, $question_id);

    if ($stmt->execute()) {
      $answer_id = $conn->insert_id;
      echo json_encode(['success' => true, 'message' => 'Answer posted successfully', 'answer_id' => $answer_id]);
    } else {
      echo json_encode(['success' => false, 'message' => 'Error posting answer: ' . $conn->error]);
    }
  }

  $stmt->close();
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['answer_id'])) {
  // Get answer data for editing
  $answer_id = $_GET['answer_id'];

  $stmt = $conn->prepare("SELECT * FROM answers WHERE id = ?");
  $stmt->bind_param("i", $answer_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Answer not found']);
    exit;
  }

  $answer = $result->fetch_assoc();

  // Check if the user owns this answer
  if ($answer['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'message' => 'You can only edit your own answers']);
    exit;
  }

  echo json_encode(['success' => true, 'answer' => $answer]);
  $stmt->close();
} else {
  echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>
