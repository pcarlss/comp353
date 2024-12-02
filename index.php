<?php
require 'session/db_connect.php';
session_start();

if (!isset($_SESSION['MemberID'])) {
    header("Location: account_setup.php");
    exit();
}

$memberID = $_SESSION['MemberID'];

// Fetch "My Posts"
$stmtMyPosts = $conn->prepare("
    SELECT Post.*, Member.Username 
    FROM Post 
    JOIN Member ON Post.MemberID = Member.MemberID 
    WHERE Post.MemberID = ? 
    ORDER BY Post.PostedAt DESC
");
$stmtMyPosts->bind_param("i", $memberID);
$stmtMyPosts->execute();
$resultMyPosts = $stmtMyPosts->get_result();
$stmtMyPosts->close();

// Fetch "Posts In My Groups"
$stmtGroupPosts = $conn->prepare("
    SELECT Post.*, Member.Username 
    FROM Post
    JOIN Member ON Post.MemberID = Member.MemberID
    WHERE Post.MemberID IN (
        SELECT gm.MemberID
        FROM GroupMember gm
        WHERE gm.GroupID IN (
            SELECT GroupID 
            FROM GroupMember 
            WHERE MemberID = ?
        )
        AND gm.MemberID != ?
    )
    ORDER BY Post.PostedAt DESC
");
$stmtGroupPosts->bind_param("ii", $memberID, $memberID);
$stmtGroupPosts->execute();
$resultGroupPosts = $stmtGroupPosts->get_result();
$stmtGroupPosts->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage</title>
    <style>
        /* Basic reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Layout styling */
        body {
            display: flex;
            justify-content: center;
            background-color: #f0f2f5;
            font-family: Arial, sans-serif;
            padding-top: 120px;
            overflow-y: scroll;
        }

        /* Top Bar Styling */
        .top-bar {
            position: fixed;
            top: 0;
            width: 100%;
            background-color: #4c87ae;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .top-bar h1 {
            font-size: 1.5em;
        }

        .top-bar button {
            background-color: #fff;
            color: #4c87ae;
            border: none;
            padding: 10px 15px;
            font-size: 1em;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .top-bar button:hover {
            background-color: #ddd;
        }

        /* Centering the main content */
        .container {
            width: 100%;
            max-width: 600px;
            padding: 10px;
        }

        .post {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            cursor: pointer;
        }

        .post h3 {
            font-size: 1.2em;
            color: #333;
            margin-bottom: 10px;
        }

        .post p {
            color: #555;
            font-size: 1em;
            line-height: 1.5;
        }

        .comment-section {
            display: none;
            margin-top: 15px;
        }

        .comment-section textarea {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-size: 1em;
            resize: none;
        }

        .comment-section button {
            background-color: #4c87ae;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }

        .post-form {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            width: 100%;
            max-width: 600px;
        }

        .post-form h3 {
            font-size: 1.2em;
            color: #333;
            margin-bottom: 10px;
        }

        .post-form textarea {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-size: 1em;
            resize: none;
            margin-bottom: 10px;
        }

        .post-form button {
            background-color: #4c87ae;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
        }

        .success-banner {
            background-color: #28a745;
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: bold;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1001;
        }
    </style>
</head>

<body>
    <!-- Top Bar -->
    <div class="top-bar">
        <h1>Home</h1>
        <a href="groups/group_tab.php" style="margin-left: 55%">
            <button>
                <h3>Groups</h3>
            </button>
        </a>
        <?php
        echo '<a style="margin-left: 1%" href="Events/create_social_event.php"><button><h3>Social Events</h3></button></a>';
        echo '<a style="margin-left: 1%" href="GiftExchange/create_gift_exchange.php"><button><h3>Gift Exchanges</h3></button></a>';
        if (isset($_SESSION['username'])) {
            echo '<a style="margin-left: 1%" href="profile.php"><button><h3>Profile</h3></button></a>';
        } else {
            echo '<a href="account_setup.php"><button><h3>Log In or Sign Up</h3></button></a>';
        }
        ?>
    </div>

    <!-- Post Form -->
    <div class="post-form">
        <h3>Create a New Post</h3>
        <form action="interactions/add_post.php" method="POST" enctype="multipart/form-data">
            <textarea name="post_content" placeholder="What's on your mind?" required></textarea>

            <label for="post_type">Post Type:</label>
            <select name="post_type" id="post_type" onchange="toggleFileInput(this.value)">
                <option value="text">Text</option>
                <option value="image">Image</option>
                <option value="video">Video</option>
            </select>

            <div id="file_input" style="display: none;">
                <input type="file" name="media_file" accept="image/*,video/*">
            </div>

            <button type="submit">Post</button>
        </form>
    </div>

    <!-- Success Message Banner -->
    <?php if (isset($_GET['message'])) : ?>
        <div class="success-banner">
            <?php echo htmlspecialchars($_GET['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Posts and Comments -->
    <div class="container">

<!-- My Posts Section -->
<section>
    <h2>My Posts</h2>
    <?php if ($resultMyPosts->num_rows > 0): ?>
        <?php while ($row = $resultMyPosts->fetch_assoc()): ?>
            <div class="post" onclick="toggleCommentSection(this)">
                <h3>Post by You</h3>
                <?php if (!empty($row['PostText'])): ?>
                    <p><?php echo htmlspecialchars($row['PostText']); ?></p>
                <?php endif; ?>
                <!-- Comments Section -->
                <div class="comment-section">
                    <?php
                    $post_id = $row['PostID'];
                    $comments_result = $conn->query("
                        SELECT Comment.*, Member.Username 
                        FROM Comment 
                        JOIN Member ON Comment.MemberID = Member.MemberID 
                        WHERE PostID = $post_id 
                        ORDER BY Comment.CommentedAt ASC
                    ");
                    if ($comments_result->num_rows > 0):
                        while ($comment = $comments_result->fetch_assoc()):
                    ?>
                            <div class="comment">
                                <p><strong><?php echo $comment['Username']; ?>:</strong> 
                                   <?php echo htmlspecialchars($comment['CommentContent']); ?></p>
                                <p style="font-size: 0.8em; color: #888;"><?php echo $comment['CommentedAt']; ?></p>
                            </div>
                    <?php
                        endwhile;
                    else:
                        echo "<p>No comments yet.</p>";
                    endif;
                    ?>
                    <form action="interactions/add_comment.php" method="POST" onclick="preventClickPropagation(event)">
                        <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                        <textarea name="comment_content" placeholder="Write a comment..." required onclick="preventClickPropagation(event)"></textarea>
                        <button type="submit" onclick="preventClickPropagation(event)">Post Comment</button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No posts to display.</p>
    <?php endif; ?>
</section>

<!-- Posts In My Groups Section -->
<section>
    <h2>Posts In My Groups</h2>
    <?php if ($resultGroupPosts->num_rows > 0): ?>
        <?php while ($row = $resultGroupPosts->fetch_assoc()): ?>
            <div class="post" onclick="toggleCommentSection(this)">
                <h3>Post by <?php echo $row['Username']; ?></h3>
                <?php if (!empty($row['PostText'])): ?>
                    <p><?php echo htmlspecialchars($row['PostText']); ?></p>
                <?php endif; ?>
                <!-- Comments Section -->
                <div class="comment-section">
                    <?php
                    $post_id = $row['PostID'];
                    $comments_result = $conn->query("
                        SELECT Comment.*, Member.Username 
                        FROM Comment 
                        JOIN Member ON Comment.MemberID = Member.MemberID 
                        WHERE PostID = $post_id 
                        ORDER BY Comment.CommentedAt ASC
                    ");
                    if ($comments_result->num_rows > 0):
                        while ($comment = $comments_result->fetch_assoc()):
                    ?>
                            <div class="comment">
                                <p><strong><?php echo $comment['Username']; ?>:</strong> 
                                   <?php echo htmlspecialchars($comment['CommentContent']); ?></p>
                                <p style="font-size: 0.8em; color: #888;"><?php echo $comment['CommentedAt']; ?></p>
                            </div>
                    <?php
                        endwhile;
                    else:
                        echo "<p>No comments yet.</p>";
                    endif;
                    ?>
                    <form action="interactions/add_comment.php" method="POST" onclick="preventClickPropagation(event)">
                        <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                        <textarea name="comment_content" placeholder="Write a comment..." required onclick="preventClickPropagation(event)"></textarea>
                        <button type="submit" onclick="preventClickPropagation(event)">Post Comment</button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No posts to display.</p>
    <?php endif; ?>
</section>

</div>

    <script>
        function toggleCommentSection(postElement) {
            const commentSection = postElement.querySelector('.comment-section');
            if (commentSection) {
                commentSection.style.display =
                    commentSection.style.display === 'none' || !commentSection.style.display
                        ? 'block'
                        : 'none';
            }
        }

        function preventClickPropagation(event) {
            event.stopPropagation();
        }

        function toggleFileInput(postType) {
            const fileInput = document.getElementById('file_input');
            fileInput.style.display = (postType === 'text') ? 'none' : 'block';
        }

        setTimeout(function () {
            const banner = document.querySelector('.success-banner');
            if (banner) banner.style.display = 'none';
        }, 1000);
    </script>
</body>

</html>
