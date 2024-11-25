<?php
session_start(); // Start or resume the session

// Prevent page caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Handle logout request
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// Check if the user is logged in; if not, redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Database connection settings
$host = 'localhost';
$dbname = 'project';
$dbUsername = 'root';
$dbPassword = '';  // Assuming no password is set

// Create a MySQLi instance
$conn = new mysqli($host, $dbUsername, $dbPassword, $dbname);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Retrieve user information from session variables
$username = $_SESSION['username'];
$memberID = $_SESSION['memberID'];

// Query the database to get FirstName and LastName using MemberID
$stmt = $conn->prepare("SELECT FirstName, LastName FROM Member WHERE MemberID = ?");
$stmt->bind_param("i", $memberID);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$firstName = $user['FirstName'] ?? '';
$lastName = $user['LastName'] ?? '';

$stmt->close();

// Handle Accept and Decline Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['requestID'])) {
    $requestID = $_POST['requestID'];
    $requestorID = $_POST['requestorID'];
    $action = $_POST['action'];

    if ($action === "accept" && isset($_POST['relationshipType'])) {
        $relationshipType = $_POST['relationshipType'];

        // Insert into Friendship table
        $stmt = $conn->prepare("INSERT INTO Friendship (MemberID1, MemberID2, RelationshipType) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $requestorID, $memberID, $relationshipType);
        if ($stmt->execute()) {
            // Delete the request after accepting
            $deleteStmt = $conn->prepare("DELETE FROM FriendOrGroupRequest WHERE RequestID = ?");
            $deleteStmt->bind_param("i", $requestID);
            $deleteStmt->execute();
            $deleteStmt->close();
        }
        $stmt->close();
    } elseif ($action === "decline") {
        // Decline request by deleting it
        $deleteStmt = $conn->prepare("DELETE FROM FriendOrGroupRequest WHERE RequestID = ?");
        $deleteStmt->bind_param("i", $requestID);
        $deleteStmt->execute();
        $deleteStmt->close();
    }
}

// Retrieve friend requests received
$requestStmt = $conn->prepare("SELECT f.RequestID, m.MemberID, m.Username FROM FriendOrGroupRequest f INNER JOIN Member m ON f.RequestorID = m.MemberID WHERE f.RequesteeID = ?");
$requestStmt->bind_param("i", $memberID);
$requestStmt->execute();
$requestResult = $requestStmt->get_result();
$friendRequests = $requestResult->fetch_all(MYSQLI_ASSOC);

$requestStmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Page</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .user-info h2, .user-info p {
            margin: 0;
        }
        .header-buttons button {
            margin: 0 5px;
            padding: 8px 16px;
            cursor: pointer;
        }
        .friend-requests {
            margin-top: 20px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #fff;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            border-radius: 8px;
        }
        .modal select, .modal button {
            margin-top: 10px;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="user-info">
            <h2><?php echo htmlspecialchars($username); ?></h2>
            <p><?php echo htmlspecialchars($firstName) . " " . htmlspecialchars($lastName); ?></p>
        </div>
        <div class="header-buttons">
            <form action="friend.php" method="get" style="display: inline;">
                <button type="submit">Search Friends</button>
            </form>
            <form action="friendlist.php" method="get" style="display: inline;">
                <button type="submit">Friend List</button>
            </form>
            <form method="post" style="display: inline;">
                <button type="submit" name="logout">Log Out</button>
            </form>
        </div>
    </div>

    <div class="friend-requests">
        <h3>Friend Requests</h3>
        <?php if (count($friendRequests) > 0): ?>
            <ul>
                <?php foreach ($friendRequests as $request): ?>
                    <li id="request-<?php echo $request['RequestID']; ?>">
                        <?php echo htmlspecialchars($request['Username']); ?>
                        <button onclick="showRelationshipModal(<?php echo $request['RequestID']; ?>, <?php echo $request['MemberID']; ?>)">Accept</button>
                        <button onclick="handleDecline(<?php echo $request['RequestID']; ?>)">Decline</button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No friend requests.</p>
        <?php endif; ?>
    </div>

    <div class="modal-overlay" id="modalOverlay"></div>
    <div class="modal" id="relationshipModal">
    <h3>Choose Relationship Type</h3>
    <form id="relationshipForm" method="post">
        <input type="hidden" name="requestID" id="modalRequestID">
        <input type="hidden" name="requestorID" id="modalRequestorID">
        <input type="hidden" name="action" value="accept">
        <select name="relationshipType" id="relationshipType" required>
            <option value="" disabled selected>Select Relationship</option>
            <option value="Family">Family</option>
            <option value="Friend">Friend</option>
            <option value="Colleague">Colleague</option>
        </select>
        <button type="submit">Accept</button>
        <button type="button" onclick="closeRelationshipModal()">Cancel</button>
    </form>
</div>


    <script>
        function showRelationshipModal(requestID, requestorID) {
            document.getElementById('modalRequestID').value = requestID;
            document.getElementById('modalRequestorID').value = requestorID;
            document.getElementById('modalOverlay').style.display = 'block';
            document.getElementById('relationshipModal').style.display = 'block';
        }

        function closeRelationshipModal() {
            document.getElementById('modalOverlay').style.display = 'none';
            document.getElementById('relationshipModal').style.display = 'none';
        }

        function handleDecline(requestID) {
            const form = document.createElement('form');
            form.method = 'post';
            form.style.display = 'none';

            const requestIDInput = document.createElement('input');
            requestIDInput.type = 'hidden';
            requestIDInput.name = 'requestID';
            requestIDInput.value = requestID;
            form.appendChild(requestIDInput);

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'decline';
            form.appendChild(actionInput);

            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>
