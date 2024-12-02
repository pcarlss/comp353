<?php
require '../session/db_connect.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_member') {
    // Ensure groupId, email, firstName, and DOB are provided
    if (!isset($_POST['groupId'], $_POST['email'], $_POST['firstName'], $_POST['dob'])) {
        echo "Missing required fields.";
        exit();
    }

    $groupId = intval($_POST['groupId']);
    $email = $_POST['email'];
    $firstName = $_POST['firstName'];
    $dob = $_POST['dob'];

    // Check if current user is the owner of the group
    $currentUserID = $_SESSION['MemberID'] ?? null;

    $stmt = $conn->prepare("SELECT OwnerID FROM GroupList WHERE GroupID = ?");
    $stmt->bind_param("i", $groupId);
    $stmt->execute();
    $ownerResult = $stmt->get_result();
    $stmt->close();

    if ($ownerResult->num_rows === 0) {
        echo "Invalid group.";
        exit();
    }

    $ownerData = $ownerResult->fetch_assoc();
    $isOwner = $ownerData['OwnerID'] == $currentUserID;

    if (!$isOwner) {
        echo "Only the group owner can add members.";
        exit();
    }

    // Check if the user exists
    $stmt = $conn->prepare("SELECT MemberID FROM Member WHERE Email = ? AND FirstName = ? AND DateOfBirth = ?");
    $stmt->bind_param("sss", $email, $firstName, $dob);
    $stmt->execute();
    $userResult = $stmt->get_result();
    $stmt->close();

    if ($userResult->num_rows === 0) {
        echo "User does not exist.";
        exit();
    }

    $user = $userResult->fetch_assoc();
    $memberID = $user['MemberID'];

    // Add the user to the group if not already a member
    $stmt = $conn->prepare("SELECT 1 FROM GroupMember WHERE GroupID = ? AND MemberID = ?");
    $stmt->bind_param("ii", $groupId, $memberID);
    $stmt->execute();
    $isMemberResult = $stmt->get_result();
    $stmt->close();

    if ($isMemberResult->num_rows > 0) {
        echo "User is already a member of this group.";
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO GroupMember (GroupID, MemberID, JoinedGroupAt) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $groupId, $memberID);
    $stmt->execute();
    $stmt->close();

    echo "success";
    exit();
}
