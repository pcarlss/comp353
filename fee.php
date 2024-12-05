<?php
require 'session/db_connect.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
$balance = 0.00;

$query = "SELECT BusinessAccount, MemberID FROM Member WHERE Username = ?";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($isBusinessAccount, $memberID);
    if ($stmt->fetch()) {
        if ($isBusinessAccount != 1) {
            header("Location: index.php");
            exit();
        }
    }
    $stmt->close();
}

$query = "
    SELECT COUNT(PostID) * 0.05 AS TotalFee
    FROM Post
    WHERE MemberID = ?";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("i", $memberID);
    $stmt->execute();
    $stmt->bind_result($balance);
    $stmt->fetch();
    $stmt->close();
} else {
    die("Database error: Unable to prepare statement.");
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Balance</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            color: #333;
            padding-top: 60px;
            margin: 0;
        }

        .top-bar {
            position: fixed;
            top: 0;
            width: 100%;
            background-color: #4c87ae;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .top-bar h1 {
            font-size: 1.5em;
            margin: 0;
        }

        .top-bar .button-container {
            display: flex;
            gap: 10px;
        }

        .top-bar button {
            background-color: #fff;
            color: #4c87ae;
            border: none;
            padding: 10px 15px;
            font-size: 1em;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            width: 140px;
            text-align: center;
            white-space: nowrap;
        }

        .top-bar button:hover {
            background-color: #ddd;
        }

        .container {
            max-width: 600px;
            margin: 100px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .container h1 {
            color: #4c87ae;
            margin-bottom: 20px;
        }

        .balance {
            font-size: 1.5em;
            color: #333;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="top-bar">
        <h1>Fee</h1>
        <a href="profile.php"><button><h3>Profile</h3></button></a>
        <a href="index.php"><button><h3>Homepage</h3></button></a>
    </div>

    <div class="container">
        <h1>Fee Balance</h1>
        <p class="balance">Your current balance: $<?php echo number_format($balance, 2); ?></p>
    </div>
</body>

</html>
