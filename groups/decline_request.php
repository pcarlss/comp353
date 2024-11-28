<?php
require '../session/db_connect.php';
session_start();

if (!isset($_SESSION['username'])) {
    die("not_logged_in");
}

$requestId = $_POST['requestId'];
$groupId = $_POST['groupId'];

// Ensure the logged-in user is the owner of the group
$stmt = $conn->prepare("
    SELECT g.OwnerID 
    FROM JoinRequests jr
    INNER JOIN GroupList g ON jr.GroupID = g.GroupID
    WHERE jr.RequestID = ? AND g.OwnerID = ?
");
$stmt->bind_param("ii", $requestId, $_SESSION['MemberID']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("unauthorized");
}
$stmt->close();

// Decline the request (delete it)
$stmt = $conn->prepare("DELETE FROM JoinRequests WHERE RequestID = ?");
$stmt->bind_param("i", $requestId);
$stmt->execute();
$stmt->close();

echo "success";
?>
