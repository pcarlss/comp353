<?php
session_start(); // Start or resume the session

// Ensure the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Get the logged-in user's ID and username
$loggedInUsername = $_SESSION['username'];
$loggedInUserID = $_SESSION['memberID']; // Assume this is stored in the session

// Database connection settings
$host = 'localhost';
$dbname = 'project';
$username = 'root';
$password = '';  // Use an empty string if no password is set

// Create a new MySQLi instance
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Retrieve usernames excluding the logged-in user and users who are blocked or have blocked the user
$users = [];




$stmt = $conn->prepare("
    SELECT MemberID, Username, 
    CASE WHEN EXISTS (
        SELECT 1 FROM Friendship 
        WHERE (MemberID1 = ? AND MemberID2 = Member.MemberID) 
        OR (MemberID2 = ? AND MemberID1 = Member.MemberID)
    ) THEN 1 ELSE 0 END AS IsFriend,
    CASE WHEN EXISTS (
        SELECT 1 FROM Blocked 
        WHERE (MemberID1 = ? AND MemberID2 = Member.MemberID)
    ) THEN 1 ELSE 0 END AS IsBlocked
    FROM Member 
    WHERE MemberID != ?  -- Exclude the logged-in user
    AND NOT EXISTS (
        SELECT 1 FROM Blocked 
        WHERE MemberID2 = ? AND MemberID1 = Member.MemberID
    )");
$stmt->bind_param("iiiii", $loggedInUserID, $loggedInUserID, $loggedInUserID, $loggedInUserID, $loggedInUserID);
$stmt->execute();


$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$stmt->close();








// Handle friend request or block submission via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = [];

    if (isset($_POST['requesteeID']) && isset($_POST['action'])) {
        $requesteeID = $_POST['requesteeID'];
        $action = $_POST['action'];

        if ($action === "block") {
            // Remove any existing friendship
            $stmt = $conn->prepare("DELETE FROM Friendship WHERE (MemberID1 = ? AND MemberID2 = ?) OR (MemberID2 = ? AND MemberID1 = ?)");
            $stmt->bind_param("iiii", $loggedInUserID, $requesteeID, $loggedInUserID, $requesteeID);
            $stmt->execute();
            $stmt->close();

            // Block the user
            $stmt = $conn->prepare("INSERT INTO Blocked (MemberID1, MemberID2) VALUES (?, ?)");
            $stmt->bind_param("ii", $loggedInUserID, $requesteeID);
            if ($stmt->execute()) {
                $response['message'] = "User blocked successfully.";
            } else {
                $response['message'] = "Failed to block user.";
            }
            $stmt->close();
        } elseif ($action === "unblock") {
            // Unblock the user
            $stmt = $conn->prepare("DELETE FROM Blocked WHERE MemberID1 = ? AND MemberID2 = ?");
            $stmt->bind_param("ii", $loggedInUserID, $requesteeID);
            if ($stmt->execute()) {
                $response['message'] = "User unblocked successfully.";
            } else {
                $response['message'] = "Failed to unblock user.";
            }
            $stmt->close();
        } elseif ($action === "friendRequest") {
            // Send a friend request
            $stmt = $conn->prepare("INSERT INTO FriendOrGroupRequest (RequestorID, RequesteeID, RequestMadeAt) VALUES (?, ?, ?)");
            $requestMadeAt = date("Y-m-d H:i:s");
            $stmt->bind_param("iis", $loggedInUserID, $requesteeID, $requestMadeAt);
            if ($stmt->execute()) {
                $response['message'] = "Friend request sent successfully.";
            } else {
                $response['message'] = "Failed to send friend request.";
            }
            $stmt->close();
        }

        echo json_encode($response);
        $conn->close();
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search for Users</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            min-height: 100vh;
        }
        .header {
            position: absolute;
            top: 10px;
            left: 10px;
        }
        .header h2 {
            margin: 0;
            font-size: 1.2em;
            color: #333;
        }
        .back-button {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .back-button button {
            padding: 8px 16px;
            font-size: 1em;
            cursor: pointer;
            border: none;
            background-color: #007BFF;
            color: #fff;
            border-radius: 4px;
        }
        h1 {
            font-size: 24px;
            color: #333;
            text-align: center;
            margin-bottom: 20px;
        }
        input[type="text"] {
            width: 100%;
            max-width: 300px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            width: 100%;
            max-width: 300px;
            box-shadow: 0 8px 16px 0 rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 0 0 4px 4px;
            overflow: hidden;
            top: 100%;
            text-align: left;
        }
        .dropdown-content div {
            padding: 12px 16px;
            cursor: pointer;
            color: #333;
        }
        .dropdown-content div:hover {
            background-color: #f1f1f1;
        }
        .selected-user-box {
            margin-top: 20px;
            padding: 20px;
            text-align: center;
            border: 1px solid #ccc;
            border-radius: 8px;
            width: 100%;
            max-width: 300px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }
        .selected-user-box .username-text {
            font-size: 1em;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .selected-user-box button {
            margin-top: 10px;
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background-color: #007BFF;
            color: #fff;
            border-radius: 4px;
            font-size: 1em;
            width: 100%;
        }
        .selected-user-box button.block {
            background-color: #DC3545;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2><?php echo htmlspecialchars($loggedInUsername); ?></h2>
    </div>

    <div class="back-button">
        <form action="main.php" method="get">
            <button type="submit">Back to Main</button>
        </form>
    </div>

    <h1>Search for Users</h1>
    <div class="dropdown" style="position: relative;">
        <input type="text" id="searchInput" placeholder="Enter username" autocomplete="off" onfocus="openDropdown()" oninput="filterFunction()">
        <div id="dropdownList" class="dropdown-content">
            <?php foreach ($users as $user): ?>
                <div onclick="selectUser('<?php echo $user['Username']; ?>', '<?php echo $user['MemberID']; ?>', <?php echo $user['IsFriend']; ?>, <?php echo $user['IsBlocked']; ?>)">
                    <?php echo htmlspecialchars($user['Username']); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div id="selectedUserContainer" class="selected-user-box" style="display: none;">
        <p id="selectedUsername" class="username-text"></p>
        <button id="sendRequestButton" onclick="sendFriendRequest()">Send Friend Request</button>
        <button id="blockButton" class="block" onclick="blockUser()">Block User</button>
    </div>

    <script>
        let selectedUserID;
        let isAlreadyFriend = false;
        let isBlocked = false;

        function openDropdown() {
            document.getElementById("dropdownList").style.display = "block";
        }

        function filterFunction() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toUpperCase();
            const divs = document.getElementById("dropdownList").getElementsByTagName("div");

            for (let i = 0; i < divs.length; i++) {
                const txtValue = divs[i].textContent || divs[i].innerText;
                divs[i].style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? "" : "none";
            }
        }


        function selectUser(username, userID, isFriend, isBlockedStatus) {
    document.getElementById("selectedUsername").textContent = username;
    document.getElementById("selectedUserContainer").style.display = "block";
    selectedUserID = userID;
    isAlreadyFriend = isFriend === 1;
    isBlocked = isBlockedStatus === 1;

    const friendButton = document.getElementById("sendRequestButton");
    const blockButton = document.getElementById("blockButton");

    if (isBlocked) {
        friendButton.disabled = true;
        friendButton.textContent = "User Blocked";

        blockButton.textContent = "Unblock User"; // Show "Unblock" button
        blockButton.disabled = false;
        blockButton.onclick = unblockUser; // Set unblock action
    } else if (isAlreadyFriend) {
        friendButton.disabled = true;
        friendButton.textContent = "Friends";

        blockButton.textContent = "Block User";
        blockButton.disabled = false;
        blockButton.onclick = blockUser;
    } else {
        friendButton.disabled = false;
        friendButton.textContent = "Send Friend Request";

        blockButton.textContent = "Block User";
        blockButton.disabled = false;
        blockButton.onclick = blockUser;
    }
}

function unblockUser() {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "friend.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            alert(response.message);
            isBlocked = false;
            selectUser(document.getElementById("selectedUsername").textContent, selectedUserID, isAlreadyFriend, 0);
        }
    };
    xhr.send("requesteeID=" + selectedUserID + "&action=unblock");
}

    




        
        function sendFriendRequest() {
            if (!isAlreadyFriend && !isBlocked) {
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "friend.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        alert(response.message);
                    }
                };
                xhr.send("requesteeID=" + selectedUserID + "&action=friendRequest");
            }
        }

        function blockUser() {
            if (!isBlocked) {
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "friend.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        alert(response.message);
                    }
                };
                xhr.send("requesteeID=" + selectedUserID + "&action=block");
            }
        }

        window.onclick = function(event) {
            if (!event.target.matches('#searchInput')) {
                document.getElementById("dropdownList").style.display = "none";
            }
        }
    </script>
</body>
</html>
