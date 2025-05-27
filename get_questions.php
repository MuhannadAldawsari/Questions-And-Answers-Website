<?php
// get_questions.php - Gets all questions for the home page

// Include database connection
require_once 'db_connection.php';

// Start session (to check if user is logged in)
session_start();

try {
  // Query to get all questions with answer count and author info
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
        GROUP BY
            q.id
        ORDER BY
            q.created_at DESC
    ";

  $result = $conn->query($query);

  if (!$result) {
    throw new Exception("Error executing query: " . $conn->error);
  }

  $questions = [];

  while ($row = $result->fetch_assoc()) {
    // Get tags for this question
    $tags_query = "
            SELECT
                t.id,
                t.name
            FROM
                tags t
                JOIN question_tags qt ON t.id = qt.tag_id
            WHERE
                qt.question_id = ?
        ";

    $stmt = $conn->prepare($tags_query);
    $stmt->bind_param("i", $row['id']);
    $stmt->execute();
    $tags_result = $stmt->get_result();

    // Add tags to question data
    $tags = [];
    while ($tag = $tags_result->fetch_assoc()) {
      $tags[] = $tag;
    }

    $row['tags'] = $tags;
    $questions[] = $row;
  }

  echo json_encode(['success' => true, 'questions' => $questions]);

} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Close database connection
$conn->close();
?>
