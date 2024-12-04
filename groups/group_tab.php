<?php
require '../session/db_connect.php';
session_start();

// Ensure user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../account_setup.php");
    exit();
}

// Fetch MemberID and Privilege if not already set in the session
if (!isset($_SESSION['MemberID']) || !isset($_SESSION['Privilege'])) {
    $username = $_SESSION['username'] ?? null;
    if ($username) {
        $stmt = $conn->prepare("SELECT MemberID, Privilege FROM Member WHERE Username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $_SESSION['MemberID'] = $row['MemberID'];
            $_SESSION['Privilege'] = $row['Privilege'];
        }
        $stmt->close();
    }
}

// Get the logged-in user's MemberID
$username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT MemberID FROM Member WHERE Username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$memberRow = $result->fetch_assoc();
$memberID = $memberRow['MemberID'];
$stmt->close();

// Get the logged-in user's Privilege
$stmt = $conn->prepare("SELECT Privilege FROM Member WHERE Username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$privilegeRow = $result->fetch_assoc();
$userPrivilege = $privilegeRow['Privilege'];
$stmt->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Page</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f2f5;
            color: #333;
        }

        header {
            background-color: #4c87ae;
            color: white;
            padding: 1rem;
            text-align: center;
        }

        main {
            padding: 2rem;
        }

        h1 {
            color: white;
        }

        h2 {
            color: #4c87ae;
        }

        .groups-list,
        .create-group {
            margin-bottom: 2rem;
            background: white;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        ul {
            list-style-type: none;
            padding: 0;
        }

        li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 0.5rem 0;
            padding: 0.5rem;
            border-bottom: 1px solid #ddd;
        }

        li:last-child {
            border-bottom: none;
        }

        button {
            background-color: #4c87ae;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            background-color: #3a6d92;
        }

        .btn-danger {
            background-color: #e53935;
        }

        .btn-danger:hover {
            background-color: #c62828;
        }

        .form-group {
            margin: 1rem 0;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }

        input,
        textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        input[type="submit"] {
            background-color: #4c87ae;
            color: white;
            padding: 0.7rem 1.5rem;
            border: none;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background-color: #3a6d92;
        }

        .hidden {
            display: none;
        }
        #groupDetailsModal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }

    #groupDetailsModal div {
        background: white;
        padding: 20px;
        border-radius: 8px;
        max-width: 500px;
        width: 90%;
        position: relative;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    #groupDetailsModal .close-button {
    position: absolute;
    top: 10px;
    right: 10px;
    background: none;
    border: none;
    font-size: 24px;
    font-weight: bold;
    color: black;
    cursor: pointer;
}
    #groupDetailsModal h2 {
        margin-top: 0;
    }
    #groupDetailsContent ul {
    max-height: 200px; /* Set the maximum height */
    overflow-y: auto; /* Enable vertical scrolling */
    padding: 10px;
    border: 1px solid #ddd; /* Optional: Add a border for better visibility */
    margin: 10px 0;
    background-color: #f9f9f9; /* Optional: Add a subtle background color */
    border-radius: 4px;
}

    </style>
    <script>
        function toggleCreateGroupForm() {
            const form = document.getElementById('createGroupForm');
            form.classList.toggle('hidden');
        }

        function editGroup(groupId, currentName) {
            const newName = prompt(`Edit group name (current: ${currentName}):`, currentName);
            if (newName) {
                const form = document.createElement("form");
                form.method = "POST";
                form.action = "edit_group.php";
                const idInput = document.createElement("input");
                idInput.type = "hidden";
                idInput.name = "groupId";
                idInput.value = groupId;
                const nameInput = document.createElement("input");
                nameInput.type = "hidden";
                nameInput.name = "groupName";
                nameInput.value = newName;
                form.appendChild(idInput);
                form.appendChild(nameInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteGroup(groupId) {
            if (confirm("Are you sure you want to delete this group?")) {
                const form = document.createElement("form");
                form.method = "POST";
                form.action = "delete_group.php";
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = "groupId";
                input.value = groupId;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
        function toggleEditGroupForm(groupId, groupName) {
            const editForm = document.getElementById('editGroupForm');
            const groupIdField = document.getElementById('editGroupId');
            const groupNameField = document.getElementById('editGroupName');

            groupIdField.value = groupId;
            groupNameField.value = groupName;

            editForm.classList.remove('hidden');
        }

        function closeEditGroupForm() {
            const editForm = document.getElementById('editGroupForm');
            editForm.classList.add('hidden');
        }

        function requestToJoin(groupId, buttonElement) {
            // Send the request using AJAX
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "request_to_join.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = xhr.responseText.trim();
                    if (response === "success") {
                        buttonElement.textContent = "Request Sent";
                        buttonElement.disabled = true; // Disable the button
                        buttonElement.style.backgroundColor = "#ccc";
                    } else if (response === "already_requested") {
                        alert("You have already requested to join this group.");
                    } else if (response === "not_logged_in") {
                        alert("You must be logged in to request to join a group.");
                    } else {
                        alert("Failed to send join request. Please try again.");
                    }
                }
            };

            // Send the group ID
            xhr.send(`groupId=${groupId}`);
        }
        function approveRequest(requestId, groupId, user, listItemElement) {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "approve_request.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (xhr.status === 200 && xhr.responseText.trim() === "success") {
                alert(`${user}'s request approved.`);
                listItemElement.remove(); // Remove the request from the DOM
            } else {
                alert("Failed to approve request: " + xhr.responseText);
            }
        }
    };

    xhr.send(`requestId=${requestId}&groupId=${groupId}`);
}


