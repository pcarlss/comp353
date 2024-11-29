<?php
require '../session/db_connect.php';
session_start();

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}
$username = $_SESSION['username'];

// Fetch the member ID and profilePic
$stmt = $conn->prepare("SELECT memberid, profilePic FROM Member WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $memberID = $row['memberid'];
    $_SESSION['memberid'] = $memberID;
    $userProfilePic = $row['profilePic'];
} else {
    header("Location: ../error.php?message=User+not+found");
    exit();
}

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

// Handle friend removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['removeFriendID'])) {
    $removeFriendID = $_POST['removeFriendID'];

    // Prevent users from removing themselves
    if ($removeFriendID != $memberID) {
        // Delete the friendship entry where the logged-in user is either MemberID1 or MemberID2
        $stmt = $conn->prepare("DELETE FROM Friendship WHERE (MemberID1 = ? AND MemberID2 = ?) OR (MemberID1 = ? AND MemberID2 = ?)");
        $stmt->bind_param("iiii", $memberID, $removeFriendID, $removeFriendID, $memberID);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle accepting a friend request with RelationshipType
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acceptFriendID']) && isset($_POST['relationshipType'])) {
    $acceptFriendID = $_POST['acceptFriendID'];
    $relationshipType = $_POST['relationshipType'];

    // Validate RelationshipType
    $allowedTypes = ['Family', 'Friend', 'Colleague'];
    if (in_array($relationshipType, $allowedTypes)) {
        // Prevent users from adding themselves
        if ($acceptFriendID != $memberID) {
            // Check if the friendship already exists
            $stmt = $conn->prepare("
                SELECT * FROM Friendship 
                WHERE (MemberID1 = ? AND MemberID2 = ?) 
                   OR (MemberID1 = ? AND MemberID2 = ?)
            ");
            $stmt->bind_param("iiii", $memberID, $acceptFriendID, $acceptFriendID, $memberID);
            $stmt->execute();
            $existingFriendship = $stmt->get_result();

            if ($existingFriendship->num_rows == 0) {
                // Insert into the Friendship table with the selected RelationshipType
                $stmt = $conn->prepare("
                    INSERT INTO Friendship (MemberID1, MemberID2, RelationshipType) 
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("iis", $memberID, $acceptFriendID, $relationshipType);

                if ($stmt->execute()) {
                    // Delete the friend request after accepting
                    $stmt = $conn->prepare("DELETE FROM FriendOrGroupRequest WHERE RequestorID = ? AND RequesteeID = ?");
                    $stmt->bind_param("ii", $acceptFriendID, $memberID);
                    $stmt->execute();
                }
                $stmt->close();
            }
        }
    }
}

// Handle declining a friend request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['declineFriendID'])) {
    $declineFriendID = $_POST['declineFriendID'];

    // Prevent users from declining their own requests
    if ($declineFriendID != $memberID) {
        // Delete the friend request
        $stmt = $conn->prepare("DELETE FROM FriendOrGroupRequest WHERE RequestorID = ? AND RequesteeID = ?");
        $stmt->bind_param("ii", $declineFriendID, $memberID);
        $stmt->execute();
        $stmt->close();
    }
}

// Retrieve friends of the logged-in user
$stmt = $conn->prepare("
    SELECT m.MemberID, m.Username, f.RelationshipType, m.profilePic
    FROM Friendship f
    JOIN Member m ON (f.MemberID1 = m.MemberID AND f.MemberID2 = ?)
                OR (f.MemberID2 = m.MemberID AND f.MemberID1 = ?)
");
$stmt->bind_param("ii", $memberID, $memberID);
$stmt->execute();
$result = $stmt->get_result();
$friends = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Retrieve friend requests sent to the logged-in user
$stmt = $conn->prepare("
    SELECT m.MemberID, m.Username, m.profilePic
    FROM FriendOrGroupRequest fr
    JOIN Member m ON fr.RequestorID = m.MemberID
    WHERE fr.RequesteeID = ?
");
$stmt->bind_param("i", $memberID);
$stmt->execute();
$FriendOrGroupRequest = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Close the database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- The rest of the head section remains the same -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friend List</title>
    <style>
        /* Include your CSS styles here */
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
            padding-top: 20px;
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

        /* Main Container */
        .main-container {
            display: flex;
            justify-content: space-between;
            width: 100%;
            max-width: 1400px;
            margin-top: 80px;
            gap: 30px;
            margin: 100px auto 0 auto;
            padding: 0 20px;
        }

        /* Friends List Styling */
        .friend-list {
            flex: 3;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            max-height: 700px;
            overflow-y: auto;
        }

        /* Friend Requests Styling */
        .friend-requests {
            flex: 1;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            max-height: 500px;
            overflow-y: auto;
        }

        /* Section Headers */
        h2 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 1.5em;
            color: #4c87ae;
        }

        /* Lists */
        ul {
            list-style: none;
            padding: 0;
        }

        /* List Items */
        li {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            margin-bottom: 10px;
            background-color: #f9f9f9;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* User Info */
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #4c87ae;
        }

        .user-info span {
            font-size: 1em;
            color: #4c87ae;
            font-weight: bold;
        }

        /* Actions */
        .actions {
            display: flex;
            gap: 10px;
        }

        .actions form {
            display: inline;
        }

        button {
            background-color: #4c87ae;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.8em;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #6caad3;
        }

        /* Specific Button Styles */
        .message-button {
            background-color: #4c87ae;
        }

        .message-button:hover {
            background-color: #6caad3;
        }

        .accept-button {
            background-color: #28a745;
        }

        .accept-button:hover {
            background-color: #3b8f3b;
        }

        .decline-button {
            background-color: #dc3545;
        }

        .decline-button:hover {
            background-color: #ff6b6b;
        }

        .remove-button {
            background-color: #dc3545;
        }

        .remove-button:hover {
            background-color: #ff6b6b;
        }

        /* Empty List Message */
        .empty-list {
            text-align: center;
            color: #888;
            font-size: 1em;
            padding: 20px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 300px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }

        .modal-header {
            margin-bottom: 10px;
        }

        .modal-header h2 {
            font-size: 1.2em;
            color: #4c87ae;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-body select {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        .modal-footer {
            text-align: right;
        }

        .modal-footer button {
            padding: 6px 12px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
                gap: 20px;
            }

            .friend-requests {
                margin-left: 0;
            }

            .user-info img {
                width: 35px;
                height: 35px;
            }

            .user-info span {
                font-size: 0.95em;
            }

            button {
                padding: 5px 8px;
                font-size: 0.7em;
            }

            .modal-content {
                width: 80%;
            }
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

    <!-- Main Content Container -->
    <div class="main-container">
        <!-- Friends List Section -->
        <div class="friend-list">
            <h2>Your Friends</h2>
            <?php if (!empty($friends)): ?>
                <ul>
                    <?php foreach ($friends as $friend): ?>
                        <li id="friend-<?php echo htmlspecialchars($friend['MemberID']); ?>">
                            <div class="user-info">
                                <img src="<?php echo getProfilePic($friend['profilePic']); ?>" alt="Profile Picture of <?php echo htmlspecialchars($friend['Username']); ?>">
                                <span><?php echo htmlspecialchars($friend['Username']); ?> (<?php echo htmlspecialchars($friend['RelationshipType']); ?>)</span>
                            </div>
                            <div class="actions">
                                <form action="message.php" method="get">
                                    <input type="hidden" name="userID" value="<?php echo htmlspecialchars($memberID); ?>">
                                    <input type="hidden" name="friendID" value="<?php echo htmlspecialchars($friend['MemberID']); ?>">
                                    <button class="message-button" type="submit">Message</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Are you sure you want to remove this friend?');">
                                    <input type="hidden" name="removeFriendID" value="<?php echo htmlspecialchars($friend['MemberID']); ?>">
                                    <button class="remove-button" type="submit">Remove</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="empty-list">You have no friends in your list.</p>
            <?php endif; ?>
        </div>

        <!-- Friend Requests Section -->
        <div class="friend-requests">
            <h2>Friend Requests</h2>
            <?php if (!empty($FriendOrGroupRequest)): ?>
                <ul>
                    <?php foreach ($FriendOrGroupRequest as $request): ?>
                        <li id="request-<?php echo htmlspecialchars($request['MemberID']); ?>">
                            <div class="user-info">
                                <img src="<?php echo getProfilePic($request['profilePic']); ?>" alt="Profile Picture of <?php echo htmlspecialchars($request['Username']); ?>">
                                <span><?php echo htmlspecialchars($request['Username']); ?></span>
                            </div>
                            <div class="actions">
                                <button class="accept-button" onclick="openModal(<?php echo htmlspecialchars($request['MemberID']); ?>)">Accept</button>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="declineFriendID" value="<?php echo htmlspecialchars($request['MemberID']); ?>">
                                    <button class="decline-button" type="submit">Decline</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="empty-list">No friend requests at this time.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for Selecting Relationship Type -->
    <div id="relationshipModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal()">&times;</span>
                <h2>Select Relationship Type</h2>
            </div>
            <div class="modal-body">
                <form id="relationshipForm" method="post" action="friendlist.php">
                    <input type="hidden" name="acceptFriendID" id="modalAcceptFriendID" value="">
                    <label for="relationshipType">Choose Relationship Type:</label>
                    <select name="relationshipType" id="relationshipType" required>
                        <option value="" disabled selected>Select type</option>
                        <option value="Family">Family</option>
                        <option value="Friend">Friend</option>
                        <option value="Colleague">Colleague</option>
                        <!-- Add more options if needed -->
                    </select>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal()">Cancel</button>
                <button type="submit">Confirm</button>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Get the modal
        var modal = document.getElementById("relationshipModal");

        // Function to open the modal and set the acceptFriendID
        function openModal(friendID) {
            document.getElementById("modalAcceptFriendID").value = friendID;
            modal.style.display = "block";
        }

        // Function to close the modal
        function closeModal() {
            modal.style.display = "none";
            document.getElementById("relationshipForm").reset();
        }

        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
