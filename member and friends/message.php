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

// Retrieve user information from session and URL parameters
$loggedInUserID = $_SESSION['memberID'];
$friendID = isset($_GET['friendID']) ? intval($_GET['friendID']) : 0;
$loggedInUsername = $_SESSION['username'];

// Retrieve the friend's username
$stmt = $conn->prepare("SELECT Username FROM Member WHERE MemberID = ?");
$stmt->bind_param("i", $friendID);
$stmt->execute();
$stmt->bind_result($friendUsername);
$stmt->fetch();
$stmt->close();

if (empty($friendUsername)) {
    die("User not found."); // Handle invalid friend ID
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['messageContent'])) {
    $messageContent = trim($_POST['messageContent']);

    if (!empty($messageContent)) {
        $stmt = $conn->prepare("INSERT INTO Message (MemberID1, MemberID2, MessageContent, SentAt) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $loggedInUserID, $friendID, $messageContent);
        $stmt->execute();
        $stmt->close();
        // Refresh the page to show the new message
        header("Location: message.php?friendID=" . $friendID);
        exit;
    }
}

// Retrieve messages between the logged-in user and the friend
$stmt = $conn->prepare("
    SELECT MemberID1, MemberID2, MessageContent, SentAt 
    FROM Message 
    WHERE (MemberID1 = ? AND MemberID2 = ?) 
       OR (MemberID1 = ? AND MemberID2 = ?) 
    ORDER BY SentAt ASC
");
$stmt->bind_param("iiii", $loggedInUserID, $friendID, $friendID, $loggedInUserID);
$stmt->execute();
$messagesResult = $stmt->get_result();
$messages = $messagesResult->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages with <?php echo htmlspecialchars($friendUsername); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .username {
            position: absolute;
            top: 10px;
            left: 20px;
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
        }
        .message-box {
            max-width: 600px;
            width: 100%;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .messages {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .message {
            padding: 10px;
            border-radius: 4px;
            max-width: 80%;
            clear: both;
            display: flex;
            flex-direction: column;
        }
        .message.sent {
            background-color: #dcf8c6;
            align-self: flex-start;
        }
        .message.received {
            background-color: #f1f0f0;
            align-self: flex-end;
        }
        .message p {
            margin: 0;
        }
        .message small {
            font-size: 0.8em;
            color: #555;
            margin-top: 5px;
        }
        .message-form {
            display: flex;
            justify-content: space-between;
            width: 100%;
        }
        .message-form textarea {
            width: 80%;
            padding: 10px;
            resize: none;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        .message-form button {
            padding: 10px 20px;
            border: none;
            background-color: #007BFF;
            color: #fff;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <!-- Display Username in the Top Left Corner -->
    <div class="username"><?php echo htmlspecialchars($loggedInUsername); ?></div>

    <div class="message-box">
        <h2>Messages with <?php echo htmlspecialchars($friendUsername); ?></h2>

        <!-- Display messages -->
        <div class="messages">
            <?php foreach ($messages as $message): ?>
                <div class="message <?php echo $message['MemberID1'] === $loggedInUserID ? 'sent' : 'received'; ?>">
                    <p><?php echo htmlspecialchars($message['MessageContent']); ?></p>
                    <small><?php echo date('Y-m-d H:i', strtotime($message['SentAt'])); ?></small>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Message sending form -->
        <form class="message-form" action="message.php?friendID=<?php echo $friendID; ?>" method="post">
            <textarea name="messageContent" rows="3" placeholder="Type your message here..."></textarea>
            <button type="submit">Send</button>
        </form>
    </div>
</body>
</html>
