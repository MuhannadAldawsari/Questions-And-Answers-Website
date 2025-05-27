<?php
// Include database connection
require_once 'db_connection.php';

// Start session for user authentication
session_start();

// Function to check if user is logged in
function isLoggedIn() {
  return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Function to sanitize input data
function sanitizeInput($data) {
  global $conn;
  return mysqli_real_escape_string($conn, trim($data));
}

// Get comment for editing
if (isset($_GET['comment_id']) && !empty($_GET['comment_id'])) {
  $commentId = (int)$_GET['comment_id'];

  if (!isLoggedIn()) {
    echo json_encode([
      'success' => false,
      'message' => 'You must be logged in to edit a comment.'
    ]);
    exit;
  }

  $userId = (int)$_SESSION['user_id'];

  // Get comment data
  $query = "SELECT * FROM comments WHERE id = $commentId AND user_id = $userId";
  $result = $conn->query($query);

  if ($result && $result->num_rows > 0) {
    $comment = $result->fetch_assoc();
    echo json_encode([
      'success' => true,
      'comment' => $comment
    ]);
  } else {
    echo json_encode([
      'success' => false,
      'message' => 'Comment not found or you do not have permission to edit it.'
    ]);
  }
  exit;
}

// Add or edit comment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Check if user is logged in
  if (!isLoggedIn()) {
    echo json_encode([
      'success' => false,
      'message' => 'You must be logged in to add or edit a comment.'
    ]);
    exit;
  }

  $userId = (int)$_SESSION['user_id'];
  $body = isset($_POST['body']) ? sanitizeInput($_POST['body']) : '';
  $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
  $parentType = isset($_POST['parent_type']) ? sanitizeInput($_POST['parent_type']) : '';
  $commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;

  // Validate input
  if (empty($body)) {
    echo json_encode([
      'success' => false,
      'message' => 'Comment body cannot be empty.'
    ]);
    exit;
  }

  if ($parentId <= 0 || !in_array($parentType, ['question', 'answer'])) {
    echo json_encode([
      'success' => false,
      'message' => 'Invalid parent ID or type.'
    ]);
    exit;
  }

  // Check if parent exists
  $parentTable = $parentType . 's'; // questions or answers
  $checkParentQuery = "SELECT * FROM $parentTable WHERE id = $parentId";
  $parentResult = $conn->query($checkParentQuery);

  if (!$parentResult || $parentResult->num_rows === 0) {
    echo json_encode([
      'success' => false,
      'message' => 'The ' . $parentType . ' you are trying to comment on does not exist.'
    ]);
    exit;
  }

  // If it's an edit, check if comment exists and belongs to the user
  if ($commentId > 0) {
    $checkCommentQuery = "SELECT * FROM comments WHERE id = $commentId AND user_id = $userId";
    $commentResult = $conn->query($checkCommentQuery);

    if (!$commentResult || $commentResult->num_rows === 0) {
      echo json_encode([
        'success' => false,
        'message' => 'Comment not found or you do not have permission to edit it.'
      ]);
      exit;
    }

    // Update the comment
    $updateQuery = "UPDATE comments SET body = '$body', updated_at = CURRENT_TIMESTAMP WHERE id = $commentId";

    if ($conn->query($updateQuery)) {
      // Get the updated comment with user info
      $getUpdatedComment = "SELECT c.*, u.name as user_name
                                FROM comments c
                                JOIN users u ON c.user_id = u.id
                                WHERE c.id = $commentId";
      $updatedResult = $conn->query($getUpdatedComment);
      $updatedComment = $updatedResult->fetch_assoc();

      echo json_encode([
        'success' => true,
        'message' => 'Comment updated successfully.',
        'comment' => $updatedComment
      ]);
    } else {
      echo json_encode([
        'success' => false,
        'message' => 'Failed to update comment: ' . $conn->error
      ]);
    }
  } else {
    // Add new comment
    $insertQuery = "INSERT INTO comments (body, user_id, parent_id, parent_type, created_at)
                      VALUES ('$body', $userId, $parentId, '$parentType', CURRENT_TIMESTAMP)";

    if ($conn->query($insertQuery)) {
      $newCommentId = $conn->insert_id;

      // Get the new comment with user info
      $getNewComment = "SELECT c.*, u.name as user_name
                            FROM comments c
                            JOIN users u ON c.user_id = u.id
                            WHERE c.id = $newCommentId";
      $newResult = $conn->query($getNewComment);
      $newComment = $newResult->fetch_assoc();

      echo json_encode([
        'success' => true,
        'message' => 'Comment added successfully.',
        'comment' => $newComment
      ]);
    } else {
      echo json_encode([
        'success' => false,
        'message' => 'Failed to add comment: ' . $conn->error
      ]);
    }
  }
  exit;
}

// Get comments for a parent (question or answer)
if (isset($_GET['parent_id']) && isset($_GET['parent_type'])) {
  $parentId = (int)$_GET['parent_id'];
  $parentType = sanitizeInput($_GET['parent_type']);

  if ($parentId <= 0 || !in_array($parentType, ['question', 'answer'])) {
    echo json_encode([
      'success' => false,
      'message' => 'Invalid parent ID or type.'
    ]);
    exit;
  }

  $query = "SELECT c.*, u.name as user_name
              FROM comments c
              JOIN users u ON c.user_id = u.id
              WHERE c.parent_id = $parentId AND c.parent_type = '$parentType'
              ORDER BY c.created_at ASC";

  $result = $conn->query($query);

  if ($result) {
    $comments = [];
    while ($row = $result->fetch_assoc()) {
      $comments[] = $row;
    }

    echo json_encode([
      'success' => true,
      'comments' => $comments
    ]);
  } else {
    echo json_encode([
      'success' => false,
      'message' => 'Error fetching comments: ' . $conn->error
    ]);
  }
  exit;
}

// If we get here, it means no valid action was specified
echo json_encode([
  'success' => false,
  'message' => 'Invalid request.'
]);
?>
