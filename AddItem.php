<?php
require_once 'access_check.php';
checkAccess('AddItem.php');

// Use config.php for database connection
require_once 'config.php';

$statusMessage = '';
$errorMessages = [];

// Convert PDO connection to mysqli for this file
try {
    $serverConnection = new mysqli($host, $username, $password, $dbname);
    if ($serverConnection->connect_error) {
        error_log('Database connection failed: ' . $serverConnection->connect_error);
        die('Database connection failed. Please contact support.');
    }
    $serverConnection->set_charset('utf8mb4');
} catch (Exception $e) {
    error_log('Database error: ' . $e->getMessage());
    die('Database connection failed. Please contact support.');
}

$createTableQuery = "
    CREATE TABLE IF NOT EXISTS additem_list (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_name VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        image_path VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
";
$serverConnection->query($createTableQuery);

$uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'additem';
if (!is_dir($uploadDirectory)) {
    if (!@mkdir($uploadDirectory, 0755, true)) {
        error_log('Failed to create upload directory: ' . $uploadDirectory);
    }
}

function sanitizeText($value)
{
    return trim((string) $value);
}

function processUploadedImage(string $key, string $targetDirectory, array &$errors): ?string
{
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$key];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Unable to upload the product image. Please try again.';
        return null;
    }

    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMime, true)) {
        $errors[] = 'Only JPG, PNG, or WEBP images are allowed.';
        return null;
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = sprintf('item_%s.%s', uniqid('', true), strtolower($extension));
    $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $errors[] = 'Unable to save the uploaded image.';
        return null;
    }

    return 'uploads/additem/' . $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productName = sanitizeText($_POST['product_name'] ?? '');
    $price = $_POST['price'] ?? '';
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $existingImage = sanitizeText($_POST['existing_image'] ?? '');
    if ($existingImage && strpos($existingImage, 'uploads/additem/') !== 0) {
        $existingImage = '';
    }
    $priceValue = $price === '' ? 0 : (float) $price;

    if ($action === 'create' || $action === 'update') {
        if ($productName === '') {
            $errorMessages[] = 'Product name is required.';
        }
        if ($price === '' || !is_numeric($price) || (float) $price < 0) {
            $errorMessages[] = 'Price must be a positive number.';
        }
        $newImage = processUploadedImage('product_image', $uploadDirectory, $errorMessages);
        $imagePath = $newImage ?: $existingImage;

        if ($action === 'create' && !$imagePath) {
            $errorMessages[] = 'Please upload an image for the product.';
        }

        if (empty($errorMessages)) {
            if ($action === 'create') {
                $stmt = $serverConnection->prepare("INSERT INTO additem_list (product_name, price, image_path) VALUES (?, ?, ?)");
                $stmt->bind_param('sds', $productName, $priceValue, $imagePath);
                $stmt->execute();
                $stmt->close();
                header('Location: ' . $_SERVER['PHP_SELF'] . '?status=created');
                exit;
            } elseif ($action === 'update' && $itemId > 0) {
                $stmt = $serverConnection->prepare("UPDATE additem_list SET product_name = ?, price = ?, image_path = ? WHERE id = ?");
                $stmt->bind_param('sdsi', $productName, $priceValue, $imagePath, $itemId);
                $stmt->execute();
                $stmt->close();
                header('Location: ' . $_SERVER['PHP_SELF'] . '?status=updated');
                exit;
            }
        }
    } elseif ($action === 'delete' && $itemId > 0) {
        $imageQuery = $serverConnection->prepare('SELECT image_path FROM additem_list WHERE id = ?');
        $imageQuery->bind_param('i', $itemId);
        $imageQuery->execute();
        $imageQuery->bind_result($imageToRemove);
        $imageQuery->fetch();
        $imageQuery->close();

        if ($imageToRemove) {
            $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . $imageToRemove;
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        $stmt = $serverConnection->prepare('DELETE FROM additem_list WHERE id = ?');
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $stmt->close();
        header('Location: ' . $_SERVER['PHP_SELF'] . '?status=deleted');
        exit;
    }
}

if (isset($_GET['status'])) {
    $statusLookup = [
        'created' => 'New item has been added.',
        'updated' => 'Item details updated.',
        'deleted' => 'Item removed successfully.'
    ];
    $statusKey = $_GET['status'];
    if (isset($statusLookup[$statusKey])) {
        $statusMessage = $statusLookup[$statusKey];
    }
}

