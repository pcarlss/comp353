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
    SELECT g.OwnerID, g.GroupID
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
$groupID = $row['GroupID'];

// Check if the current user is the owner or an administrator
$isOwner = $currentUserID == $ownerID;

// Check if the current user has administrator privileges
$stmt = $conn->prepare("SELECT Privilege FROM Member WHERE MemberID = ?");
$stmt->bind_param("i", $currentUserID);
$stmt->execute();
$privilegeResult = $stmt->get_result();
$stmt->close();

$userPrivilege = $privilegeResult->fetch_assoc()['Privilege'] ?? null;
$isAdmin = $userPrivilege === 'Administrator';

// Authorize the action
if (!$isOwner && !$isAdmin) {
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
