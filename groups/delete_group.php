<?php
require '../session/db_connect.php';
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../account_setup.php");
    exit();
}

// Get group details from form
$groupID = $_POST['groupId'];

// Check if the logged-in user is the group owner
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
    die("You are not authorized to delete this group.");
}
$stmt->close();

// Delete group memberships
$stmt = $conn->prepare("DELETE FROM GroupMember WHERE GroupID = ?");
$stmt->bind_param("i", $groupID);
$stmt->execute();
$stmt->close();

// Delete the group
$stmt = $conn->prepare("DELETE FROM GroupList WHERE GroupID = ?");
$stmt->bind_param("i", $groupID);
$stmt->execute();
$stmt->close();

// Redirect back to community page
header("Location: group_tab.php");
exit();
?>
