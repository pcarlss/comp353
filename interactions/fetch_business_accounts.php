<?php
require '../session/db_connect.php';
session_start();

$currentUserID = $_SESSION['MemberID'] ?? null;

// Fetch business accounts
$stmt = $conn->prepare("
    SELECT MemberID, Username, FirstName, LastName, Email
    FROM Member
    WHERE BusinessAccount = 1 AND Status = 'Active'
");
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Fetch votes by the current user
$votedStmt = $conn->prepare("
    SELECT SelectedOptionID FROM Vote WHERE VoterID = ?
");
$votedStmt->bind_param("i", $currentUserID);
$votedStmt->execute();
$votedResult = $votedStmt->get_result();
$votedStmt->close();

$votedOptions = [];
while ($row = $votedResult->fetch_assoc()) {
    $votedOptions[] = $row['SelectedOptionID'];
}

if ($result->num_rows > 0) {
    echo "<h3>Business Accounts</h3>";
    echo "<ul style='max-height: 300px; overflow-y: auto; padding: 0; margin: 0; list-style-type: none;'>";

    while ($row = $result->fetch_assoc()) {
        $memberID = $row['MemberID'];
        $username = htmlspecialchars($row['Username']);
        $firstName = htmlspecialchars($row['FirstName']);
        $lastName = htmlspecialchars($row['LastName']);
        $email = htmlspecialchars($row['Email']);
        $voted = in_array($memberID, $votedOptions);

        echo "<li style='display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #ddd;'>";
        echo "<span style='flex-grow: 1;'><strong>$username</strong> ($firstName $lastName)<br><span style='font-size: 0.9em; color: #666;'>$email</span></span>";
        echo "<button onclick=\"voteToOust($memberID, this)\" 
              style='background-color: " . ($voted ? "#ccc" : "#f39c12") . "; color: white; border: none; padding: 5px 10px; 
                     border-radius: 4px; cursor: " . ($voted ? "default" : "pointer") . ";' 
              " . ($voted ? "disabled" : "") . ">
              " . ($voted ? "Voted" : "Vote to Oust") . "</button>";
        echo "</li>";
    }

    echo "</ul>";
} else {
    echo "<p>No business accounts found.</p>";
}
?>
