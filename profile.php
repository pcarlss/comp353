<?php
// Start the session
session_start();

// Database connection
$conn = new mysqli('localhost', 'root', '', 'project');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the session variable for the username is set
if (!empty($_SESSION['username'])) {
    // Use the session variable for the username
    $username = $_SESSION['username'];

    // Query to fetch user details from the database
    $query = "SELECT FirstName, LastName, Email, DateOfBirth, City, Country, Profession, BusinessAccount 
              FROM Member 
              WHERE Username = ?";
    
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Fetching the user data
            $user = $result->fetch_assoc();
            
            // Storing user data into variables
            $firstName = $user['FirstName'];
            $lastName = $user['LastName'];
            $email = $user['Email'];
            $dob = $user['DateOfBirth'];
            $city = $user['City'];
            $country = $user['Country'];
            $profession = $user['Profession'];
            $businessAccount = $user['BusinessAccount'] ? 'Business' : 'Personal';
        } else {
            echo "User not found.";
        }
        $stmt->close();
    } else {
        echo "Error preparing query: " . $conn->error;
    }
} else {
    echo "No username found in session. Please log in.";
}

// Close the database connection
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Page</title>
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
            max-width: 600px;
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
            justify-content: center;
            margin-bottom: 20px;
        }

        .profile-picture img {
            border-radius: 50%;
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 3px solid #4c87ae;
        }

        /* Profile section */
        .profile-info {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .profile-item {
            display: grid;
            grid-template-columns: 1fr 2fr auto;
            align-items: center;
            gap: 10px;
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
        input[type="file"] {
            font-size: 1.2em;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 100%;
        }
    </style>
</head>

<body>

    <!-- Top Bar -->
    <div class="top-bar">
        <h1>Account</h1>
        <a href="index.php" style="margin-left: 70%"><button>
                <h3>Homepage</h3>
            </button></a>
        <a href="session/signout.php"><button>
            <h3>Sign Out</h3>
        </button></a>
    </div>

    <!-- Profile Content -->
    <div class="container">
        <h2>Profile Information</h2>

        <!-- Profile Picture Section -->
        <div class="profile-picture">
            <img id="profile-picture" src="uploads\images\default_pfp.png" alt="Profile Picture">
        </div>

        <!-- Profile Section -->
        <!-- <div class="profile-info">
            <div class="profile-item">
                <p>Name:</p>
                <span id="profile-name">Your Name</span>
                <input type="text" id="edit-name" style="display: none;" value="Your Name">
                <button id="name-button" onclick="toggleEdit('name')">
                    <h3>Edit</h3>
                </button>
            </div>

            <div class="profile-item">
                <p>Email:</p>
                <span id="profile-email">your.email@example.com</span>
                <input type="email" id="edit-email" style="display: none;" value="your.email@example.com">
                <button id="email-button" onclick="toggleEdit('email')">
                    <h3>Edit</h3>
                </button>
            </div>

            <div class="profile-item">
                <p>Profile Picture:</p>
                <span></span>
                <input type="file" id="edit-picture" style="display: none;" accept="image/*">
                <button id="picture-button" onclick="toggleEditPicture()">
                    <h3>Edit</h3>
                </button>
            </div>
        </div> -->

        <div class="profile-info">
        <div class="profile-info">
    <div class="profile-item">
        <p>Name:</p>
        <span id="profile-name"><?php echo htmlspecialchars($firstName . ' ' . $lastName); ?></span>
    </div>
    <div class="profile-item">
        <p>Email:</p>
        <span id="profile-email"><?php echo htmlspecialchars($email); ?></span>
    </div>
    <div class="profile-item">
        <p>Date of Birth:</p>
        <span id="profile-dob"><?php echo htmlspecialchars($dob); ?></span>
    </div>
    <div class="profile-item">
        <p>City:</p>
        <span id="profile-city"><?php echo htmlspecialchars($city); ?></span>
    </div>
    <div class="profile-item">
        <p>Country:</p>
        <span id="profile-country"><?php echo htmlspecialchars($country); ?></span>
    </div>
    <div class="profile-item">
        <p>Profession:</p>
        <span id="profile-profession"><?php echo htmlspecialchars($profession); ?></span>
    </div>
    <div class="profile-item">
        <p>Account Type:</p>
        <span id="profile-account"><?php echo htmlspecialchars($businessAccount); ?></span>
    </div>
</div>


    </div>

    <script>
        function toggleEdit(field) {
            const profileField = document.getElementById('profile-' + field);
            const editField = document.getElementById('edit-' + field);
            const button = document.getElementById(field + '-button');

            if (editField.style.display === "none") {
                editField.style.display = "block";
                profileField.style.display = "none";
                button.innerHTML = "<h3>Save</h3>";
            } else {
                profileField.style.display = "block";
                profileField.textContent = editField.value;
                editField.style.display = "none";
                button.innerHTML = "<h3>Edit</h3>";
            }
        }

        function toggleEditPicture() {
            const editPicture = document.getElementById('edit-picture');
            const button = document.getElementById('picture-button');

            if (editPicture.style.display === "none") {
                editPicture.style.display = "block";
                button.innerHTML = "<h3>Save</h3>";
            } else {
                const file = editPicture.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        document.getElementById('profile-picture').src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                }
                editPicture.style.display = "none";
                button.innerHTML = "<h3>Edit</h3>";
            }
        }
    </script>

</body>

</html>
