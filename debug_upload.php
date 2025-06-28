<?php
// Upload diagnostic script
echo "<h2>PHP Upload Configuration Check</h2>";

echo "<h3>PHP Configuration:</h3>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";
echo "file_uploads: " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . " seconds<br>";

echo "<h3>Directory Permissions:</h3>";
$uploads_dir = 'uploads';

if (is_dir($uploads_dir)) {
    echo "Uploads directory exists: ✓<br>";
    echo "Directory path: " . realpath($uploads_dir) . "<br>";
    echo "Is writable: " . (is_writable($uploads_dir) ? '✓' : '✗') . "<br>";
    echo "Permissions: " . substr(sprintf('%o', fileperms($uploads_dir)), -4) . "<br>";
} else {
    echo "Uploads directory does not exist: ✗<br>";
    echo "Attempting to create directory...<br>";
    if (mkdir($uploads_dir, 0755, true)) {
        echo "Directory created successfully: ✓<br>";
    } else {
        echo "Failed to create directory: ✗<br>";
    }
}

echo "<h3>Database Connection:</h3>";
try {
    require_once 'config.php';
    echo "Database connection: ✓<br>";
    
    // Check if images table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'images'");
    if ($stmt->rowCount() > 0) {
        echo "Images table exists: ✓<br>";
    } else {
        echo "Images table does not exist: ✗<br>";
        echo "Please run setup.php to create the table.<br>";
    }
} catch (Exception $e) {
    echo "Database connection failed: ✗<br>";
    echo "Error: " . $e->getMessage() . "<br>";
}

echo "<h3>Test Upload Form:</h3>";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['test_image'])) {
    $file = $_FILES['test_image'];
    echo "<h4>Upload Test Results:</h4>";
    echo "File name: " . htmlspecialchars($file['name']) . "<br>";
    echo "File size: " . $file['size'] . " bytes<br>";
    echo "File type: " . htmlspecialchars($file['type']) . "<br>";
    echo "Upload error code: " . $file['error'] . "<br>";
    echo "Temporary file: " . htmlspecialchars($file['tmp_name']) . "<br>";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        echo "<span style='color: green;'>File uploaded successfully to temporary location!</span><br>";
        
        // Test moving file
        $test_filename = 'test_' . uniqid() . '.jpg';
        $test_path = $uploads_dir . '/' . $test_filename;
        
        if (move_uploaded_file($file['tmp_name'], $test_path)) {
            echo "<span style='color: green;'>File moved to uploads directory successfully!</span><br>";
            echo "File saved as: " . $test_filename . "<br>";
            
            // Clean up test file
            unlink($test_path);
            echo "Test file cleaned up.<br>";
        } else {
            echo "<span style='color: red;'>Failed to move file to uploads directory!</span><br>";
        }
    } else {
        echo "<span style='color: red;'>Upload failed with error code: " . $file['error'] . "</span><br>";
    }
}
?>

<form method="POST" enctype="multipart/form-data">
    <label for="test_image">Test Image Upload:</label>
    <input type="file" name="test_image" id="test_image" accept="image/*">
    <button type="submit">Test Upload</button>
</form>

<p><a href="dashboard.php">← Back to Dashboard</a></p>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
form { margin-top: 20px; padding: 20px; background: #f5f5f5; border-radius: 5px; }
input, button { margin: 5px; padding: 8px; }
</style>
