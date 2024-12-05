<?php
require 'session/db_connect.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['username']) || $_SESSION['Privilege'] !== 'Administrator') {
    header("Location: index.php");
    exit();
}

$errors = [];
$success = '';
$edit_memberID = $_SESSION['edit_memberID'] ?? null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? null;
            $firstName = trim($_POST['firstName'] ?? '');
            $lastName = trim($_POST['lastName'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $dateOfBirth = trim($_POST['dateOfBirth'] ?? '') ?: null;
            $city = trim($_POST['city'] ?? '') ?: null;
            $country = trim($_POST['country'] ?? '') ?: null;
            $profession = trim($_POST['profession'] ?? '') ?: null;
            $businessAccount = isset($_POST['businessAccount']) ? 1 : 0;
            $privilege = $_POST['privilege'] ?? 'Junior';
            $status = 'Active';

            if (validateMemberInput($username, $password, $firstName, $lastName, $email)) {
                if (isUniqueField('Username', $username) && isUniqueField('Email', $email)) {
                    if (strlen($password) > 45) {
                        $errors[] = "Password must not exceed 45 characters.";
                        break;
                    }

                    $stmt = $conn->prepare("
                        INSERT INTO Member 
                        (Username, Password, FirstName, LastName, Email, DateOfBirth, City, Country, Profession, Privilege, Status, BusinessAccount, UserCreatedAt, UserUpdatedAt) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURDATE())
                    ");

                    if ($stmt) {
                        $stmt->bind_param(
                            "sssssssssiis",
                            $username,
                            $password,
                            $firstName,
                            $lastName,
                            $email,
                            $dateOfBirth,
                            $city,
                            $country,
                            $profession,
                            $privilege,
                            $status,
                            $businessAccount
                        );

                        if ($stmt->execute()) {
                            $success = "Member '$username' created successfully.";
                            $username = $firstName = $lastName = $email = $dateOfBirth = $city = $country = $profession = '';
                            $businessAccount = 0;
                        } else {
                            $errors[] = "Error during member creation: " . htmlspecialchars($stmt->error);
                        }
                        $stmt->close();
                    } else {
                        $errors[] = "Database error: Unable to prepare insert statement. " . htmlspecialchars($conn->error);
                    }
                }
            }
            break;

        case 'delete':
            $memberID = isset($_POST['memberID']) ? (int)$_POST['memberID'] : 0;

            if ($memberID > 0) {
                if ($memberID === $_SESSION['memberid']) {
                    $errors[] = "You cannot delete your own account.";
                    break;
                }

                $stmt = $conn->prepare("DELETE FROM Member WHERE MemberID = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $memberID);
                    if ($stmt->execute()) {
                        $success = "Member deleted successfully.";
                    } else {
                        $errors[] = "Error during member deletion: " . htmlspecialchars($stmt->error);
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Database error: Unable to prepare delete statement. " . htmlspecialchars($conn->error);
                }
            } else {
                $errors[] = "Valid Member ID is required for deletion.";
            }
            break;

            case 'edit':
                $edit_memberID = isset($_POST['memberID']) ? (int)$_POST['memberID'] : null;
            
                if ($edit_memberID) {
                    $_SESSION['edit_memberID'] = $edit_memberID;
            
                    // Ensure $members is populated
                    if (empty($members)) {
                        $stmt = $conn->prepare("SELECT MemberID, Password FROM Member");
                        if ($stmt) {
                            if ($stmt->execute()) {
                                $result = $stmt->get_result();
                                while ($row = $result->fetch_assoc()) {
                                    $members[] = $row;
                                }
                            }
                            $stmt->close();
                        }
                    }
            
                    // Find and save the password for the selected member
                    foreach ($members as $member) {
                        if ((int)$member['MemberID'] === $edit_memberID) {
                            $_SESSION['edit_password'] = $member['Password'];
                            break;
                        }
                    }
                }
                break;            
                                            
                


            case 'save':
                    
                    if (!isset($_SESSION['edit_memberID'])) {
                        $errors[] = "Error";
                        break;
                    }
                
                    $memberID = $_SESSION['edit_memberID'];
                
             
                    $username    = trim($_POST['username'] ?? '');
                    $password    = trim($_POST['password'] ?? '');
                    $firstName   = trim($_POST['firstName'] ?? '');
                    $lastName    = trim($_POST['lastName'] ?? '');
                    $email       = trim($_POST['email'] ?? '');
                    $dateOfBirth = trim($_POST['dateOfBirth'] ?? '') ?: null;
                    $city        = trim($_POST['city'] ?? '') ?: null;
                    $country     = trim($_POST['country'] ?? '') ?: null;
                    $profession  = trim($_POST['profession'] ?? '') ?: null;
                    $privilege   = $_POST['privilege'] ?? 'Junior';
                    $status      = $_POST['Status'] ?? 'Active';
                
                
                    
                    if (validateMemberInput($username, $password, $firstName, $lastName, $email, true)) {
                        if (isUniqueField('Username', $username, $memberID) && isUniqueField('Email', $email, $memberID)) {
                            
                            
                            if (empty($password)) {
                                if (isset($_SESSION['edit_password'])) {
                                    $password = $_SESSION['edit_password'];
                                } else {
                                    $errors[] = "Existing password not found. Please try editing again.";
                                    break;
                                }
                            } else {
            
                                if (strlen($password) > 45) {
                                    $errors[] = "Password must not exceed 45 characters.";
                                    break;
                                }
                            }
                
                            $stmt = $conn->prepare("
                                UPDATE Member 
                                SET 
                                    Username = ?, 
                                    Password = ?, 
                                    FirstName = ?, 
                                    LastName = ?, 
                                    Email = ?, 
                                    DateOfBirth = ?, 
                                    City = ?, 
                                    Country = ?, 
                                    Profession = ?, 
                                    Privilege = ?, 
                                    Status = ?, 
                                    UserUpdatedAt = CURDATE()
                                WHERE MemberID = ?
                            ");
                
                            if ($stmt) {
                                $stmt->bind_param(
                                    "sssssssssssi",
                                    $username,
                                    $password,
                                    $firstName,
                                    $lastName,
                                    $email,
                                    $dateOfBirth,
                                    $city,
                                    $country,
                                    $profession,
                                    $privilege,
                                    $status,
                                    $memberID
                                );
                
                                if ($stmt->execute()) {
                                    $success = "Member '$username' updated successfully.";
                                    
                                    unset($_SESSION['edit_memberID']);
                                    unset($_SESSION['edit_password']);
                                } else {
                                    $errors[] = "Error during member update: " . htmlspecialchars($stmt->error);
                                }
                
                                $stmt->close();
                            } else {
                                $errors[] = "Database error: Unable to prepare update statement. " . htmlspecialchars($conn->error);
                            }
                        }
                    }
                    if (empty($errors)) { 
   
                        header("Location: administrator.php"); 
                        exit(); 
                    }
                    break;
                
                   
        case 'accept_promotion':
            $memberID = isset($_POST['memberID']) ? (int)$_POST['memberID'] : 0;
            if ($memberID > 0) {
                $stmt = $conn->prepare("UPDATE Member SET Privilege = 'Senior', UserUpdatedAt = CURDATE() WHERE MemberID = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $memberID);
                    if ($stmt->execute()) {
                        $stmt->close();
                        $stmt_del = $conn->prepare("DELETE FROM PromotionRequests WHERE MemberID = ?");
                        if ($stmt_del) {
                            $stmt_del->bind_param("i", $memberID);
                            $stmt_del->execute();
                            $stmt_del->close();
                            $success = "Promotion accepted and member upgraded to Senior.";
                        } else {
                            $errors[] = "Database error: Unable to prepare delete statement. " . htmlspecialchars($conn->error);
                        }
                    } else {
                        $errors[] = "Error during privilege update: " . htmlspecialchars($stmt->error);
                        $stmt->close();
                    }
                } else {
                    $errors[] = "Database error: Unable to prepare update statement. " . htmlspecialchars($conn->error);
                }
            } else {
                $errors[] = "Valid Member ID is required to accept promotion.";
            }
            break;

        case 'refuse_promotion':
            $memberID = isset($_POST['memberID']) ? (int)$_POST['memberID'] : 0;
            if ($memberID > 0) {
                $stmt = $conn->prepare("DELETE FROM PromotionRequests WHERE MemberID = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $memberID);
                    if ($stmt->execute()) {
                        $success = "Promotion request refused.";
                    } else {
                        $errors[] = "Error during refusal: " . htmlspecialchars($stmt->error);
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Database error: Unable to prepare delete statement. " . htmlspecialchars($conn->error);
                }
            } else {
                $errors[] = "Valid Member ID is required to refuse promotion.";
            }
            break;

        case 'cancel_edit':
                unset($_SESSION['edit_memberID']);
                unset($_SESSION['edit_password']); // Clear the password as well if needed
                header("Location: administrator.php"); // Redirect back to the same page
            break;
    }
}

$members = [];
$stmt = $conn->prepare("SELECT MemberID, Username, Password, FirstName, LastName, Email, DateOfBirth, City, Country, Profession, BusinessAccount, Privilege, Status, UserCreatedAt, UserUpdatedAt FROM Member");
if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
    } else {
        $errors[] = "Error fetching members: " . htmlspecialchars($stmt->error);
    }
    $stmt->close();
} else {
    $errors[] = "Database error: Unable to prepare select statement. " . htmlspecialchars($conn->error);
}

$promotionRequests = [];
$stmt_promo = $conn->prepare("
    SELECT PR.RequestID, PR.MemberID, M.Username 
    FROM PromotionRequests PR
    JOIN Member M ON PR.MemberID = M.MemberID
");
if ($stmt_promo) {
    if ($stmt_promo->execute()) {
        $result_promo = $stmt_promo->get_result();
        while ($row = $result_promo->fetch_assoc()) {
            $promotionRequests[] = $row;
        }
    } else {
        $errors[] = "Error fetching promotion requests: " . htmlspecialchars($stmt_promo->error);
    }
    $stmt_promo->close();
} else {
    $errors[] = "Database error: Unable to prepare promotion requests statement. " . htmlspecialchars($conn->error);
}

$conn->close();

function validateMemberInput($username, $password, $firstName, $lastName, $email, $isEdit = false) {
    global $errors;
    if (empty($username) || empty($firstName) || empty($lastName) || empty($email)) {
        $errors[] = "Username, First Name, Last Name, and Email are required.";
    }

    $fields = [
        'Username' => $username,
        'First Name' => $firstName,
        'Last Name' => $lastName,
        'Email' => $email
    ];

    foreach ($fields as $fieldName => $value) {
        if (strlen($value) > 45) {
            $errors[] = "$fieldName must not exceed 45 characters.";
        }
    }

    if (!$isEdit || ($isEdit && !empty($password))) {
        if (empty($password)) {
            $errors[] = "Password is required.";
        }
        if (!empty($password) && strlen($password) > 45) {
            $errors[] = "Password must not exceed 45 characters.";
        }
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (!empty($_POST['dateOfBirth'])) {
        $dateOfBirth = $_POST['dateOfBirth'];
        if (!validateDate($dateOfBirth)) {
            $errors[] = "Invalid Date of Birth. Please use the format YYYY-MM-DD.";
        }
    }

    return empty($errors);
}

function isUniqueField($field, $value, $excludeID = null) {
    global $conn, $errors;
    $query = "SELECT MemberID FROM Member WHERE LOWER($field) = LOWER(?)";
    if ($excludeID) {
        $query .= " AND MemberID != ?";
    }
    $stmt = $conn->prepare($query);
    if ($stmt) {
        if ($excludeID) {
            $stmt->bind_param("si", $value, $excludeID);
        } else {
            $stmt->bind_param("s", $value);
        }
        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = ucfirst($field) . " '$value' is already in use.";
                $stmt->close();
                return false;
            }
        } else {
            $errors[] = "Database error during uniqueness check: " . htmlspecialchars($stmt->error);
            $stmt->close();
            return false;
        }
        $stmt->close();
    } else {
        $errors[] = "Database error: Unable to prepare uniqueness check statement. " . htmlspecialchars($conn->error);
        return false;
    }
    return true;
}

function validateDate($date) {
    if (empty($date)) {
        return true;
    }
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Panel</title>
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
            width: 120px;
            text-align: center;
            white-space: nowrap;
        }

        .top-bar button:hover {
            background-color: #ddd;
        }

        .container {
            max-width: 1200px;
            margin: 80px auto 40px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .messages {
            margin-bottom: 20px;
        }

        .messages p {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .messages .error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .messages .success {
            background-color: #d4edda;
            color: #155724;
        }

        .scroll-table {
            overflow-y: auto;
            max-height: 300px;
            margin-bottom: 40px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1700px;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background-color: #4c87ae;
            color: white;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .form-section {
            margin-bottom: 40px;
        }

        .form-section h3 {
            margin-bottom: 15px;
            color: #4c87ae;
        }

        .form-section form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .form-section label {
            width: 100%;
            font-weight: bold;
        }

        .form-section input[type="text"],
        .form-section input[type="password"],
        .form-section input[type="email"],
        .form-section select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .form-section .half-width {
            width: 48%;
        }

        .form-section .full-width {
            width: 100%;
        }

        .form-section button {
            padding: 10px 20px;
            background-color: #4c87ae;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .form-section button:hover {
            background-color: #6caad3;
        }

        .action-buttons form {
            display: inline;
        }

        .action-buttons .delete-button {
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            padding: 5px 10px;
            transition: background-color 0.3s;
        }

        .action-buttons .delete-button:hover {
            background-color: #c0392b;
        }

        .action-buttons button {
            padding: 5px 10px;
            margin-right: 5px;
            background-color: #4c87ae;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .action-buttons button:hover {
            background-color: #6caad3;
        }

        .promotion-request-button {
            background-color: #4c87ae;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .promotion-request-button:hover {
            background-color: #6caad3;
        }

        .promotion-request-button.accept {
            background-color: #28a745;
        }

        .promotion-request-button.accept:hover {
            background-color: #218838;
        }

        .promotion-request-button.refuse {
            background-color: #dc3545;
        }

        .promotion-request-button.refuse:hover {
            background-color: #c82333;
        }

        @media (max-width: 768px) {
            .form-section .half-width {
                width: 100%;
            }

            table {
                min-width: 1700px;
            }
        }
    </style>
    <script>

        window.addEventListener('DOMContentLoaded', (event) => {
            const passwordField = document.querySelector('input[name="password"]');
            if (passwordField) {
                const savedPassword = sessionStorage.getItem('edit_password');
                if (savedPassword) {
                    passwordField.value = savedPassword;
                }

                passwordField.addEventListener('input', () => {
                    sessionStorage.setItem('edit_password', passwordField.value);
                });
            }

            const formSections = document.querySelectorAll('.form-section form');
            formSections.forEach(form => {
                form.addEventListener('submit', () => {
                    sessionStorage.removeItem('edit_password');
                });
            });
        });
    </script>
</head>

<body>
    <!-- Top Bar -->
    <div class="top-bar">
        <h1>Console</h1>
        <a href="profile.php"><button>
                <h3>Profile</h3>
            </button></a>
        <a href="index.php"><button>
                <h3>Homepage</h3>
            </button></a>
        </div>

    <div class="container">
        <?php if (!empty($errors)): ?>
            <div class="messages">
                <?php foreach ($errors as $error): ?>
                    <p class="error"><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="messages">
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>

        <div class="scroll-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Password</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Email</th>
                        <th>Date of Birth</th>
                        <th>City</th>
                        <th>Country</th>
                        <th>Profession</th>
                        <th>Business Account</th>
                        <th>Privilege</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Updated At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($members)): ?>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['MemberID']); ?></td>
                                <?php if ($edit_memberID === (int)$member['MemberID']): ?>
                                    <form method="POST">
                                        <td>
                                            <input type="text" name="username" value="<?php echo htmlspecialchars($member['Username']); ?>" required maxlength="45">
                                        </td>
                                        <td>
                                           <input type="text" name="password" value="<?php echo htmlspecialchars($_SESSION['edit_password'] ?? ''); ?>" maxlength="45">
                                        </td>
                                        <td>
                                            <input type="text" name="firstName" value="<?php echo htmlspecialchars($member['FirstName']); ?>" required maxlength="45">
                                        </td>
                                        <td>
                                            <input type="text" name="lastName" value="<?php echo htmlspecialchars($member['LastName']); ?>" required maxlength="45">
                                        </td>
                                        <td>
                                            <input type="email" name="email" value="<?php echo htmlspecialchars($member['Email']); ?>" required maxlength="45">
                                        </td>
                                        <td>
                                            <input type="text" name="dateOfBirth" value="<?php echo htmlspecialchars($member['DateOfBirth'] ?? ''); ?>" placeholder="YYYY-MM-DD">
                                        </td>
                                        <td>
                                            <input type="text" name="city" value="<?php echo htmlspecialchars($member['City'] ?? ''); ?>" maxlength="45">
                                        </td>
                                        <td>
                                            <input type="text" name="country" value="<?php echo htmlspecialchars($member['Country'] ?? ''); ?>" maxlength="45">
                                        </td>
                                        <td>
                                            <input type="text" name="profession" value="<?php echo htmlspecialchars($member['Profession'] ?? ''); ?>" maxlength="45">
                                        </td>
                                        <td>
                                            <input type="checkbox" name="businessAccount" <?php echo ($member['BusinessAccount']) ? 'checked' : ''; ?>>
                                        </td>
                                        <td>
                                            <?php if ($member['Privilege'] === 'Administrator'): ?>
                                                <select name="privilege" disabled>
                                                    <option value="Administrator" selected>Administrator</option>
                                                </select>
                                            <?php else: ?>
                                                <select name="privilege">
                                                    <option value="Junior" <?php echo ($member['Privilege'] === 'Junior') ? 'selected' : ''; ?>>Junior</option>
                                                    <option value="Senior" <?php echo ($member['Privilege'] === 'Senior') ? 'selected' : ''; ?>>Senior</option>
                                                    <option value="Administrator" <?php echo ($member['Privilege'] === 'Administrator') ? 'selected' : ''; ?>>Administrator</option>
                                                </select>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td>
                                            <?php if ($member['Privilege'] === 'Administrator'):  ?>
                                                <select name="Status" disabled>
                                                    <option value="Active" selected>Active</option>
                                                </select>
                                            <?php else: ?>
                                                <select name="Status">
                                                    <option value="Active" <?php echo ($member['Status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                                                    <option value="Inactive" <?php echo ($member['Status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                    <option value="Suspended" <?php echo ($member['Status'] === 'Suspended') ? 'selected' : ''; ?>>Suspended</option>
                                                </select>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php echo htmlspecialchars($member['UserCreatedAt']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($member['UserUpdatedAt']); ?>
                                        </td>
                                        <td class="action-buttons">
                                            <input type="hidden" name="action" value="save">
                                            <input type="hidden" name="memberID" value="<?php echo $member['MemberID']; ?>">
                                            <button type="submit">Save</button>
                                        </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="cancel_edit">
                                                <button type="submit">Cancel</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <td><?php echo htmlspecialchars($member['Username']); ?></td>
                                    <td><?php echo htmlspecialchars($member['Password']); ?></td>
                                    <td><?php echo htmlspecialchars($member['FirstName']); ?></td>
                                    <td><?php echo htmlspecialchars($member['LastName']); ?></td>
                                    <td><?php echo htmlspecialchars($member['Email']); ?></td>
                                    <td><?php echo htmlspecialchars($member['DateOfBirth'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($member['City'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($member['Country'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($member['Profession'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($member['BusinessAccount'] ? 'Yes' : 'No'); ?></td>
                                    <td><?php echo htmlspecialchars($member['Privilege']); ?></td>
                                    <td><?php echo htmlspecialchars($member['Status']); ?></td>
                                    <td><?php echo htmlspecialchars($member['UserCreatedAt']); ?></td>
                                    <td><?php echo htmlspecialchars($member['UserUpdatedAt']); ?></td>
                                    <td class="action-buttons">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="memberID" value="<?php echo $member['MemberID']; ?>">
                                            <button type="submit">Edit</button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="memberID" value="<?php echo $member['MemberID']; ?>">
                                            <button type="submit" class="delete-button">Delete</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="16" style="text-align: center;">No members found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="form-section">
            <h3>Create New Member</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create">

                <label>Username<span style="color: red;">*</span>:</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required maxlength="45">

                <label>Password<span style="color: red;">*</span>:</label>
                <input type="password" name="password" required maxlength="45" placeholder="Enter password">

                <label>First Name<span style="color: red;">*</span>:</label>
                <input type="text" name="firstName" value="<?php echo htmlspecialchars($firstName ?? ''); ?>" required maxlength="45">

                <label>Last Name<span style="color: red;">*</span>:</label>
                <input type="text" name="lastName" value="<?php echo htmlspecialchars($lastName ?? ''); ?>" required maxlength="45">

                <label>Email<span style="color: red;">*</span>:</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required maxlength="45">

                <label>Date of Birth:</label>
                <input type="text" name="dateOfBirth" value="<?php echo htmlspecialchars($dateOfBirth ?? ''); ?>" placeholder="YYYY-MM-DD">

                <label>City:</label>
                <input type="text" name="city" value="<?php echo htmlspecialchars($city ?? ''); ?>" maxlength="45">

                <label>Country:</label>
                <input type="text" name="country" value="<?php echo htmlspecialchars($country ?? ''); ?>" maxlength="45">

                <label>Profession:</label>
                <input type="text" name="profession" value="<?php echo htmlspecialchars($profession ?? ''); ?>" maxlength="45">

                <label>Business Account:</label>
                <input type="checkbox" name="businessAccount" <?php echo (isset($businessAccount) && $businessAccount) ? 'checked' : ''; ?>>

                <label>Privilege:</label>
                <select name="privilege">
                    <option value="Junior" <?php echo (isset($privilege) && $privilege === 'Junior') ? 'selected' : ''; ?>>Junior</option>
                    <option value="Senior" <?php echo (isset($privilege) && $privilege === 'Senior') ? 'selected' : ''; ?>>Senior</option>
                    <option value="Administrator" <?php echo (isset($privilege) && $privilege === 'Administrator') ? 'selected' : ''; ?>>Administrator</option>
                </select>

                <button type="submit">Create Member</button>
            </form>
        </div>

        <div class="form-section">
            <h3>Promotion Requests</h3>
            <div class="scroll-table" style="max-height: 300px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Member ID</th>
                            <th>Username</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($promotionRequests)): ?>
                            <?php foreach ($promotionRequests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['RequestID']); ?></td>
                                    <td><?php echo htmlspecialchars($request['MemberID']); ?></td>
                                    <td><?php echo htmlspecialchars($request['Username']); ?></td>
                                    <td class="action-buttons">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="accept_promotion">
                                            <input type="hidden" name="memberID" value="<?php echo htmlspecialchars($request['MemberID']); ?>">
                                            <button type="submit" class="promotion-request-button accept">Accept</button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="refuse_promotion">
                                            <input type="hidden" name="memberID" value="<?php echo htmlspecialchars($request['MemberID']); ?>">
                                            <button type="submit" class="promotion-request-button refuse">Refuse</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center;">No promotion requests found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>
