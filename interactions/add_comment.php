<?php
require '../session/db_connect.php';

// Start session
session_start();

// Check if the user is logged in and has a username in the session
if (!isset($_SESSION['username'])) {
    // Send a redirect after 2 seconds
    echo "You must be logged in to create a post.<br><br>";
    echo "Redirecting to Sign Up...";
    header("Refresh: 2; url=../account_setup.php");
    exit;
}

// Check if the request method is POST and required data is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id']) && isset($_POST['comment_content'])) {
    $post_id = intval($_POST['post_id']);
    $username = $_SESSION['username']; // Retrieve the logged-in username

    // Fetch the member ID for the logged-in user
    $result = $conn->query("SELECT memberid FROM Member WHERE username = '$username'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc(); // Fetch the member ID
        $member_id = $row['memberid'];
    } else {
        echo "Error: Unable to find Member ID.";
        exit;
    }

    $comment_content = $conn->real_escape_string($_POST['comment_content']);
    $commented_at = date('Y-m-d H:i:s');

    // Insert comment into the Comment table
    $sql = "INSERT INTO Comment (PostID, MemberID, CommentContent, CommentedAt) VALUES ('$post_id', '$member_id', '$comment_content', '$commented_at')";

    if ($conn->query($sql) === TRUE) {
        header("Location: ../index.php");
        exit;
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

$conn->close();
?>