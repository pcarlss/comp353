<?php
require 'session/db_connect.php';
session_start();

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/your/php-error.log'); 
error_reporting(E_ALL);


$errors = [];
$success = '';
$firstName = $lastName = $email = $dob = $city = $country = $profession = $status = '';
$businessAccount = 'Personal';
$profilePic = 'uploads/images/default_pfp.png'; 


if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}


if (!empty($_SESSION['username'])) {
    $username = $_SESSION['username'];

    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        
        if ($action === 'update_privacy') {
            
            if (empty($_SESSION['username'])) {
                echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
                exit();
            }

            $field = $_POST['field'] ?? '';
            $value = $_POST['value'] ?? '';

            
            $allowed_fields = [
                'Fname'      => 'Fname',
                'Lname'      => 'Lname',
                'BirthDate'  => 'BirthDate',
                'pCity'      => 'pCity',
                'pCountry'   => 'pCountry',
                'Work'       => 'Work',
                'pStatus'    => 'pStatus'
            ];

            
            if (!array_key_exists($field, $allowed_fields)) {
                echo json_encode(['success' => false, 'message' => 'Invalid privacy field.']);
                exit();
            }

            
            if (!in_array($value, ['0', '1'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid value for privacy setting.']);
                exit();
            }

            
            $memberIdQuery = "SELECT MemberID FROM Member WHERE Username = ?";
            $memberIdStmt = $conn->prepare($memberIdQuery);
            if (!$memberIdStmt) {
                error_log("Prepare failed: " . $conn->error);
                echo json_encode(['success' => false, 'message' => 'Database error.']);
                exit();
            }
            $memberIdStmt->bind_param("s", $username);
            $memberIdStmt->execute();
            $memberIdStmt->bind_result($memberId);
            if (!$memberIdStmt->fetch()) {
                $memberIdStmt->close();
                echo json_encode(['success' => false, 'message' => 'User not found.']);
                exit();
            }
            $memberIdStmt->close();

            
            $updateQuery = "UPDATE Privacy SET {$allowed_fields[$field]} = ? WHERE PrivacyID = ?";
            $updateStmt = $conn->prepare($updateQuery);
            if (!$updateStmt) {
                error_log("Prepare failed: " . $conn->error);
                echo json_encode(['success' => false, 'message' => 'Database error.']);
                exit();
            }
            $updateStmt->bind_param("ii", $value, $memberId);
            if ($updateStmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Privacy setting updated successfully.']);
            } else {
                error_log("Execute failed: " . $updateStmt->error);
                echo json_encode(['success' => false, 'message' => 'Failed to update privacy setting.']);
            }
            $updateStmt->close();
            exit();
        }

        
        elseif ($action === 'request_promotion') {
            
            if ($_SESSION['Privilege'] !== 'Junior') {
                $errors[] = "Only Junior members can request a promotion.";
            } else {
                
                $query = "SELECT MemberID FROM Member WHERE Username = ?";
                $stmt = $conn->prepare($query);
                if ($stmt) {
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $stmt->bind_result($memberID);
                    if (!$stmt->fetch()) {
                        $errors[] = "Member not found.";
                    }
                    $stmt->close();

                    
                    $checkQuery = "SELECT COUNT(*) FROM PromotionRequests WHERE MemberID = ?";
                    $stmt = $conn->prepare($checkQuery);
                    if ($stmt) {
                        $stmt->bind_param("i", $memberID);
                        $stmt->execute();
                        $stmt->bind_result($count);
                        $stmt->fetch();
                        $stmt->close();

                        if ($count > 0) {
                            $errors[] = "You already have a pending promotion request.";
                        } else {
                            
                            $insertQuery = "INSERT INTO PromotionRequests (MemberID) VALUES (?)";
                            $stmt = $conn->prepare($insertQuery);
                            if ($stmt) {
                                $stmt->bind_param("i", $memberID);
                                if ($stmt->execute()) {
                                    $success = "Promotion request submitted successfully.";
                                } else {
                                    $errors[] = "Failed to submit promotion request.";
                                }
                                $stmt->close();
                            } else {
                                $errors[] = "Database error: " . $conn->error;
                            }
                        }
                    } else {
                        $errors[] = "Database error: " . $conn->error;
                    }
                } else {
                    $errors[] = "Database error: " . $conn->error;
                }
            }

            
            if (!empty($errors)) {
                $_SESSION['errors'] = $errors;
                header("Location: profile.php");
                exit();
            } elseif (!empty($success)) {
                $_SESSION['success'] = $success;
                header("Location: profile.php");
                exit();
            }
        }
    }

    
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_picture'])) {
        $maxFileSize = 5 * 1024 * 1024; // 5MB

        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            $fileSize = $file['size'];

            
            if ($fileSize > $maxFileSize) {
                $errors[] = "File size must be less than 5MB.";
            }

            
            $check = getimagesize($file['tmp_name']);
            if ($check !== false) {
                $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

            
                if (!in_array($fileExtension, $allowedExtensions)) {
                    $errors[] = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
                }

                
                $newFileName = uniqid('pfp_', true) . '.' . $fileExtension;

                
                $uploadDir = __DIR__ . '/uploads/images/';
                $relativeDir = 'uploads/images/';
                $uploadPath = $uploadDir . $newFileName;
                $relativePath = $relativeDir . $newFileName;

                
                if (!file_exists($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        $errors[] = "Failed to create directory for uploads.";
                    }
                }

                
                if (!is_writable($uploadDir)) {
                    $errors[] = "Upload directory is not writable.";
                }

                
                if (empty($errors)) {
                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        
                        $stmt = $conn->prepare("UPDATE Member SET ProfilePic = ?, UserUpdatedAt = NOW() WHERE Username = ?");
                        if (!$stmt) {
                            $errors[] = "Database error: " . $conn->error;
                        } else {
                            $stmt->bind_param('ss', $relativePath, $username);
                            if ($stmt->execute()) {
                                $success = "Your profile picture has been updated successfully.";
                                $profilePic = $relativePath;
                            } else {
                                $errors[] = "Database update failed: " . $stmt->error;
                            }
                            $stmt->close();
                        }
                    } else {
                        $errors[] = "Failed to upload the file. Please check directory permissions.";
                    }
                }
            } else {
                $errors[] = "Uploaded file is not a valid image.";
            }
        } else {
            $errors[] = "No file uploaded or there was an upload error.";
        }

        
        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            header("Location: profile.php");
            exit();
        } elseif (!empty($success)) {
            $_SESSION['success'] = $success;
            header("Location: profile.php");
            exit();
        }
    }

    
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['field']) && !isset($_POST['upload_picture'])) {
        $field = $_POST['field'];
        $value = trim($_POST['value']);

        $allowed_fields = [
            'firstname'   => 'FirstName',
            'lastname'    => 'LastName',
            'email'       => 'Email',
            'dob'         => 'DateOfBirth',
            'city'        => 'City',
            'country'     => 'Country',
            'profession'  => 'Profession'
        ];

        if (array_key_exists($field, $allowed_fields)) {
            $db_field = $allowed_fields[$field];

            
            if (in_array($field, ['firstname', 'lastname', 'email']) && empty($value)) {
                $errors[] = ucfirst($field) . " is required.";
            }

        
            if (strlen($value) > 45) {
                $errors[] = ucfirst($field) . " must not exceed 45 characters.";
            }

            
            if ($field === 'email') {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Please enter a valid email address.";
                } else {
                    
                    $stmt = $conn->prepare("SELECT Username FROM Member WHERE Email = ? AND Username != ?");
                    if (!$stmt) {
                        $errors[] = "Database error: " . $conn->error;
                    } else {
                        $stmt->bind_param('ss', $value, $username);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result->num_rows > 0) {
                            $errors[] = "This email address is already registered to another account.";
                        }
                        $stmt->close();
                    }
                }
            }

            if ($field === 'dob' && !empty($value)) {
                $dateParts = explode('-', $value);
                if (
                    count($dateParts) === 3 &&
                    is_numeric($dateParts[0]) &&
                    is_numeric($dateParts[1]) &&
                    is_numeric($dateParts[2]) &&
                    checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])
                ) {
                    
                } else {
                    $errors[] = "Invalid date format or date does not exist. Please enter a valid date (YYYY-MM-DD).";
                }
            }

            
            if (empty($errors)) {
                $stmt = $conn->prepare("UPDATE Member SET $db_field = ?, UserUpdatedAt = NOW() WHERE Username = ?");
                if (!$stmt) {
                    $errors[] = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param('ss', $value, $username);
                    if ($stmt->execute()) {
                        $success = "Your profile has been updated successfully.";
                    } else {
                        $errors[] = "Database update failed: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }

            if (!empty($errors)) {
                $_SESSION['errors'] = $errors;
                header("Location: profile.php");
                exit();
            } elseif (!empty($success)) {
                $_SESSION['success'] = $success;
                header("Location: profile.php");
                exit();
            }
        } else {
            $errors[] = "Invalid field.";
            $_SESSION['errors'] = $errors;
            header("Location: profile.php");
            exit();
        }
    }

    $query = "SELECT MemberID, FirstName, LastName, Email, DateOfBirth, City, Country, Profession, BusinessAccount, ProfilePic, Privilege, Status 
              FROM Member 
              WHERE Username = ?";

    $isAdmin = false;

    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            $memberId = $user['MemberID'];
            $firstName = $user['FirstName'] ?? '';
            $lastName = $user['LastName'] ?? '';
            $email = $user['Email'] ?? '';
            $dob = $user['DateOfBirth'] ?? '';
            $city = $user['City'] ?? '';
            $country = $user['Country'] ?? '';
            $profession = $user['Profession'] ?? '';
            $status = $user['Status'] ?? '';
            $businessAccount = $user['BusinessAccount'] ? 'Business' : 'Personal';
            $profilePic = $user['ProfilePic'] ?? 'uploads/images/default_pfp.png';

            $_SESSION['Privilege'] = $user['Privilege'];

            if ($_SESSION['Privilege'] === 'Administrator') {
                $isAdmin = true;
            }
        } else {
            $errors[] = "User not found.";
        }
        $stmt->close();
    }

    $privacyQuery = "SELECT Fname, Lname, BirthDate, pCity, pCountry, Work, pStatus FROM Privacy WHERE PrivacyID = ?";
    $privacyStmt = $conn->prepare($privacyQuery);
    if ($privacyStmt) {
        $privacyStmt->bind_param("i", $memberId);
        $privacyStmt->execute();
        $privacyResult = $privacyStmt->get_result();

        if ($privacyResult->num_rows > 0) {
            $userPrivacy = $privacyResult->fetch_assoc();
        } else {
            $userPrivacy = [
                'Fname'      => 0,
                'Lname'      => 0,
                'BirthDate'  => 0,
                'pCity'      => 0,
                'pCountry'   => 0,
                'Work'       => 0,
                'pStatus'    => 0
            ];
        }
        $privacyStmt->close();
    } else {
        $errors[] = "Failed to fetch privacy settings.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Page</title>
    <style>
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        
        body {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            background-color: #f0f2f5;
            font-family: Arial, sans-serif;
            min-height: 100vh;
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
            width: 140px; 
            text-align: center;
            white-space: nowrap;
        }

        .top-bar button:hover {
            background-color: #ddd;
        }

        .container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            width: 100%;
            max-width: 800px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 70px;
            margin-bottom: 70px;
        }

        h2 {
            font-size: 1.8em;
            margin-bottom: 20px;
            color: #333;
        }

        .profile-picture {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
        }

        .profile-picture img {
            border-radius: 50%;
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 3px solid #4c87ae;
        }

        .profile-picture form {
            margin-top: 10px;
        }

        .profile-picture input[type="file"] {
            display: none;
        }

        .profile-picture label {
            background-color: #4c87ae;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s;
        }

        .profile-picture label:hover {
            background-color: #6caad3;
        }

        
