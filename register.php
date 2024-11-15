<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection settings
$host = 'localhost';
$dbname = 'project';
$username = 'root';
$password = '';  // Use an empty string if no password is set

// Create a new MySQLi instance
$conn = new mysqli($host, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize error messages
$errors = [];

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
            // Redirect to main.php after successful registration
            header("Location: main.php");
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
    <title>Register</title>
    <style>
        .error-border {
            border: 1.5px solid #f28b82;
        }
        .required {
            color: red;
        }
        .error-message {
            color: red;
            font-size: 0.9em;
        }
    </style>
    <script>
        function validateForm(event) {
            let isValid = true;
            const requiredFields = document.querySelectorAll('input[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('error-border');
                    isValid = false;
                } else {
                    field.classList.remove('error-border');
                }
            });

            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            if (password !== confirmPassword) {
                document.getElementById('passwordError').textContent = "Passwords do not match.";
                document.getElementById('password').classList.add('error-border');
                document.getElementById('confirmPassword').classList.add('error-border');
                isValid = false;
            } else {
                document.getElementById('passwordError').textContent = "";
                document.getElementById('password').classList.remove('error-border');
                document.getElementById('confirmPassword').classList.remove('error-border');
            }

            const emailField = document.getElementById('email');
            const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            if (!emailPattern.test(emailField.value)) {
                document.getElementById('emailError').textContent = "Please enter a valid email address.";
                emailField.classList.add('error-border');
                isValid = false;
            } else {
                document.getElementById('emailError').textContent = "";
                emailField.classList.remove('error-border');
            }

            if (!isValid) event.preventDefault();
        }

        document.addEventListener("DOMContentLoaded", () => {
            document.querySelector("form").addEventListener("submit", validateForm);
        });
    </script>
</head>
<body>
    <h2>Register</h2>

    <?php if (!empty($errors)): ?>
        <div class="error-message">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php" novalidate>
        <label>Username<span class="required">*</span></label>
        <input type="text" name="username" required><br><br>

        <label>Password<span class="required">*</span></label>
        <input type="password" id="password" name="password" required><br><br>

        <label>Confirm Password<span class="required">*</span></label>
        <input type="password" id="confirmPassword" name="confirmPassword" required>
        <span id="passwordError" class="error-message"></span><br><br>

        <label>First Name<span class="required">*</span></label>
        <input type="text" name="firstName" required><br><br>

        <label>Last Name<span class="required">*</span></label>
        <input type="text" name="lastName" required><br><br>

        <label>Email<span class="required">*</span></label>
        <input type="email" id="email" name="email" required>
        <span id="emailError" class="error-message"></span><br><br>

        <label>Date of Birth</label>
        <input type="date" name="dateOfBirth"><br><br>

        <label>City</label>
        <input type="text" name="city"><br><br>

        <label>Country</label>
        <input type="text" name="country"><br><br>

        <label>Profession</label>
        <input type="text" name="profession"><br><br>

        <label>Business Account</label>
        <input type="checkbox" name="businessAccount"><br><br>

        <button type="submit">Register</button>
    </form>
</body>
</html>
