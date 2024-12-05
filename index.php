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

// Fetch "Posts In My Groups" with Group Information
$stmtGroupPosts = $conn->prepare("
    SELECT Post.*, Member.Username, GroupList.GroupID, GroupList.GroupName, GroupList.OwnerID
    FROM Post
    JOIN Member ON Post.MemberID = Member.MemberID
    JOIN GroupMember gm ON Post.MemberID = gm.MemberID
    JOIN GroupList ON gm.GroupID = GroupList.GroupID
    WHERE gm.GroupID IN (
        SELECT GroupID 
        FROM GroupMember 
        WHERE MemberID = ?
    )
    AND Post.MemberID != ? -- Exclude user's own posts
    ORDER BY GroupList.GroupName, Post.PostedAt DESC
");
$stmtGroupPosts->bind_param("ii", $memberID, $memberID);
$stmtGroupPosts->execute();
$resultGroupPosts = $stmtGroupPosts->get_result();
$stmtGroupPosts->close();

// Organize posts by group and ownership
$groupedPosts = [];
$groupInfo = []; // To store group ownership info
while ($row = $resultGroupPosts->fetch_assoc()) {
    $groupID = $row['GroupID'];
    $groupName = $row['GroupName'];
    $ownerID = $row['OwnerID'];
    $isOwner = $ownerID == $memberID;

    $groupInfo[$groupName] = $isOwner ? "(owner)" : "(member)"; // Store relationship
    $groupedPosts[$groupName][] = $row; // Group posts under group names
}

// Fetch "Public Posts (Admin)"
$stmtAdminPosts = $conn->prepare("
    SELECT Post.*, Member.Username 
    FROM Post 
    JOIN Member ON Post.MemberID = Member.MemberID 
    WHERE Member.Privilege = 'Administrator'
    ORDER BY Post.PostedAt DESC
");
$stmtAdminPosts->execute();
$resultAdminPosts = $stmtAdminPosts->get_result();
$stmtAdminPosts->close();

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
            height: 200px;
            /* Fixed height */
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

        .plebiscite-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            /* Ensure it stays above other elements */
        }

        .plebiscite-button button {
            background-color: #4c87ae;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 1em;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s, transform 0.2s;
        }

        .plebiscite-button button:hover {
            background-color: #35688a;
            transform: translateY(-2px);
        }

        /* Modal styling */
        .modal {
            display: none;
            /* Hidden by default */
            position: fixed;
            z-index: 1000;
            /* On top */
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
            /* Black background with opacity */
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            /* Center the modal */
            padding: 20px;
            border: 1px solid #888;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
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
    <?php if (isset($_GET['message'])): ?>
        <div class="success-banner">
            <?php echo htmlspecialchars($_GET['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Public Posts (Admin) Section -->
    <section style="margin-top: 10px; margin-left: 20px; margin-right: 20px;">
        <h2>Public Posts (Admin)</h2>
        <?php if ($resultAdminPosts->num_rows > 0): ?>
            <?php while ($row = $resultAdminPosts->fetch_assoc()): ?>
                <div class="post" onclick="toggleCommentSection(this)">
                    <h3>Post by <?php echo htmlspecialchars($row['Username']); ?></h3>
                    <p style="font-size: 0.8em; color: #888;">Posted on: <?php echo htmlspecialchars($row['PostedAt']); ?></p>
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
                            <textarea name="comment_content" placeholder="Write a comment..." required
                                onclick="preventClickPropagation(event)"></textarea>
                            <button type="submit" onclick="preventClickPropagation(event)">Post Comment</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No posts to display.</p>
        <?php endif; ?>
    </section>

    <!-- Posts and Comments -->
    <div class="container">

        <!-- My Posts Section -->
        <section>
            <h2>My Posts</h2>
            <?php if ($resultMyPosts->num_rows > 0): ?>
                <?php while ($row = $resultMyPosts->fetch_assoc()): ?>
                    <div class="post" onclick="toggleCommentSection(this)">
                        <h3>Post by You</h3>
                        <p style="font-size: 0.8em; color: #888;">Posted on: <?php echo htmlspecialchars($row['PostedAt']); ?>
                        </p>
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
                                <textarea name="comment_content" placeholder="Write a comment..." required
                                    onclick="preventClickPropagation(event)"></textarea>
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
            <?php if (!empty($groupedPosts)): ?>
                <?php foreach ($groupedPosts as $groupName => $posts): ?>
                    <div class="group-section">
                        <h3>
                            Group: <?php echo htmlspecialchars($groupName); ?>
                            <span style="font-weight: normal; font-size: 0.9em;">
                                <?php echo htmlspecialchars($groupInfo[$groupName]); ?>
                            </span>
                        </h3>
                        <?php foreach ($posts as $row): ?>
                            <?php
                            // Parse visibility JSON
                            $visibility = json_decode($row['Visibility'], true);
                            $level = $visibility['level'] ?? 'view_add_or_link';
                            switch ($level) {
                                case 'view_only':
                                    $permissionLabel = 'View Only';
                                    break;
                                case 'view_and_comment':
                                    $permissionLabel = 'View and Comment';
                                    break;
                                case 'view_add_or_link':
                                    $permissionLabel = 'View, Add, or Link to Other Contents';
                                    break;
                                default:
                                    $permissionLabel = 'Unknown';
                                    break;
                            }
                            ?>
                            <div class="post" style="position: relative;" onclick="toggleCommentSection(this)">
                                <h4>Post by <?php echo htmlspecialchars($row['Username']); ?></h4>
                                <p style="font-size: 0.8em; color: #888;">Posted on:
                                    <?php echo htmlspecialchars($row['PostedAt']); ?>
                                </p>

                                <!-- "Contents and Permissions" button -->
                                <?php if ($row['OwnerID'] == $memberID): ?>
                                    <button
                                        style="position: absolute; top: 10px; right: 10px; background-color: #4c87ae; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;"
                                        onclick="openContentsPermissionsModal(<?php echo $row['PostID']; ?>)">
                                        Contents and Permissions
                                    </button>
                                <?php endif; ?>

                                <?php if (!empty($row['PostText'])): ?>
                                    <p><?php echo htmlspecialchars($row['PostText']); ?></p>
                                <?php endif; ?>

                                <!-- Comments Section -->
                                <div class="comment-section">
                                    <?php
                                    $post_id = $row['PostID'];
                                    $visibility = json_decode($row['Visibility'], true);
                                    $permissionLevel = $visibility['level'] ?? 'view_add_or_link';

                                    if ($permissionLevel === 'view_only') {
                                        // Display message for "view-only" posts
                                        echo "<p style='color: red; font-weight: bold;'>This post is view-only. Comments are disabled.</p>";
                                    } else {
                                        // Fetch and display comments
                                        $comments_result = $conn->query("
            SELECT Comment.*, Member.Username 
            FROM Comment 
            JOIN Member ON Comment.MemberID = Member.MemberID 
            WHERE PostID = $post_id 
            ORDER BY Comment.CommentedAt ASC
        ");

                                        if ($comments_result->num_rows > 0) {
                                            while ($comment = $comments_result->fetch_assoc()) {
                                                echo "<div class='comment'>";
                                                echo "<p><strong>" . htmlspecialchars($comment['Username']) . ":</strong> " . htmlspecialchars($comment['CommentContent']) . "</p>";
                                                echo "<p style='font-size: 0.8em; color: #888;'>" . $comment['CommentedAt'] . "</p>";
                                                echo "</div>";
                                            }
                                        } else {
                                            echo "<p>No comments yet.</p>";
                                        }
                                        ?>
                                        <!-- Display comment form if not "view-only" -->
                                        <form action="interactions/add_comment.php" method="POST"
                                            onclick="preventClickPropagation(event)">
                                            <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                                            <textarea name="comment_content" placeholder="Write a comment..." required
                                                onclick="preventClickPropagation(event)"></textarea>
                                            <button type="submit" onclick="preventClickPropagation(event)">Post Comment</button>
                                        </form>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No posts to display.</p>
            <?php endif; ?>
        </section>

        <!-- Modal Structure -->
        <div id="contentsPermissionsModal"
            style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000;">
            <div
                style="background: white; padding: 20px; border-radius: 8px; max-width: 500px; width: 90%; position: relative;">
                <button
                    style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 24px; font-weight: bold; color: black; cursor: pointer;"
                    onclick="closeContentsPermissionsModal()">×</button>
                <h3>Set Permissions for Post</h3>
                <div id="modalContent">
                    <form id="setPermissionsForm" onsubmit="submitPermissions(event)">
                        <input type="hidden" id="modalPostID" name="postID">
                        <div style="margin-bottom: 10px;">
                            <label for="permissionLevel">Choose Permission Level:</label>
                            <select id="permissionLevel" name="permissionLevel" style="width: 100%; padding: 8px;">
                                <option value="view_only">View Only</option>
                                <option value="view_and_comment">View and Comment</option>
                                <option value="view_add_or_link">View, Add, or Link to Other Contents</option>
                            </select>
                        </div>
                        <div style="text-align: right;">
                            <button type="submit"
                                style="background-color: #4c87ae; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

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


        function openContentsPermissionsModal(postID) {
            const modal = document.getElementById('contentsPermissionsModal');
            const modalPostID = document.getElementById('modalPostID'); // Hidden input for postID
            const modalContent = document.getElementById('modalContent'); // Modal content area

            modal.style.display = 'flex'; // Show modal
            modalPostID.value = postID; // Set the postID for the form
            modalContent.innerHTML = '<p>Loading...</p>'; // Show loading message

            // Fetch the visibility form from contents_permissions.php
            fetch(`contents_permissions.php?postID=${postID}`)
                .then(response => response.text())
                .then(data => {
                    modalContent.innerHTML = data; // Populate modal with form
                })
                .catch(err => {
                    modalContent.innerHTML = '<p>Error loading permissions. Please try again.</p>';
                });
        }


        function closeContentsPermissionsModal() {
            const modal = document.getElementById('contentsPermissionsModal');
            modal.style.display = 'none';
        }

        function submitPermissions(event) {
            event.preventDefault();

            const formData = new FormData(document.getElementById('setPermissionsForm'));

            fetch('save_permissions.php', {
                method: 'POST',
                body: formData,
            })
                .then(response => response.text())
                .then(data => {
                    alert(data); // Display success or error message
                    closeContentsPermissionsModal();
                    location.reload(); // Reload page to reflect changes
                })
                .catch(err => {
                    alert('An error occurred. Please try again.');
                });
        }

        function openPlebisciteModal() {
            const modal = document.getElementById('plebisciteModal');
            const businessAccountsList = document.getElementById('businessAccountsList');

            modal.style.display = 'block'; // Show modal
            businessAccountsList.innerHTML = '<li>Loading...</li>'; // Placeholder while fetching

            // Fetch the business accounts from the server
            fetch('interactions/fetch_business_accounts.php', {
                credentials: 'include', // Add this line
            })
                .then(response => response.text())
                .then(data => {
                    businessAccountsList.innerHTML = data; // Populate modal with fetched data
                })
                .catch(err => {
                    businessAccountsList.innerHTML = '<li>Failed to fetch business accounts.</li>';
                });
        }


        function closePlebisciteModal() {
            const modal = document.getElementById('plebisciteModal');
            modal.style.display = 'none';
        }

        function voteToOust(memberID, button) {
            button.disabled = true; // Prevent multiple clicks
            button.textContent = "Voting...";

            fetch('interactions/vote_to_oust.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `memberID=${memberID}`,
                credentials: 'include', // Add this line
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.status === 'success') {
                        button.textContent = "Voted";
                        button.style.backgroundColor = "#ccc"; // Change button appearance
                        button.style.cursor = "default";
                    } else {
                        button.textContent = "Vote to Oust";
                        button.disabled = false; // Re-enable button on error
                        alert(data.message);
                    }
                })
                .catch((err) => {
                    button.textContent = "Vote to Oust";
                    button.disabled = false;
                    alert("An error occurred. Please try again.");
                });
        }

    </script>
    <div class="plebiscite-button" style="position: fixed; bottom: 10px; right: 10px;">
        <button onclick="openPlebisciteModal()">Organize a Plebiscite</button>
    </div>

    <div id="plebisciteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closePlebisciteModal()">&times;</span>
            <h3>Organize a Plebiscite</h3>
            <ul id="businessAccountsList"></ul>
        </div>
    </div>


</body>

</html>