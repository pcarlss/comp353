<?php
require '../session/db_connect.php';
session_start();

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

// Validate the incoming friendID parameter
if (!isset($_GET['friendID']) || !is_numeric($_GET['friendID'])) {
    header("Location: ../error.php?message=Invalid+friend+ID");
    exit;
}

$friendID = (int)$_GET['friendID'];

// Fetch friend details
$stmt = $conn->prepare("
    SELECT 
        m.FirstName, m.LastName, m.DateOfBirth, m.City, m.Country, 
        m.Profession, m.Privilege, m.Status, m.BusinessAccount, 
        m.Username, m.Email, m.profilePic, 
        p.Fname, p.Lname, p.BirthDate, p.pCity, p.pCountry, p.Work, p.pStatus
    FROM Member m
    JOIN Privacy p ON m.MemberID = p.PrivacyID
    WHERE m.MemberID = ?
");
$stmt->bind_param("i", $friendID);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $friend = $result->fetch_assoc();
} else {
    header("Location: ../error.php?message=Friend+not+found");
    exit;
}

// Function to get profile picture URL
function getProfilePic($profilePic) {
    $defaultPicPath = "../uploads/images/default_pfp.png";

    if (!empty($profilePic) && file_exists(__DIR__ . "/../" . $profilePic)) {
        return "../" . htmlspecialchars($profilePic);
    } else {
        return $defaultPicPath;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friend Info</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f0f2f5;
            font-family: Arial, sans-serif;
        }
        .info-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 400px;
        }
        .info-card img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 2px solid #4c87ae;
        }
        .info-card h2 {
            font-size: 1.5em;
            margin-bottom: 10px;
            color: #4c87ae;
        }
        .info-card p {
            font-size: 1em;
            margin: 5px 0;
            color: #333;
        }
        .back-button {
            margin-top: 20px;
            display: inline-block;
            background-color: #4c87ae;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9em;
            transition: background-color 0.3s;
        }
        .back-button:hover {
            background-color: #6caad3;
        }
    </style>
</head>
<body>
    <div class="info-card">
        <img src="<?php echo getProfilePic($friend['profilePic']); ?>" alt="Profile Picture">
        <h2>
            <?php
            echo ($friend['Fname'] ? htmlspecialchars($friend['FirstName']) : "") .
                 ($friend['Lname'] ? " " . htmlspecialchars($friend['LastName']) : "");
            ?>
        </h2>

        <?php if ($friend['Fname'] || $friend['Lname']): ?>
            <p><strong>Full Name:</strong> <?php echo htmlspecialchars(trim($friend['FirstName'] . " " . $friend['LastName'])); ?></p>
        <?php endif; ?>
        <p><strong>Username:</strong> <?php echo htmlspecialchars($friend['Username']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($friend['Email']); ?></p> <!-- Always displayed -->
        <?php if ($friend['BirthDate']): ?>
            <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($friend['DateOfBirth']); ?></p>
        <?php endif; ?>
        <?php if ($friend['pCity']): ?>
            <p><strong>City:</strong> <?php echo htmlspecialchars($friend['City']); ?></p>
        <?php endif; ?>
        <?php if ($friend['pCountry']): ?>
            <p><strong>Country:</strong> <?php echo htmlspecialchars($friend['Country']); ?></p>
        <?php endif; ?>
        <?php if ($friend['Work']): ?>
            <p><strong>Profession:</strong> <?php echo htmlspecialchars($friend['Profession']); ?></p>
        <?php endif; ?>
        <?php if ($friend['pStatus']): ?>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($friend['Status']); ?></p>
        <?php endif; ?>

        <p><strong>Business Account:</strong> <?php echo $friend['BusinessAccount'] ? 'Yes' : 'No'; ?></p>
        <a href="friendlist.php" class="back-button">Back to Friend List</a>
    </div>
</body>
</html>
