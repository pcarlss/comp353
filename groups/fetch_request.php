<?php
require '../session/db_connect.php';
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../account_setup.php");
    exit();
}

$username = $_SESSION['username'];

// Get the logged-in user's MemberID
$stmt = $conn->prepare("SELECT MemberID FROM Member WHERE Username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$memberRow = $result->fetch_assoc();
$memberID = $memberRow['MemberID'];
$stmt->close();

// Fetch all pending requests for groups owned by the logged-in user
$stmt = $conn->prepare("
    SELECT r.RequestID, r.GroupID, r.MemberID, r.RequestDate, g.GroupName, m.Username 
    FROM JoinRequests r
    JOIN GroupList g ON r.GroupID = g.GroupID
    JOIN Member m ON r.MemberID = m.MemberID
    WHERE g.OwnerID = ? AND r.Status IS NULL
");
$stmt->bind_param("i", $memberID);
$stmt->execute();
$result = $stmt->get_result();

$requests = [];
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

header('Content-Type: application/json');
echo json_encode($requests);

$stmt->close();
$conn->close();
?>
