<?php
require '../session/db_connect.php';
session_start();

if (!isset($_POST['groupMemberID'])) {
    echo "Group Member ID is missing.";
    exit();
}

$groupMemberID = intval($_POST['groupMemberID']);
$currentUserID = $_SESSION['MemberID'] ?? null;


// Fetch the group owner for the specified membership
$stmt = $conn->prepare("
    SELECT g.OwnerID
    FROM GroupMember gm
    JOIN GroupList g ON gm.GroupID = g.GroupID
    WHERE gm.GroupMemberID = ?
");
$stmt->bind_param("i", $groupMemberID);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    echo "Membership not found.";
    exit();
}

$row = $result->fetch_assoc();
$ownerID = $row['OwnerID'];

// Check if the current user is the owner
if ($currentUserID != $ownerID) {
    echo "Unauthorized action.";
    exit();
}

// Remove the member from the group
$stmt = $conn->prepare("DELETE FROM GroupMember WHERE GroupMemberID = ?");
$stmt->bind_param("i", $groupMemberID);
if ($stmt->execute()) {
    echo "success";
} else {
    echo "Error removing member.";
}
$stmt->close();
?>
