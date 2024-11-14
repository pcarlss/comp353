<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'project');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the request method is POST and required data is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id']) && isset($_POST['comment_content'])) {
    $post_id = intval($_POST['post_id']);
    $member_id = 1; // Assuming a fixed MemberID for now; this would normally be the logged-in user's ID
    $comment_content = $conn->real_escape_string($_POST['comment_content']);
    $commented_at = date('Y-m-d H:i:s');

    // Insert comment into the Comment table
    $sql = "INSERT INTO Comment (PostID, MemberID, CommentContent, CommentedAt) VALUES ('$post_id', '$member_id', '$comment_content', '$commented_at')";

    if ($conn->query($sql) === TRUE) {
        header("Location: index.php");
        exit;
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

$conn->close();
?>
