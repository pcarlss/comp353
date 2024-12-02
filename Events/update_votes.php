<?php
require '../session/db_connect.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input
    $record_id = intval($_POST['record_id']);  // Cast to integer to avoid SQL injection

    // 3. Update the database record
    $sql = "UPDATE EventOptions SET Votes = Votes + 1 WHERE OptionID = $record_id";

    if ($conn->query($sql) === TRUE) {
        header("Location: create_social_event.php");
        exit;
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }

    $conn->close();
}

?>