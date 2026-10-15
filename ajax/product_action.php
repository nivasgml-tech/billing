<?php
// ajax/product_action.php
header('Content-Type: application/json');
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasRole(['Super Admin', 'Admin', 'Staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit();
}

$action = sanitize($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);

if ($action === 'get_branch_stock' && $id > 0) {
    $stmt = $pdo->prepare("
        SELECT b.branch_name, COALESCE(bs.quantity, 0) as stock_qty 
        FROM branches b
        LEFT JOIN branch_stock bs ON b.id = bs.branch_id AND bs.product_id = ?
        ORDER BY b.branch_name
    ");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit();
}

// Product details
$product_name = sanitize($_POST['product_name'] ?? '');
$barcode = sanitize($_POST['barcode'] ?? '');
$category_id = (int)($_POST['category_id'] ?? 0);
$unit_id = (int)($_POST['unit_id'] ?? 0);
$quantity_type = sanitize($_POST['quantity_type'] ?? 'Single');
$hsn_code = sanitize($_POST['hsn_code'] ?? '');
$gst_rate = (float)($_POST['gst_rate'] ?? 0.00);
$purchase_price = (float)($_POST['purchase_price'] ?? 0.00);
$sale_price = (float)($_POST['sale_price'] ?? 0.00);
$min_stock_alert = (int)($_POST['min_stock_alert'] ?? 0);
$status = (int)($_POST['status'] ?? 1);


/* Generate a unique 8-digit numeric barcode if empty */
if (empty($barcode)) {
    do {
        $barcode = (string) random_int(10000000, 99999999);

        $checkBarcode = $pdo->prepare(
            "SELECT id FROM products WHERE barcode = ? LIMIT 1"
        );
        $checkBarcode->execute([$barcode]);

    } while ($checkBarcode->fetchColumn());
}

// Validation
if (empty($product_name) || $category_id <= 0 || $unit_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields.']);
    exit();
}

// Handle Image Upload
$imagePath = '';
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $uploadDir = '../assets/uploads/products/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $filename = $_FILES['image']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (in_array($ext, $allowed)) {
        $newName = uniqid('prod_') . '.' . $ext;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newName)) {
            $imagePath = 'assets/uploads/products/' . $newName; // Path for DB
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid image format. Allowed: JPG, PNG, WEBP']);
        exit();
    }
}

if ($action === 'add') {
    $stmt = $pdo->prepare("
        INSERT INTO products (product_name, category_id, unit_id, hsn_code, gst_rate, 
        purchase_price, sale_price, quantity_type, min_stock_alert, image, barcode, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $params = [
        $product_name,
        $category_id,
        $unit_id,
        $hsn_code,
        $gst_rate,
        $purchase_price,
        $sale_price,
        $quantity_type,
        $min_stock_alert,
        $imagePath,
        $barcode,
        $status
    ];

    if ($stmt->execute($params)) {
        echo json_encode(['success' => true, 'message' => 'Product added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add product']);
    }
} elseif ($action === 'edit' && $id > 0) {

    if ($imagePath != '') {
        $stmt = $pdo->prepare("
            UPDATE products SET product_name=?, category_id=?, unit_id=?, hsn_code=?, gst_rate=?, 
            purchase_price=?, sale_price=?, quantity_type=?, min_stock_alert=?, image=?, barcode=?, status=? 
            WHERE id=?
        ");
        $params = [
            $product_name,
            $category_id,
            $unit_id,
            $hsn_code,
            $gst_rate,
            $purchase_price,
            $sale_price,
            $quantity_type,
            $min_stock_alert,
            $imagePath,
            $barcode,
            $status,
            $id
        ];
    } else {
        $stmt = $pdo->prepare("
            UPDATE products SET product_name=?, category_id=?, unit_id=?, hsn_code=?, gst_rate=?, 
            purchase_price=?, sale_price=?, quantity_type=?, min_stock_alert=?, barcode=?, status=? 
            WHERE id=?
        ");
        $params = [
            $product_name,
            $category_id,
            $unit_id,
            $hsn_code,
            $gst_rate,
            $purchase_price,
            $sale_price,
            $quantity_type,
            $min_stock_alert,
            $barcode,
            $status,
            $id
        ];
    }

    if ($stmt->execute($params)) {
        echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update product']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
