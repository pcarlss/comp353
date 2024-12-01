<?php
require 'session/db_connect.php';
session_start();

// Suppress error output to the browser and log errors instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/your/php-error.log'); // Adjust the path as needed
error_reporting(E_ALL);

// Initialize variables and errors array
$errors = [];
$success = '';
$firstName = $lastName = $email = $dob = $city = $country = $profession = $profilePic = '';
$businessAccount = 'Personal';

// Check for messages from previous actions
if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Check if the session variable for the username is set
if (!empty($_SESSION['username'])) {
    $username = $_SESSION['username'];

    // Handle profile picture upload
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_picture'])) {
        // Define size limit
        $maxFileSize = 5 * 1024 * 1024; // 5MB

        // Check if file was uploaded without errors
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            $fileSize = $file['size'];

            // Validate file size
            if ($fileSize > $maxFileSize) {
                $errors[] = "File size must be less than 5MB.";
            }

            // Verify that the file is an image
            $check = getimagesize($file['tmp_name']);
            if ($check !== false) {
                // Get the image MIME type
                $fileType = $check['mime'];
                // Generate a unique filename
                $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newFileName = uniqid('pfp_', true) . '.' . $fileExtension;

                // Define upload directories
                $uploadDir = __DIR__ . '/uploads/images/';
                $relativeDir = 'uploads/images/';
                $uploadPath = $uploadDir . $newFileName;
                $relativePath = $relativeDir . $newFileName;

                // Check if the uploads/images directory exists, if not, create it
                if (!file_exists($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        $errors[] = "Failed to create directory for uploads.";
                    }
                }

                // Check if the directory is writable
                if (!is_writable($uploadDir)) {
                    $errors[] = "Upload directory is not writable.";
                }

                if (empty($errors)) {
                    // Move the uploaded file to the designated directory
                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        // Update the ProfilePic field in the database
                        $stmt = $conn->prepare("UPDATE Member SET ProfilePic = ?, UserUpdatedAt = NOW() WHERE Username = ?");
                        $stmt->bind_param('ss', $relativePath, $username);

                        if ($stmt->execute()) {
                            $success = "Your profile picture has been updated successfully.";
                            $profilePic = $relativePath;
                        } else {
                            $errors[] = "Database update failed: " . $stmt->error;
                        }
                        $stmt->close();
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
    }

    // Check if the request is an AJAX call for email uniqueness check
    elseif (isset($_POST['action']) && $_POST['action'] == 'check_email') {
        $emailToCheck = trim($_POST['email']);
        $response = ['isUnique' => true];

        // Validate email format
        if (!filter_var($emailToCheck, FILTER_VALIDATE_EMAIL)) {
            $response['isUnique'] = false;
            $response['message'] = 'Invalid email format.';
        } else {
            // Check if email is already in use by another user
            $stmt = $conn->prepare("SELECT Username FROM Member WHERE Email = ? AND Username != ?");
            $stmt->bind_param('ss', $emailToCheck, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $response['isUnique'] = false;
                $response['message'] = 'This email address is already registered to another account.';
            }
            $stmt->close();
        }

        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Check if form data has been submitted for profile updates
    elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['field'])) {
        // Get the field and value from POST data
        $field = $_POST['field'];
        $value = trim($_POST['value']);

        // Validate the field and value
        $allowed_fields = ['firstname', 'lastname', 'email', 'dob', 'city', 'country', 'profession'];
        if (in_array($field, $allowed_fields)) {
            // Map field names to database column names
            $field_map = [
                'firstname' => 'FirstName',
                'lastname' => 'LastName',
                'email' => 'Email',
                'dob' => 'DateOfBirth',
                'city' => 'City',
                'country' => 'Country',
                'profession' => 'Profession',
            ];
            $db_field = $field_map[$field];

            // Perform validation based on the field
            if (empty($value) && in_array($field, ['firstname', 'lastname', 'email'])) {
                $errors[] = ucfirst($field) . " is required.";
            }

            // Enforce 45-character limit
            if (strlen($value) > 45) {
                $errors[] = ucfirst($field) . " must not exceed 45 characters.";
            }

            // Additional validation for email
            if ($field == 'email') {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Please enter a valid email address.";
                } else {
                    // Check for email uniqueness
                    $stmt = $conn->prepare("SELECT Username FROM Member WHERE Email = ? AND Username != ?");
                    $stmt->bind_param('ss', $value, $username);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $errors[] = "This email address is already registered to another account.";
                    }
                    $stmt->close();
                }
            }

            // Additional validation for date of birth
            if ($field == 'dob' && !empty($value)) {
                // Validate date format and check if it's a valid date
                $dateParts = explode('-', $value);
                if (
                    count($dateParts) === 3 &&
                    is_numeric($dateParts[0]) &&
                    is_numeric($dateParts[1]) &&
                    is_numeric($dateParts[2]) &&
                    checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])
                ) {
                    // Valid date
                } else {
                    $errors[] = "Invalid date format or date does not exist. Please enter a valid date (YYYY-MM-DD).";
                }
            }

            // If no errors, proceed to update the database
            if (empty($errors)) {
                // Prepare the SQL statement
                $stmt = $conn->prepare("UPDATE Member SET $db_field = ?, UserUpdatedAt = NOW() WHERE Username = ?");
                $stmt->bind_param('ss', $value, $username);

                if ($stmt->execute()) {
                    $success = "Your profile has been updated successfully.";
                } else {
                    $errors[] = "Database update failed: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $errors[] = "Invalid field.";
        }
    }

    // Query to fetch user details from the database
    $query = "SELECT FirstName, LastName, Email, DateOfBirth, City, Country, Profession, BusinessAccount, ProfilePic 
              FROM Member 
              WHERE Username = ?";

    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Fetching the user data
            $user = $result->fetch_assoc();

            // Fetch and assign user data
            $firstName = $user['FirstName'] ?? '';
            $lastName = $user['LastName'] ?? '';
            $email = $user['Email'] ?? '';
            $dob = $user['DateOfBirth'] ?? '';
            $city = $user['City'] ?? '';
            $country = $user['Country'] ?? '';
            $profession = $user['Profession'] ?? '';
            $businessAccount = $user['BusinessAccount'] ? 'Business' : 'Personal';
            $profilePic = $user['ProfilePic'] ?? 'uploads/images/default_pfp.png';
        } else {
            // Handle case when user data is not found
            $errors[] = "User not found.";
        }
        $stmt->close();
    } else {
        // Handle query preparation errors
        $errors[] = "Error preparing query: " . $conn->error;
    }
} else {
    // Handle case when no username is found in session
    $errors[] = "No username found in session. Please log in.";
}

