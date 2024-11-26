<?php
require 'session/db_connect.php';

session_start();

$errors = [];

// Check for errors in the session
if (isset($_SESSION['login_errors'])) {
    $errors = $_SESSION['login_errors'];
    unset($_SESSION['login_errors']); // Clear errors from session after fetching
}

// Check if form data has been submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data and validate
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $email = $_POST['email'];
    $dateOfBirth = !empty($_POST['dateOfBirth']) ? $_POST['dateOfBirth'] : NULL;
    $city = !empty($_POST['city']) ? $_POST['city'] : NULL;
    $country = !empty($_POST['country']) ? $_POST['country'] : NULL;
    $profession = !empty($_POST['profession']) ? $_POST['profession'] : NULL;
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

    // If no errors, proceed to insert data
    if (empty($errors)) {
        // Hash the password
        $hashedPassword = $password;

        // Default privilege and status
        $privilege = 'Junior';
        $status = 'Active';

        // Prepare the SQL insert statement
        $stmt = $conn->prepare("
            INSERT INTO Member (Username, Password, FirstName, LastName, Email, DateOfBirth, City, Country, Profession, Privilege, Status, BusinessAccount, UserCreatedAt, UserUpdatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURDATE())
        ");

        // Bind parameters to the statement
        $stmt->bind_param(
            "sssssssssssi",
            $username,
            $hashedPassword,
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
            header("Location: profile.php");
            exit();
        } else {
            $errors[] = "Error: " . $stmt->error;
        }

        // Close the statement
        $stmt->close();
    }
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign-up / Login</title>
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
            max-width: 400px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
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
        }

        form.active {
            display: flex; /* Show only the active form */
        }

        label {
            font-size: 1em;
            margin-bottom: 5px;
        }

        input {
            width: 100%;
            padding: 10px;
            font-size: 1em;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            width: 100%;
            padding: 10px;
            font-size: 1em;
            color: #fff;
            background-color: #4c87ae;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #6caad3;
        }

        .toggle-buttons {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
        }

        .toggle-buttons button {
            background-color: #e0e0e0;
            color: #333;
            border: none;
            padding: 10px;
            font-size: 1em;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .toggle-buttons button.active {
            background-color: #4c87ae;
            color: #fff;
        }
    </style>
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
            // Default form to show
            toggleForm('login');
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

<!-- Display Errors -->
<?php if (!empty($errors)): ?>
    <div class="error-messages">
        <ul>
            <?php foreach ($errors as $error): ?>
                <h5 style="color: red;">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); 
                          echo "<br><br> "?>
            </h5>
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
    <form id="loginForm" action="session/login.php" method="POST" class="active">
        <label for="login-username">Username:</label>
        <input type="text" id="login-username" name="username" required>

        <label for="login-password">Password:</label>
        <input type="password" id="login-password" name="password" required>

        <button type="submit">Login</button>
    </form>

    <!-- Sign-Up Form -->
    <form id="signupForm" action="session/register.php" method="POST">
        <label>Username<span class="required">*</span></label>
        <input type="text" name="username" required>

        <label>Password<span class="required">*</span></label>
        <input type="password" id="password" name="password" required>

        <label>Confirm Password<span class="required">*</span></label>
        <input type="password" id="confirmPassword" name="confirmPassword" required>

        <label>First Name<span class="required">*</span></label>
        <input type="text" name="firstName" required>

        <label>Last Name<span class="required">*</span></label>
        <input type="text" name="lastName" required>

        <label>Email<span class="required">*</span></label>
        <input type="email" id="email" name="email" required>

        <label>Date of Birth</label>
        <input type="date" name="dateOfBirth">

        <label>City</label>
        <input type="text" name="city">

        <label>Country</label>
        <input type="text" name="country">

        <label>Profession</label>
        <input type="text" name="profession">

        <label>Business Account</label>
        <input type="checkbox" name="businessAccount">

        <button type="submit">Register</button>
    </form>
</div>

</body>
</html>