$items = [];
$result = $serverConnection->query('SELECT * FROM additem_list ORDER BY created_at DESC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $result->close();
}
$serverConnection->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/MoonClaveLogo3.svg">
    <title>Add Item</title>
    <!-- Global Layout Styles -->
    <link rel="stylesheet" href="includes/global_layout.css">
    <!-- Page-specific Styles -->
    <link rel="stylesheet" href="additem.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="role-based-menu.js?v=18"></script>
    <script src="auto_logout.js" defer></script>
    <script src="cancellation-notification.js?v=15" defer></script>
    <!-- Global Sidebar Scripts -->
    <script src="includes/sidebar_scripts.js" defer></script>
    <style>
        /* Compact filter styles */
        .search-bar-container {
            display: flex;
            align-items: center;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            height: 32px;
            overflow: hidden;
        }

        .search-bar-input {
            border: none;
            outline: none;
            background: transparent;
            font-family: 'Poppins', sans-serif;
            font-size: 11px;
            color: #222;
            padding: 0 10px;
            height: 100%;
            min-width: 180px;
        }

        .search-bar-input::placeholder {
            color: #94a3b8;
        }

        .search-bar-btn {
            margin-right: -10px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0 12px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .search-bar-btn:hover {
            background: #f8fafc;
        }

        .search-bar-icon {
            width: 14px;
            height: 14px;
            opacity: 1;
        }

        .add-item-btn {
            background: #000000;
            border: none;
            color: #ffffff;
            font-family: 'Poppins', sans-serif;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            padding: 0 16px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            white-space: nowrap;
        }

        .add-item-btn:hover {
            background: #1f2937;
        }

        .add-item-btn .add-symbol {
            font-size: 16px;
            vertical-align: middle;
            margin-right: 6px;
        }

        .header-title {
            font-size: 18px;
            font-weight: 700;
            color: #222;
            font-family: "Poppins", "Segoe UI", Arial, sans-serif;
            margin-top: 0px;
            margin-bottom: 0;
        }
    </style>
</head>

<body>
    <div class="split-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="right-panel">
            <?php include 'includes/header.php'; ?>
            <div class="content-container">
                <h2 class="header-title">Add Item</h2>
                <div class="table-card">
                    <div class="table-toolbar calendar-additem-container">
                        <div class="search-bar-container">
                            <input type="text" id="searchInput" class="search-bar-input" placeholder="Search">
                            <button class="search-bar-btn" type="button">
                                <img src="Icon/searchicon_system.svg" alt="Search" class="search-bar-icon">
                            </button>
                        </div>
                        <button class="add-item-btn" id="openItemModal">
                            <span class="add-symbol">+</span>
                            Add Item
                        </button>
                    </div>
                    <?php if (!empty($statusMessage)): ?>
                        <p class="inline-alert success-inline"><?php echo $statusMessage; ?></p>
                    <?php endif; ?>
                    <?php if (!empty($errorMessages)): ?>
                        <ul class="inline-alert error-inline">
                            <?php foreach ($errorMessages as $message): ?>
                                <li><?php echo $message; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <table class="add-item-table">
                        <thead>
                            <tr>
                                <th>Product Image</th>
                                <th>Product Name</th>
                                <th>Price</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if (empty($items)): ?>
                                <tr class="empty-row">
                                    <td colspan="4">No items added yet. Click "Add Item" to get started.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <tr
                                        data-product-name="<?php echo htmlspecialchars(strtolower($item['product_name']), ENT_QUOTES, 'UTF-8'); ?>">
                                        <td>
                                            <?php if (!empty($item['image_path'])): ?>
                                                <img src="<?php echo htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    alt="<?php echo htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    class="product-thumb">
                                            <?php else: ?>
                                                <span class="no-image">No Image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="product-name-cell">
                                            <?php echo htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td>₱<?php echo number_format((float) $item['price'], 2); ?></td>
                                        <td class="action-cell">

                                            <button type="button" class="table-btn edit-btn" data-item="<?=
                                                htmlspecialchars(json_encode([
                                                    'id' => (int) $item['id'],
                                                    'product_name' => $item['product_name'],
                                                    'price' => number_format((float) $item['price'], 2, '.', ''),
                                                    'image_path' => $item['image_path']
                                                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                            ?>">
                                                Edit
                                            </button>


                                            <button type="button" class="table-btn delete-btn"
                                                onclick="deleteItem(<?= (int) $item['id']; ?>)">
                                                Delete
                                            </button>


                                            <form id="delete-form-<?= (int) $item['id']; ?>" method="post"
                                                style="display:none;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="item_id" value="<?= (int) $item['id']; ?>">
                                            </form>

                                        </td>


                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="itemModal">
        <div class="modal-content">
            <button class="modal-close" type="button" id="closeModalBtn">×</button>
            <h3 class="modal-title" id="modalTitle">Add Item</h3>
            <form class="modal-form" id="itemForm" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="item_id" id="itemId">
                <input type="hidden" name="existing_image" id="existingImagePath">

                <label for="productName">Product Name</label>
                <input type="text" id="productName" name="product_name" placeholder="Enter product name" required>

                <label for="productPrice">Price</label>
                <div>
                    <input type="number" id="productPrice" name="price" placeholder="0.00" step="0.01" min="0" required>
                </div>

                <label for="productImage">Product Image</label>
                <input type="file" id="productImage" name="product_image" accept="image/png,image/jpeg,image/webp">
                <div class="image-preview" id="imagePreview">
                    <span>No image selected</span>
                </div>

                <div class="modal-actions">
                    <button type="button" class="modal-cancel" id="cancelModalBtn">Cancel</button>
                    <button type="submit" class="modal-submit">Save Item</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('itemModal');
        const openModalBtn = document.getElementById('openItemModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const cancelModalBtn = document.getElementById('cancelModalBtn');
        const form = document.getElementById('itemForm');
        const actionInput = document.getElementById('formAction');
        const modalTitle = document.getElementById('modalTitle');
        const itemIdInput = document.getElementById('itemId');
        const productNameInput = document.getElementById('productName');
        const productPriceInput = document.getElementById('productPrice');
        const existingImageInput = document.getElementById('existingImagePath');
        const imagePreview = document.getElementById('imagePreview');
        const productImageInput = document.getElementById('productImage');
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('tableBody');

        const resetForm = () => {
            form.reset();
            actionInput.value = 'create';
            modalTitle.textContent = 'Add Item';
            itemIdInput.value = '';
            existingImageInput.value = '';
            updatePreview();
        };

        const openModal = () => {
            modal.classList.add('visible');
        };




        function deleteItem(id) {
            if (confirm("Delete this item?")) {
                document.getElementById("delete-form-" + id).submit();
            }
        }
        const closeModal = () => {
            modal.classList.remove('visible');
        };

        const updatePreview = (src = '') => {
            if (src) {
                imagePreview.innerHTML = '<img src="' + src + '" alt="Preview">';
            } else {
                imagePreview.innerHTML = '<span>No image selected</span>';
            }
        };

        if (openModalBtn) {
            openModalBtn.addEventListener('click', () => {
                resetForm();
                openModal();
            });
        }

        [closeModalBtn, cancelModalBtn].forEach(btn => {
            btn.addEventListener('click', () => {
                closeModal();
            });
        });

        window.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        if (productImageInput) {
            productImageInput.addEventListener('change', (event) => {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (e) => updatePreview(e.target.result);
                    reader.readAsDataURL(file);
                } else {
                    updatePreview(existingImageInput.value);
                }
            });
        }

        document.querySelectorAll('.edit-btn').forEach((button) => {
            button.addEventListener('click', () => {
                const data = JSON.parse(button.dataset.item);
                actionInput.value = 'update';
                modalTitle.textContent = 'Edit Item';
                itemIdInput.value = data.id;
                productNameInput.value = data.product_name;
                productPriceInput.value = data.price;
                existingImageInput.value = data.image_path || '';
                updatePreview(data.image_path || '');
                openModal();
            });
        });

        const filterRows = () => {
            const query = searchInput.value.trim().toLowerCase();
            const rows = tableBody.querySelectorAll('tr');
            let visibleCount = 0;

            rows.forEach((row) => {
                if (row.classList.contains('empty-row')) {
                    row.style.display = query ? 'none' : '';
                    return;
                }
                const name = row.dataset.productName || '';
                const isVisible = name.includes(query);
                row.style.display = isVisible ? '' : 'none';
                if (isVisible) {
                    visibleCount += 1;
                }
            });

            let noResultRow = document.getElementById('noResultsRow');
            if (!noResultRow) {
                noResultRow = document.createElement('tr');
                noResultRow.id = 'noResultsRow';
                noResultRow.innerHTML = '<td colspan="4">No matching items.</td>';
                tableBody.appendChild(noResultRow);
            }
            noResultRow.style.display = visibleCount === 0 && query ? '' : 'none';
        };

        if (searchInput) {
            searchInput.addEventListener('input', filterRows);
        }

    </script>
</body>

</html>