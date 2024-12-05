<?php
session_start();
require '../session/db_connect.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];

$stmt = $conn->prepare("SELECT MemberID FROM Member WHERE Username = ?");
if (!$stmt) {
    die("Database Error: " . $conn->error);
}
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $memberID = $row['MemberID'];
    $_SESSION['memberid'] = $memberID;
} else {
    die("Error: User not found.");
}

$loggedInUserID = $_SESSION['memberid'];
$friendID = isset($_GET['friendID']) ? intval($_GET['friendID']) : 0;
$loggedInUsername = $_SESSION['username'];

if ($friendID <= 0) {
    die("Invalid friend ID.");
}

$stmt = $conn->prepare("SELECT Username FROM Member WHERE MemberID = ?");
if (!$stmt) {
    die("Database Error: " . $conn->error);
}
$stmt->bind_param("i", $friendID);
$stmt->execute();
$stmt->bind_result($friendUsername);
$stmt->fetch();
$stmt->close();

if (empty($friendUsername)) {
    die("User not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $messageContent = isset($_POST['messageContent']) ? trim($_POST['messageContent']) : '';
    $mediaType = 'None';
    $mediaPath = NULL;

    if (isset($_FILES['media']) && $_FILES['media']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $allowedVideoTypes = ['video/mp4', 'video/avi', 'video/mpeg'];
        $allowedTypes = array_merge($allowedImageTypes, $allowedVideoTypes);

        $fileTmpPath = $_FILES['media']['tmp_name'];
        $fileName = basename($_FILES['media']['name']);
        $fileSize = $_FILES['media']['size'];
        $fileType = mime_content_type($fileTmpPath);
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $uploadDir = __DIR__ . '/../uploads/messages/';
        $uploadFileDir = realpath($uploadDir) ? realpath($uploadDir) . '/' : $uploadDir;

        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }

        if (in_array($fileType, $allowedImageTypes)) {
            $mediaType = 'Image';
        } elseif (in_array($fileType, $allowedVideoTypes)) {
            $mediaType = 'Video';
        } else {
            $mediaType = 'None';
        }

        if ($fileSize <= 5 * 1024 * 1024 && $mediaType !== 'None') {
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $destPath = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $mediaPath = 'uploads/messages/' . $newFileName;
            } else {
                $mediaType = 'None';
            }
        }
    }

    if (!empty($messageContent) || $mediaType !== 'None') {
        $stmt = $conn->prepare("INSERT INTO Message (MemberID1, MemberID2, MessageContent, SentAt, MediaType, MediaPath) VALUES (?, ?, ?, NOW(), ?, ?)");
        if (!$stmt) {
            die("Database Error: " . $conn->error);
        }
        $stmt->bind_param("iisss", $loggedInUserID, $friendID, $messageContent, $mediaType, $mediaPath);
        $stmt->execute();
        $stmt->close();
        header("Location: message.php?friendID=" . $friendID);
        exit;
    }
}

$stmt = $conn->prepare("
    SELECT MemberID1, MemberID2, MessageContent, SentAt, MediaType, MediaPath 
    FROM Message 
    WHERE (MemberID1 = ? AND MemberID2 = ?) 
       OR (MemberID1 = ? AND MemberID2 = ?) 
    ORDER BY SentAt ASC
");
if (!$stmt) {
    die("Database Error: " . $conn->error);
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
            padding-top: 60px;
            margin: 0;
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
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .top-bar h1 {
            font-size: 1.5em;
            margin: 0;
        }

        .top-bar .button-container {
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
            width: 140px;
            text-align: center;
            white-space: nowrap;
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
            word-wrap: break-word;
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
        }

        .message small {
            font-size: 0.8em;
            color: #555;
            margin-top: 5px;
            align-self: flex-end;
        }

        .message-media img, .message-media video {
            max-width: 100%;
            border-radius: 4px;
            margin-top: 10px;
        }

        .message-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .message-form textarea {
            width: 100%;
            padding: 10px;
            resize: none;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-size: 1em;
        }

        .message-form .form-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .message-form input[type="file"] {
            flex: 1;
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
            margin-left: 10px;
            white-space: nowrap;
        }

        .message-form button:hover {
            background-color: #0056b3;
        }

        .messages::-webkit-scrollbar {
            width: 8px;
        }

        .messages::-webkit-scrollbar-track {
            background: #f1f1f1; 
        }

        .messages::-webkit-scrollbar-thumb {
            background: #4c87ae; 
            border-radius: 4px;
        }

        .messages::-webkit-scrollbar-thumb:hover {
            background: #6caad3; 
        }

        @media (max-width: 600px) {
            .top-bar h1 {
                font-size: 1.2em;
            }

            .top-bar button {
                padding: 8px 12px;
                font-size: 0.9em;
                width: 100px;
            }

            .message-form button {
                padding: 8px 16px;
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <h1>Fee</h1>
        <div class="button-container">
            <a href="profile.php"><button>Profile</button></a>
            <a href="index.php"><button>Homepage</button></a>
        </div>
    </div>

    <div class="container">
        <div class="message-box">
            <h2>Messages with <?php echo htmlspecialchars($friendUsername); ?></h2>
            <div class="messages" id="messages">
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="message <?php echo $message['MemberID1'] === $loggedInUserID ? 'sent' : 'received'; ?>">
                            <?php if (!empty($message['MessageContent'])): ?>
                                <p><?php echo htmlspecialchars($message['MessageContent']); ?></p>
                            <?php endif; ?>
                            <?php if ($message['MediaType'] === 'Image' && !empty($message['MediaPath'])): ?>
                                <div class="message-media">
                                    <img src="<?php echo htmlspecialchars($message['MediaPath']); ?>" alt="Image">
                                </div>
                            <?php elseif ($message['MediaType'] === 'Video' && !empty($message['MediaPath'])): ?>
                                <div class="message-media">
                                    <video controls>
                                        <source src="<?php echo htmlspecialchars($message['MediaPath']); ?>" type="<?php echo mime_content_type(__DIR__ . '/../' . $message['MediaPath']); ?>">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
                            <?php endif; ?>
                            <small><?php echo date('Y-m-d H:i', strtotime($message['SentAt'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No messages yet. Start the conversation!</p>
                <?php endif; ?>
            </div>
            <form class="message-form" action="message.php?friendID=<?php echo $friendID; ?>" method="post" enctype="multipart/form-data">
                <textarea name="messageContent" rows="3" placeholder="Type your message here..."></textarea>
                <div class="form-controls">
                    <input type="file" name="media" accept="image/*,video/*">
                    <button type="submit">Send</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        window.onload = function() {
            var messagesDiv = document.getElementById('messages');
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        };
    </script>
</body>
</html>
