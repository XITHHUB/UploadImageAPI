<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Tester - Image Upload</title>
    <link rel="stylesheet" href="style.css">
    <style>
    .api-tester {
        max-width: 800px;
        margin: 40px auto;
        padding: 30px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    }

    .test-section {
        margin: 30px 0;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background: #f8f9fa;
    }

    .response-area {
        background: #000;
        color: #0f0;
        padding: 15px;
        border-radius: 5px;
        font-family: monospace;
        font-size: 12px;
        min-height: 100px;
        white-space: pre-wrap;
        word-wrap: break-word;
        margin-top: 15px;
    }

    .test-btn {
        background: #28a745;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        margin-top: 10px;
    }

    .test-btn:hover {
        background: #218838;
    }

    .test-btn:disabled {
        background: #6c757d;
        cursor: not-allowed;
    }
    </style>
</head>

<body>
    <div class="api-tester">
        <div class="header">
            <h1>API Tester</h1>
            <a href="dashboard.php" class="logout-btn">← Back to Dashboard</a>
        </div>

        <p>Test your API endpoints with your API keys. Make sure you have generated an API key first.</p>

        <!-- Test Upload API -->
        <div class="test-section">
            <h3>Test Image Upload API</h3>
            <form id="uploadTestForm">
                <div class="form-group">
                    <label for="api_key_upload">API Key:</label>
                    <input type="text" id="api_key_upload" placeholder="Enter your API key" required>
                </div>

                <div class="form-group">
                    <label for="test_image">Test Image:</label>
                    <input type="file" id="test_image" accept="image/*" required>
                </div>

                <div class="form-group">
                    <label for="test_title">Title (optional):</label>
                    <input type="text" id="test_title" placeholder="Enter image title" maxlength="255">
                </div>

                <div class="form-group">
                    <label for="test_description">Description (optional):</label>
                    <textarea id="test_description" placeholder="Enter image description" maxlength="1000"
                        rows="3"></textarea>
                </div>

                <button type="submit" class="test-btn">Test Upload API</button>
            </form>

            <div class="response-area" id="uploadResponse">Response will appear here...</div>
        </div>

        <!-- Test List Images API -->
        <div class="test-section">
            <h3>Test List Images API</h3>
            <form id="listTestForm">
                <div class="form-group">
                    <label for="api_key_list">API Key:</label>
                    <input type="text" id="api_key_list" placeholder="Enter your API key" required>
                </div>

                <div class="form-group">
                    <label for="page_num">Page (optional):</label>
                    <input type="number" id="page_num" value="1" min="1">
                </div>

                <div class="form-group">
                    <label for="limit_num">Limit (optional):</label>
                    <input type="number" id="limit_num" value="20" min="1" max="100">
                </div>

                <button type="submit" class="test-btn">Test List API</button>
            </form>

            <div class="response-area" id="listResponse">Response will appear here...</div>
        </div>
    </div>

    <script>
    // Test upload API
    document.getElementById('uploadTestForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const apiKey = document.getElementById('api_key_upload').value;
        const imageFile = document.getElementById('test_image').files[0];
        const responseArea = document.getElementById('uploadResponse');

        if (!apiKey || !imageFile) {
            responseArea.textContent = 'Please provide both API key and image file.';
            return;
        }

        responseArea.textContent = 'Testing upload API...';

        const formData = new FormData();
        formData.append('image', imageFile);

        // Add title and description if provided
        const title = document.getElementById('test_title').value.trim();
        const description = document.getElementById('test_description').value.trim();

        if (title) {
            formData.append('title', title);
        }

        if (description) {
            formData.append('description', description);
        }

        try {
            const response = await fetch('api_upload.php', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${apiKey}`
                },
                body: formData
            });

            const result = await response.json();

            responseArea.textContent = `Status: ${response.status} ${response.statusText}\n\n` +
                JSON.stringify(result, null, 2);

        } catch (error) {
            responseArea.textContent = `Error: ${error.message}`;
        }
    });

    // Test list images API
    document.getElementById('listTestForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const apiKey = document.getElementById('api_key_list').value;
        const page = document.getElementById('page_num').value;
        const limit = document.getElementById('limit_num').value;
        const responseArea = document.getElementById('listResponse');

        if (!apiKey) {
            responseArea.textContent = 'Please provide API key.';
            return;
        }

        responseArea.textContent = 'Testing list images API...';

        const url = new URL('api_images.php', window.location.origin + window.location.pathname.replace(
            '/api_tester.php', ''));
        if (page) url.searchParams.append('page', page);
        if (limit) url.searchParams.append('limit', limit);

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${apiKey}`
                }
            });

            const result = await response.json();

            responseArea.textContent = `Status: ${response.status} ${response.statusText}\n\n` +
                JSON.stringify(result, null, 2);

        } catch (error) {
            responseArea.textContent = `Error: ${error.message}`;
        }
    });
    </script>
</body>

</html>