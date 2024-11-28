<?php
require '../session/db_connect.php';
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
$username = $_SESSION['username'];

// Fetch the member ID
$stmt = $conn->prepare("SELECT memberid FROM Member WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc(); // Fetch the row as an associative array
    $memberid = $row['memberid'];  // Extract the member ID
    $_SESSION['memberid'] = $memberid;
} else {
    die("Error: User not found.");
}

// Handle friend removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['removeFriendID'])) {
    $removeFriendID = $_POST['removeFriendID'];

    // Delete the friendship entry where the logged-in user is either MemberID1 or MemberID2
    $stmt = $conn->prepare("DELETE FROM Friendship WHERE (MemberID1 = ? AND MemberID2 = ?) OR (MemberID1 = ? AND MemberID2 = ?)");
    $stmt->bind_param("iiii", $memberID, $removeFriendID, $removeFriendID, $memberID);

    if ($stmt->execute()) {
        $message = "Friend removed successfully.";
    } else {
        $message = "Failed to remove friend.";
    }
    $stmt->close();
}

// Retrieve friends of the logged-in user
$stmt = $conn->prepare("
    SELECT m.MemberID, m.Username, f.RelationshipType 
    FROM Friendship f
    JOIN Member m ON (f.MemberID1 = m.MemberID AND f.MemberID2 = ?)
                OR (f.MemberID2 = m.MemberID AND f.MemberID1 = ?)
");
$stmt->bind_param("ii", $memberID, $memberID);
$stmt->execute();
$result = $stmt->get_result();
$friends = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friend List</title>
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
        .friend-list h2 {
            text-align: center;
            margin-bottom: 20px; /* Adds some space below the heading */
            font-size: 1.5em; /* Optional: Adjust the font size if needed */
            color: #333; /* Optional: Adjust the color */
        }
    </style>
</head>
<body>

<div class="top-bar">
        <h1>Friends List</h1>
        <a href="friend.php" style="margin-left: -2%">
            <button>
                <h3>Add Friends</h3>
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

    <div class="friend-list">
    <h2>Your Friends</h2>
    <?php if (isset($message)): ?>
        <p><?php echo $message; ?></p>
    <?php endif; ?>
    <?php if (count($friends) > 0): ?>
        <ul>
            <?php foreach ($friends as $friend): ?>
                <li class="friend" id="friend-<?php echo $friend['MemberID']; ?>">
                    <span><?php echo htmlspecialchars($friend['Username']); ?> (<?php echo htmlspecialchars($friend['RelationshipType']); ?>)</span>
                    <div>
                        <form action="message.php" method="get" style="display: inline;">
                            <input type="hidden" name="userID" value="<?php echo $memberID; ?>">
                            <input type="hidden" name="friendID" value="<?php echo $friend['MemberID']; ?>">
                            <button class="message-button" type="submit">Message</button>
                        </form>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="removeFriendID" value="<?php echo $friend['MemberID']; ?>">
                            <button class="remove-button" type="submit">Remove</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>You have no friends in your list.</p>
    <?php endif; ?>
</div>

</body>
</html>
