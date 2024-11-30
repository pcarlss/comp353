<?php
require '../session/db_connect.php';

// Start session
session_start();

// Check if the user is logged in and has a username in the session
if (!isset($_SESSION['username'])) {
    // Send a redirect after 2 seconds
    echo "You must be logged in to create a gift exchange event.<br><br>";
    echo "Redirecting to Sign Up...";
    header("Refresh: 2; url=../account_setup.php");
    exit;
}

// Retrieve the username from the session
$username = $_SESSION['username'];

// Query the database to get the MemberID associated with the username
$sql_member = "SELECT MemberID FROM Member WHERE Username = ?";
$stmt = $conn->prepare($sql_member);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found in the database.");
}

// Fetch the MemberID
$member_row = $result->fetch_assoc();
$member_id = $member_row['MemberID'];

// Close the statement
$stmt->close();

// Retrieve the gift exchange information from the form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ge_name= $conn->real_escape_string($_POST['ge_name']);
    $ge_desc = $conn->real_escape_string($_POST['ge_desc']);
    $ge_date = $conn->real_escape_string($_POST['ge_date']);
    $created_at = date('Y-m-d H:i:s'); // Current timestamp

    // Insert gift exchange into the database
    $sql = "INSERT INTO GiftExchange (MemberID, GiftExchangeName, GiftExchangeDesc, GiftExchangeDate, GiftExchangeCreatedAt)
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param( "issss",$member_id, $ge_name, $ge_desc, $ge_date, $created_at);

    if ($stmt->execute()) {
        // Redirect to homepage after successful creation
        header("Location: ../index.php?message=Post%20added");
        exit;
    } else {
        echo "Error: " . $stmt->error;
    }

    // Close the statement
    $stmt->close();
}

// Close the connection
$conn->close();
?>
