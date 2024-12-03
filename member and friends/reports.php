<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require '../session/db_connect.php';
session_start();


if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];


$errors = [];
$success = '';
$reportResults = [];


$allowedFields = [
    'FirstName'    => 'First Name',
    'LastName'     => 'Last Name',
    'DateOfBirth'  => 'Date of Birth',
    'City'         => 'City',
    'Country'      => 'Country',
    'Profession'   => 'Profession',
    'Privilege'    => 'Privilege'
];


if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'autocomplete') {
    header('Content-Type: application/json');
    $field = $_GET['field'] ?? '';
    $query = $_GET['query'] ?? '';

    if (!array_key_exists($field, $allowedFields)) {
        echo json_encode(['error' => 'Invalid field selected.']);
        exit();
    }

    
    $sql = "SELECT DISTINCT `$field` FROM Member WHERE `$field` IS NOT NULL AND `$field` LIKE ? ORDER BY `$field` ASC LIMIT 10";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $likeQuery = $query . '%';
        $stmt->bind_param("s", $likeQuery);
        $stmt->execute();
        $result = $stmt->get_result();
        $values = [];
        while ($row = $result->fetch_assoc()) {
            $values[] = $row[$field];
        }
        echo json_encode(['values' => $values]);
    } else {
        echo json_encode(['error' => 'Database error.']);
    }
    exit();
}


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'generate_report') {
    $selectedFieldKey = $_POST['selectedField'] ?? '';
    $selectedValue = trim($_POST['selectedValue'] ?? '');

    if (!array_key_exists($selectedFieldKey, $allowedFields)) {
        $errors[] = "Invalid field selected.";
    }

    if (empty($selectedValue)) {
        $errors[] = "Please select a value for the chosen field.";
    }

    if (empty($errors)) {
        // Prepare SQL to fetch users based on selected field and value, excluding inactive/suspended
        $sql = "SELECT Username, ProfilePic FROM Member WHERE `$selectedFieldKey` = ? AND Status NOT IN ('Inactive', 'Suspended')";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $selectedValue);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $reportResults[] = [
                        'Username'   => $row['Username'],
                        'ProfilePic' => $row['ProfilePic']
                    ];
                }
                if (empty($reportResults)) {
                    $success = "No active users found matching the criteria.";
                } else {
                    $success = count($reportResults) . " user(s) found.";
                }
            } else {
                $errors[] = "Error fetching report: " . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } else {
            $errors[] = "Database error: " . htmlspecialchars($conn->error);
        }
    }
}


