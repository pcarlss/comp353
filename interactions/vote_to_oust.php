<?php
require '../session/db_connect.php';
session_start();

// Ensure user is logged in
if (!isset($_SESSION['MemberID'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit();
}

$voterID = $_SESSION['MemberID'];
$businessMemberID = intval($_POST['memberID'] ?? 0);

// Validate input
if ($businessMemberID <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid business member ID.']);
    exit();
}

// Check if the voter is a non-business member
$stmt = $conn->prepare("SELECT BusinessAccount FROM Member WHERE MemberID = ?");
$stmt->bind_param("i", $voterID);
$stmt->execute();
$voterData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$voterData || $voterData['BusinessAccount'] == 1) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Only non-business members can vote.']);
    exit();
}

// Check if the user has already voted
$stmt = $conn->prepare("SELECT COUNT(*) as voteCount FROM BusinessVote WHERE VoterID = ? AND BusinessMemberID = ?");
$stmt->bind_param("ii", $voterID, $businessMemberID);
$stmt->execute();
$alreadyVoted = $stmt->get_result()->fetch_assoc()['voteCount'] > 0;
$stmt->close();

if ($alreadyVoted) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'You have already voted.']);
    exit();
}

// Add the vote
$stmt = $conn->prepare("INSERT INTO BusinessVote (VoterID, BusinessMemberID, VotedAt) VALUES (?, ?, NOW())");
$stmt->bind_param("ii", $voterID, $businessMemberID);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to register vote.']);
    exit();
}
$stmt->close();

// Count votes from non-business members
$stmt = $conn->prepare("
    SELECT COUNT(*) as voteCount
    FROM BusinessVote v
    JOIN Member m ON v.VoterID = m.MemberID
    WHERE v.BusinessMemberID = ? AND m.BusinessAccount = 0
");
$stmt->bind_param("i", $businessMemberID);
$stmt->execute();
$voteCount = $stmt->get_result()->fetch_assoc()['voteCount'] ?? 0;
$stmt->close();

// Delete posts if threshold is reached
if ($voteCount >= 3) {
    $stmt = $conn->prepare("DELETE FROM Post WHERE MemberID = ?");
    $stmt->bind_param("i", $businessMemberID);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete posts.']);
        exit();
    }
    $stmt->close();

    echo json_encode(['status' => 'success', 'message' => 'Vote registered. Posts deleted.']);
    exit();
}

echo json_encode(['status' => 'success', 'message' => 'Vote registered.']);
?>