function declineRequest(requestId, groupId, user, listItemElement) {
    if (confirm(`Decline ${user}'s request to join this group?`)) {
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "decline_request.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                if (xhr.responseText.trim() === "success") {
                    alert(`${user}'s request declined.`);
                    listItemElement.remove(); // Remove the request from the DOM
                } else {
                    alert("Failed to decline request.");
                }
            }
        };

        xhr.send(`requestId=${requestId}&groupId=${groupId}`);
    }
}
function leaveGroup(groupId, buttonElement) {
    if (confirm("Are you sure you want to leave this group?")) {
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "leave_group.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                if (xhr.status === 200 && xhr.responseText.trim() === "success") {
                    alert("You have left the group.");
                    buttonElement.parentElement.remove(); // Remove the group from the UI
                } else {
                    alert("Failed to leave the group: " + xhr.responseText);
                }
            }
        };

        xhr.send(`groupId=${groupId}`);
    }
}
function viewGroupDetails(groupId) {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "group_details.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            const modal = document.getElementById("groupDetailsModal");
            const content = document.getElementById("groupDetailsContent");
            content.innerHTML = xhr.responseText;
            modal.style.display = "flex"; // Show modal
        }
    };

    xhr.send(`groupId=${groupId}`);
}

function closeGroupDetails() {
    const modal = document.getElementById("groupDetailsModal");
    modal.style.display = "none"; // Hide modal
}

