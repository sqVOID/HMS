<?php
/**
 * Password Hash Generator
 * Use this tool to generate password hashes for creating new users in phpMyAdmin
 */

$password_hash = '';
$password_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_input = $_POST['password'] ?? '';
    if (!empty($password_input)) {
        $password_hash = password_hash($password_input, PASSWORD_DEFAULT);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hash Generator</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
        }
        .result {
            margin-top: 30px;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 6px;
            border-left: 4px solid #667eea;
        }
        .result-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        .hash-output {
            background: white;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            word-break: break-all;
            color: #333;
            border: 1px solid #ddd;
        }
        .instructions {
            margin-top: 30px;
            padding: 20px;
            background: #e3f2fd;
            border-radius: 6px;
            border-left: 4px solid #2196f3;
        }
        .instructions h3 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        .instructions ol {
            margin-left: 20px;
            color: #555;
        }
        .instructions li {
            margin-bottom: 8px;
        }
        .warning {
            margin-top: 20px;
            padding: 15px;
            background: #fff3cd;
            border-radius: 6px;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Password Hash Generator</h1>
        <p class="subtitle">Generate secure password hashes for user accounts</p>
        
        <form method="POST">
            <div class="form-group">
                <label for="password">Enter Password:</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="Enter the password you want to hash"
                    required
                    autocomplete="off"
                />
            </div>
            <button type="submit">Generate Hash</button>
        </form>

        <?php if (!empty($password_hash)): ?>
        <div class="result">
            <div class="result-label">Generated Password Hash:</div>
            <div class="hash-output" id="hashOutput"><?php echo htmlspecialchars($password_hash); ?></div>
            <button onclick="copyHash()" style="margin-top: 10px; padding: 8px 20px; font-size: 14px;">Copy Hash</button>
        </div>
        <?php endif; ?>

        <div class="instructions">
            <h3>📋 How to Use:</h3>
            <ol>
                <li>Enter the password you want to use for the new user</li>
                <li>Click "Generate Hash"</li>
                <li>Copy the generated hash</li>
                <li>Go to phpMyAdmin → `users` table → Insert</li>
                <li>Paste the hash into the `password` field</li>
                <li>Fill in `username` and `access_level` (staff/admin/user)</li>
                <li>Save the new user</li>
            </ol>
        </div>

        <div class="warning">
            <strong>⚠️ Security Note:</strong> Never share this tool publicly. Delete this file after setup or restrict access to it.
        </div>
    </div>

    <script>
        function copyHash() {
            const hashOutput = document.getElementById('hashOutput');
            const text = hashOutput.textContent;
            
            navigator.clipboard.writeText(text).then(() => {
                alert('Hash copied to clipboard!');
            }).catch(err => {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('Hash copied to clipboard!');
            });
        }
    </script>
</body>
</html>

