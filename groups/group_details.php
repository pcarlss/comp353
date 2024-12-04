<?php
require '../session/db_connect.php';
session_start();

// Ensure groupId is passed
if (!isset($_POST['groupId'])) {
    echo "Group ID is missing.";
    exit();
}

$groupId = intval($_POST['groupId']);

// Fetch group details, including created and updated dates
$stmt = $conn->prepare("
    SELECT g.GroupID, g.GroupName, g.GroupCreatedAt, g.GroupUpdatedAt, g.OwnerID, m.Username AS OwnerUsername
    FROM GroupList g
    JOIN Member m ON g.OwnerID = m.MemberID
    WHERE g.GroupID = ?
");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$groupResult = $stmt->get_result();
$stmt->close();

if ($groupResult->num_rows === 0) {
    echo "Group not found.";
    exit();
}

$group = $groupResult->fetch_assoc();
$groupID = htmlspecialchars($group['GroupID']);
$groupName = htmlspecialchars($group['GroupName']);
$groupCreatedAt = htmlspecialchars($group['GroupCreatedAt']);
$groupUpdatedAt = htmlspecialchars($group['GroupUpdatedAt']);
$ownerID = $group['OwnerID'];
$ownerUsername = htmlspecialchars($group['OwnerUsername']);

// Check if the current user is the owner or an administrator
$currentUserID = $_SESSION['MemberID'] ?? null;

// Check if the user has administrator privileges
$stmt = $conn->prepare("SELECT Privilege FROM Member WHERE MemberID = ?");
$stmt->bind_param("i", $currentUserID);
$stmt->execute();
$userResult = $stmt->get_result();
$userPrivilege = $userResult->fetch_assoc()['Privilege'] ?? null;
$stmt->close();

$isOwner = $currentUserID == $ownerID;
$isAdmin = $userPrivilege === 'Administrator';

// Fetch group members
$stmt = $conn->prepare("
    SELECT gm.GroupMemberID, m.MemberID, m.Username
    FROM GroupMember gm
    JOIN Member m ON gm.MemberID = m.MemberID
    WHERE gm.GroupID = ?
");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$membersResult = $stmt->get_result();
$stmt->close();

$members = [];
while ($row = $membersResult->fetch_assoc()) {
    $members[] = [
        'GroupMemberID' => $row['GroupMemberID'],
        'MemberID' => $row['MemberID'],
        'Username' => htmlspecialchars($row['Username']),
    ];
}

// Generate the HTML response
echo "<h3>Group Name: $groupName</h3>";
echo "<p>Group ID: $groupID</p>";
echo "<p>Created On: $groupCreatedAt</p>";
echo "<p>Last Updated: $groupUpdatedAt</p>";
echo "<p>Owner: $ownerUsername</p>";

echo "<h4>Members:</h4>";
if ($isOwner || $isAdmin) {
    echo "<button onclick=\"showAddMemberForm($groupID)\" style='margin-bottom: 5px; background-color: #4c87ae; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;'>Add Members</button>";
}

if (count($members) > 0) {
    echo "<ul style='max-height: 200px; overflow-y: auto; padding: 0; margin: 0; list-style-type: none;'>"; // Enable scrolling
    foreach ($members as $member) {
        echo "<li style='display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #ddd;'>";

        // Display the member's name
        echo "<span style='flex-grow: 1;'>{$member['Username']}</span>";

        // Show the remove button only if the current user is the owner or an administrator and the member is not the owner
        if (($isOwner || $isAdmin) && $member['MemberID'] != $ownerID) {
            echo "<button onclick=\"removeMember({$member['GroupMemberID']}, this)\" 
                  style='background-color: #e53935; color: white; border: none; padding: 5px 10px; 
                         border-radius: 4px; cursor: pointer; margin-left: 10px;'>
                  Remove</button>";
        }
        echo "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No members in this group.</p>";
}
?>
