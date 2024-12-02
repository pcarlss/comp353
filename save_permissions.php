<?php
require 'session/db_connect.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if postID is set
    if (!isset($_POST['postID'])) {
        echo "Error: postID not provided.";
        exit;
    }
    $postID = intval($_POST['postID']);
    $permissionLevel = $_POST['permissionLevel'] ?? '';
    // Validate permission level
    if (!in_array($permissionLevel, ['view_only', 'view_and_comment', 'view_add_or_link'])) {
        echo "Invalid permission level.";
        exit;
    }
    $visibility = json_encode(['level' => $permissionLevel]);
    // Update the post's visibility in the database
    $stmt = $conn->prepare("UPDATE Post SET Visibility = ? WHERE PostID = ?");
    $stmt->bind_param("si", $visibility, $postID);
    if ($stmt->execute()) {
        echo "Permissions updated successfully.";
    } else {
        echo "Failed to update permissions: " . $stmt->error;
    }
    $stmt->close();
} else {
    echo "Invalid request.";
}
?>