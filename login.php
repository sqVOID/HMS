<?php
session_start();
require_once 'config.php';

// Clear any existing session when accessing login page
// This ensures users must login fresh
if (isset($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Only redirect if not submitting login form
    session_destroy();
    session_start();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize inputs
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    // Validate inputs are not empty
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif (strlen($username) < 1 || strlen($password) < 1) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            // Check if user exists
            $stmt = $conn->prepare("SELECT id, username, first_name, last_name, password, access_level, status FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Strict password verification - must match exactly
            if (!$user || !isset($user['id']) || empty($user['id'])) {
                // User doesn't exist
                $error = 'Invalid username or password.';
            } elseif (!isset($user['password']) || $user['password'] === '' || trim($user['password']) === '') {
                // User exists but has no password set
                $error = 'Invalid username or password.';
            } else {
                // Get password values for comparison (already trimmed above)
                $inputPassword = $password; // Already trimmed
                $dbPassword = trim($user['password']);
                
                // Strict comparison - must match exactly (case-sensitive)
                // Check if passwords match character by character
                if ($inputPassword === '' || $dbPassword === '') {
                    $error = 'Invalid username or password.';
                } elseif (strlen($inputPassword) !== strlen($dbPassword)) {
                    // Different lengths - definitely wrong
                    $error = 'Invalid username or password.';
                } elseif ($inputPassword !== $dbPassword) {
                    // Password doesn't match exactly (strict comparison)
                    $error = 'Invalid username or password.';
                } else {
                    // Login successful - password matches exactly
                    // Check if account is active
                    $accountStatus = trim($user['status'] ?? 'Active');
                    if ($accountStatus === 'Inactive') {
                        $error = 'Your account has been deactivated. Please contact the administrator.';
                    } else {
                        $_SESSION['user_id'] = (int)$user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['first_name'] = $user['first_name'] ?? '';
                        $_SESSION['last_name'] = $user['last_name'] ?? '';
                        $_SESSION['access_level'] = $user['access_level'];

                        // Record login time in user_sessions table
                        try {
                            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
                            
                            $sessionStmt = $conn->prepare("
                                INSERT INTO user_sessions 
                                (user_id, username, login_at, session_status, user_agent) 
                                VALUES (?, ?, NOW(), 'active', ?)
                            ");
                            $sessionStmt->execute([
                                $user['id'],
                                $user['username'],
                                $userAgent
                            ]);
                            
                            // Store session ID for later use (logout, break, turnover)
                            $_SESSION['session_id'] = $conn->lastInsertId();
                        } catch (PDOException $e) {
                            // Log error but don't prevent login
                            error_log('Session tracking error: ' . $e->getMessage());
                        }

                        // Redirect based on access level
                        $accessLevel = $user['access_level'];
                        if ($accessLevel === 'super_admin') {
                            header('Location: Createuser.php');
                        } elseif ($accessLevel === 'admin' || $accessLevel === 'staff') {
                            header('Location: Report.php');
                        } elseif ($accessLevel === 'auditor') {
                            header('Location: Report.php');
                        } else {
                            header('Location: Booking.html');
                        }
                        exit;
                    }
                }
            }
        } catch (PDOException $e) {
            // Check if the error is because table doesn't exist
            if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Unknown table") !== false) {
                $error = 'Users table not found. Please run setup_database.php first.';
            } else {
                $error = 'Database error. Please try again later.';
            }
            error_log('Login error: ' . $e->getMessage());
        }
    }
}

// If there's an error or GET request, redirect back to Login.html with error message
if (!empty($error)) {
    header('Location: Login.html?error=' . urlencode($error));
} else {
    header('Location: Login.html');
}
exit;
?>