function removeMember(groupMemberID, buttonElement) {
    if (confirm("Are you sure you want to remove this member?")) {
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "remove_member.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                if (xhr.responseText.trim() === "success") {
                    // Remove the member's list item from the DOM
                    const liElement = buttonElement.parentElement;
                    liElement.remove();
                } else {
                    alert("Failed to remove member: " + xhr.responseText);
                }
            }
        };

        xhr.send(`groupMemberID=${groupMemberID}`);
    }
}
function showAddMemberForm(groupId) {
    closeGroupDetails();

        const formHtml = `
            <div id="addMemberModal" style="display: flex; justify-content: center; align-items: center; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5);">
                <div style="background: white; padding: 20px; border-radius: 8px; max-width: 400px; width: 90%; position: relative;">
                    <button onclick="closeAddMemberForm()" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 24px; font-weight: bold; color: black; cursor: pointer;">×</button>
                    <h2>Add Member</h2>
                    <form id="addMemberForm">
                        <input type="hidden" name="groupId" value="${groupId}">
                        <div>
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div>
                            <label for="firstName">First Name:</label>
                            <input type="text" id="firstName" name="firstName" required>
                        </div>
                        <div>
                            <label for="dob">Date of Birth:</label>
                            <input type="date" id="dob" name="dob" required>
                        </div>
                        <button type="submit">Add Member</button>
                    </form>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', formHtml);

        const form = document.getElementById("addMemberForm");
        form.addEventListener("submit", function (e) {
            e.preventDefault();

            const formData = new FormData(form);
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "add_member.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

            xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                const response = xhr.responseText.trim();
                if (response === "success") {
                    // Close the Add Member modal
                    closeAddMemberForm();

                    // Refresh the group details dynamically
                    const groupId = formData.get("groupId");
                    viewGroupDetails(groupId);
                } else {
                    alert(response); // Display error message
                }
            } else {
                alert("An error occurred while processing the request.");
            }
        }
    };

            const params = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                params.append(key, value);
            }
            params.append("action", "add_member");

            xhr.send(params.toString());
        });
    }

    function closeAddMemberForm() {
        const modal = document.getElementById("addMemberModal");
        if (modal) modal.remove();
    }
    </script>

</head>

<body>
<header>
    <nav style="display: flex; justify-content: space-between; align-items: center; padding: 0 1rem; background-color: #4c87ae; color: white;">
        <!-- Left Side: Homepage Link -->
        <a href="/comp353/index.php" style="text-decoration: none; color: white; font-size: 1.2rem; font-weight: bold;">
            Home
        </a>

        <!-- Center Title -->
        <div style="flex-grow: 1; text-align: center;">
            <h1 style="margin: 0; font-size: 1.8rem;">Group Page</h1>
        </div>

        <!-- Right Side: Empty for now -->
        <div style="width: 100px;"></div>
    </nav>
</header>
    <main>
        <!-- Display Groups Section -->
        <section class="groups-list">
            <h2>All Groups</h2>
            <ul>
    <?php
    // Fetch all groups
    $stmt = $conn->prepare("
        SELECT g.GroupID, g.GroupName, g.OwnerID, 
               EXISTS (
                   SELECT 1 FROM GroupMember gm WHERE gm.GroupID = g.GroupID AND gm.MemberID = ?
               ) AS IsMember
        FROM GroupList g
    ");
    $stmt->bind_param("i", $memberID);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $groupId = $row['GroupID'];
        $groupName = htmlspecialchars($row['GroupName']);
        $isMember = $row['IsMember'];
        $isOwner = $row['OwnerID'] == $memberID;

        echo "<li>";
        echo "<strong>$groupName</strong>";

         // Check if user is an administrator or owner
         $hasAdminPrivileges = $userPrivilege === 'Administrator';

        if ($isOwner || $hasAdminPrivileges) {
                    // Show "Details" and "Edit" buttons for administrators or owners
                    echo "<div>";
                    echo "<button onclick=\"viewGroupDetails($groupId)\">Details</button>";
                    echo "<button onclick=\"toggleEditGroupForm($groupId, '$groupName')\">Edit</button>";
                    echo "<button class='btn-danger' onclick=\"deleteGroup($groupId)\">Delete</button>";

                    if ($isOwner) {
                        echo "<p>Owner of this group</p>";
                    }
                    echo "</div>";
                } elseif ($isMember) {
                    echo "<p>You are a member of this group</p>";
                    // Show "Leave Group" button for members
                    echo "<button onclick=\"leaveGroup($groupId, this)\">Leave Group</button>";
                    echo "<button onclick=\"viewGroupDetails($groupId)\">Details</button>";
                } else {
                    // Show "Request to Join" button for non-members
                    echo "<button onclick=\"requestToJoin($groupId, this)\">Request to Join</button>";
                }

                echo "</li>";
            }

            $stmt->close();
            ?>
</ul>


        <button onclick="toggleCreateGroupForm()">Create Group</button>
        <section id="createGroupForm" class="create-group hidden">
            <h2>Create a New Group</h2>
            <form action="create_group.php" method="POST">
                <div class="form-group">
                    <label for="groupName">Group Name</label>
                    <input type="text" id="groupName" name="groupName" required>
                </div>
                <input type="submit" value="Create Group">
            </form>
        </section>
        <!-- Edit Group Form -->
        <section id="editGroupForm" class="edit-group hidden">
            <h2>Edit Group</h2>
            <form action="edit_group.php" method="POST">
                <input type="hidden" id="editGroupId" name="groupId">
                <div class="form-group">
                    <label for="editGroupName">Group Name</label>
                    <input type="text" id="editGroupName" name="groupName" required>
                </div>
                <div class="form-group">
                    <button type="button" onclick="closeEditGroupForm()">Cancel</button>
                    <input type="submit" value="Save Changes">
                </div>
            </form>
        </section>
        <section class="requests-list">
    <h2>Pending Join Requests</h2>
    <ul>
        <?php
        $stmt = $conn->prepare("
            SELECT jr.RequestID, jr.GroupID, g.GroupName, m.Username AS RequestingUser
            FROM JoinRequests jr
            INNER JOIN GroupList g ON jr.GroupID = g.GroupID
            INNER JOIN Member m ON jr.MemberID = m.MemberID
            WHERE g.OwnerID = ? OR EXISTS (
                SELECT 1 FROM Member
                WHERE MemberID = ? AND Privilege = 'Administrator'
            )
        ");
        $stmt->bind_param("ii", $memberID, $memberID);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $requestId = $row['RequestID'];
                $groupId = $row['GroupID'];
                $groupName = htmlspecialchars($row['GroupName']);
                $requestingUser = htmlspecialchars($row['RequestingUser']);

                echo "<li id='request-$requestId'>";
                echo "<strong>$requestingUser</strong> wants to join <strong>$groupName</strong>";
                echo " <button onclick=\"approveRequest($requestId, $groupId, '$requestingUser', this.parentElement)\">Approve</button>";
                echo " <button class='btn-danger' onclick=\"declineRequest($requestId, $groupId, '$requestingUser', this.parentElement)\">Decline</button>";
                echo "</li>";
            }
        } else {
            echo "<p>No pending join requests.</p>";
        }

        $stmt->close();
        ?>
    </ul>
</section>

<div id="groupDetailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: white; padding: 20px; border-radius: 8px; max-width: 500px; width: 90%; position: relative; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
        <button onclick="closeGroupDetails()" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 24px; font-weight: bold; color: black; cursor: pointer;">×</button>
        <h2>Group Details</h2>
        <div id="groupDetailsContent">
            <!-- Group details will be loaded dynamically here -->
        </div>
    </div>
</div>



    </main>
</body>

</html>