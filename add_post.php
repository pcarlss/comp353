<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'project');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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
            VALUES (1, '$post_content', '" . ($media_path ? json_encode([$media_path]) : '[]') . "', '{}', '$post_type', '$posted_at')";

    if ($conn->query($sql) === TRUE) {
        // Redirect to homepage after successful post
        header("Location: index.php?message=Post%20added");
        exit;
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

$conn->close();
?>
