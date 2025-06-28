<?php
// Migration script to add title and description columns to images table
require_once 'config.php';

try {
    // Add title and description columns to images table
    $sql = "ALTER TABLE images 
            ADD COLUMN title VARCHAR(255) DEFAULT NULL,
            ADD COLUMN description TEXT DEFAULT NULL";
    
    $pdo->exec($sql);
    echo "Migration completed successfully! Added title and description columns to images table.<br>";
    
} catch(PDOException $e) {
    // Check if columns already exist
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist. No migration needed.<br>";
    } else {
        die("Migration error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Database Migration</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        margin: 50px;
    }

    .success {
        color: green;
    }

    .link {
        margin-top: 20px;
    }

    a {
        color: #007bff;
        text-decoration: none;
    }

    a:hover {
        text-decoration: underline;
    }
    </style>
</head>

<body>
    <h2>Database Migration Complete</h2>
    <p class="success">Title and description fields have been added to the images table!</p>
    <div class="link">
        <a href="dashboard.php">Go to Dashboard</a>
    </div>
</body>

</html>