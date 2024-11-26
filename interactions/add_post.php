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

// Retrieve the username from the session
$username = $_SESSION['username'];

// Query the database to get the MemberID associated with the username
$sql_member = "SELECT MemberID FROM Member WHERE Username = ?";
$stmt = $conn->prepare($sql_member);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found in the database.");
}

// Fetch the MemberID
$member_row = $result->fetch_assoc();
$member_id = $member_row['MemberID'];

// Close the statement
$stmt->close();

// Retrieve the post content and type from the form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_content = $conn->real_escape_string($_POST['post_content']);
    $post_type = $_POST['post_type'];
    $posted_at = date('Y-m-d H:i:s'); // Current timestamp

    // Handle file upload for image or video posts
    $media_path = null;
    if ($post_type === 'image' || $post_type === 'video') {
        if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
            $media_folder = $post_type === 'image' ? 'uploads/images/' : 'uploads/videos/';
            $media_path = $media_folder . basename($_FILES['media_file']['name']);

            // Check if the directory exists
            if (!file_exists($media_folder)) {
                mkdir($media_folder, 0777, true); // Create the directory if it doesn't exist
            }

            // Move uploaded file to the correct folder
            if (!move_uploaded_file($_FILES['media_file']['tmp_name'], $media_path)) {
                die("Error uploading media file.");
            }
        } else {
            die("Please upload a file for image or video posts.");
        }
    }

    // Insert post into the database, setting PostImages to the media path or empty array
    $sql = "INSERT INTO Post (MemberID, PostText, PostImages, Visibility, PostType, PostedAt)
            VALUES (?, ?, ?, '{}', ?, ?)";
    $stmt = $conn->prepare($sql);
    $media_json = $media_path ? json_encode([$media_path]) : '[]';
    $stmt->bind_param("issss", $member_id, $post_content, $media_json, $post_type, $posted_at);

    if ($stmt->execute()) {
        // Redirect to homepage after successful post
        header("Location: ../index.php?message=Post%20added");
        exit;
    } else {
        echo "Error: " . $stmt->error;
    }

    // Close the statement
    $stmt->close();
}

// Close the connection
$conn->close();
?>
