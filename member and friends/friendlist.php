<?php
session_start(); // Start or resume the session

// Check if the user is logged in; if not, redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'project');

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the logged-in user's ID and username
$memberID = $_SESSION['memberID'];
$username = $_SESSION['username'];

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
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        /* Username in the top-left corner */
        .username {
            position: absolute;
            top: 10px;
            left: 20px;
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
        }
        .back-button {
            margin-bottom: 20px;
            text-align: center;
        }
        .back-button button {
            background-color: #007BFF;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 5px;
            text-transform: uppercase;
        }
        .back-button button:hover {
            background-color: #0056b3;
        }
        .friend-list {
            max-width: 600px;
            width: 100%;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .friend-list h2 {
            text-align: center;
            color: #333;
        }
        .friend {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .friend:last-child {
            border-bottom: none;
        }
        .relationship-type {
            color: #555;
            font-style: italic;
        }
        .remove-button, .message-button {
            background-color: #28a745;
            color: #fff;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .remove-button {
            background-color: #dc3545;
        }
    </style>
</head>
<body>
    <!-- Display Username in the Top Left Corner -->
    <div class="username"><?php echo htmlspecialchars($username); ?></div>

    <!-- Back to Main Button -->
    <div class="back-button">
        <form action="main.php" method="get">
            <button type="submit">Back to Main</button>
        </form>
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
