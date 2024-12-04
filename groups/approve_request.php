<?php
require '../session/db_connect.php';
session_start();

if (!isset($_SESSION['username'])) {
    die("not_logged_in");
}

if (!isset($_SESSION['MemberID'])) {
    die("Session MemberID not set");
}

$requestId = $_POST['requestId'];
$groupId = $_POST['groupId'];

if (empty($requestId) || empty($groupId)) {
    die("Invalid request or group ID");
}

// Check if the logged-in user is the group owner
$stmt = $conn->prepare("
    SELECT g.OwnerID 
    FROM JoinRequests jr
    INNER JOIN GroupList g ON jr.GroupID = g.GroupID
    WHERE jr.RequestID = ? AND g.OwnerID = ?
");
$stmt->bind_param("ii", $requestId, $_SESSION['MemberID']);
$stmt->execute();
$result = $stmt->get_result();

// If the user is neither the owner nor an administrator
if ($result->num_rows === 0) {
    $stmt->close();

    // Check if the user is an administrator
    $stmt = $conn->prepare("
        SELECT m.Privilege 
        FROM Member m
        WHERE m.MemberID = ? AND m.Privilege = 'Administrator'
    ");
    $stmt->bind_param("i", $_SESSION['MemberID']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("unauthorized");
    }
}
$stmt->close();

// Approve request: Add the member to the group
$stmt = $conn->prepare("
    INSERT INTO GroupMember (GroupID, MemberID, JoinedGroupAt)
    SELECT GroupID, MemberID, CURDATE()
    FROM JoinRequests
    WHERE RequestID = ?
");
$stmt->bind_param("i", $requestId);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    die("insert_failed"); // Check if the insert was successful
}
$stmt->close();

// Delete the join request
$stmt = $conn->prepare("DELETE FROM JoinRequests WHERE RequestID = ?");
$stmt->bind_param("i", $requestId);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    die("delete_failed"); // Check if the delete was successful
}
$stmt->close();

echo "success";
?>
