<?php
/**
 * Page Template
 * Use this as a starting point for new pages in the HMS system
 * 
 * Instructions:
 * 1. Copy this file and rename it (e.g., MyNewPage.php)
 * 2. Update the title and page name
 * 3. Add your page-specific content in the content-container
 * 4. Add your page-specific CSS in a separate file
 * 5. Add page-specific JavaScript at the bottom
 */

// Security check
require_once 'access_check.php';
checkAccess('page_template.php'); // Change to your page name

// Database connection
require_once 'config.php';

// ═══════════════════════════════════════════════════════════
// YOUR PHP LOGIC HERE
// ═══════════════════════════════════════════════════════════

$statusMessage = '';
$errorMessages = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Handle different actions
    switch ($action) {
        case 'create':
            // Create logic
            break;
        case 'update':
            // Update logic
            break;
        case 'delete':
            // Delete logic
            break;
    }
}

// Fetch data for display
$data = [];
try {
    $stmt = $conn->query("SELECT * FROM your_table ORDER BY id DESC");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessages[] = "Database error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/MoonClaveLogo3.svg">
    <title>Your Page Title - HMS</title>
    
    <!-- Global Layout Styles (Required) -->
    <link rel="stylesheet" href="includes/global_layout.css">
    
    <!-- Your Page-Specific CSS -->
    <link rel="stylesheet" href="yourpage.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Required Scripts -->
    <script src="role-based-menu.js" defer></script>
    <script src="auto_logout.js" defer></script>
    <script src="cancellation-notification.js" defer></script>
    
    <!-- Global Sidebar Scripts (Required) -->
    <script src="includes/sidebar_scripts.js" defer></script>
</head>

<body>
    <div class="split-container">
        <!-- ═══════════════════════════════════════════════════════════
             GLOBAL SIDEBAR (Do not modify)
             ═══════════════════════════════════════════════════════════ -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- ═══════════════════════════════════════════════════════════
             RIGHT PANEL
             ═══════════════════════════════════════════════════════════ -->
        <div class="right-panel">
            <!-- Global Header (Do not modify) -->
            <?php include 'includes/header.php'; ?>
            
            <!-- ═══════════════════════════════════════════════════════════
                 YOUR PAGE CONTENT STARTS HERE
                 ═══════════════════════════════════════════════════════════ -->
            <div class="content-container">
                <!-- Page Title -->
                <h2 class="header-title">Your Page Title</h2>

                <!-- Status Messages -->
                <?php if (!empty($statusMessage)): ?>
                    <div class="alert-box alert-success">
                        <?php echo htmlspecialchars($statusMessage); ?>
                    </div>
                <?php endif; ?>

                <!-- Error Messages -->
                <?php if (!empty($errorMessages)): ?>
                    <div class="alert-box alert-error">
                        <ul>
                            <?php foreach ($errorMessages as $msg): ?>
                                <li><?php echo htmlspecialchars($msg); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- ─────────────────────────────────────────────────────
                     EXAMPLE: Action Button
                     ───────────────────────────────────────────────────── -->
                <button class="primary-btn" onclick="showForm()">
                    Create New
                </button>

                <!-- ─────────────────────────────────────────────────────
                     EXAMPLE: Form Card
                     ───────────────────────────────────────────────────── -->
                <div class="form-card" id="formCard" style="display: none;">
                    <form method="post" id="mainForm">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="field1">Field 1</label>
                                <input type="text" id="field1" name="field1" placeholder="Enter value" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="field2">Field 2</label>
                                <input type="text" id="field2" name="field2" placeholder="Enter value" required>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="submit-btn">Submit</button>
                            <button type="button" class="cancel-btn" onclick="hideForm()">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- ─────────────────────────────────────────────────────
                     EXAMPLE: Data Table
                     ───────────────────────────────────────────────────── -->
                <div class="table-card">
                    <div class="table-toolbar">
                        <div class="search-bar-container">
                            <input type="text" id="searchInput" class="search-bar-input" placeholder="Search...">
                            <button class="search-bar-btn" type="button">
                                <img src="Icon/searchicon_system.svg" alt="Search" class="search-bar-icon">
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Column 1</th>
                                    <th>Column 2</th>
                                    <th>Column 3</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <?php if (empty($data)): ?>
                                    <tr class="empty-row">
                                        <td colspan="4">No data found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($data as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['column1'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['column2'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['column3'] ?? ''); ?></td>
                                            <td>
                                                <button type="button" class="action-btn btn-edit" 
                                                    onclick="editRow(<?php echo $row['id']; ?>)">
                                                    Edit
                                                </button>
                                                <button type="button" class="action-btn btn-delete" 
                                                    onclick="deleteRow(<?php echo $row['id']; ?>)">
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ─────────────────────────────────────────────────────
                     EXAMPLE: Modal
                     ───────────────────────────────────────────────────── -->
                <div class="modal-overlay" id="editModal">
                    <div class="modal-box">
                        <button class="modal-close" type="button" onclick="closeModal()">×</button>
                        <h3 class="modal-title">Edit Item</h3>
                        
                        <form method="post" id="editForm">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" id="editId">
                            
                            <div class="form-group">
                                <label for="editField1">Field 1</label>
                                <input type="text" id="editField1" name="field1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="editField2">Field 2</label>
                                <input type="text" id="editField2" name="field2" required>
                            </div>
                            
                            <div class="modal-actions">
                                <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
                                <button type="submit" class="save-btn">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
            <!-- ═══════════════════════════════════════════════════════════
                 YOUR PAGE CONTENT ENDS HERE
                 ═══════════════════════════════════════════════════════════ -->
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         YOUR PAGE-SPECIFIC JAVASCRIPT
         ═══════════════════════════════════════════════════════════ -->
    <script>
        // ── Form Display ─────────────────────────────────────────
        function showForm() {
            document.getElementById('formCard').style.display = 'block';
        }

        function hideForm() {
            document.getElementById('formCard').style.display = 'none';
        }

        // ── Modal Functions ──────────────────────────────────────
        function editRow(id) {
            // Fetch data for this row and populate modal
            // This is just an example - implement your actual logic
            document.getElementById('editId').value = id;
            document.getElementById('editModal').classList.add('visible');
        }

        function closeModal() {
            document.getElementById('editModal').classList.remove('visible');
        }

        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // ── Delete Function ──────────────────────────────────────
        function deleteRow(id) {
            if (confirm('Are you sure you want to delete this item?')) {
                // Create and submit a delete form
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // ── Search Function ──────────────────────────────────────
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('tableBody');

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                const rows = tableBody.querySelectorAll('tr:not(.empty-row)');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            });
        }

        // ── Form Validation ──────────────────────────────────────
        document.getElementById('mainForm').addEventListener('submit', function(e) {
            // Add your validation logic here
            // e.preventDefault(); to stop submission if validation fails
        });

        // ── Page Initialization ──────────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {
            // Your initialization code here
            console.log('Page loaded successfully');
        });
    </script>
</body>

</html>
