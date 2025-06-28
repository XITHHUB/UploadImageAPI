<?php
// Simple upload test
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['test_file'])) {
    $file = $_FILES['test_file'];
    
    echo "<h3>Upload Debug Information:</h3>";
    echo "File name: " . htmlspecialchars($file['name']) . "<br>";
    echo "File size: " . $file['size'] . " bytes<br>";
    echo "File type: " . htmlspecialchars($file['type']) . "<br>";
    echo "Upload error: " . $file['error'] . "<br>";
    echo "Temp file: " . htmlspecialchars($file['tmp_name']) . "<br>";
    echo "Temp file exists: " . (file_exists($file['tmp_name']) ? 'YES' : 'NO') . "<br>";
    echo "Current directory: " . getcwd() . "<br>";
    echo "Script directory: " . __DIR__ . "<br>";
    
    $upload_dir = __DIR__ . '/uploads/';
    echo "Upload directory: " . $upload_dir . "<br>";
    echo "Upload dir exists: " . (is_dir($upload_dir) ? 'YES' : 'NO') . "<br>";
    echo "Upload dir writable: " . (is_writable($upload_dir) ? 'YES' : 'NO') . "<br>";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $filename = 'test_' . time() . '.jpg';
        $upload_path = $upload_dir . $filename;
        
        echo "Target path: " . $upload_path . "<br>";
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            echo "<span style='color: green; font-weight: bold;'>SUCCESS: File uploaded!</span><br>";
            echo "File saved to: " . $upload_path . "<br>";
            
            // Verify file exists
            if (file_exists($upload_path)) {
                echo "File verified to exist<br>";
                echo "File size on disk: " . filesize($upload_path) . " bytes<br>";
            }
        } else {
            echo "<span style='color: red; font-weight: bold;'>FAILED: Could not move file</span><br>";
            $error = error_get_last();
            if ($error) {
                echo "Last PHP error: " . $error['message'] . "<br>";
            }
        }
    } else {
        echo "<span style='color: red;'>Upload error code: " . $file['error'] . "</span><br>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Simple Upload Test</title>
</head>
<body>
    <h2>Simple Upload Test</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="test_file" accept="image/*" required>
        <button type="submit">Upload Test</button>
    </form>
    
    <p><a href="dashboard.php">← Back to Dashboard</a></p>
</body>
</html>
