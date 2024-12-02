<?php
require 'session/db_connect.php';
if (!isset($_GET['postID'])) {
    echo '<p>Invalid post ID.</p>';
    exit;
}
$postID = intval($_GET['postID']);
// Fetch visibility data for the specified post
$stmt = $conn->prepare("SELECT Visibility FROM Post WHERE PostID = ?");
$stmt->bind_param("i", $postID);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $visibility = json_decode($row['Visibility'], true);
    $currentPermission = $visibility['level'] ?? 'view_add_or_link'; // Default value if 'level' key is missing
    ?>
    <div style="padding: 20px;">
        <h3>Permissions for This Post</h3>
        <p><strong>Current Permission Level:</strong> 
            <?php 
                echo htmlspecialchars(
                    match ($currentPermission) {
                        'view_only' => 'View Only',
                        'view_and_comment' => 'View and Comment',
                        'view_add_or_link' => 'View, Add, or Link',
                        default => 'Unknown',
                    }
                ); 
            ?>
        </p>
        <form id="setPermissionsForm" onsubmit="submitPermissions(event)">
    <input type="hidden" id="modalPostID" name="postID" value="<?php echo htmlspecialchars($postID); ?>">
    <label for="permissionLevel">Choose Permission Level:</label>
    <select id="permissionLevel" name="permissionLevel">
        <option value="view_only" <?php echo $currentPermission === 'view_only' ? 'selected' : ''; ?>>View Only</option>
        <option value="view_and_comment" <?php echo $currentPermission === 'view_and_comment' ? 'selected' : ''; ?>>View and Comment</option>
        <option value="view_add_or_link" <?php echo $currentPermission === 'view_add_or_link' ? 'selected' : ''; ?>>View, Add, or Link</option>
    </select>
    <button type="submit">Save</button>
</form>
    </div>
    <?php
} else {
    echo '<p>Post not found or no visibility information available.</p>';
}
$stmt->close();
$conn->close();
?>