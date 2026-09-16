<?php
/**
 * Create user_sessions table to track user login/logout/break/turnover times
 * Run this file once to create the table
 */

require_once 'config.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        username VARCHAR(100) NOT NULL,
        login_at DATETIME NOT NULL,
        logout_at DATETIME DEFAULT NULL,
        break_at DATETIME DEFAULT NULL,
        turnover_at DATETIME DEFAULT NULL,
        session_status ENUM('active', 'logged_out', 'on_break', 'turnover') DEFAULT 'active',
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_login_at (login_at),
        INDEX idx_session_status (session_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->exec($sql);
    echo "✓ Table 'user_sessions' created successfully!\n";
    echo "You can now track user login, logout, break, and turnover times.\n";

} catch (PDOException $e) {
    echo "✗ Error creating table: " . $e->getMessage() . "\n";
    exit(1);
}
?>
