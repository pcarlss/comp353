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

// Check if the request method is POST and required data is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['giftexchange_id']) && isset($_POST['gift_name'])) {
    $giftexchange_id = intval($_POST['giftexchange_id']);
    $gift_name = $conn->real_escape_string($_POST['gift_name']);
    $username = $_SESSION['username']; // Retrieve the logged-in username

    // Fetch the member ID for the logged-in user
    $result = $conn->query("SELECT memberid FROM Member WHERE username = '$username'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc(); // Fetch the member ID
        $member_id = $row['memberid'];
    } else {
        echo "Error: Unable to find Member ID.";
        exit;
    }

    //$gift_content = $conn->real_escape_string($_POST['gift_content']);

    // Insert gift into the Gift table
    $sql = "INSERT INTO Gift (GiftExchangeEventID, GiftName, GiftforID) VALUES ('$giftexchange_id', '$gift_name', '$member_id')";

    if ($conn->query($sql) === TRUE) {
        header("Location: ../index.php");
        exit;
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

$conn->close();
?>
