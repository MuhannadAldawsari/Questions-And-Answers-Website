<?php
// search_handler.php - Handles all search-related functionality
include 'db_connection.php';

// Initialize response array
$response = array(
  'success' => false,
  'message' => '',
  'results' => array()
);

// Determine action based on request method and parameters
$action = $_SERVER['REQUEST_METHOD'] === 'GET' ? 'search' : 'invalid';

// HANDLE SEARCH ACTION (GET request)
if ($action === 'search') {
  // Get search query from GET parameters
  if (!isset($_GET['query']) || empty(trim($_GET['query']))) {
    $response['message'] = 'Search query is required';
    echo json_encode($response);
    exit;
  }

  $search_query = trim($_GET['query']);
  $search_term = '%' . $search_query . '%';

  try {
    // Prepare SQL query to search in questions
    // We search in title, body, and tags
    $sql = "
    SELECT
        q.id,
        q.title,
        q.body,
        q.created_at,
        q.updated_at,
        q.views,
        u.name as user_name,
        u.id as user_id,
        (SELECT COUNT(*) FROM answers WHERE question_id = q.id) as answers_count
    FROM
        questions q
    JOIN
        users u ON q.user_id = u.id
    WHERE
        q.title LIKE ? OR
        q.body LIKE ?
    GROUP BY
        q.id
    ORDER BY
        q.created_at DESC
    LIMIT 50
";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();

    $questions = array();

    while ($row = $result->fetch_assoc()) {
      // Get tags for this question
      $tag_sql = "
                SELECT t.id, t.name
                FROM tags t
                JOIN question_tags qt ON t.id = qt.tag_id
                WHERE qt.question_id = ?
            ";

      $tag_stmt = $conn->prepare($tag_sql);
      $tag_stmt->bind_param("i", $row['id']);
      $tag_stmt->execute();
      $tag_result = $tag_stmt->get_result();

      $tags = array();
      while ($tag_row = $tag_result->fetch_assoc()) {
        $tags[] = array(
          'id' => $tag_row['id'],
          'name' => $tag_row['name']
        );
      }

      $tag_stmt->close();

      // Add tags to the question data
      $row['tags'] = $tags;

      // Format the question body (truncate for preview)
      if (strlen($row['body']) > 200) {
        $row['body_preview'] = substr($row['body'], 0, 200) . '...';
      } else {
        $row['body_preview'] = $row['body'];
      }

      // Add to results array
      $questions[] = $row;
    }

    $stmt->close();

    // Update response
    $response['success'] = true;
    $response['message'] = count($questions) > 0
      ? 'Search results found'
      : 'No results found for "' . htmlspecialchars($search_query) . '"';
    $response['results'] = $questions;
    $response['query'] = $search_query;

  } catch (Exception $e) {
    $response['message'] = 'An error occurred: ' . $e->getMessage();
  }
} else {
  $response['message'] = 'Invalid request method';
}

// Close database connection
$conn->close();

// Return response as JSON
header('Content-Type: application/json');
echo json_encode($response);
?>
