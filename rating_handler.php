<?php
// rating_handler.php - Handle answer rating functionality
include 'db_connection.php';

// Initialize response array
$response = array(
  'success' => false,
  'message' => ''
);

// Start session
session_start();

// Determine the action based on request method
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'rate' : 'check';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  if ($action === 'rate') {
    $response['message'] = 'You must be logged in to rate answers';
    echo json_encode($response);
    exit;
  } else {
    $response['message'] = 'User not logged in';
    $response['success'] = true;
    $response['has_rating'] = false;
    echo json_encode($response);
    exit;
  }
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// HANDLE RATING ACTION (POST request)
if ($action === 'rate') {
  // Validate request parameters
  if (!isset($_POST['answer_id']) || !isset($_POST['rating_type'])) {
    $response['message'] = 'Missing required parameters';
    echo json_encode($response);
    exit;
  }

  $answer_id = intval($_POST['answer_id']);
  $rating_type = $_POST['rating_type'];

  // Validate rating type
  if ($rating_type !== 'up' && $rating_type !== 'down') {
    $response['message'] = 'Invalid rating type';
    echo json_encode($response);
    exit;
  }

  // Check if this answer exists
  $stmt = $conn->prepare("SELECT id FROM answers WHERE id = ?");
  $stmt->bind_param("i", $answer_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    $response['message'] = 'Answer not found';
    echo json_encode($response);
    exit;
  }
  $stmt->close();

  // Begin transaction
  $conn->begin_transaction();

  try {
    // Check if the user has already rated this answer
    $stmt = $conn->prepare("SELECT id, rating_type FROM ratings WHERE user_id = ? AND answer_id = ?");
    $stmt->bind_param("ii", $user_id, $answer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing_rating = $result->fetch_assoc();
    $stmt->close();

    if ($existing_rating) {
      // User has already rated this answer
      $existing_rating_type = $existing_rating['rating_type'];
      $rating_id = $existing_rating['id'];

      if ($existing_rating_type === $rating_type) {
        // User is clicking the same rating again - remove their rating
        $stmt = $conn->prepare("DELETE FROM ratings WHERE id = ?");
        $stmt->bind_param("i", $rating_id);
        $stmt->execute();
        $stmt->close();

        $action = 'removed';
        $message = 'Your rating has been removed';
      } else {
        // User is changing their rating
        $stmt = $conn->prepare("UPDATE ratings SET rating_type = ? WHERE id = ?");
        $stmt->bind_param("si", $rating_type, $rating_id);
        $stmt->execute();
        $stmt->close();

        $action = 'changed';
        $message = 'Your rating has been updated';
      }
    } else {
      // User hasn't rated this answer yet - insert new rating
      $stmt = $conn->prepare("INSERT INTO ratings (user_id, answer_id, rating_type) VALUES (?, ?, ?)");
      $stmt->bind_param("iis", $user_id, $answer_id, $rating_type);
      $stmt->execute();
      $stmt->close();

      $action = 'added';
      $message = 'Your rating has been added';
    }

    // Update the votes count in the answers table
    // This counts the difference between upvotes and downvotes
    $stmt = $conn->prepare("
            UPDATE answers
            SET votes = (
                SELECT COUNT(CASE WHEN rating_type = 'up' THEN 1 END) -
                       COUNT(CASE WHEN rating_type = 'down' THEN 1 END)
                FROM ratings
                WHERE answer_id = ?
            )
            WHERE id = ?
        ");
    $stmt->bind_param("ii", $answer_id, $answer_id);
    $stmt->execute();
    $stmt->close();

    // Get the updated vote count
    $stmt = $conn->prepare("SELECT votes FROM answers WHERE id = ?");
    $stmt->bind_param("i", $answer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $answer = $result->fetch_assoc();
    $votes = $answer['votes'];
    $stmt->close();

    // Get user's current rating for UI highlighting
    $stmt = $conn->prepare("SELECT rating_type FROM ratings WHERE user_id = ? AND answer_id = ?");
    $stmt->bind_param("ii", $user_id, $answer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_rating = $result->num_rows > 0 ? $result->fetch_assoc()['rating_type'] : null;
    $stmt->close();

    // Commit transaction
    $conn->commit();

    // Return success response
    $response['success'] = true;
    $response['message'] = $message;
    $response['votes'] = $votes;
    $response['action'] = $action;
    $response['current_rating'] = $current_rating;

  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $response['message'] = 'An error occurred: ' . $e->getMessage();
  }
}
// HANDLE CHECK RATING ACTION (GET request)
else {
  // Validate request parameters
  if (!isset($_GET['answer_id'])) {
    $response['message'] = 'Missing answer ID';
    echo json_encode($response);
    exit;
  }

  $answer_id = intval($_GET['answer_id']);

  // Check if the user has already rated this answer
  $stmt = $conn->prepare("SELECT rating_type FROM ratings WHERE user_id = ? AND answer_id = ?");
  $stmt->bind_param("ii", $user_id, $answer_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $rating = $result->fetch_assoc();
    $response['has_rating'] = true;
    $response['rating_type'] = $rating['rating_type'];
  } else {
    $response['has_rating'] = false;
  }

  $stmt->close();

  // Set success flag
  $response['success'] = true;
}

// Close connection
$conn->close();

// Return response as JSON
echo json_encode($response);
?>
