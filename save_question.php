<?php
// save_question.php - Handles both adding and editing questions

// Include database connection
require_once 'db_connection.php';

// Start session to check if user is logged in
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'You must be logged in to perform this action']);
  exit;
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Get form data
  $title = trim($_POST['title']);
  $body = trim($_POST['body']);
  $tags = isset($_POST['tags']) ? trim($_POST['tags']) : '';
  $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;

  // Validate input
  if (empty($title) || empty($body)) {
    echo json_encode(['success' => false, 'message' => 'Title and body are required']);
    exit;
  }

  // Create or update question
  if ($question_id > 0) {
    // Edit existing question

    // Check if user owns this question
    $stmt = $conn->prepare("SELECT user_id FROM questions WHERE id = ?");
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      echo json_encode(['success' => false, 'message' => 'Question not found']);
      exit;
    }

    $question = $result->fetch_assoc();

    if ($question['user_id'] != $user_id) {
      echo json_encode(['success' => false, 'message' => 'You can only edit your own questions']);
      exit;
    }

    // Update question
    $stmt = $conn->prepare("UPDATE questions SET title = ?, body = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("ssi", $title, $body, $question_id);

    if ($stmt->execute()) {
      // Remove existing tags
      $stmt = $conn->prepare("DELETE FROM question_tags WHERE question_id = ?");
      $stmt->bind_param("i", $question_id);
      $stmt->execute();

      // Process tags
      if (!empty($tags)) {
        processTags($tags, $question_id, $conn);
      }

      echo json_encode(['success' => true, 'message' => 'Question updated successfully', 'question_id' => $question_id]);
    } else {
      echo json_encode(['success' => false, 'message' => 'Error updating question: ' . $stmt->error]);
    }

  } else {
    // Add new question
    $stmt = $conn->prepare("INSERT INTO questions (title, body, user_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $title, $body, $user_id);

    if ($stmt->execute()) {
      $question_id = $conn->insert_id;

      // Process tags
      if (!empty($tags)) {
        processTags($tags, $question_id, $conn);
      }

      echo json_encode(['success' => true, 'message' => 'Question added successfully', 'question_id' => $question_id]);
    } else {
      echo json_encode(['success' => false, 'message' => 'Error adding question: ' . $stmt->error]);
    }
  }

} else {
  echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Function to process tags
function processTags($tags_string, $question_id, $conn) {
  // Split tags by comma
  $tags_array = array_map('trim', explode(',', $tags_string));
  $tags_array = array_filter($tags_array); // Remove empty values

  // Process each tag (max 5 tags)
  $count = 0;
  foreach ($tags_array as $tag_name) {
    if ($count >= 5) break; // Limit to 5 tags

    // Check if tag exists
    $stmt = $conn->prepare("SELECT id FROM tags WHERE name = ?");
    $stmt->bind_param("s", $tag_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      // Tag exists, get its ID
      $tag = $result->fetch_assoc();
      $tag_id = $tag['id'];
    } else {
      // Create new tag
      $stmt = $conn->prepare("INSERT INTO tags (name) VALUES (?)");
      $stmt->bind_param("s", $tag_name);
      $stmt->execute();
      $tag_id = $conn->insert_id;
    }

    // Link tag to question
    $stmt = $conn->prepare("INSERT INTO question_tags (question_id, tag_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $question_id, $tag_id);
    $stmt->execute();

    $count++;
  }
}

// Close database connection
$conn->close();
?>
