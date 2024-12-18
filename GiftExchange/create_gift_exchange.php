<?php
require '../session/db_connect.php';
session_start();
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
    <a href="/comp353/index.php" style="text-decoration: none; color: white; font-size: 1.2rem; font-weight: bold;">
        Home
    </a>
    <a href="../groups/group_tab.php" style="margin-left: 70%">
        <button>
            <h3>Join a Group</h3>
        </button>
    </a>
    <?php
    if (isset($_SESSION['username'])) {
        echo '<a href="../profile.php"><button><h3>Profile</h3></button></a>';
    } else {
        echo '<a href="../account_setup.php"><button><h3>Log In or Sign Up</h3></button></a>';
    }
    ?>
</div>

<!-- Post Form -->
<div class="post-form">
    <h3>Create a Gift Exchange </h3>
    <form action="add_giftexchange.php" method="POST" enctype="multipart/form-data">
        <label>Gift Exchange Name:</label>
        <input type="text" name="ge_name" id="ge_name" placeholder="Give your gift exchange a name" required><br>

        <label for="ge_desc">Event Information:</label>
        <textarea name="ge_desc" id="ge_desc" placeholder="Describe your event"></textarea>

        <label for="ge_date">Event Date/Place:</label>
        <textarea name="ge_date" id="ge_date" placeholder="When/where will the gift exchange be?"></textarea>
        <button type="submit">Create</button>
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
    <?php
    $result = $conn->query("SELECT GiftExchange.*, Member.username FROM GiftExchange JOIN Member ON GiftExchange.memberid = Member.memberid");
    //$result = $conn->query("SELECT Post.*, Member.username FROM Post JOIN Member ON Post.memberid = Member.memberid ORDER BY Post.PostedAt DESC");



    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '<div class="post" onclick="toggleCommentSection(this)">';
            echo '<h3 >' . $row['GiftExchangeName'] . '</h3>';

            if (!empty($row['GiftExchangeDesc'])) {
                echo '<p>Description:<br>' . htmlspecialchars($row['GiftExchangeDesc']) . '</p><br>';
            }

            if (!empty($row['GiftExchangeDate'])) {
                echo '<p>Date/Place:<br>' . htmlspecialchars($row['GiftExchangeDate']) . '</p><br>';
            }

            $giftexchange_id = $row['GiftExchangeID'];
            $gifts_result = $conn->query("SELECT Gift.* FROM Gift JOIN Member ON Gift.GiftforID = Member.memberid WHERE Gift.GiftExchangeEventID = $giftexchange_id");

            echo '<div class="comment-section">';
            if ($gifts_result->num_rows > 0) {
                while ($gift = $gifts_result->fetch_assoc()) {
                    $giftforid = $gift['GiftforID'];
                    $giftusername = $conn->query("SELECT Member.username FROM Member WHERE Member.memberid = $giftforid");

                    while ($giftu = $giftusername->fetch_assoc()) {
                        echo '<div class="comment">';
                        echo '<p><strong>' . $giftu['username'] . '\'s wish list item: </strong> ' . htmlspecialchars($gift['GiftName']) . '</p>';
                        echo '</div>';
                    }
                }
            } else {
                echo '<p>No gifts requested yet.</p>';
            }

            echo '<form action="add_gift.php" method="POST" onclick="preventClickPropagation(event)">';
            echo '<input type="hidden" name="giftexchange_id" value="' . $giftexchange_id . '">';
            echo '<textarea name="gift_name" placeholder="What gift would you like?" required onclick="preventClickPropagation(event)"></textarea>';
            echo '<button type="submit" onclick="preventClickPropagation(event)">Suggest Gift</button>';
            echo '</form>';
            echo '</div>'; // End of gifts section
            echo '</div>'; // End of gift exchange section
        }
    } else {
        echo "<p>No gift exchanges are happening.</p>";
    }

    $conn->close();
    ?>
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
