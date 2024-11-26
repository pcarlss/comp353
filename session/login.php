<?php
require 'db_connect.php';

// Start session
session_start();

// Initialize error messages
$errors = [];

// Check if form data has been submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data and sanitize
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Validate required fields
    if (empty($username) || empty($password)) {
        $errors[] = "Both username and password are required.";
    }

    // If no errors, proceed with authentication
    if (empty($errors)) {
        // Prepare the SQL select statement
        $stmt = $conn->prepare("SELECT Password FROM Member WHERE Username = ? AND Status = 'Active'");
        $stmt->bind_param("s", $username);

        // Execute the statement
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            // Bind the result to a variable
            $stmt->bind_result($hashedPassword);
            $stmt->fetch();

            // Verify the password
            if ($password === $hashedPassword) { // Replace with password_verify() if passwords are hashed
                // Set session variables
                $_SESSION['username'] = $username;

                $stmt = $conn->prepare("SELECT memberid FROM Member WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $memberid = $stmt->get_result();

                $_SESSION['memberid'] = $memberid;

                // Redirect to profile.php upon successful login
                header("Location: ../profile.php");
                exit();
            } else {
                $errors[] = "Invalid username or password.";
            }
        } else {
            $errors[] = "Invalid username or password.";
        }

        // Close the statement
        $stmt->close();
    }

    // Save errors to session and redirect to error_test.php if there are any
    if (!empty($errors)) {
        $_SESSION['login_errors'] = $errors;
        header("Location: ../account_setup.php");
        exit();
    }
}

// Close the database connection
$conn->close();
?>
