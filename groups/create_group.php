<?php
require '../session/db_connect.php';
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../account_setup.php");
    exit();
}

$username = $_SESSION['username'];

// Get MemberID of the logged-in user
$stmt = $conn->prepare("SELECT MemberID FROM Member WHERE Username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$memberRow = $result->fetch_assoc();
$ownerID = $memberRow['MemberID'];
$stmt->close();

// Collect group data from form
$groupName = $_POST['groupName'];
$groupDescription = $_POST['groupDescription'];
$createdAt = date('Y-m-d');

// Insert into GroupList
$stmt = $conn->prepare("INSERT INTO GroupList (GroupName, OwnerID, GroupCreatedAt, GroupUpdatedAt) VALUES (?, ?, ?, ?)");
$stmt->bind_param("siss", $groupName, $ownerID, $createdAt, $createdAt);
$stmt->execute();
$groupID = $stmt->insert_id; // Get the newly created group ID
$stmt->close();

// Add the creator to GroupMember
$stmt = $conn->prepare("INSERT INTO GroupMember (GroupID, MemberID, JoinedGroupAt) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $groupID, $ownerID, $createdAt);
$stmt->execute();
$stmt->close();

// Redirect back to community page
header("Location: community_tab.php");
exit();
?>
