<?php
require '../session/db_connect.php';
session_start();


if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
$username = $_SESSION['username'];


$stmt = $conn->prepare("SELECT memberid FROM Member WHERE username = ?");
if (!$stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc(); // Fetch the row as an associative array
    $memberid = $row['memberid'];  // Extract the member ID
    $_SESSION['memberid'] = $memberid;
} else {
    die("Error: User not found.");
}

// Retrieve user information from session and URL parameters
$loggedInUserID = $_SESSION['memberid'];
$friendID = isset($_GET['friendID']) ? intval($_GET['friendID']) : 0;
$loggedInUsername = $_SESSION['username'];


if ($friendID <= 0) {
    die("Invalid friend ID.");
}


$stmt = $conn->prepare("SELECT Username FROM Member WHERE MemberID = ?");
if (!$stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$stmt->bind_param("i", $friendID);
$stmt->execute();
$stmt->bind_result($friendUsername);
$stmt->fetch();
$stmt->close();

if (empty($friendUsername)) {
    die("User not found."); // Handle invalid friend ID
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['messageContent'])) {
    $messageContent = trim($_POST['messageContent']);

    if (!empty($messageContent)) {
        $stmt = $conn->prepare("INSERT INTO Message (MemberID1, MemberID2, MessageContent, SentAt) VALUES (?, ?, ?, NOW())");
        if (!$stmt) {
            die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }
        $stmt->bind_param("iis", $loggedInUserID, $friendID, $messageContent);
        if ($stmt->execute()) {
            $stmt->close();
            // Refresh the page to show the new message
            header("Location: message.php?friendID=" . $friendID);
            exit;
        } else {
            $stmt->close();
            die("Error: Unable to send message.");
        }
    } else {
        // Optional: Handle empty message content
        // For example, set an error message in the session or display on the page
    }
}


$stmt = $conn->prepare("
    SELECT MemberID1, MemberID2, MessageContent, SentAt 
    FROM Message 
    WHERE (MemberID1 = ? AND MemberID2 = ?) 
       OR (MemberID1 = ? AND MemberID2 = ?) 
    ORDER BY SentAt ASC
");
if (!$stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
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
       
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

       
        body {
            display: flex;
            justify-content: center;
            background-color: #f0f2f5;
            font-family: Arial, sans-serif;
            padding-top: 60px; /* Adjusted to accommodate the fixed top bar */
            overflow-y: scroll;
        }

        
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

        .top-bar .button-group {
            display: flex;
            gap: 10px;
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

        
        .container {
            width: 100%;
            max-width: 600px;
            padding: 10px;
        }

        
        .message-box {
            width: 100%;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .message-box h2 {
            margin-bottom: 20px;
            color: #4c87ae;
        }

        
        .messages {
            max-height: 400px;
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
            display: flex;
            flex-direction: column;
        }
        .message.sent {
            background-color: #dcf8c6;
            align-self: flex-end;
        }
        .message.received {
            background-color: #f1f0f0;
            align-self: flex-start;
        }
        .message p {
            margin: 0;
            word-wrap: break-word;
        }
        .message small {
            font-size: 0.8em;
            color: #555;
            margin-top: 5px;
            align-self: flex-end;
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
            font-size: 1em;
        }
        .message-form button {
            padding: 10px 20px;
            border: none;
            background-color: #007BFF;
            color: #fff;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 1em;
        }
        .message-form button:hover {
            background-color: #0056b3;
        }

        /* Responsive Design */
        @media (max-width: 600px) {
            .top-bar h1 {
                font-size: 1.2em;
            }
            .top-bar button {
                padding: 8px 12px;
                font-size: 0.9em;
            }
            .message-form textarea {
                width: 70%;
            }
            .message-form button {
                padding: 8px 16px;
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="left">
            <h1>Messages</h1>
        </div>
        <div class="center">
            <a href="friendlist.php">
                <button>
                    <h3>Friend List</h3>
                </button>
            </a>
        </div>
        <div class="right">
            <a href="friend.php">
                <button>
                    <h3>Add Friends</h3>
                </button>
            </a>
        </div>
    </div>

    <!-- Main Content Container -->
    <div class="container">
        <div class="message-box">
            <h2>Messages with <?php echo htmlspecialchars($friendUsername); ?></h2>

            <!-- Display messages -->
            <div class="messages">
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="message <?php echo $message['MemberID1'] === $loggedInUserID ? 'sent' : 'received'; ?>">
                            <p><?php echo htmlspecialchars($message['MessageContent']); ?></p>
                            <small><?php echo date('Y-m-d H:i', strtotime($message['SentAt'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No messages yet. Start the conversation!</p>
                <?php endif; ?>
            </div>

            <!-- Message sending form -->
            <form class="message-form" action="message.php?friendID=<?php echo $friendID; ?>" method="post">
                <textarea name="messageContent" rows="3" placeholder="Type your message here..." required></textarea>
                <button type="submit">Send</button>
            </form>
        </div>
    </div>
</body>
</html>