.profile-meta {
    display: inline-block; 
    margin-bottom: 10px; 
    font-size: 1.2em; 
}

.profile-meta .label {
    color: #4c87ae;
    font-weight: bold; 
}

.profile-meta .value {
    color: #000; 
    font-weight: normal; 
}

        
        .profile-item {
            display: grid;
            grid-template-columns: 1fr 2fr 200px 100px; 
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .profile-item p {
            font-size: 1.2em;
            margin: 0;
            color: #4c87ae;
            font-weight: bold;
        }

        .profile-item span {
            color: #333;
            font-weight: normal;
            font-size: 1.2em;
        }

        .profile-item input[type="text"],
        .profile-item input[type="email"] {
            font-size: 1.2em;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 100%;
            display: none;
        }

        .button-group {
            display: flex;
            gap: 5px;
            justify-content: flex-end;
        }

        .button-group button {
            background-color: #4c87ae;
            color: white;
            border: none;
            padding: 6px 12px;
            font-size: 0.9em;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .button-group button:hover {
            background-color: #6caad3;
        }

        .privacy-checkbox-container {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .privacy-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .promotion-request-form {
    text-align: left; 
}

.promotion-button {
    background-color: #4c87ae; 
    color: white; 
    border: none;
    padding: 10px 10px; 
    font-size: 1em;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s, transform 0.2s; 
}

.promotion-button:hover {
    background-color: #6caad3; 
    transform: scale(1.05); 
}


    
        .message {
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 5px;
            opacity: 1;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
        }

        @media (max-width: 600px) {
            .profile-item {
                grid-template-columns: 1fr 2fr 150px 80px; 
            }

            .top-bar .button-container {
                flex-direction: column;
                align-items: stretch;
            }

            .top-bar button {
                width: 100%; 
            }
            

        }
    </style>

    <script>
        function toggleEdit(field) {
            const profileField = document.getElementById('profile-' + field);
            const editField = document.getElementById('edit-' + field);
            const saveButton = document.getElementById(field + '-save-button');
            const cancelButton = document.getElementById(field + '-cancel-button');
            const editButton = document.getElementById(field + '-edit-button');

            editField.style.display = "block";
            saveButton.style.display = "inline-block";
            cancelButton.style.display = "inline-block";

            profileField.style.display = "none";
            editButton.style.display = "none";
        }

        function saveEdit(field) {
            const editField = document.getElementById('edit-' + field);
            const newValue = editField.value.trim();

            if (field === 'dob' && !/^\d{4}-\d{2}-\d{2}$/.test(newValue)) {
                displayErrorMessage("Please enter a valid date in the format YYYY-MM-DD.");
                return;
            }

            if (field === 'email') {
                checkEmailUniqueness(newValue)
                    .then(isUnique => {
                        if (isUnique) {
                            submitProfileForm(field, newValue);
                        } else {
                            displayErrorMessage('This email address is already registered to another account.');
                        }
                    })
                    .catch(error => {
                        console.error('Error checking email uniqueness:', error);
                        displayErrorMessage('An error occurred while checking the email.');
                    });
            } else {
                submitProfileForm(field, newValue);
            }
        }

        function cancelEdit(field) {
            const profileField = document.getElementById('profile-' + field);
            const editField = document.getElementById('edit-' + field);
            const saveButton = document.getElementById(field + '-save-button');
            const cancelButton = document.getElementById(field + '-cancel-button');
            const editButton = document.getElementById(field + '-edit-button');

            editField.style.display = "none";
            saveButton.style.display = "none";
            cancelButton.style.display = "none";

            profileField.style.display = "inline";
            editButton.style.display = "inline";

            editField.value = profileField.textContent.trim();
        }

        function submitProfileForm(field, value) {
            document.getElementById('field').value = field;
            document.getElementById('value').value = value;
            document.getElementById('profileForm').submit();
        }

        function checkEmailUniqueness(email) {
            return fetch('profile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=check_email&email=' + encodeURIComponent(email)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.isUnique) {
                        return true;
                    } else {
                        return false;
                    }
                });
        }

        function displayErrorMessage(message) {
            let errorDiv = document.querySelector('.message.error');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'message error';
                document.querySelector('.container').insertBefore(errorDiv, document.querySelector('.container').lastElementChild);
            }
            errorDiv.innerHTML = '<p>' + message + '</p>';

            setTimeout(function () {
                if (errorDiv) {
                    errorDiv.style.transition = 'opacity 0.5s';
                    errorDiv.style.opacity = '0';
                    setTimeout(function () {
                        errorDiv.remove();
                    }, 500);
                }
            }, 3000);
        }

        function displaySuccessMessage(message) {
            let successDiv = document.querySelector('.message.success');
            if (!successDiv) {
                successDiv = document.createElement('div');
                successDiv.className = 'message success';
                document.querySelector('.container').insertBefore(successDiv, document.querySelector('.container').lastElementChild);
            }
            successDiv.innerHTML = `<p>${message}</p>`;
            successDiv.style.display = 'block';

            setTimeout(() => {
                successDiv.style.transition = 'opacity 0.5s';
                successDiv.style.opacity = '0';
                setTimeout(() => {
                    successDiv.remove();
                }, 500);
            }, 3000);
        }

        window.onload = function () {
            setTimeout(function () {
                let messages = document.querySelectorAll('.message');
                messages.forEach(function (message) {
                    message.style.transition = 'opacity 0.5s';
                    message.style.opacity = '0';
                    setTimeout(function () {
                        message.remove();
                    }, 500); 
                });
            }, 3000); 
        };

        function handleDateInput(input) {
            let value = input.value.replace(/[^0-9]/g, ""); 

            let formattedValue = "";
            if (value.length > 0) {
                formattedValue += value.substring(0, 4); 
            }
            if (value.length > 4) {
                formattedValue += "-" + value.substring(4, 6); 
            }
            if (value.length > 6) {
                formattedValue += "-" + value.substring(6, 8); 
            }

            input.value = formattedValue; 
        }

        document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.privacy-checkbox').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const field = this.getAttribute('data-field');
            const value = this.checked ? 1 : 0;

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'profile.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onload = function () {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            displaySuccessMessage(response.message);
                        } else {
                            displayErrorMessage(response.message);
                            checkbox.checked = !checkbox.checked; 
                        }
                    } catch (e) {
                        console.error('Invalid JSON response');
                    }
                } else {
                    console.error('Request failed. Status:', xhr.status);
                    checkbox.checked = !checkbox.checked; 
                }
            };

            
            xhr.onerror = function () {
                console.error('Request error...');
                checkbox.checked = !checkbox.checked; 
            };

            
            const params = `action=update_privacy&field=${encodeURIComponent(field)}&value=${encodeURIComponent(value)}`;
            xhr.send(params);
        });
    });
});
    </script>

