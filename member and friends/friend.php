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

// Fetch the member ID and profilePic
$stmt = $conn->prepare("SELECT memberid, profilePic FROM Member WHERE username = ?");
if (!$stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $memberID = $row['memberid'];
    $_SESSION['memberid'] = $memberID;
    $userProfilePic = $row['profilePic'];
} else {
    header("Location: error.php?message=User+not+found");
    exit();
}

$stmt->close();

// Function to get profile picture URL
function getProfilePic($profilePic) {
    $defaultPicPath = "../uploads/images/default_pfp.png";

    // If profilePic is set and the file exists, return its path
    if (!empty($profilePic) && file_exists(__DIR__ . "/../" . $profilePic)) {
        return "../" . htmlspecialchars($profilePic);
    } else {
        return $defaultPicPath;
    }
}

// Get the logged-in user's ID and username
$loggedInUsername = $_SESSION['username'];
$loggedInUserID = $_SESSION['memberid'];

// Retrieve users excluding the logged-in user and users who are blocked or have blocked the user
$users = [];
$stmt = $conn->prepare("
    SELECT MemberID, Username, profilePic,
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
    ) THEN 1 ELSE 0 END AS IsRequestSent,
    CASE WHEN EXISTS (
        SELECT 1 FROM FriendOrGroupRequest 
        WHERE (RequestorID = Member.MemberID AND RequesteeID = ?)
    ) THEN 1 ELSE 0 END AS HasSentRequestToMe
    FROM Member 
    WHERE MemberID != ?  -- Exclude the logged-in user
    AND NOT EXISTS (
        SELECT 1 FROM Blocked 
        WHERE MemberID2 = ? AND MemberID1 = Member.MemberID
    )
");
$stmt->bind_param("iiiiiii", $loggedInUserID, $loggedInUserID, $loggedInUserID, $loggedInUserID, $loggedInUserID, $loggedInUserID, $loggedInUserID);

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

// Handle friend request, cancel friend request, or block/unblock submission via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = [];

    if (isset($_POST['requesteeID']) && isset($_POST['action'])) {
        $requesteeID = intval($_POST['requesteeID']);
        $action = $_POST['action'];

        if ($action === "block") {
            // Remove any existing friendship
            $stmt = $conn->prepare("DELETE FROM Friendship WHERE (MemberID1 = ? AND MemberID2 = ?) OR (MemberID2 = ? AND MemberID1 = ?)");
            if ($stmt) {
                $stmt->bind_param("iiii", $loggedInUserID, $requesteeID, $loggedInUserID, $requesteeID);
                $stmt->execute();
                $stmt->close();
            }

            // Block the user
            $stmt = $conn->prepare("INSERT INTO Blocked (MemberID1, MemberID2) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param("ii", $loggedInUserID, $requesteeID);
                if ($stmt->execute()) {
                    $response['message'] = "User blocked successfully.";
                } else {
                    $response['message'] = "Failed to block user.";
                }
                $stmt->close();
            }
        } elseif ($action === "unblock") {
            // Unblock the user
            $stmt = $conn->prepare("DELETE FROM Blocked WHERE MemberID1 = ? AND MemberID2 = ?");
            if ($stmt) {
                $stmt->bind_param("ii", $loggedInUserID, $requesteeID);
                if ($stmt->execute()) {
                    $response['message'] = "User unblocked successfully.";
                } else {
                    $response['message'] = "Failed to unblock user.";
                }
                $stmt->close();
            }
        } elseif ($action === "friendRequest") {
            // Check if a friend request already exists from the requestee to the logged-in user
            $stmt = $conn->prepare("SELECT * FROM FriendOrGroupRequest WHERE RequestorID = ? AND RequesteeID = ?");
            if ($stmt) {
                $stmt->bind_param("ii", $requesteeID, $loggedInUserID);
                $stmt->execute();
                $existingRequest = $stmt->get_result();
                $stmt->close();

                if ($existingRequest->num_rows > 0) {
                    // Mutual friend request exists
                    $response['message'] = "User has already sent you a friend request. Please check your friend list.";
                } else {
                    // Check if a friend request already exists from logged-in user to requestee
                    $stmt = $conn->prepare("SELECT * FROM FriendOrGroupRequest WHERE RequestorID = ? AND RequesteeID = ?");
                    if ($stmt) {
                        $stmt->bind_param("ii", $loggedInUserID, $requesteeID);
                        $stmt->execute();
                        $existingSentRequest = $stmt->get_result();
                        $stmt->close();

                        if ($existingSentRequest->num_rows > 0) {
                            $response['message'] = "Friend request already sent.";
                        } else {
                            // Send a friend request
                            $stmt = $conn->prepare("INSERT INTO FriendOrGroupRequest (RequestorID, RequesteeID, RequestMadeAt) VALUES (?, ?, ?)");
                            if ($stmt) {
                                $requestMadeAt = date("Y-m-d H:i:s");
                                $stmt->bind_param("iis", $loggedInUserID, $requesteeID, $requestMadeAt);
                                if ($stmt->execute()) {
                                    $response['message'] = "Friend request sent successfully.";
                                    $response['isRequestSent'] = true;
                                } else {
                                    $response['message'] = "Failed to send friend request.";
                                }
                                $stmt->close();
                            }
                        }
                    }
                }
            }
        } elseif ($action === "cancelFriendRequest") {
            // Cancel a friend request
            $stmt = $conn->prepare("DELETE FROM FriendOrGroupRequest WHERE RequestorID = ? AND RequesteeID = ?");
            if ($stmt) {
                $stmt->bind_param("ii", $loggedInUserID, $requesteeID);
                if ($stmt->execute()) {
                    $response['message'] = "Friend request canceled.";
                } else {
                    $response['message'] = "Failed to cancel friend request.";
                }
                $stmt->close();
            }
        }

        echo json_encode($response);
        $conn->close();
        exit;
    }
}

