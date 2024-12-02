<?php
require 'session/db_connect.php';

session_start();

$errors = [];
$activeForm = 'login'; // Default active form

// Check if form data has been submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'signup') {
    // **Sign-Up Form Processing**

    // Get form data and sanitize
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $dateOfBirth = !empty($_POST['dateOfBirth']) ? trim($_POST['dateOfBirth']) : null;
    $city = !empty($_POST['city']) ? trim($_POST['city']) : null;
    $country = !empty($_POST['country']) ? trim($_POST['country']) : null;
    $profession = !empty($_POST['profession']) ? trim($_POST['profession']) : null;
    $businessAccount = isset($_POST['businessAccount']) ? 1 : 0;

    // Validate required fields and matching passwords
    if (empty($username) || empty($password) || empty($confirmPassword) || empty($firstName) || empty($lastName) || empty($email)) {
        $errors[] = "All required fields must be filled out.";
    }

    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    // Validate date of birth
    if (!empty($dateOfBirth)) {
        $dateParts = explode('-', $dateOfBirth);

        // Validate format and check if it's a valid date
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
    } else {
        $dateOfBirth = null; // Allow null if the field is empty
    }

    // Check for duplicate username or email before inserting into database
    if (empty($errors)) {
        // Use case-insensitive comparison
        $stmt = $conn->prepare("SELECT Username, Email FROM Member WHERE LOWER(Username) = LOWER(?) OR LOWER(Email) = LOWER(?)");
        if ($stmt) {
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $stmt->bind_result($existingUsername, $existingEmail);
                while ($stmt->fetch()) {
                    if (strcasecmp($existingUsername, $username) === 0) { // Case-insensitive comparison
                        $errors[] = "Username already exists.";
                    }
                    if (strcasecmp($existingEmail, $email) === 0) { // Case-insensitive comparison
                        $errors[] = "Email already exists.";
                    }
                }
            }
            $stmt->close();
        } else {
            $errors[] = "Database error: Unable to prepare statement.";
        }
    }

    // If there are errors, set the active form to sign-up
    if (!empty($errors)) {
        $activeForm = 'signup';
    } else {
        // If no errors, proceed to insert data
        // **Password is stored in plain text** *(Not Recommended)*
        $plainPassword = $password;

        // Default privilege and status
        $privilege = 'Junior';
        $status = 'Active';

        // Prepare the SQL insert statement
        $stmt = $conn->prepare("INSERT INTO Member (Username, Password, FirstName, LastName, Email, DateOfBirth, City, Country, Profession, Privilege, Status, BusinessAccount, UserCreatedAt, UserUpdatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURDATE())");

        if ($stmt) {
            // Bind parameters to the statement
            $stmt->bind_param(
                "sssssssssssi",
                $username,
                $plainPassword,
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

            // Execute the statement and check if successful
            if ($stmt->execute()) {
                // Registration successful, redirect to profile page
                header("Location: profile.php");
                exit();
            } else {
                $errors[] = "Error during registration: " . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8');
                $activeForm = 'signup';
            }

            // Close the statement
            $stmt->close();
        } else {
            $errors[] = "Database error: Unable to prepare insert statement.";
            $activeForm = 'signup';
        }
    }
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta Tags and Title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign-up / Login</title>
    
    <!-- Styles -->
    <style>
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
            align-items: center;
            background-color: #f0f2f5;
            font-family: Arial, sans-serif;
            min-height: 100vh;
            color: #333;
            padding-top: 60px; /* Add padding for navbar */
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
            padding: 15px 20px; /* Adjust padding for consistent alignment */
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

        .container {
            width: 100%;
            max-width: 500px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 30px 20px;
            text-align: center;
            margin-top: 50px;
        }

        h1 {
            font-size: 1.8em;
            margin-bottom: 20px;
        }

        form {
            display: none; /* Forms are hidden by default */
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
            width: 100%;
        }

        form.active {
            display: flex; /* Show only the active form */
        }

        label {
            font-size: 1em;
            margin-bottom: 5px;
        }

        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 10px;
            font-size: 1em;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button[type="submit"] {
            width: 100%;
            padding: 10px;
            font-size: 1em;
            color: #fff;
            background-color: #4c87ae;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
        }

        button[type="submit"]:hover {
            background-color: #6caad3;
        }

        .toggle-buttons {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .toggle-buttons button {
            background-color: #e0e0e0;
            color: #333;
            border: none;
            padding: 10px 20px;
            font-size: 1em;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            flex: 1;
        }

        .toggle-buttons button.active {
            background-color: #4c87ae;
            color: #fff;
        }

        /* Remove space between toggle buttons */
        .toggle-buttons button:not(:last-child) {
            margin-right: 0px;
        }

        .error-messages {
            width: 100%;
            margin-bottom: 20px;
        }

        .error-messages ul {
            list-style-type: none;
        }

        .error-messages li {
            color: red;
            margin-bottom: 5px;
            text-align: left;
        }

        .date-container {
            width: 100%;
            position: relative;
        }

        #dateError {
            position: absolute;
            top: 100%;
            left: 0;
            font-size: 0.9em;
            color: red;
            margin-top: 2px;
        }

        .required {
            color: red;
        }
    </style>
    
    <!-- JavaScript -->
    <script>
        function toggleForm(formToShow) {
            const loginForm = document.getElementById('loginForm');
            const signupForm = document.getElementById('signupForm');
            const loginButton = document.getElementById('toggleLogin');
            const signupButton = document.getElementById('toggleSignup');

            if (formToShow === 'login') {
                loginForm.classList.add('active');
                signupForm.classList.remove('active');
                loginButton.classList.add('active');
                signupButton.classList.remove('active');
            } else {
                signupForm.classList.add('active');
                loginForm.classList.remove('active');
                signupButton.classList.add('active');
                loginButton.classList.remove('active');
            }
        }

        document.addEventListener("DOMContentLoaded", () => {
            // Determine which form to show based on PHP variable
            const activeForm = '<?php echo $activeForm; ?>';
            toggleForm(activeForm);
        });
    </script>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar">
        <h1>Account</h1>
        <a href="index.php"><button><h3>Homepage</h3></button></a>
    </div>

    <!-- Main Container -->
    <div class="container">
        <h1>Let's Get Started</h1>

        <!-- Display Errors for Sign-Up -->
        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Toggle Buttons -->
        <div class="toggle-buttons">
            <button id="toggleLogin" onclick="toggleForm('login')">Log In</button>
            <button id="toggleSignup" onclick="toggleForm('signup')">Sign Up</button>
        </div>

        <!-- Login Form -->
        <form id="loginForm" action="session/login.php" method="POST" class="<?php echo ($activeForm === 'login') ? 'active' : ''; ?>">
            <input type="hidden" name="form_type" value="login">
            <label for="login-username">Username:</label>
            <input type="text" id="login-username" name="username" required>

            <label for="login-password">Password:</label>
            <input type="password" id="login-password" name="password" required>

            <button type="submit">Login</button>
        </form>

        <!-- Sign-Up Form -->
        <form id="signupForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="<?php echo ($activeForm === 'signup') ? 'active' : ''; ?>">
            <input type="hidden" name="form_type" value="signup">

            <label for="signup-username">Username<span class="required">*</span></label>
            <input type="text" id="signup-username" name="username" value="<?php echo isset($username) ? htmlspecialchars($username, ENT_QUOTES, 'UTF-8') : ''; ?>" required>

            <label for="signup-password">Password<span class="required">*</span></label>
            <input type="password" id="signup-password" name="password" required>

            <label for="signup-confirmPassword">Confirm Password<span class="required">*</span></label>
            <input type="password" id="signup-confirmPassword" name="confirmPassword" required>

            <label for="signup-firstName">First Name<span class="required">*</span></label>
            <input type="text" id="signup-firstName" name="firstName" value="<?php echo isset($firstName) ? htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') : ''; ?>" required>

            <label for="signup-lastName">Last Name<span class="required">*</span></label>
            <input type="text" id="signup-lastName" name="lastName" value="<?php echo isset($lastName) ? htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8') : ''; ?>" required>

            <label for="signup-email">Email<span class="required">*</span></label>
            <input type="email" id="signup-email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : ''; ?>" required>

            <label for="dateOfBirth">Date of Birth</label>
            <div class="date-container">
                <input 
                    type="text" 
                    name="dateOfBirth" 
                    id="dateOfBirth" 
                    placeholder="YYYY-MM-DD"
                    pattern="\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])"
                    title="Enter a date in the format YYYY-MM-DD"
                    value="<?php echo isset($dateOfBirth) ? htmlspecialchars($dateOfBirth, ENT_QUOTES, 'UTF-8') : ''; ?>"
                >
                <p id="dateError" style="color: red; display: none;">Please enter a valid date in the format YYYY-MM-DD.</p>
            </div>

            <label for="signup-city">City</label>
            <input type="text" id="signup-city" name="city" value="<?php echo isset($city) ? htmlspecialchars($city, ENT_QUOTES, 'UTF-8') : ''; ?>">

            <label for="signup-country">Country</label>
            <input type="text" id="signup-country" name="country" value="<?php echo isset($country) ? htmlspecialchars($country, ENT_QUOTES, 'UTF-8') : ''; ?>">

            <label for="signup-profession">Profession</label>
            <input type="text" id="signup-profession" name="profession" value="<?php echo isset($profession) ? htmlspecialchars($profession, ENT_QUOTES, 'UTF-8') : ''; ?>">

            <label for="signup-businessAccount">Business Account</label>
            <input type="checkbox" id="signup-businessAccount" name="businessAccount" <?php echo (isset($businessAccount) && $businessAccount) ? 'checked' : ''; ?>>

            <button type="submit">Register</button>
        </form>
    </div>

    <!-- Additional JavaScript for Date Validation -->
    <script>
        document.querySelector("#signupForm").addEventListener("submit", function (e) {
            const dateInput = document.getElementById("dateOfBirth");
            const dateError = document.getElementById("dateError");
            const dateRegex = /^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/;

            // Hide error by default
            dateError.style.display = "none";

            // Check if the field has a value and validate it
            if (dateInput.value && !dateRegex.test(dateInput.value)) {
                e.preventDefault(); // Prevent form submission
                dateError.style.display = "block"; // Show error message
            }
        });
    </script>
</body>
</html>
