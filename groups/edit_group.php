<?php
require '../session/db_connect.php';
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../account_setup.php");
    exit();
}

// Get group details from the form
$groupID = $_POST['groupId'];
$newGroupName = $_POST['groupName'];
$updatedAt = date('Y-m-d');

// Ensure logged-in user is authorized
$username = $_SESSION['username'];
$stmt = $conn->prepare("
    SELECT m.Privilege 
    FROM Member m
    INNER JOIN GroupList g ON g.GroupID = ?
    WHERE (g.OwnerID = m.MemberID OR m.Privilege = 'Administrator') AND m.Username = ?
");
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

if ($stmt->execute()) {
    // Redirect to the community page
    header("Location: group_tab.php");
    exit();
} else {
    die("Failed to update the group. Please try again.");
}

$stmt->close();
$conn->close();
?>
