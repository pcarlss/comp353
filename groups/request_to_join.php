<?php
require '../session/db_connect.php';
session_start();

if (!isset($_SESSION['username'])) {
    echo "not_logged_in";
    exit();
}

$groupID = $_POST['groupId'];
$username = $_SESSION['username'];

// Get the logged-in user's MemberID
$stmt = $conn->prepare("SELECT MemberID FROM Member WHERE Username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$memberRow = $result->fetch_assoc();
$memberID = $memberRow['MemberID'];
$stmt->close();

// Check if the user has already requested to join the group
$stmt = $conn->prepare("SELECT * FROM JoinRequests WHERE MemberID = ? AND GroupID = ?");
$stmt->bind_param("ii", $memberID, $groupID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "already_requested";
    exit();
}
$stmt->close();

// Insert the join request
$stmt = $conn->prepare("INSERT INTO JoinRequests (GroupID, MemberID, RequestDate) VALUES (?, ?, ?)");
$requestDate = date('Y-m-d');
$stmt->bind_param("iis", $groupID, $memberID, $requestDate);

if ($stmt->execute()) {
    echo "success";
} else {
    echo "failure";
}

$stmt->close();
$conn->close();
?>
