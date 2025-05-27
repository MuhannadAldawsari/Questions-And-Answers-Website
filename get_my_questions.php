<?php
// get_my_questions.php - Gets questions for the logged-in user

// Include database connection
require_once 'db_connection.php';

// Start session to check if user is logged in
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'You must be logged in to view your questions']);
  exit;
}

$user_id = $_SESSION['user_id'];

try {
  // Query to get user's questions with answer count
  $query = "
        SELECT
            q.id,
            q.title,
            q.body,
            q.user_id,
            q.created_at,
            q.updated_at,
            q.views,
            u.name AS user_name,
            COUNT(DISTINCT a.id) AS answers_count
        FROM
            questions q
            LEFT JOIN users u ON q.user_id = u.id
            LEFT JOIN answers a ON q.id = a.question_id
        WHERE
            q.user_id = ?
        GROUP BY
            q.id
        ORDER BY
            q.created_at DESC
    ";

  // Prepare and execute the statement
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  // Fetch all questions
  $questions = [];
  while ($row = $result->fetch_assoc()) {
    // Get tags for this question
    $tags_query = "
            SELECT t.id, t.name
            FROM tags t
            JOIN question_tags qt ON t.id = qt.tag_id
            WHERE qt.question_id = ?
        ";
    $tags_stmt = $conn->prepare($tags_query);
    $tags_stmt->bind_param("i", $row['id']);
    $tags_stmt->execute();
    $tags_result = $tags_stmt->get_result();

    // Add tags to question
    $tags = [];
    while ($tag = $tags_result->fetch_assoc()) {
      $tags[] = $tag;
    }
    $row['tags'] = $tags;

    $questions[] = $row;
  }

  // Return questions as JSON
  echo json_encode(['success' => true, 'questions' => $questions]);

} catch (Exception $e) {
  // Return error message
  echo json_encode(['success' => false, 'message' => 'Error retrieving questions: ' . $e->getMessage()]);
}

// Close database connection
$conn->close();
?>
