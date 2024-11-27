<?php
require '../session/db_connect.php';
session_start();

if (!isset($_SESSION['username'])) {
    die("not_logged_in");
}

$groupId = $_POST['groupId'];

// Validate that the user is a member of the group
$stmt = $conn->prepare("
    SELECT 1 
    FROM GroupMember 
    WHERE GroupID = ? AND MemberID = ?
");
$stmt->bind_param("ii", $groupId, $_SESSION['MemberID']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("not_a_member");
}
$stmt->close();

// Remove the user from the group
$stmt = $conn->prepare("DELETE FROM GroupMember WHERE GroupID = ? AND MemberID = ?");
$stmt->bind_param("ii", $groupId, $_SESSION['MemberID']);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    die("leave_failed");
}

$stmt->close();
echo "success";
?>