$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Reports</title>
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
            min-height: 100vh;
            padding-top: 60px; /* Space for the fixed top bar */
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
        }

        .top-bar button:hover {
            background-color: #ddd;
        }

        
        .container {
            max-width: 1000px;
            margin: 30px auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        h2 {
            font-size: 1.8em;
            margin-bottom: 20px;
            color: #333;
        }

        
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            position: relative;
        }

        label {
            font-weight: bold;
        }

        select,
        input[type="text"] {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1em;
            width: 100%;
        }

        button[type="submit"] {
            width: 150px;
            align-self: flex-start;
            background-color: #4c87ae;
            color: white;
            border: none;
            padding: 10px 15px;
            font-size: 1em;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button[type="submit"]:hover {
            background-color: #6caad3;
        }

        
        .message {
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 5px;
            opacity: 1; /* Ensure full visibility initially */
            transition: opacity 0.5s ease-out; /* Smooth transition for opacity changes */
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
        }

    
        .report-container {
            margin-top: 20px;
        }

        .report-header {
            margin-bottom: 10px;
            font-size: 1.2em;
            color: #333;
        }

        .users-list {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .user-card {
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: #f9f9f9;
            padding: 10px;
            border-radius: 5px;
            width: calc(33.333% - 10px); /* Three cards per row with 15px gap */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .user-card img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-card .username {
            font-size: 1em;
            color: #333;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Responsive Design */
        @media (max-width: 900px) {
            .user-card {
                width: calc(50% - 10px); /* Two cards per row */
            }
        }

        @media (max-width: 600px) {
            .user-card {
                width: 100%; /* One card per row */
            }
        }

       
        .suggestions {
            border: 1px solid #ccc;
            border-top: none;
            max-height: 150px;
            overflow-y: auto;
            position: absolute;
            background-color: white;
            width: calc(100% - 22px);
            z-index: 1001;
        }

        .suggestion-item {
            padding: 10px;
            cursor: pointer;
        }

        .suggestion-item:hover {
            background-color: #f0f2f5;
        }

        
        .autocomplete-container {
            position: relative;
            width: 100%;
        }
    </style>
</head>

<body>
<div class="top-bar">
    <h1>Add Friends</h1>
    <a href="friendlist.php"><button>
        <h3>Friends List</h3>
    </button></a>
    <a href="friend.php"><button>
        <h3>Friends</h3>
    </button></a>
</div>

   
    <div class="container">
        <h2>Generate Reports</h2>

        
        <?php if (!empty($errors)): ?>
            <div class="message error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        
        <?php if (!empty($success)): ?>
            <div class="message success">
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>

        
        <form method="POST" id="reportForm">
            <input type="hidden" name="action" value="generate_report">

            
            <label for="selectedField">Select Parameter:</label>
            <select name="selectedField" id="selectedField" required>
                <option value="">--Select Parameter--</option>
                <?php foreach ($allowedFields as $key => $label): ?>
                    <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>

            
            <div class="autocomplete-container">
                <label for="selectedValue">Enter Value:</label>
                <input type="text" id="selectedValue" name="selectedValue" autocomplete="off" required>
                <div id="suggestions" class="suggestions" style="display: none;"></div>
            </div>

            <button type="submit">Generate Report</button>
        </form>

       
        <?php if (!empty($reportResults)): ?>
            <div class="report-container">
                <div class="report-header">
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <div class="users-list">
                    <?php foreach ($reportResults as $user): ?>
                        <?php
                            // Construct the profile picture path
                            $profilePicFilename = $user['ProfilePic'] ?? 'default_pfp.png';
                            $profilePicPath = '../uploads/images/' . $profilePicFilename;

                            // Check if the image file exists on the server
                            if (!file_exists($profilePicPath)) {
                                // Fallback to default image if file doesn't exist
                                $profilePicPath = '../uploads/images/default_pfp.png';
                            }
                        ?>
                        <div class="user-card">
                            <!-- Image Path: <?php echo htmlspecialchars($profilePicPath); ?> -->
                            <img src="<?php echo htmlspecialchars($profilePicPath); ?>" alt="Profile Picture" onerror="this.onerror=null; this.src='../uploads/images/default_pfp.png';">
                            <div class="username"><?php echo htmlspecialchars($user['Username']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif ($_SERVER["REQUEST_METHOD"] === "POST" && empty($errors)): ?>
            <p>No users found matching the selected criteria.</p>
        <?php endif; ?>
    </div>

    
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectedField = document.getElementById('selectedField');
            const selectedValue = document.getElementById('selectedValue');
            const suggestionsBox = document.getElementById('suggestions');

            selectedField.addEventListener('change', function () {
                selectedValue.value = '';
                suggestionsBox.style.display = 'none';
                suggestionsBox.innerHTML = '';
            });

            selectedValue.addEventListener('input', function () {
                const query = this.value.trim();
                const field = selectedField.value;

                if (field === '' || query.length === 0) {
                    suggestionsBox.style.display = 'none';
                    suggestionsBox.innerHTML = '';
                    return;
                }

                
                const xhr = new XMLHttpRequest();
                xhr.open('GET', `reports.php?action=autocomplete&field=${encodeURIComponent(field)}&query=${encodeURIComponent(query)}`, true);
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.values && response.values.length > 0) {
                                suggestionsBox.innerHTML = '';
                                response.values.forEach(function (item) {
                                    const div = document.createElement('div');
                                    div.classList.add('suggestion-item');
                                    div.textContent = item;
                                    div.addEventListener('click', function () {
                                        selectedValue.value = item;
                                        suggestionsBox.style.display = 'none';
                                        suggestionsBox.innerHTML = '';
                                    });
                                    suggestionsBox.appendChild(div);
                                });
                                suggestionsBox.style.display = 'block';
                            } else {
                                suggestionsBox.style.display = 'none';
                                suggestionsBox.innerHTML = '';
                            }
                        } catch (e) {
                            console.error('Invalid JSON response');
                        }
                    }
                };
                xhr.send();
            });

            
            const messages = document.querySelectorAll('.message');
            messages.forEach(function(message) {
                setTimeout(function() {
                    message.style.opacity = '0'; // Start fading out
                    // Remove the message from the DOM after the transition completes
                    setTimeout(function() {
                        if(message.parentNode) {
                            message.parentNode.removeChild(message);
                        }
                    }, 500); // Duration matches the CSS transition (0.5s)
                }, 3000); // 3 seconds delay before fading out
            });

            
            document.addEventListener('click', function (e) {
                if (!e.target.closest('.autocomplete-container')) {
                    suggestionsBox.style.display = 'none';
                    suggestionsBox.innerHTML = '';
                }
            });
        });
    </script>
</body>

</html>
