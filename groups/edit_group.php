<?php
require '../session/db_connect.php';
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../account_setup.php");
    exit();
}

// Get group details from form
$groupID = $_POST['groupId'];
$newGroupName = $_POST['groupName'];
$updatedAt = date('Y-m-d');

// Ensure logged-in user is the group owner
$username = $_SESSION['username'];
$stmt = $conn->prepare("
    SELECT g.GroupID 
    FROM GroupList g 
    INNER JOIN Member m ON g.OwnerID = m.MemberID 
    WHERE g.GroupID = ? AND m.Username = ?");
$stmt->bind_param("is", $groupID, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("You are not authorized to edit this group.");
}
$stmt->close();

// Update the group name
$stmt = $conn->prepare("UPDATE GroupList SET GroupName = ?, GroupUpdatedAt = ? WHERE GroupID = ?");
$stmt->bind_param("ssi", $newGroupName, $updatedAt, $groupID);
$stmt->execute();
$stmt->close();

// Redirect to community page
header("Location: community_tab.php");
exit();
?>
