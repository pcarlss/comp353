<?php
require '../session/db_connect.php';
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// Get the logged-in username from the session
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

$stmt->close();

// Get the logged-in user's ID and username
$loggedInUsername = $_SESSION['username'];
$loggedInUserID = $_SESSION['memberid'];

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
    ) THEN 1 ELSE 0 END AS IsBlocked,
    CASE WHEN EXISTS (
        SELECT 1 FROM FriendOrGroupRequest 
        WHERE (RequestorID = ? AND RequesteeID = Member.MemberID)
    ) THEN 1 ELSE 0 END AS IsRequestSent
    FROM Member 
    WHERE MemberID != ?  -- Exclude the logged-in user
    AND NOT EXISTS (
        SELECT 1 FROM Blocked 
        WHERE MemberID2 = ? AND MemberID1 = Member.MemberID
    )
");
$stmt->bind_param("iiiiii", $loggedInUserID, $loggedInUserID, $loggedInUserID, $loggedInUserID, $loggedInUserID, $loggedInUserID);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

// Handle friend request, cancel friend request, or block submission via AJAX
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
        } elseif ($action === "cancelFriendRequest") {
            // Cancel a friend request
            $stmt = $conn->prepare("DELETE FROM FriendOrGroupRequest WHERE RequestorID = ? AND RequesteeID = ?");
            $stmt->bind_param("ii", $loggedInUserID, $requesteeID);
            if ($stmt->execute()) {
                $response['message'] = "Friend request canceled.";
            } else {
                $response['message'] = "Failed to cancel friend request.";
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
            background-color: #f9f9f9;
            width: 100%;
            max-width: 300px;
            box-shadow: 0 8px 16px 0 rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 0 0 4px 4px;
            overflow: hidden;
            top: 100%;
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
    <!-- Top Bar -->
    <div class="top-bar">
        <h1>Add Friends</h1>
        <a href="friendlist.php"><button>
            <h3>Friends List</h3>
        </button></a>
        <a href="../index.php"><button>
            <h3>Homepage</h3>
        </button></a>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Title for Dropdown -->
        <h1 style="text-align: center; margin-bottom: 20px;">Search for User</h1>

        <!-- Centered Dropdown -->
        <div style="display: flex; justify-content: center; align-items: center;">
            <div class="dropdown" style="position: relative; width: 100%; max-width: 300px;">
                <input type="text" id="searchInput" placeholder="Search User" autocomplete="off" onfocus="openDropdown()" oninput="filterFunction()" style="width: 100%;">
                <div id="dropdownList" class="dropdown-content">
                    <?php foreach ($users as $user): ?>
                        <div onclick="selectUser('<?php echo $user['Username']; ?>', '<?php echo $user['MemberID']; ?>', <?php echo $user['IsFriend']; ?>, <?php echo $user['IsBlocked']; ?>, <?php echo $user['IsRequestSent']; ?>)">
                            <?php echo htmlspecialchars($user['Username']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Selected User Info -->
        <div id="selectedUserContainer" class="selected-user-box" style="display: none; padding-top: 20px; margin: auto">
            <p id="selectedUsername" class="username-text"></p>
            <button id="sendRequestButton" onclick="sendFriendRequest()">Send Friend Request</button>
            <button id="blockButton" class="block" onclick="blockUser()">Block User</button>
        </div>
    </div>

    <script>
        let selectedUserID;
        let isAlreadyFriend = false;
        let isBlocked = false;
        let isRequestSent = false;

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

        function selectUser(username, userID, isFriend, isBlockedStatus, isRequestSentStatus) {
            document.getElementById("selectedUsername").textContent = username;
            document.getElementById("selectedUserContainer").style.display = "block";
            selectedUserID = userID;
            isAlreadyFriend = isFriend === 1;
            isBlocked = isBlockedStatus === 1;
            isRequestSent = isRequestSentStatus === 1;

            const friendButton = document.getElementById("sendRequestButton");
            const blockButton = document.getElementById("blockButton");

            if (isBlocked) {
                friendButton.disabled = true;
                friendButton.textContent = "User Blocked";

                blockButton.textContent = "Unblock User";
                blockButton.onclick = unblockUser;
            } else if (isAlreadyFriend) {
                friendButton.disabled = true;
                friendButton.textContent = "Friends";

                blockButton.textContent = "Block User";
                blockButton.onclick = blockUser;
            } else if (isRequestSent) {
                friendButton.disabled = false;
                friendButton.textContent = "Cancel Friend Request";
                friendButton.onclick = cancelFriendRequest;
            } else {
                friendButton.disabled = false;
                friendButton.textContent = "Send Friend Request";
                friendButton.onclick = sendFriendRequest;
            }
        }

        function sendFriendRequest() {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "friend.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    alert(response.message);

                    if (response.message === "Friend request sent successfully.") {
                        isRequestSent = true;
                        selectUser(document.getElementById("selectedUsername").textContent, selectedUserID, isAlreadyFriend, isBlocked, 1);
                    }
                }
            };
            xhr.send("requesteeID=" + selectedUserID + "&action=friendRequest");
        }

        function cancelFriendRequest() {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "friend.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    alert(response.message);

                    if (response.message === "Friend request canceled.") {
                        isRequestSent = false;
                        selectUser(document.getElementById("selectedUsername").textContent, selectedUserID, isAlreadyFriend, isBlocked, 0);
                    }
                }
            };
            xhr.send("requesteeID=" + selectedUserID + "&action=cancelFriendRequest");
        }

        function blockUser() {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "friend.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    alert(response.message);
                    isBlocked = true;
                    selectUser(document.getElementById("selectedUsername").textContent, selectedUserID, isAlreadyFriend, 1, isRequestSent);
                }
            };
            xhr.send("requesteeID=" + selectedUserID + "&action=block");
        }

        function unblockUser() {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "friend.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    alert(response.message);

                    if (response.message === "User unblocked successfully.") {
                        isBlocked = false;

                        const friendButton = document.getElementById("sendRequestButton");
                        const blockButton = document.getElementById("blockButton");

                        if (isAlreadyFriend) {
                            friendButton.disabled = true;
                            friendButton.textContent = "Friends";
                        } else if (isRequestSent) {
                            friendButton.disabled = false;
                            friendButton.textContent = "Cancel Friend Request";
                            friendButton.onclick = cancelFriendRequest;
                        } else {
                            friendButton.disabled = false;
                            friendButton.textContent = "Send Friend Request";
                            friendButton.onclick = sendFriendRequest;
                        }

                        blockButton.textContent = "Block User";
                        blockButton.onclick = blockUser;
                    }
                }
            };
            xhr.send("requesteeID=" + selectedUserID + "&action=unblock");
        }

        window.onclick = function(event) {
            if (!event.target.matches('#searchInput')) {
                document.getElementById("dropdownList").style.display = "none";
            }
        }
    </script>
</body>
</html>
