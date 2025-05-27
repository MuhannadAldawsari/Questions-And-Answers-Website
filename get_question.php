<?php
// get_question.php - Gets details for a specific question

require_once 'db_connection.php';

// Check if question ID is provided
if (!isset($_GET['id'])) {
  echo json_encode(['success' => false, 'message' => 'Question ID is required']);
  exit;
}

$question_id = intval($_GET['id']);

try {
  $conn->query("UPDATE questions SET views = views + 1 WHERE id = $question_id");
  // Get question details
  $query = "
        SELECT
            q.*,
            u.name AS user_name
        FROM
            questions q
            JOIN users u ON q.user_id = u.id
        WHERE
            q.id = ?
    ";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $question_id);
  $stmt->execute();
  $question = $stmt->get_result()->fetch_assoc();

  if (!$question) {
    echo json_encode(['success' => false, 'message' => 'Question not found']);
    exit;
  }

  // Get answers for the question
  $answers_query = "
        SELECT
            a.*,
            u.name AS user_name
        FROM
            answers a
            JOIN users u ON a.user_id = u.id
        WHERE
            a.question_id = ?
        ORDER BY
            a.created_at DESC
    ";
  $answers_stmt = $conn->prepare($answers_query);
  $answers_stmt->bind_param("i", $question_id);
  $answers_stmt->execute();
  $answers = $answers_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

  // Get tags for the question
  $tags_query = "
        SELECT t.name
        FROM tags t
        JOIN question_tags qt ON t.id = qt.tag_id
        WHERE qt.question_id = ?
    ";
  $tags_stmt = $conn->prepare($tags_query);
  $tags_stmt->bind_param("i", $question_id);
  $tags_stmt->execute();
  $tags = $tags_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

  echo json_encode([
    'success' => true,
    'question' => array_merge($question, ['tags' => $tags]),
    'answers' => $answers
  ]);

} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
?>