// Close the database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Include your CSS styles here -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Page</title>
    <style>
        /* Your existing CSS styles */
        /* Basic reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Layout and styling */
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

        /* Top Bar Styling */
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

        .top-bar button {
            background-color: #fff;
            color: #4c87ae;
            border: none;
            padding: 10px 15px;
            font-size: 1em;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .top-bar button:hover {
            background-color: #ddd;
        }

        /* Profile Container */
        .container {
            width: 100%;
            max-width: 700px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 120px;
        }

        h2 {
            font-size: 1.5em;
            margin: 20px 0;
            color: #333;
        }

        /* Profile Picture Section */
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

        .profile-item {
            display: grid;
            grid-template-columns: 1fr 2fr auto auto;
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
        }

        .profile-item input {
            font-size: 1.2em;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 100%;
            display: none;
        }

        .profile-item button {
            background-color: #4c87ae;
            color: white;
            border: none;
            padding: 8px 15px;
            font-size: 1em;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .profile-item button:hover {
            background-color: #6caad3;
        }

        /* Input fields for editing */
        input[type="text"],
        input[type="email"],
        input[type="date"] {
            font-size: 1.2em;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 100%;
        }

        /* Error and success messages */
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
    </style>
</head>

<body>

    <!-- Top Bar -->
    <div class="top-bar">
        <h1>Account</h1>
        <a href="index.php"><button>
                <h3>Homepage</h3>
            </button></a>
        <a href="member and friends/friendlist.php"><button>
                <h3>Friend List</h3>
            </button></a>
        <a href="session/signout.php"><button>
                <h3>Sign Out</h3>
            </button></a>
    </div>

    <!-- Profile Content -->
    <div class="container">
        <h2>Profile Information</h2>

        <!-- Display Error Messages -->
        <?php if (!empty($errors)): ?>
        <div class="message error">
            <?php foreach ($errors as $error): ?>
            <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Display Success Message -->
        <?php if (!empty($success)): ?>
        <div class="message success">
            <p><?php echo htmlspecialchars($success); ?></p>
        </div>
        <?php endif; ?>

        <!-- Profile Picture Section -->
        <div class="profile-picture">
            <img id="profile-picture" src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profile Picture">
            <form id="profilePicForm" method="POST" action="profile.php" enctype="multipart/form-data">
                <!-- Hidden input to indicate picture upload -->
                <input type="hidden" name="upload_picture" value="1">
                <input type="file" name="profile_picture" id="profile_picture_input" accept="image/*" onchange="document.getElementById('profilePicForm').submit();">
                <label for="profile_picture_input">Change Picture</label>
            </form>
        </div>

        <!-- Hidden Form for Updating Profile -->
        <form id="profileForm" method="POST" action="profile.php">
            <input type="hidden" name="field" id="field">
            <input type="hidden" name="value" id="value">
        </form>

<div class="profile-item">
    <p>First Name:</p>
    <span id="profile-firstname"><?php echo htmlspecialchars($firstName); ?></span>
    <input id="edit-firstname" type="text" maxlength="45" value="<?php echo htmlspecialchars($firstName); ?>" style="display: none;">
    
    <!-- Save Button -->
    <button id="firstname-save-button" onclick="saveEdit('firstname')" style="display: none;">
        <h3>Save</h3>
    </button>
    
    <!-- Cancel Button -->
    <button id="firstname-cancel-button" onclick="cancelEdit('firstname')" style="display: none;">
        <h3>Cancel</h3>
    </button>
    
    <!-- Edit Button -->
    <button id="firstname-edit-button" onclick="toggleEdit('firstname')">
        <h3>Edit</h3>
    </button>
</div>


        <!-- Last Name -->
<div class="profile-item">
    <p>Last Name:</p>
    <span id="profile-lastname"><?php echo htmlspecialchars($lastName); ?></span>
    <input id="edit-lastname" type="text" maxlength="45" value="<?php echo htmlspecialchars($lastName); ?>" style="display: none;">
    
    <!-- Save Button -->
    <button type="button" id="lastname-save-button" class="save-button" onclick="saveEdit('lastname')" style="display: none;">
        <h3>Save</h3>
    </button>
    
    <!-- Cancel Button -->
    <button type="button" id="lastname-cancel-button" class="cancel-button" onclick="cancelEdit('lastname')" style="display: none;">
        <h3>Cancel</h3>
    </button>
    
    <!-- Edit Button -->
    <button type="button" id="lastname-edit-button" class="edit-button" onclick="toggleEdit('lastname')">
        <h3>Edit</h3>
    </button>
</div>


        <!-- Email -->
<div class="profile-item">
    <p>Email:</p>
    <span id="profile-email"><?php echo htmlspecialchars($email); ?></span>
    <input id="edit-email" type="email" maxlength="45" value="<?php echo htmlspecialchars($email); ?>" style="display: none;">
    
    <!-- Save Button -->
    <button type="button" id="email-save-button" class="save-button" onclick="saveEdit('email')" style="display: none;">
        <h3>Save</h3>
    </button>
    
    <!-- Cancel Button -->
    <button type="button" id="email-cancel-button" class="cancel-button" onclick="cancelEdit('email')" style="display: none;">
        <h3>Cancel</h3>
    </button>
    
    <!-- Edit Button -->
    <button type="button" id="email-edit-button" class="edit-button" onclick="toggleEdit('email')">
        <h3>Edit</h3>
    </button>
</div>


        <!-- Date of Birth -->
<div class="profile-item">
    <p>Date of Birth:</p>
    <span id="profile-dob"><?php echo htmlspecialchars($dob); ?></span>
    <input 
        id="edit-dob" 
        type="text" 
        placeholder="YYYY-MM-DD" 
        maxlength="10" 
        value="<?php echo htmlspecialchars($dob); ?>" 
        oninput="handleDateInput(this)" 
        style="display: none;"
    >
    
    <!-- Save Button -->
    <button type="button" id="dob-save-button" class="save-button" onclick="saveEdit('dob')" style="display: none;">
        <h3>Save</h3>
    </button>
    
    <!-- Cancel Button -->
    <button type="button" id="dob-cancel-button" class="cancel-button" onclick="cancelEdit('dob')" style="display: none;">
        <h3>Cancel</h3>
    </button>
    
    <!-- Edit Button -->
    <button type="button" id="dob-edit-button" class="edit-button" onclick="toggleEdit('dob')">
        <h3>Edit</h3>
    </button>
</div>


        <!-- City -->
<div class="profile-item">
    <p>City:</p>
    <span id="profile-city"><?php echo htmlspecialchars($city); ?></span>
    <input id="edit-city" type="text" maxlength="45" value="<?php echo htmlspecialchars($city); ?>" style="display: none;">
    
    <!-- Save Button -->
    <button type="button" id="city-save-button" class="save-button" onclick="saveEdit('city')" style="display: none;">
        <h3>Save</h3>
    </button>
    
    <!-- Cancel Button -->
    <button type="button" id="city-cancel-button" class="cancel-button" onclick="cancelEdit('city')" style="display: none;">
        <h3>Cancel</h3>
    </button>
    
    <!-- Edit Button -->
    <button type="button" id="city-edit-button" class="edit-button" onclick="toggleEdit('city')">
        <h3>Edit</h3>
    </button>
</div>


        <!-- Country -->
<div class="profile-item">
    <p>Country:</p>
    <span id="profile-country"><?php echo htmlspecialchars($country); ?></span>
    <input id="edit-country" type="text" maxlength="45" value="<?php echo htmlspecialchars($country); ?>" style="display: none;">
    
    <!-- Save Button -->
    <button type="button" id="country-save-button" class="save-button" onclick="saveEdit('country')" style="display: none;">
        <h3>Save</h3>
    </button>
    
    <!-- Cancel Button -->
    <button type="button" id="country-cancel-button" class="cancel-button" onclick="cancelEdit('country')" style="display: none;">
        <h3>Cancel</h3>
    </button>
    
    <!-- Edit Button -->
    <button type="button" id="country-edit-button" class="edit-button" onclick="toggleEdit('country')">
        <h3>Edit</h3>
    </button>
</div>


        <!-- Profession -->
<div class="profile-item">
    <p>Profession:</p>
    <span id="profile-profession"><?php echo htmlspecialchars($profession); ?></span>
    <input id="edit-profession" type="text" maxlength="45" value="<?php echo htmlspecialchars($profession); ?>" style="display: none;">
    
    <!-- Save Button -->
    <button type="button" id="profession-save-button" class="save-button" onclick="saveEdit('profession')" style="display: none;">
        <h3>Save</h3>
    </button>
    
    <!-- Cancel Button -->
    <button type="button" id="profession-cancel-button" class="cancel-button" onclick="cancelEdit('profession')" style="display: none;">
        <h3>Cancel</h3>
    </button>
    
    <!-- Edit Button -->
    <button type="button" id="profession-edit-button" class="edit-button" onclick="toggleEdit('profession')">
        <h3>Edit</h3>
    </button>
</div>


        <!-- Account Type -->
        <div class="profile-item">
            <p>Account Type:</p>
            <span id="profile-account"><?php echo htmlspecialchars($businessAccount); ?></span>
        </div>
    </div>

    <script>
    function toggleEdit(field) {
        const profileField = document.getElementById('profile-' + field);
        const editField = document.getElementById('edit-' + field);
        const saveButton = document.getElementById(field + '-save-button');
        const cancelButton = document.getElementById(field + '-cancel-button');
        const editButton = document.getElementById(field + '-edit-button');

        // Show input field and Save/Cancel buttons
        editField.style.display = "block";
        saveButton.style.display = "inline-block";
        cancelButton.style.display = "inline-block";

        // Hide the profile field and Edit button
        profileField.style.display = "none";
        editButton.style.display = "none";
    }

    function saveEdit(field) {
        const editField = document.getElementById('edit-' + field);
        const newValue = editField.value.trim();

        // For 'dob' field, validate the date format (YYYY-MM-DD)
        if (field === 'dob' && !/^\d{4}-\d{2}-\d{2}$/.test(newValue)) {
            displayErrorMessage("Please enter a valid date in the format YYYY-MM-DD.");
            return;
        }

        // For email field, check uniqueness before submitting
        if (field === 'email') {
            checkEmailUniqueness(newValue)
                .then(isUnique => {
                    if (isUnique) {
                        // Proceed to submit the form
                        submitProfileForm(field, newValue);
                    } else {
                        // Display error message
                        displayErrorMessage('This email address is already registered to another account.');
                    }
                })
                .catch(error => {
                    console.error('Error checking email uniqueness:', error);
                    displayErrorMessage('An error occurred while checking the email.');
                });
        } else {
            // For other fields, submit the form directly
            submitProfileForm(field, newValue);
        }
    }

    function cancelEdit(field) {
        const profileField = document.getElementById('profile-' + field);
        const editField = document.getElementById('edit-' + field);
        const saveButton = document.getElementById(field + '-save-button');
        const cancelButton = document.getElementById(field + '-cancel-button');
        const editButton = document.getElementById(field + '-edit-button');

        // Hide input and Save/Cancel buttons
        editField.style.display = "none";
        saveButton.style.display = "none";
        cancelButton.style.display = "none";

        // Show the profile field and Edit button
        profileField.style.display = "inline";
        editButton.style.display = "inline";

        // Reset the input value to the original value from the profile field
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
        // Create or update the error message div
        let errorDiv = document.querySelector('.message.error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'message error';
            document.querySelector('.container').insertBefore(errorDiv, document.querySelector('.profile-picture'));
        }
        errorDiv.innerHTML = '<p>' + message + '</p>';

        // Set timeout to remove the error message
        setTimeout(function() {
            if (errorDiv) {
                errorDiv.style.transition = 'opacity 0.5s';
                errorDiv.style.opacity = '0';
                setTimeout(function() {
                    errorDiv.remove();
                }, 500);
            }
        }, 3000);
    }

    // Remove messages after 3 seconds
    window.onload = function() {
        // Set timeout to remove messages
        setTimeout(function() {
            let messages = document.querySelectorAll('.message');
            messages.forEach(function(message) {
                message.style.transition = 'opacity 0.5s';
                message.style.opacity = '0';
                setTimeout(function() {
                    message.remove();
                }, 500); // Wait for the fade-out transition to complete
            });
        }, 3000); // 3000 milliseconds = 3 seconds
    };

    function handleDateInput(input) {
        let value = input.value.replace(/[^0-9]/g, ""); // Remove non-numeric characters

        // Dynamically format to YYYY-MM-DD
        let formattedValue = "";
        if (value.length > 0) {
            formattedValue += value.substring(0, 4); // Year
        }
        if (value.length > 4) {
            formattedValue += "-" + value.substring(4, 6); // Month
        }
        if (value.length > 6) {
            formattedValue += "-" + value.substring(6, 8); // Day
        }

        input.value = formattedValue; // Update input value
    }
</script>     

</body>

</html>