// Close the database connection if not already closed
if ($conn) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Existing head content -->
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
            padding-top: 120px; /* Adjusted to accommodate the fixed top bar */
            overflow-y: scroll;
        }

        /* Top Bar Styling (Unchanged) */
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

        /* Dropdown Item Styling */
        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .dropdown-item img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            border: 1px solid #ccc;
        }

        .dropdown-item span {
            font-size: 1em;
            color: #333;
        }

        .dropdown-item:hover {
            background-color: #f1f1f1;
        }

        /* Dropdown Content */
        .dropdown-content {
            position: absolute;
            background-color: #fff;
            width: 100%;
            max-height: 300px;
            overflow-y: auto;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 0 0 4px 4px;
        }

        /* Ensure the search box size remains unchanged */
        .dropdown input[type="text"] {
            width: 100%;
            max-width: 300px; /* Maintain consistent size */
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        /* Selected User Box Styling */
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

        .selected-user-box img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #4c87ae;
            margin-bottom: 10px;
        }

        .selected-user-box .username-text {
            font-size: 1.2em;
            color: #4c87ae;
            text-align: center;
            margin: 10px 0;
            font-weight: bold;
        }

        .selected-user-box button {
            margin-top: 10px;
            padding: 8px 12px;
            cursor: pointer;
            border: none;
            background-color: #4c87ae;
            color: #fff;
            border-radius: 4px;
            font-size: 1em;
            width: auto;
            max-width: 100%;
            transition: background-color 0.3s, transform 0.2s;
        }

        .selected-user-box button:hover {
            background-color: #6caad3;
            transform: scale(1.05);
        }

        .selected-user-box button:active {
            background-color: #3b6892;
            transform: scale(0.98);
        }

        .selected-user-box button.block {
            background-color: #dc3545;
        }

        .selected-user-box button.block:hover {
            background-color: #ff6b6b;
        }


        /* Responsive Design */
        @media (max-width: 768px) {
            .dropdown-item img {
                width: 25px;
                height: 25px;
            }

            .dropdown-item span {
                font-size: 0.9em;
            }

            .selected-user-box {
                max-width: 100%;
            }

            .selected-user-box img {
                width: 60px;
                height: 60px;
            }

            .selected-user-box button {
                padding: 6px 10px;
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <!-- Top Bar (Unchanged) -->
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
                    <div class="dropdown-item" onclick="selectUser('<?php echo htmlspecialchars($user['Username']); ?>', '<?php echo htmlspecialchars($user['MemberID']); ?>', '<?php echo getProfilePic($user['profilePic']); ?>', <?php echo $user['IsFriend']; ?>, <?php echo $user['IsBlocked']; ?>, <?php echo $user['IsRequestSent']; ?>, <?php echo $user['HasSentRequestToMe']; ?>)">
                       <img src="<?php echo getProfilePic($user['profilePic']); ?>" alt="Profile Picture of <?php echo htmlspecialchars($user['Username']); ?>">
                <span><?php echo htmlspecialchars($user['Username']); ?></span>
            </div>
        <?php endforeach; ?>
                </div>
            </div>
        </div>


       <!-- Selected User Info -->
      <div id="selectedUserContainer" class="selected-user-box" style="display: none; padding-top: 20px; margin: auto">
          <img id="selectedUserProfilePic" src="<?php echo getProfilePic($userProfilePic); ?>" alt="Selected User's Profile Picture">
          <p id="selectedUsername" class="username-text"></p>
          <button id="sendRequestButton" onclick="sendFriendRequest()">Send Friend Request</button>
          <button id="blockButton" class="block" onclick="blockUser()">Block User</button>
      </div>

    </div>

    <!-- Notification Section -->
    <?php if (!empty($message)): ?>
        <div class="notification <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- JavaScript -->
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

        function selectUser(username, userID, profilePic, isFriend, isBlockedStatus, isRequestSentStatus, hasSentRequestToMeStatus) {
    document.getElementById("selectedUsername").textContent = username;
    document.getElementById("selectedUserContainer").style.display = "block";
    selectedUserID = userID;
    isAlreadyFriend = isFriend === 1;
    isBlocked = isBlockedStatus === 1;
    isRequestSent = isRequestSentStatus === 1;
    hasSentRequestToMe = hasSentRequestToMeStatus === 1;

    // Set the profile picture of the selected user
    const selectedUserProfilePic = document.getElementById("selectedUserProfilePic");
    selectedUserProfilePic.src = profilePic;
    selectedUserProfilePic.alt = "Profile Picture of " + username;

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
    } else if (hasSentRequestToMe) {
        // Display "Accept Friend Request" button
        friendButton.disabled = false;
        friendButton.textContent = "Accept Friend Request";
        friendButton.onclick = function() {
            // Redirect to friendlist.php with necessary parameters
            window.location.href = "friendlist.php?action=accept&requestorID=" + selectedUserID;
        };

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
                    if (response.message === "Friend request sent successfully.") {
                        isRequestSent = true;
                        selectUser(
                            document.getElementById("selectedUsername").textContent,
                            selectedUserID,
                            document.getElementById("selectedUserProfilePic").src,
                            isAlreadyFriend,
                            isBlocked,
                            1
                        );
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
                    if (response.message === "Friend request canceled.") {
                        isRequestSent = false;
                        selectUser(
                            document.getElementById("selectedUsername").textContent,
                            selectedUserID,
                            document.getElementById("selectedUserProfilePic").src,
                            isAlreadyFriend,
                            isBlocked,
                            0
                        );
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
                    if (response.message === "User blocked successfully.") {
                        isBlocked = true;
                        selectUser(
                            document.getElementById("selectedUsername").textContent,
                            selectedUserID,
                            document.getElementById("selectedUserProfilePic").src,
                            isAlreadyFriend,
                            1,
                            isRequestSent
                        );
                    }
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

        window.onclick = function (event) {
            if (!event.target.matches("#searchInput")) {
                document.getElementById("dropdownList").style.display = "none";
            }
        };
    </script>
</body>
</html>