</head>

<body>

   
    <div class="top-bar">
        <h1>Account</h1>
        <a href="index.php"><button>
                <h3>Homepage</h3>
            </button></a>
        <a href="member and friends/friendlist.php"><button>
                <h3>Friends List</h3>
            </button></a>
            <?php if ($isAdmin): ?>
                <a href="administrator.php"><button><h3>Admin Panel</h3></button></a>
            <?php endif; ?>
            <?php if ($businessAccount === 'Business'): ?>
                <a href="fee.php"><button><h3>Fee</h3></button></a>
            <?php endif; ?>
            <a href="session/signout.php"><button>
                <h3>Sign Out</h3>
            </button></a>
        </div>

    <div class="container">
        <h2>Profile Information</h2>
        
<div class="profile-meta">
    <span class="label">Username:</span> <span class="value"><?php echo htmlspecialchars($username); ?></span><br>
    <span class="label">Member ID:</span> <span class="value"><?php echo htmlspecialchars($memberId ?? 'N/A'); ?></span>
</div>


        <?php if (!empty($errors)): ?>
        <div class="message error">
            <?php foreach ($errors as $error): ?>
            <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
        <div class="message success">
            <p><?php echo htmlspecialchars($success); ?></p>
        </div>
        <?php endif; ?>

        <div class="profile-picture">
            <img id="profile-picture" src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profile Picture">
            <form id="profilePicForm" method="POST" action="profile.php" enctype="multipart/form-data">
                <input type="hidden" name="upload_picture" value="1">
                <input type="file" name="profile_picture" id="profile_picture_input" accept="image/*"
                    onchange="document.getElementById('profilePicForm').submit();">
                <label for="profile_picture_input">Change Picture</label>
            </form>
        </div>

        <form id="profileForm" method="POST" action="profile.php">
            <input type="hidden" name="field" id="field">
            <input type="hidden" name="value" id="value">
        </form>

        <div class="profile-item">
            <p>First Name:</p>
            <span id="profile-firstname"><?php echo htmlspecialchars($firstName); ?></span>
            <input id="edit-firstname" type="text" maxlength="45" value="<?php echo htmlspecialchars($firstName); ?>"
                style="display: none;">

            <div class="button-group">
                <button type="button" id="firstname-edit-button" onclick="toggleEdit('firstname')">
                    Edit
                </button>

                <button type="button" id="firstname-save-button" onclick="saveEdit('firstname')" style="display: none;">
                    Save
                </button>

                <button type="button" id="firstname-cancel-button" onclick="cancelEdit('firstname')" style="display: none;">
                    Cancel
                </button>
            </div>

            <div class="privacy-checkbox-container">
                <input type="checkbox" class="privacy-checkbox" data-field="Fname"
                    <?php echo ($userPrivacy['Fname'] ? 'checked' : ''); ?> id="privacy-fname">
                <label for="privacy-fname">Public</label>
            </div>
        </div>

        <div class="profile-item">
            <p>Last Name:</p>
            <span id="profile-lastname"><?php echo htmlspecialchars($lastName); ?></span>
            <input id="edit-lastname" type="text" maxlength="45" value="<?php echo htmlspecialchars($lastName); ?>"
                style="display: none;">

            <div class="button-group">
                <button type="button" id="lastname-edit-button" onclick="toggleEdit('lastname')">
                    Edit
                </button>

                <button type="button" id="lastname-save-button" onclick="saveEdit('lastname')" style="display: none;">
                    Save
                </button>

                <button type="button" id="lastname-cancel-button" onclick="cancelEdit('lastname')" style="display: none;">
                    Cancel
                </button>
            </div>

            <div class="privacy-checkbox-container">
                <input type="checkbox" class="privacy-checkbox" data-field="Lname"
                    <?php echo ($userPrivacy['Lname'] ? 'checked' : ''); ?> id="privacy-lname">
                <label for="privacy-lname">Public</label>
            </div>
        </div>

        <div class="profile-item">
            <p>Email:</p>
            <span id="profile-email"><?php echo htmlspecialchars($email); ?></span>
            <input id="edit-email" type="email" maxlength="45" value="<?php echo htmlspecialchars($email); ?>"
                style="display: none;">

            <div class="button-group">
                <button type="button" id="email-edit-button" onclick="toggleEdit('email')">
                    Edit
                </button>

                <button type="button" id="email-save-button" onclick="saveEdit('email')" style="display: none;">
                    Save
                </button>

                <button type="button" id="email-cancel-button" onclick="cancelEdit('email')" style="display: none;">
                    Cancel
                </button>
            </div>

            <div class="privacy-checkbox-container">
            </div>
        </div>

        <div class="profile-item">
            <p>Date of Birth:</p>
            <span id="profile-dob"><?php echo htmlspecialchars($dob); ?></span>
            <input id="edit-dob" type="text" placeholder="YYYY-MM-DD" maxlength="10"
                value="<?php echo htmlspecialchars($dob); ?>" oninput="handleDateInput(this)" style="display: none;">

            <div class="button-group">
                <button type="button" id="dob-edit-button" onclick="toggleEdit('dob')">
                    Edit
                </button>

                <button type="button" id="dob-save-button" onclick="saveEdit('dob')" style="display: none;">
                    Save
                </button>

                <button type="button" id="dob-cancel-button" onclick="cancelEdit('dob')" style="display: none;">
                    Cancel
                </button>
            </div>

            <div class="privacy-checkbox-container">
                <input type="checkbox" class="privacy-checkbox" data-field="BirthDate"
                    <?php echo ($userPrivacy['BirthDate'] ? 'checked' : ''); ?> id="privacy-birthdate">
                <label for="privacy-birthdate">Public</label>
            </div>
        </div>

        <div class="profile-item">
            <p>City:</p>
            <span id="profile-city"><?php echo htmlspecialchars($city); ?></span>
            <input id="edit-city" type="text" maxlength="45" value="<?php echo htmlspecialchars($city); ?>"
                style="display: none;">

            <div class="button-group">
                <button type="button" id="city-edit-button" onclick="toggleEdit('city')">
                    Edit
                </button>

                <button type="button" id="city-save-button" onclick="saveEdit('city')" style="display: none;">
                    Save
                </button>

                <button type="button" id="city-cancel-button" onclick="cancelEdit('city')" style="display: none;">
                    Cancel
                </button>
            </div>

            <div class="privacy-checkbox-container">
                <input type="checkbox" class="privacy-checkbox" data-field="pCity"
                    <?php echo ($userPrivacy['pCity'] ? 'checked' : ''); ?> id="privacy-pcity">
                <label for="privacy-pcity">Public</label>
            </div>
        </div>

        <div class="profile-item">
            <p>Country:</p>
            <span id="profile-country"><?php echo htmlspecialchars($country); ?></span>
            <input id="edit-country" type="text" maxlength="45" value="<?php echo htmlspecialchars($country); ?>"
                style="display: none;">

            <div class="button-group">
                <button type="button" id="country-edit-button" onclick="toggleEdit('country')">
                    Edit
                </button>

                <button type="button" id="country-save-button" onclick="saveEdit('country')" style="display: none;">
                    Save
                </button>

                <button type="button" id="country-cancel-button" onclick="cancelEdit('country')" style="display: none;">
                    Cancel
                </button>
            </div>

            <div class="privacy-checkbox-container">
                <input type="checkbox" class="privacy-checkbox" data-field="pCountry"
                    <?php echo ($userPrivacy['pCountry'] ? 'checked' : ''); ?> id="privacy-pcountry">
                <label for="privacy-pcountry">Public</label>
            </div>
        </div>

        <div class="profile-item">
            <p>Profession:</p>
            <span id="profile-profession"><?php echo htmlspecialchars($profession); ?></span>
            <input id="edit-profession" type="text" maxlength="45" value="<?php echo htmlspecialchars($profession); ?>"
                style="display: none;">

            <div class="button-group">
                <button type="button" id="profession-edit-button" onclick="toggleEdit('profession')">
                    Edit
                </button>

                <button type="button" id="profession-save-button" onclick="saveEdit('profession')" style="display: none;">
                    Save
                </button>

                <button type="button" id="profession-cancel-button" onclick="cancelEdit('profession')" style="display: none;">
                    Cancel
                </button>
            </div>

            <div class="privacy-checkbox-container">
                <input type="checkbox" class="privacy-checkbox" data-field="Work"
                    <?php echo ($userPrivacy['Work'] ? 'checked' : ''); ?> id="privacy-work">
                <label for="privacy-work">Public</label>
            </div>
        </div>

        <div class="profile-item">
            <p>Status:</p>
            <span id="profile-status"><?php echo htmlspecialchars($status); ?></span>

            <div class="button-group">
            </div>

            <div class="privacy-checkbox-container">
                <input type="checkbox" class="privacy-checkbox" data-field="pStatus"
                    <?php echo ($userPrivacy['pStatus'] ? 'checked' : ''); ?> id="privacy-pstatus">
                <label for="privacy-pstatus">Public</label>
            </div>
        </div>
        
        <?php if ($_SESSION['Privilege'] === 'Junior'): ?>
    <form method="POST" action="profile.php" class="promotion-request-form">
        <input type="hidden" name="action" value="request_promotion">
        <button type="submit" class="promotion-button">Request Promotion</button>
    </form>
<?php endif; ?>

        <div class="profile-item">
            <p>Account Type:</p>
            <span id="profile-account"><?php echo htmlspecialchars($businessAccount); ?></span>
            <div class="button-group">
            </div>

            <div class="privacy-checkbox-container">
            </div>
        </div>
    </div>

</body>

</html>
