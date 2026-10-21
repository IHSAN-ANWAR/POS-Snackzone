<?php
    require 'config.php';

    // session_start() MUST come before setCORS() so headers aren't already sent
    session_start();
    setCORS();

    // ── Auth check — all write actions require admin or receptionist session ──
    $action           = $_POST['action'] ?? $_GET['action'] ?? '';
    $readOnly         = ['get_categories', 'get_pending_count', 'get_delivery_modal_settings', 'get_site_settings', 'get_pending_orders', 'get_order_details', 'get_bill_history', 'get_profit_data', 'get_suppliers', 'get_profit_summary', 'get_profit_daily_breakdown', 'get_raw_purchases'];
    $receptOnlyAllowed= ['receptionist_update_order', 'toggle_stock', 'save_pos_order'];
    $isAdmin          = isset($_SESSION['snackzone_admin']);
    $isReceptionist   = isset($_SESSION['snackzone_receptionist']);

    if (!$isAdmin && !$isReceptionist && !in_array($action, $readOnly)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // Receptionist can only call their allowed actions
    if ($isReceptionist && !$isAdmin && !in_array($action, array_merge($readOnly, $receptOnlyAllowed))) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized — receptionist cannot perform this action']);
        exit;
    }

    $conn = getDB();

    // ── Schema (run once per table — idempotent) ───────────────
    $conn->query("CREATE TABLE IF NOT EXISTS menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(50) NOT NULL,
        title VARCHAR(150) NOT NULL,
        description TEXT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        image_path VARCHAR(255) NOT NULL DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) DEFAULT '',
        email VARCHAR(150) DEFAULT '',
        subject VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS notices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        body TEXT NOT NULL,
        image_path VARCHAR(255) DEFAULT '',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS daily_shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_date DATE NOT NULL UNIQUE,
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    notes TEXT DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS shift_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_id INT NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit VARCHAR(20) DEFAULT 'kg',
    cost_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shift_id) REFERENCES daily_shifts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Fix generated column if exists - safe approach
@$conn->query("ALTER TABLE shift_materials MODIFY COLUMN total_cost DECIMAL(10,2) NOT NULL DEFAULT 0");

// ── ADD CATEGORY ───────────────────────────────────────────────
    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Category name required']);
            exit;
        }

        // Ensure banner_image column exists
        $conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS banner_image VARCHAR(255) DEFAULT ''");

        $slug = trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $name)), '_');

        // Handle optional banner image upload
        $bannerPath = '';
        if (!empty($_FILES['banner']['name'])) {
            $allowed = ['jpg','jpeg','png','webp','gif'];
            $ext     = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                // Security: Validate actual MIME type
                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $_FILES['banner']['tmp_name']);
                finfo_close($finfo);
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (in_array($mimeType, $allowedMimes) && $_FILES['banner']['size'] <= 5 * 1024 * 1024) {
                    $uploadDir = '../img/banners/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $filename   = 'banner_' . $slug . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['banner']['tmp_name'], $uploadDir . $filename)) {
                        $bannerPath = 'img/banners/' . $filename;
                    }
                }
            }
        }

        $stmt = $conn->prepare("INSERT INTO categories (name, slug, banner_image) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $name, $slug, $bannerPath);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $conn->insert_id, 'name' => $name, 'slug' => $slug, 'banner_image' => $bannerPath]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Category already exists']);
        }
        $stmt->close();
        exit;
    }

    // ── DELETE CATEGORY ────────────────────────────────────────
    elseif ($action === 'delete_category') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }

        // Ensure banner_image column exists
        $conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS banner_image VARCHAR(255) DEFAULT ''");

        // Get slug + banner via prepared statement
        $stmt = $conn->prepare("SELECT slug, banner_image FROM categories WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Category not found']);
            exit;
        }
        $slug = $row['slug'];

        // Delete banner image
        if (!empty($row['banner_image'])) {
            $bannerFile = '../' . $row['banner_image'];
            if (file_exists($bannerFile)) unlink($bannerFile);
        }

        // Delete menu item images from filesystem
        $stmt = $conn->prepare("SELECT image_path FROM menu_items WHERE category = ?");
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $imgs = $stmt->get_result();
        while ($imgRow = $imgs->fetch_assoc()) {
            if ($imgRow['image_path']) {
                foreach ([__DIR__ . '/../public/' . $imgRow['image_path'], __DIR__ . '/../' . $imgRow['image_path']] as $file) {
                    if (file_exists($file)) unlink($file);
                }
            }
        }
        $stmt->close();

        // Delete items
        $stmt = $conn->prepare("DELETE FROM menu_items WHERE category = ?");
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $deletedItems = $stmt->affected_rows;
        $stmt->close();

        // Delete category
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'deleted_items' => $deletedItems, 'slug' => $slug]);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error']);
        }
        $stmt->close();
        exit;
    }

    // ── GET CATEGORIES ─────────────────────────────────────────
    elseif ($action === 'get_categories') {
        $conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS banner_image VARCHAR(255) DEFAULT ''");
        $res  = $conn->query("SELECT id, name, slug, banner_image FROM categories ORDER BY name ASC");
        $cats = [];
        while ($r = $res->fetch_assoc()) $cats[] = $r;
        echo json_encode(['success' => true, 'categories' => $cats]);
        exit;
    }

    // ── UPDATE CATEGORY BANNER ─────────────────────────────────
    elseif ($action === 'update_category_banner') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }

        $conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS banner_image VARCHAR(255) DEFAULT ''");

        if (empty($_FILES['banner']['name'])) {
            echo json_encode(['success' => false, 'message' => 'No image uploaded']);
            exit;
        }

        $allowed = ['jpg','jpeg','png','webp','gif'];
        $ext     = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid image format']);
            exit;
        }
        // Security: Validate actual MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['banner']['tmp_name']);
        finfo_close($finfo);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mimeType, $allowedMimes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type']);
            exit;
        }
        // Security: Check file size (max 5MB)
        if ($_FILES['banner']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File too large (max 5MB)']);
            exit;
        }

        // Get slug for filename
        $stmt = $conn->prepare("SELECT slug, banner_image FROM categories WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) { echo json_encode(['success' => false, 'message' => 'Category not found']); exit; }

        // Delete old banner
        if (!empty($row['banner_image'])) {
            $old = '../' . $row['banner_image'];
            if (file_exists($old)) unlink($old);
        }

        $uploadDir = '../img/banners/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $filename   = 'banner_' . $row['slug'] . '_' . uniqid() . '.' . $ext;
        if (!move_uploaded_file($_FILES['banner']['tmp_name'], $uploadDir . $filename)) {
            echo json_encode(['success' => false, 'message' => 'Upload failed']);
            exit;
        }

        $bannerPath = 'img/banners/' . $filename;
        $stmt = $conn->prepare("UPDATE categories SET banner_image = ? WHERE id = ?");
        $stmt->bind_param('si', $bannerPath, $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'banner_image' => $bannerPath]);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error']);
        }
        $stmt->close();
        exit;
    }

    // ── ADD MENU ITEM ──────────────────────────────────────────
    elseif ($action === 'add_item') {
        $category        = trim($_POST['category']        ?? '');
        $title           = trim($_POST['title']           ?? '');
        $description     = trim($_POST['description']     ?? '');
        $price           = trim($_POST['price']           ?? '');
        $discount_percent= intval($_POST['discount_percent'] ?? 0);

        if (!$category || !$title || !$description || !$price) {
            echo json_encode(['success' => false, 'message' => 'All fields required']);
            exit;
        }
        
        // Validate discount
        if ($discount_percent < 0) $discount_percent = 0;
        if ($discount_percent > 99) $discount_percent = 99;
        if (empty($_FILES['image']['name'])) {
            echo json_encode(['success' => false, 'message' => 'Image required']);
            exit;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'jfif'];
        $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid image format']);
            exit;
        }
        
        // Security: Validate actual file type (MIME)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mimeType, $allowedMimes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type']);
            exit;
        }
        
        // Security: Check file size (max 5MB)
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File too large (max 5MB)']);
            exit;
        }

        $uploadDir = __DIR__ . '/../img/menu/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $filename = uniqid('item_') . '.' . $ext;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
            echo json_encode(['success' => false, 'message' => 'Image upload failed']);
            exit;
        }

        $imagePath = 'img/menu/' . $filename;
        $stmt = $conn->prepare(
            "INSERT INTO menu_items (category, title, description, price, discount_percent, image_path) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('sssdis', $category, $title, $description, $price, $discount_percent, $imagePath);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error']);
        }
        $stmt->close();
        exit;
    }

    // ── DELETE MENU ITEM ───────────────────────────────────────
    elseif ($action === 'delete_item') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }

        $stmt = $conn->prepare("SELECT image_path FROM menu_items WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && $row['image_path']) {
            foreach ([__DIR__ . '/../public/' . $row['image_path'], __DIR__ . '/../' . $row['image_path']] as $file) {
                if (file_exists($file)) unlink($file);
            }
        }

        $stmt = $conn->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->bind_param('i', $id);
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
        exit;
    }

    // ── EDIT MENU ITEM ─────────────────────────────────────────
    elseif ($action === 'edit_item') {
        $id              = intval($_POST['id']              ?? 0);
        $title           = trim($_POST['title']             ?? '');
        $description     = trim($_POST['description']       ?? '');
        $price           = trim($_POST['price']             ?? '');
        $category        = trim($_POST['category']          ?? '');
        $discount_percent= intval($_POST['discount_percent'] ?? 0);

        if (!$id || !$title || !$description || !$price || !$category) {
            echo json_encode(['success' => false, 'message' => 'All fields required']);
            exit;
        }
        
        // Validate discount
        if ($discount_percent < 0) $discount_percent = 0;
        if ($discount_percent > 99) $discount_percent = 99;

        if (!empty($_FILES['image']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                echo json_encode(['success' => false, 'message' => 'Invalid image format']);
                exit;
            }
            // Security: Validate actual MIME type
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
            finfo_close($finfo);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($mimeType, $allowedMimes)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type']);
                exit;
            }
            // Security: Check file size (max 5MB)
            if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'File too large (max 5MB)']);
                exit;
            }

            $uploadDir = __DIR__ . '/../img/menu/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = uniqid('item_') . '.' . $ext;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                echo json_encode(['success' => false, 'message' => 'Image upload failed']);
                exit;
            }

            // Delete old image
            $stmt = $conn->prepare("SELECT image_path FROM menu_items WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $old = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($old && $old['image_path']) {
                foreach ([__DIR__ . '/../public/' . $old['image_path'], __DIR__ . '/../' . $old['image_path']] as $oldFile) {
                    if (file_exists($oldFile)) unlink($oldFile);
                }
            }

            $imagePath = 'img/menu/' . $filename;
            $stmt = $conn->prepare(
                "UPDATE menu_items SET title=?, description=?, price=?, category=?, discount_percent=?, image_path=? WHERE id=?"
            );
            $stmt->bind_param('ssdsisi', $title, $description, $price, $category, $discount_percent, $imagePath, $id);
        } else {
            $stmt = $conn->prepare(
                "UPDATE menu_items SET title=?, description=?, price=?, category=?, discount_percent=? WHERE id=?"
            );
            $stmt->bind_param('ssdsii', $title, $description, $price, $category, $discount_percent, $id);
        }

        if ($stmt->execute()) {
            $stmt2 = $conn->prepare("SELECT image_path, category FROM menu_items WHERE id = ?");
            $stmt2->bind_param('i', $id);
            $stmt2->execute();
            $row = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            echo json_encode(['success' => true, 'image_path' => $row['image_path'], 'category' => $row['category']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
        }
        $stmt->close();
        exit;
    }

    // ── MARK MESSAGE READ ──────────────────────────────────────
    elseif ($action === 'mark_read') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?");
        $stmt->bind_param('i', $id);
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
        exit;
    }

    // ── DELETE MESSAGE ─────────────────────────────────────────
    elseif ($action === 'delete_message') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id=?");
        $stmt->bind_param('i', $id);
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
        exit;
    }

    // ── MARK BOOKING READ ──────────────────────────────────────
    elseif ($action === 'mark_booking_read') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE event_bookings SET is_read=1 WHERE id=?");
        $stmt->bind_param('i', $id);
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
        exit;
    }

    // ── DELETE BOOKING ─────────────────────────────────────────
    elseif ($action === 'delete_booking') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM event_bookings WHERE id=?");
        $stmt->bind_param('i', $id);
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
        exit;
    }

    // ── ADD NOTICE ─────────────────────────────────────────────
    elseif ($action === 'add_notice') {
        $title = trim($_POST['title'] ?? '');
        $body  = trim($_POST['body']  ?? '');

        if (!$title || !$body) {
            echo json_encode(['success' => false, 'message' => 'Title and message are required']);
            exit;
        }

        $imagePath = '';
        if (!empty($_FILES['image']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                echo json_encode(['success' => false, 'message' => 'Invalid image format']);
                exit;
            }
            // Security: Validate actual MIME type
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
            finfo_close($finfo);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($mimeType, $allowedMimes)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type']);
                exit;
            }
            // Security: Check file size (max 5MB)
            if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'File too large (max 5MB)']);
                exit;
            }
            $uploadDir = '../img/notices/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = uniqid('notice_') . '.' . $ext;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                echo json_encode(['success' => false, 'message' => 'Image upload failed']);
                exit;
            }
            $imagePath = 'img/notices/' . $filename;
        }

        $conn->query("UPDATE notices SET is_active=0");
        $stmt = $conn->prepare("INSERT INTO notices (title, body, image_path, is_active) VALUES (?, ?, ?, 1)");
        $stmt->bind_param('sss', $title, $body, $imagePath);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error']);
        }
        $stmt->close();
        exit;
    }

    // ── TOGGLE NOTICE ──────────────────────────────────────────
    elseif ($action === 'toggle_notice') {
        $id     = intval($_POST['id']     ?? 0);
        $active = intval($_POST['active'] ?? 0) ? 1 : 0;
        if (!$id) {
            echo json_encode(['success' => false]);
            exit;
        }
        if ($active) $conn->query("UPDATE notices SET is_active=0");
        $stmt = $conn->prepare("UPDATE notices SET is_active=? WHERE id=?");
        $stmt->bind_param('ii', $active, $id);
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
        exit;
    }

    // ── DELETE NOTICE ──────────────────────────────────────────
    elseif ($action === 'delete_notice') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }

        $stmt = $conn->prepare("SELECT image_path FROM notices WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && $row['image_path']) {
            $file = '../' . $row['image_path'];
            if (file_exists($file)) unlink($file);
        }

        $stmt = $conn->prepare("DELETE FROM notices WHERE id=?");
        $stmt->bind_param('i', $id);
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
        exit;
    }

    // ── UPDATE ORDER STATUS ────────────────────────────────────
    elseif ($action === 'update_order_status') {
        $id        = intval($_POST['id']        ?? 0);
        $status    = trim($_POST['status']      ?? '');
        $prep_time = trim($_POST['prep_time']   ?? '');
        $allowed   = ['pending', 'confirmed', 'dispatched', 'cancelled'];

        if (!$id || !in_array($status, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }

        // Add column if missing (existing installs)
        $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS prep_time VARCHAR(50) DEFAULT ''");

        $stmt = $conn->prepare("UPDATE orders SET status=?, prep_time=? WHERE id=?");
        $stmt->bind_param('ssi', $status, $prep_time, $id);
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
        exit;
    }

    // ── DELETE ORDER ───────────────────────────────────────────
    elseif ($action === 'delete_order') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM orders WHERE id=?");
        $stmt->bind_param('i', $id);
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
        exit;
    }

    // ── GET PENDING ORDER COUNT ────────────────────────────────
    elseif ($action === 'get_pending_count') {
        $res = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='pending'");
        $cnt = $res ? $res->fetch_assoc()['c'] : 0;
        echo json_encode(['success' => true, 'count' => (int)$cnt]);
        exit;
    }

    // ── DELETE USER ────────────────────────────────────────────
    elseif ($action === 'delete_user') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param('i', $id);
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
        exit;
    }

    // ── ADD ADDON ─────────────────────────────────────────────
    elseif ($action === 'add_addon') {
        $conn->query("CREATE TABLE IF NOT EXISTS delivery_modal_settings (
            id TINYINT(1) PRIMARY KEY DEFAULT 1,
            is_enabled TINYINT(1) DEFAULT 1,
            pickup_location TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Insert default row if missing
        $conn->query("INSERT IGNORE INTO delivery_modal_settings (id, is_enabled, pickup_location) VALUES (1, 1, 'Snack Zone, 6th Road , Rawalpindi')");

        $conn->query("CREATE TABLE IF NOT EXISTS menu_item_addons (
            id        INT AUTO_INCREMENT PRIMARY KEY,
            item_id   INT           NOT NULL,
            name      VARCHAR(150)  NOT NULL,
            price     DECIMAL(10,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (item_id) REFERENCES menu_items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $item_id = intval($_POST['item_id'] ?? 0);
        $name    = trim($_POST['name']    ?? '');
        $price   = floatval($_POST['price'] ?? 0);

        if (!$item_id || !$name) {
            echo json_encode(['success' => false, 'message' => 'item_id and name required']);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO menu_item_addons (item_id, name, price) VALUES (?,?,?)");
        $stmt->bind_param('isd', $item_id, $name, $price);
        $ok = $stmt->execute();
        $newId = $conn->insert_id;
        $stmt->close();
        echo json_encode(['success' => $ok, 'id' => $newId, 'item_id' => $item_id, 'name' => $name, 'price' => $price]);
        exit;
    }

    // ── DELETE ADDON ───────────────────────────────────────────
    elseif ($action === 'delete_addon') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false]); exit; }
        $stmt = $conn->prepare("DELETE FROM menu_item_addons WHERE id=?");
        $stmt->bind_param('i', $id);
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
        exit;
    }

    // ── GET ADDONS FOR ITEM ────────────────────────────────────
    elseif ($action === 'get_addons') {
        $item_id = intval($_GET['item_id'] ?? $_POST['item_id'] ?? 0);
        if (!$item_id) { echo json_encode(['success' => false, 'addons' => []]); exit; }
        $conn->query("CREATE TABLE IF NOT EXISTS menu_item_addons (
            id INT AUTO_INCREMENT PRIMARY KEY, item_id INT NOT NULL,
            name VARCHAR(150) NOT NULL, price DECIMAL(10,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmt = $conn->prepare("SELECT id, name, price FROM menu_item_addons WHERE item_id=? ORDER BY id ASC");
        $stmt->bind_param('i', $item_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $addons = [];
        while ($r = $res->fetch_assoc()) { $r['price'] = (float)$r['price']; $addons[] = $r; }
        $stmt->close();
        echo json_encode(['success' => true, 'addons' => $addons]);
        exit;
    }

    // ── GET DELIVERY MODAL SETTINGS ────────────────────────────
    elseif ($action === 'get_delivery_modal_settings') {
        $conn->query("CREATE TABLE IF NOT EXISTS delivery_modal_settings (
            id TINYINT(1) PRIMARY KEY DEFAULT 1,
            is_enabled TINYINT(1) DEFAULT 1,
            pickup_location TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $conn->query("INSERT IGNORE INTO delivery_modal_settings (id, is_enabled, pickup_location) VALUES (1, 1, 'Snack Zone, 6th Road , Rawalpindi')");
        
        $res = $conn->query("SELECT is_enabled, pickup_location FROM delivery_modal_settings WHERE id=1");
        $row = $res->fetch_assoc();
        echo json_encode([
            'success' => true,
            'is_enabled' => (bool)$row['is_enabled'],
            'pickup_location' => $row['pickup_location'] ?? 'Snack Zone, 6th Road , Rawalpindi'
        ]);
        exit;
    }

    // ── UPDATE DELIVERY MODAL SETTINGS ─────────────────────────
    elseif ($action === 'update_delivery_modal_settings') {
        $is_enabled = intval($_POST['is_enabled'] ?? 1) ? 1 : 0;
        $pickup_location = trim($_POST['pickup_location'] ?? 'Snack Zone, 6th Road , Rawalpindi');
        
        $conn->query("CREATE TABLE IF NOT EXISTS delivery_modal_settings (
            id TINYINT(1) PRIMARY KEY DEFAULT 1,
            pickup_location TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $stmt = $conn->prepare("INSERT INTO delivery_modal_settings (id, is_enabled, pickup_location) VALUES (1, ?, ?) ON DUPLICATE KEY UPDATE is_enabled=?, pickup_location=?");
        $stmt->bind_param('isis', $is_enabled, $pickup_location, $is_enabled, $pickup_location);
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
        exit;
    }

    // ── GET SITE SETTINGS ──────────────────────────────────────
    elseif ($action === 'get_site_settings') {
        $conn->query("CREATE TABLE IF NOT EXISTS site_settings (
            k   VARCHAR(100) PRIMARY KEY,
            v   TEXT         NOT NULL DEFAULT '',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Seed defaults
        $conn->query("INSERT IGNORE INTO site_settings (k, v) VALUES ('whatsapp_number', '923045860472')");
        $conn->query("INSERT IGNORE INTO site_settings (k, v) VALUES ('site_name', 'SNACKZONE')");
        $conn->query("INSERT IGNORE INTO site_settings (k, v) VALUES ('site_tagline', 'Fresh, Fast & Delicious')");
        $conn->query("INSERT IGNORE INTO site_settings (k, v) VALUES ('site_address', '6th Rd, Opposite Noor Mobile Plaza, Satellite Town, Rawalpindi 43600')");
        $conn->query("INSERT IGNORE INTO site_settings (k, v) VALUES ('site_phone', '')");
        $conn->query("INSERT IGNORE INTO site_settings (k, v) VALUES ('wa_button_enabled', '1')");

        $res  = $conn->query("SELECT k, v FROM site_settings");
        $data = [];
        while ($row = $res->fetch_assoc()) $data[$row['k']] = $row['v'];
        
        // Return flattened data for easier access
        echo json_encode([
            'success' => true,
            'whatsapp_number' => $data['whatsapp_number'] ?? '',
            'site_name' => $data['site_name'] ?? 'SNACKZONE',
            'site_tagline' => $data['site_tagline'] ?? '',
            'site_address' => $data['site_address'] ?? '',
            'site_phone' => $data['site_phone'] ?? '',
            'wa_button_enabled' => isset($data['wa_button_enabled']) ? (bool)$data['wa_button_enabled'] : true,
        ]);
        exit;
    }

    // ── SAVE SITE SETTINGS ─────────────────────────────────────
    elseif ($action === 'save_site_settings') {
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $conn->query("CREATE TABLE IF NOT EXISTS site_settings (
            k   VARCHAR(100) PRIMARY KEY,
            v   TEXT         NOT NULL DEFAULT '',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $whatsapp    = trim($_POST['whatsapp_number'] ?? '');
        $siteName    = trim($_POST['site_name'] ?? '');
        $siteTagline = trim($_POST['site_tagline'] ?? '');
        $siteAddress = trim($_POST['site_address'] ?? '');
        $sitePhone   = trim($_POST['site_phone'] ?? '');
        $waBtnEnabled = isset($_POST['wa_button_enabled']) ? (intval($_POST['wa_button_enabled']) ? '1' : '0') : null;

        // Validate WhatsApp: digits only, 10-15 chars (international format without +)
        if ($whatsapp && !preg_match('/^[0-9]{10,15}$/', $whatsapp)) {
            echo json_encode(['success' => false, 'message' => 'Invalid WhatsApp number. Use international format without + (e.g. 923106653966)']);
            exit;
        }

        // Validate Site Name
        if ($siteName && (strlen($siteName) < 2 || strlen($siteName) > 50)) {
            echo json_encode(['success' => false, 'message' => 'Site name must be between 2 and 50 characters']);
            exit;
        }

        // Save settings
        $settings = [
            'whatsapp_number' => $whatsapp,
            'site_name'       => $siteName,
            'site_tagline'    => $siteTagline,
            'site_address'    => $siteAddress,
            'site_phone'      => $sitePhone,
        ];
        if ($waBtnEnabled !== null) $settings['wa_button_enabled'] = $waBtnEnabled;

        foreach ($settings as $key => $value) {
            if ($value !== null) {
                $stmt = $conn->prepare("INSERT INTO site_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v=?");
                $stmt->bind_param('sss', $key, $value, $value);
                $stmt->execute();
                $stmt->close();
            }
        }

        echo json_encode(['success' => true, 'message' => 'Settings saved']);
        exit;
    }

    // ── ADD HOMEPAGE SLIDE ─────────────────────────────────────
    elseif ($action === 'add_slide') {
        $conn->query("CREATE TABLE IF NOT EXISTS homepage_slides (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            image_path VARCHAR(255) NOT NULL,
            label      VARCHAR(100) DEFAULT '',
            sort_order INT          DEFAULT 0,
            is_active  TINYINT(1)   DEFAULT 1,
            created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $label = trim($_POST['label'] ?? '');

        if (empty($_FILES['image']['tmp_name'])) {
            echo json_encode(['success' => false, 'message' => 'Image required.']);
            exit;
        }

        $uploadDir = __DIR__ . '/../img/slides/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid image type.']);
            exit;
        }
        // Security: Validate actual MIME type
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mimeType, $allowedMimes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type.']);
            exit;
        }
        // Security: Check file size (max 5MB)
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File too large (max 5MB).']);
            exit;
        }

        $filename = 'slide_' . uniqid() . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'message' => 'Upload failed. Check folder permissions.']);
            exit;
        }

        $imagePath = 'img/slides/' . $filename;
        $sortOrder = intval($conn->query("SELECT COUNT(*) as c FROM homepage_slides")->fetch_assoc()['c']);

        $stmt = $conn->prepare("INSERT INTO homepage_slides (image_path, label, sort_order) VALUES (?,?,?)");
        $stmt->bind_param('ssi', $imagePath, $label, $sortOrder);
        $ok  = $stmt->execute();
        $newId = $conn->insert_id;
        $stmt->close();

        echo json_encode(['success' => $ok, 'id' => $newId, 'image_path' => $imagePath, 'label' => $label]);
        exit;
    }

    // ── DELETE HOMEPAGE SLIDE ──────────────────────────────────
    elseif ($action === 'delete_slide') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false]); exit; }

        $row = $conn->query("SELECT image_path FROM homepage_slides WHERE id=" . intval($id))->fetch_assoc();
        if ($row) {
            $file = __DIR__ . '/../' . $row['image_path'];
            if (file_exists($file)) @unlink($file);
        }

        $stmt = $conn->prepare("DELETE FROM homepage_slides WHERE id=?");
        $stmt->bind_param('i', $id);
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
        exit;
    }

    // ── VENDOR ACTION (Approve/Reject/Suspend) ─────────────────
    elseif ($action === 'vendor_action') {
        $vendor_id = intval($_POST['vendor_id'] ?? 0);
        $status    = trim($_POST['status'] ?? '');
        $allowed   = ['approved', 'rejected', 'suspended'];

        if (!$vendor_id || !in_array($status, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE vendors SET status=? WHERE id=?");
        $stmt->bind_param('si', $status, $vendor_id);
        
        if ($stmt->execute()) {
            // If approved, also activate the vendor's store
            if ($status === 'approved') {
                $vstmt = $conn->prepare("UPDATE vendor_stores SET is_active=1 WHERE vendor_id=?");
                $vstmt->bind_param('i', $vendor_id);
                $vstmt->execute();
                $vstmt->close();
            } else {
                $vstmt = $conn->prepare("UPDATE vendor_stores SET is_active=0 WHERE vendor_id=?");
                $vstmt->bind_param('i', $vendor_id);
                $vstmt->execute();
                $vstmt->close();
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        $stmt->close();
        exit;
    }

    // ── GET VENDOR DETAILS ─────────────────────────────────────
    elseif ($action === 'get_vendor_details') {
        $vendor_id = intval($_GET['vendor_id'] ?? $_POST['vendor_id'] ?? 0);
        
        if (!$vendor_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid vendor ID']);
            exit;
        }

        $stmt = $conn->prepare("SELECT v.*, vs.store_name, vs.is_active 
                                FROM vendors v 
                                LEFT JOIN vendor_stores vs ON v.id = vs.vendor_id 
                                WHERE v.id = ?");
        $stmt->bind_param('i', $vendor_id);
        $stmt->execute();
        $vendor = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($vendor) {
            echo json_encode(['success' => true, 'vendor' => $vendor]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Vendor not found']);
        }
        exit;
    }

    // ── MANAGE RECEPTIONIST (Admin only) ──────────────────────
    elseif ($action === 'create_receptionist') {
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        // Ensure admin_users table exists
        $conn->query("CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','receptionist') NOT NULL DEFAULT 'admin',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Ensure role column exists
        $conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS role ENUM('admin','receptionist') NOT NULL DEFAULT 'admin'");

        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$username || !$password) {
            echo json_encode(['success' => false, 'message' => 'Username and password required']);
            exit;
        }
        if (strlen($username) < 3 || strlen($username) > 30) {
            echo json_encode(['success' => false, 'message' => 'Username must be 3-30 characters']);
            exit;
        }
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
            exit;
        }

        // Only one receptionist allowed at a time — check if already exists
        $check = $conn->query("SELECT id FROM admin_users WHERE role='receptionist' LIMIT 1");
        if ($check && $check->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'A receptionist account already exists. Update it instead.']);
            exit;
        }

        // Check username uniqueness
        $stmt = $conn->prepare("SELECT id FROM admin_users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Username already taken']);
            exit;
        }
        $stmt->close();

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $role = 'receptionist';
        $stmt = $conn->prepare("INSERT INTO admin_users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $username, $hash, $role);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Receptionist account created']);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
        }
        $stmt->close();
        exit;
    }

    elseif ($action === 'update_receptionist') {
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS role ENUM('admin','receptionist') NOT NULL DEFAULT 'admin'");

        $new_username = trim($_POST['username'] ?? '');
        $new_password = trim($_POST['password'] ?? '');

        if (!$new_username) {
            echo json_encode(['success' => false, 'message' => 'Username required']);
            exit;
        }
        if (strlen($new_username) < 3 || strlen($new_username) > 30) {
            echo json_encode(['success' => false, 'message' => 'Username must be 3-30 characters']);
            exit;
        }
        if ($new_password && strlen($new_password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
            exit;
        }

        // Get existing receptionist
        $check = $conn->query("SELECT id FROM admin_users WHERE role='receptionist' LIMIT 1");
        if (!$check || $check->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No receptionist account found. Create one first.']);
            exit;
        }
        $recept = $check->fetch_assoc();
        $recept_id = $recept['id'];

        // Check username uniqueness (exclude self)
        $stmt = $conn->prepare("SELECT id FROM admin_users WHERE username = ? AND id != ?");
        $stmt->bind_param('si', $new_username, $recept_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Username already taken']);
            exit;
        }
        $stmt->close();

        if ($new_password) {
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE admin_users SET username=?, password=? WHERE id=?");
            $stmt->bind_param('ssi', $new_username, $hash, $recept_id);
        } else {
            $stmt = $conn->prepare("UPDATE admin_users SET username=? WHERE id=?");
            $stmt->bind_param('si', $new_username, $recept_id);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Receptionist credentials updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
        }
        $stmt->close();
        exit;
    }

    elseif ($action === 'delete_receptionist') {
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS role ENUM('admin','receptionist') NOT NULL DEFAULT 'admin'");

        $stmt = $conn->prepare("DELETE FROM admin_users WHERE role='receptionist'");
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Receptionist account deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error']);
        }
        $stmt->close();
        exit;
    }

    elseif ($action === 'get_receptionist') {
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        // Ensure role column exists
        $conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS role ENUM('admin','receptionist') NOT NULL DEFAULT 'admin'");
        
        // Direct query - simple and reliable
        $stmt = $conn->prepare("SELECT id, username, created_at, role FROM admin_users WHERE role = ? LIMIT 1");
        $roleValue = 'receptionist';
        $stmt->bind_param('s', $roleValue);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $r = $result->fetch_assoc();
            echo json_encode([
                'success' => true, 
                'exists' => true, 
                'username' => $r['username'], 
                'created_at' => $r['created_at'],
                'id' => $r['id']
            ]);
        } else {
            // Debug: Check if any receptionist exists by username
            $debugCheck = $conn->query("SELECT id, username, role FROM admin_users WHERE username='receptionist'");
            if ($debugCheck && $debugCheck->num_rows > 0) {
                $debug = $debugCheck->fetch_assoc();
                echo json_encode([
                    'success' => true, 
                    'exists' => false,
                    'debug' => 'Found user "receptionist" but role is: ' . $debug['role']
                ]);
            } else {
                echo json_encode(['success' => true, 'exists' => false]);
            }
        }
        $stmt->close();
        exit;
    }

    // ── RECEPTIONIST: UPDATE ORDER STATUS ─────────────────────
    elseif ($action === 'receptionist_update_order') {
        // Already auth-checked at top — both admin and receptionist reach here
        $id        = intval($_POST['id']        ?? 0);
        $status    = trim($_POST['status']      ?? '');
        $prep_time = trim($_POST['prep_time']   ?? '');
        $allowed   = ['pending', 'confirmed', 'dispatched', 'cancelled'];

        if (!$id || !in_array($status, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }

        $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS prep_time VARCHAR(50) DEFAULT ''");
        $stmt = $conn->prepare("UPDATE orders SET status=?, prep_time=? WHERE id=?");
        $stmt->bind_param('ssi', $status, $prep_time, $id);
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
        exit;
    }

    // ── TOGGLE MENU ITEM STOCK STATUS ─────────────────────────
    elseif ($action === 'toggle_stock') {
        if (!$isAdmin && !$isReceptionist) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $itemId = (int) ($_POST['item_id'] ?? 0);
        $inStock = (int) ($_POST['in_stock'] ?? 1);

        if (!$itemId) {
            echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
            exit;
        }

        // Ensure in_stock column exists
        $conn->query("ALTER TABLE menu_items ADD COLUMN IF NOT EXISTS in_stock TINYINT(1) NOT NULL DEFAULT 1");

        $stmt = $conn->prepare("UPDATE menu_items SET in_stock = ? WHERE id = ?");
        $stmt->bind_param('ii', $inStock, $itemId);
        
        if ($stmt->execute()) {
            $message = $inStock ? 'Item marked as in-stock' : 'Item marked as out-of-stock';
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        $stmt->close();
        exit;
    }

// ── GET TODAY'S SHIFT ──────────────────────────────────────
elseif ($action === 'get_today_shift') {
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
    }

    // Ensure tables exist
    $conn->query("CREATE TABLE IF NOT EXISTS daily_shifts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shift_date DATE NOT NULL UNIQUE,
        notes TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("CREATE TABLE IF NOT EXISTS shift_materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shift_id INT NOT NULL,
        item_name VARCHAR(150) NOT NULL,
        quantity DECIMAL(10,2) NOT NULL,
        unit VARCHAR(20) DEFAULT 'kg',
        cost_per_unit DECIMAL(10,2) NOT NULL,
        total_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (shift_id) REFERENCES daily_shifts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $hour = (int) date('H');
    $shift_date = ($hour >= 15) ? date('Y-m-d') : date('Y-m-d', strtotime('yesterday'));

    $conn->query("INSERT IGNORE INTO daily_shifts (shift_date) VALUES ('$shift_date')");

    $stmt = $conn->prepare("SELECT * FROM daily_shifts WHERE shift_date = ?");
    $stmt->bind_param('s', $shift_date);
    $stmt->execute();
    $shift = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $shift_id = $shift['id'];

    // Materials
    $stmt = $conn->prepare("SELECT * FROM shift_materials WHERE shift_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $shift_id);
    $stmt->execute();
    $materialsRes = $stmt->get_result();
    $materials = [];
    $material_cost = 0;
    while ($m = $materialsRes->fetch_assoc()) {
        $m['total_cost'] = (float) $m['total_cost'];
        $material_cost += $m['total_cost'];
        $materials[] = $m;
    }
    $stmt->close();

    // Sales
    $window_start = $shift_date . ' 15:00:00';
    $window_end   = date('Y-m-d', strtotime($shift_date . ' +1 day')) . ' 15:00:00';
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total), 0) as sales_total, COUNT(*) as orders_count FROM orders WHERE status != 'cancelled' AND created_at >= ? AND created_at < ?");
    $stmt->bind_param('ss', $window_start, $window_end);
    $stmt->execute();
    $salesRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'success'       => true,
        'shift_id'      => $shift_id,
        'shift_date'    => $shift_date,
        'materials'     => $materials,
        'sales'         => (float) $salesRow['sales_total'],
        'material_cost' => $material_cost,
        'profit'        => (float) $salesRow['sales_total'] - $material_cost,
        'orders_count'  => (int) $salesRow['orders_count'],
    ]);
    exit;
}

elseif ($action === 'add_material') {
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
    }
    $shift_id   = intval($_POST['shift_id']   ?? 0);
    $item_name  = trim($_POST['item_name']    ?? '');
    $quantity   = floatval($_POST['quantity'] ?? 0);
    $unit       = trim($_POST['unit']         ?? 'kg');
    $total_cost = floatval($_POST['total_cost'] ?? 0);

    if (!$shift_id || !$item_name || $quantity <= 0 || $total_cost <= 0) {
        echo json_encode(['success' => false, 'message' => 'All fields required']); exit;
    }
    $unit = in_array($unit, ['kg','liters','pieces','grams','packets']) ? $unit : 'kg';

    $stmt = $conn->prepare("INSERT INTO shift_materials (shift_id, item_name, quantity, unit, cost_per_unit, total_cost) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isdsdd', $shift_id, $item_name, $quantity, $unit, $total_cost, $total_cost);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Material added']);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// ── DELETE RAW MATERIAL ────────────────────────────────────
elseif ($action === 'delete_material') {
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
    }
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }
    $stmt = $conn->prepare("DELETE FROM shift_materials WHERE id = ?");
    $stmt->bind_param('i', $id);
    echo json_encode(['success' => $stmt->execute(), 'message' => 'Deleted']);
    $stmt->close();
    exit;
}

elseif ($action === 'get_shift_history') {
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
    }
    $stmt = $conn->prepare("
        SELECT
            ds.id,
            ds.shift_date,
            ds.opened_at,
            ds.closed_at,
            ds.notes,
            COALESCE(SUM(sm.total_cost), 0) AS material_cost
        FROM daily_shifts ds
        LEFT JOIN shift_materials sm ON sm.shift_id = ds.id
        GROUP BY ds.id
        ORDER BY ds.shift_date DESC
        LIMIT 30
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $shifts = [];
    while ($row = $result->fetch_assoc()) {
        // Compute sales for each shift window
        $window_start = $row['shift_date'] . ' 15:00:00';
        $window_end   = date('Y-m-d', strtotime($row['shift_date'] . ' +1 day')) . ' 15:00:00';
        $s2 = $conn->prepare("SELECT COALESCE(SUM(total),0) as st, COUNT(*) as oc FROM orders WHERE status != 'cancelled' AND created_at >= ? AND created_at < ?");
        $s2->bind_param('ss', $window_start, $window_end);
        $s2->execute();
        $sRow = $s2->get_result()->fetch_assoc();
        $s2->close();
        $row['sales_total']   = (float) $sRow['st'];
        $row['orders_count']  = (int)   $sRow['oc'];
        $row['material_cost'] = (float) $row['material_cost'];
        $row['profit']        = $row['sales_total'] - $row['material_cost'];
        $shifts[] = $row;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'shifts' => $shifts]);
    exit;
}

// ── CLOSE SHIFT ────────────────────────────────────────────
elseif ($action === 'close_shift') {
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
    }
    $shift_id = intval($_POST['shift_id'] ?? 0);
    $notes    = trim($_POST['notes'] ?? '');
    if (!$shift_id) { echo json_encode(['success' => false, 'message' => 'Invalid shift ID']); exit; }
    $stmt = $conn->prepare("UPDATE daily_shifts SET closed_at = NOW(), notes = ? WHERE id = ?");
    $stmt->bind_param('si', $notes, $shift_id);
    echo json_encode(['success' => $stmt->execute()]);
    $stmt->close();
    exit;
}

elseif ($action === 'get_weekly_report') {
    if (!$isAdmin) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

    $hour = (int)date('H');
    $today = $hour >= 15 ? date('Y-m-d') : date('Y-m-d', strtotime('yesterday'));
    $dayOfWeek = date('N', strtotime($today));
    $monday = date('Y-m-d', strtotime($today . ' -' . ($dayOfWeek - 1) . ' days'));
    $fromDt = $monday . ' 15:00:00';
    $toDt   = date('Y-m-d', strtotime($today . ' +1 day')) . ' 15:00:00';

    $stmt = $conn->prepare("SELECT COALESCE(SUM(total),0) as sales, COUNT(*) as cnt FROM orders WHERE status != 'cancelled' AND created_at >= ? AND created_at < ?");
    $stmt->bind_param('ss', $fromDt, $toDt);
    $stmt->execute();
    $sRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(sm.total_cost),0) as mc FROM shift_materials sm JOIN daily_shifts ds ON sm.shift_id=ds.id WHERE ds.shift_date >= ? AND ds.shift_date <= ?");
    $stmt->bind_param('ss', $monday, $today);
    $stmt->execute();
    $mcRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT ds.shift_date,
        COALESCE((SELECT SUM(total) FROM orders WHERE status!='cancelled' AND created_at >= CONCAT(ds.shift_date,' 15:00:00') AND created_at < DATE_ADD(CONCAT(ds.shift_date,' 15:00:00'), INTERVAL 24 HOUR)),0) as sales,
        COALESCE((SELECT SUM(total_cost) FROM shift_materials WHERE shift_id=ds.id),0) as material_cost,
        COALESCE((SELECT COUNT(*) FROM orders WHERE status!='cancelled' AND created_at >= CONCAT(ds.shift_date,' 15:00:00') AND created_at < DATE_ADD(CONCAT(ds.shift_date,' 15:00:00'), INTERVAL 24 HOUR)),0) as orders_count
        FROM daily_shifts ds WHERE ds.shift_date >= ? AND ds.shift_date <= ? ORDER BY ds.shift_date ASC");
    $stmt->bind_param('ss', $monday, $today);
    $stmt->execute();
    $daysRes = $stmt->get_result();
    $days = [];
    while ($d = $daysRes->fetch_assoc()) {
        $d['sales'] = floatval($d['sales']); $d['material_cost'] = floatval($d['material_cost']); $d['orders_count'] = intval($d['orders_count']); $days[] = $d;
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT ds.shift_date, sm.item_name, sm.quantity, sm.unit, sm.total_cost FROM shift_materials sm JOIN daily_shifts ds ON sm.shift_id=ds.id WHERE ds.shift_date >= ? AND ds.shift_date <= ? ORDER BY ds.shift_date ASC, sm.id ASC");
    $stmt->bind_param('ss', $monday, $today);
    $stmt->execute();
    $matsRes = $stmt->get_result();
    $materials = [];
    while ($m = $matsRes->fetch_assoc()) { $m['total_cost'] = floatval($m['total_cost']); $materials[] = $m; }
    $stmt->close();

    echo json_encode(['success'=>true,'from_date'=>$monday,'to_date'=>$today,'sales'=>floatval($sRow['sales']),'orders_count'=>intval($sRow['cnt']),'material_cost'=>floatval($mcRow['mc']),'days'=>$days,'materials'=>$materials]);
    exit;
}

elseif ($action === 'get_custom_report') {
    if (!$isAdmin) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

    $fromDate = trim($_GET['from_date'] ?? '');
    $toDate   = trim($_GET['to_date']   ?? '');
    if (!$fromDate || !$toDate) { echo json_encode(['success'=>false,'message'=>'Both dates required']); exit; }

    $fromDt = $fromDate . ' 00:00:00';
    $toDt   = $toDate   . ' 23:59:59';

    $stmt = $conn->prepare("SELECT COALESCE(SUM(total),0) as sales, COUNT(*) as cnt FROM orders WHERE status != 'cancelled' AND created_at >= ? AND created_at <= ?");
    $stmt->bind_param('ss', $fromDt, $toDt);
    $stmt->execute();
    $sRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT ds.shift_date, sm.item_name, sm.quantity, sm.unit, sm.total_cost FROM shift_materials sm JOIN daily_shifts ds ON sm.shift_id=ds.id WHERE ds.shift_date >= ? AND ds.shift_date <= ? ORDER BY ds.shift_date ASC, sm.id ASC");
    $stmt->bind_param('ss', $fromDate, $toDate);
    $stmt->execute();
    $matsRes = $stmt->get_result();
    $materials = []; $matTotal = 0;
    while ($m = $matsRes->fetch_assoc()) { $m['total_cost'] = floatval($m['total_cost']); $matTotal += $m['total_cost']; $materials[] = $m; }
    $stmt->close();

    echo json_encode(['success'=>true,'from_date'=>$fromDate,'to_date'=>$toDate,'sales'=>floatval($sRow['sales']),'orders_count'=>intval($sRow['cnt']),'material_cost'=>$matTotal,'materials'=>$materials]);
    exit;
}

elseif ($action === 'get_shift_history') {
    if (!$isAdmin) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

    $conn->query("CREATE TABLE IF NOT EXISTS daily_shifts (id INT AUTO_INCREMENT PRIMARY KEY, shift_date DATE NOT NULL UNIQUE, notes TEXT DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $result = $conn->query("SELECT ds.id, ds.shift_date,
        COALESCE((SELECT SUM(total) FROM orders WHERE created_at >= CONCAT(ds.shift_date,' 15:00:00') AND created_at < DATE_ADD(CONCAT(ds.shift_date,' 15:00:00'), INTERVAL 24 HOUR) AND status != 'cancelled'),0) as sales,
        COALESCE((SELECT SUM(total_cost) FROM shift_materials WHERE shift_id = ds.id),0) as material_cost,
        COALESCE((SELECT COUNT(*) FROM orders WHERE created_at >= CONCAT(ds.shift_date,' 15:00:00') AND created_at < DATE_ADD(CONCAT(ds.shift_date,' 15:00:00'), INTERVAL 24 HOUR) AND status != 'cancelled'),0) as orders_count
        FROM daily_shifts ds ORDER BY ds.shift_date DESC LIMIT 30");

    $shifts = [];
    while ($row = $result->fetch_assoc()) {
        $row['sales']         = floatval($row['sales']);
        $row['material_cost'] = floatval($row['material_cost']);
        $row['orders_count']  = intval($row['orders_count']);
        $shifts[] = $row;
    }
    echo json_encode(['success' => true, 'shifts' => $shifts]);
    exit;
}

elseif ($action === 'save_pos_order') {
    $name       = trim($_POST['name']       ?? 'Walk-in');
    $phone      = trim($_POST['phone']      ?? '');
    $items_raw  = $_POST['items']           ?? '[]';
    $total      = floatval($_POST['total']  ?? 0);
    $discount   = floatval($_POST['discount'] ?? 0);
    $order_type = trim($_POST['order_type'] ?? 'Dine-in');
    $notes      = trim($_POST['notes']      ?? '');

    // Validate items JSON
    $items_decoded = json_decode($items_raw, true);
    if (!is_array($items_decoded) || empty($items_decoded)) {
        echo json_encode(['success' => false, 'message' => 'No items in order']);
        exit;
    }

    $address = ($order_type === 'Delivery') ? 'Delivery' : $order_type;

    $stmt = $conn->prepare(
        "INSERT INTO orders (name, phone, address, items, total, notes, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 'confirmed', NOW())"
    );
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'DB prepare error']);
        exit;
    }
    $stmt->bind_param('ssssds', $name, $phone, $address, $items_raw, $total, $notes);
    if ($stmt->execute()) {
        $order_id = $conn->insert_id;
        echo json_encode(['success' => true, 'order_id' => $order_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// ── Get pending orders for POS ──────────────────────────────
elseif ($action === 'get_pending_orders') {
    $stmt = $conn->prepare("SELECT id, name, phone, address, items, total, notes, created_at FROM orders WHERE status = 'pending' AND DATE(created_at) = CURDATE() ORDER BY created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'orders' => $orders]);
    exit;
}

// ── Get order details for POS ──────────────────────────────
elseif ($action === 'get_order_details') {
    $order_id = intval($_GET['order_id'] ?? 0);
    if (!$order_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        exit;
    }
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    echo json_encode(['success' => true, 'order' => $order]);
    exit;
}

// ── Get bill history (all orders from today) ──────────────────
elseif ($action === 'get_bill_history') {
    $stmt = $conn->prepare("SELECT id, name, phone, address, items, total, notes, token_number, status, created_at FROM orders WHERE DATE(created_at) = CURDATE() ORDER BY created_at ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'orders' => $orders]);
    exit;
}

// -- Get Profit Data ----------------------------------------------
elseif ($action === 'get_profit_data') {
    $filter = $_GET['filter'] ?? 'today';
    $dateCondition = "DATE(o.created_at) = CURDATE()";
    switch($filter) {
        case 'yesterday': $dateCondition = "DATE(o.created_at) = CURDATE() - INTERVAL 1 DAY"; break;
        case '7days': $dateCondition = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"; break;
        case '30days': $dateCondition = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"; break;
        case '90days': $dateCondition = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)"; break;
        case 'week': $dateCondition = "YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)"; break;
        case 'month': $dateCondition = "YEAR(o.created_at) = YEAR(CURDATE()) AND MONTH(o.created_at) = MONTH(CURDATE())"; break;
        case 'all': $dateCondition = "1=1"; break;
    }
    $tablesExist = false;
    $checkTables = $conn->query("SHOW TABLES LIKE 'raw_item_materials'");
    if ($checkTables && $checkTables->num_rows > 0) { $tablesExist = true; }
    $query = "SELECT o.id, o.name as customer_name, o.phone, o.total as revenue, o.items, o.created_at, o.status FROM orders o WHERE $dateCondition AND o.status IN ('confirmed', 'dispatched') ORDER BY o.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = []; $totalRevenue = 0; $totalCost = 0;
    while ($order = $result->fetch_assoc()) {
        $items = json_decode($order['items'], true) ?: [];
        $itemCount = array_reduce($items, function($sum, $item) { return $sum + ($item['qty'] ?? 0); }, 0);
        $orderCost = 0;
        if ($tablesExist) {
            foreach ($items as $item) {
                $itemId = $item['id'] ?? 0; $qty = $item['qty'] ?? 0;
                $costStmt = $conn->prepare("SELECT SUM(rm.price_per_unit * rim.quantity) as item_cost FROM raw_item_materials rim JOIN raw_materials rm ON rim.material_id = rm.id WHERE rim.item_id = ?");
                $costStmt->bind_param('i', $itemId);
                $costStmt->execute();
                $costResult = $costStmt->get_result();
                $costRow = $costResult->fetch_assoc();
                $itemCost = $costRow['item_cost'] ?? 0;
                $orderCost += $itemCost * $qty;
                $costStmt->close();
            }
        } else { $orderCost = $order['revenue'] * 0.40; }
        $orders[] = ['id' => $order['id'], 'customer_name' => $order['customer_name'], 'phone' => $order['phone'], 'revenue' => floatval($order['revenue']), 'cost' => floatval($orderCost), 'item_count' => $itemCount, 'created_at' => $order['created_at']];
        $totalRevenue += floatval($order['revenue']);
        $totalCost += floatval($orderCost);
    }
    $netProfit = $totalRevenue - $totalCost;
    echo json_encode(['success' => true, 'summary' => ['total_revenue' => $totalRevenue, 'total_cost' => $totalCost, 'net_profit' => $netProfit, 'total_orders' => count($orders)], 'orders' => $orders, 'using_estimates' => !$tablesExist]);
    exit;
}

// ── Suppliers Management ─────────────────────────────────────────
elseif ($action === 'add_raw_purchase') {
    // Add raw material purchase for profit tracking
    if (!$isAdmin && !$isReceptionist) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $material_name = trim($_POST['material_name'] ?? '');
    $quantity = floatval($_POST['quantity'] ?? 0);
    $unit = trim($_POST['unit'] ?? 'kg');
    $price_per_unit = floatval($_POST['price_per_unit'] ?? 0);
    $purchase_date = trim($_POST['purchase_date'] ?? date('Y-m-d'));
    $supplier_id = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : NULL;
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if (empty($material_name)) {
        echo json_encode(['success' => false, 'message' => 'Material name is required']);
        exit;
    }
    if ($quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity must be greater than 0']);
        exit;
    }
    if ($price_per_unit < 0) {
        echo json_encode(['success' => false, 'message' => 'Price cannot be negative']);
        exit;
    }
    
    $total_cost = $quantity * $price_per_unit;
    
    // Create table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS raw_material_purchases (
        id INT(11) NOT NULL AUTO_INCREMENT,
        material_name VARCHAR(150) NOT NULL,
        quantity DECIMAL(10,2) NOT NULL,
        unit VARCHAR(20) NOT NULL DEFAULT 'kg',
        price_per_unit DECIMAL(10,2) NOT NULL,
        total_cost DECIMAL(10,2) NOT NULL,
        purchase_date DATE NOT NULL,
        supplier_id INT(11) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY purchase_date (purchase_date),
        KEY supplier_id (supplier_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $stmt = $conn->prepare("INSERT INTO raw_material_purchases 
        (material_name, quantity, unit, price_per_unit, total_cost, purchase_date, supplier_id, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sdsddsss', $material_name, $quantity, $unit, $price_per_unit, $total_cost, $purchase_date, $supplier_id, $notes);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Purchase recorded successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to record purchase']);
    }
    $stmt->close();
    exit;
}

elseif ($action === 'get_profit_summary') {
    // Get profit summary for date range
    if (!$isAdmin && !$isReceptionist) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $filter = $_GET['filter'] ?? 'today';
    $from_date = $_GET['from_date'] ?? '';
    $to_date = $_GET['to_date'] ?? '';
    
    // Calculate date range
    $today = date('Y-m-d');
    switch($filter) {
        case 'today':
            $from_date = $today;
            $to_date = $today;
            break;
        case 'week':
            $from_date = date('Y-m-d', strtotime('monday this week'));
            $to_date = $today;
            break;
        case 'month':
            $from_date = date('Y-m-01');
            $to_date = $today;
            break;
        case 'custom':
            // Use provided dates, validate format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
                echo json_encode(['success' => false, 'message' => 'Invalid date format']);
                exit;
            }
            break;
        default:
            $from_date = $today;
            $to_date = $today;
    }
    
    // Get total sales (completed orders only)
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total), 0) as total_sales, COUNT(*) as order_count 
        FROM orders 
        WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? 
        AND status IN ('confirmed', 'dispatched')");
    $stmt->bind_param('ss', $from_date, $to_date);
    $stmt->execute();
    $sales_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $total_sales = floatval($sales_result['total_sales']);
    $order_count = intval($sales_result['order_count']);
    
    // Get raw material costs
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_cost), 0) as total_cost 
        FROM raw_material_purchases 
        WHERE purchase_date >= ? AND purchase_date <= ?");
    $stmt->bind_param('ss', $from_date, $to_date);
    $stmt->execute();
    $cost_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $total_raw_cost = floatval($cost_result['total_cost']);
    $profit = $total_sales - $total_raw_cost;
    $profit_margin = $total_sales > 0 ? ($profit / $total_sales) * 100 : 0;
    
    echo json_encode([
        'success' => true,
        'from_date' => $from_date,
        'to_date' => $to_date,
        'total_sales' => $total_sales,
        'total_raw_cost' => $total_raw_cost,
        'profit' => $profit,
        'profit_margin' => round($profit_margin, 2),
        'order_count' => $order_count
    ]);
    exit;
}

elseif ($action === 'get_profit_daily_breakdown') {
    // Get daily breakdown table
    if (!$isAdmin && !$isReceptionist) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $from_date = $_GET['from_date'] ?? date('Y-m-d');
    $to_date = $_GET['to_date'] ?? date('Y-m-d');
    
    // Validate dates
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit;
    }
    
    // Generate all dates in range
    $period = new DatePeriod(
        new DateTime($from_date),
        new DateInterval('P1D'),
        (new DateTime($to_date))->modify('+1 day')
    );
    
    $daily_data = [];
    foreach ($period as $date) {
        $day = $date->format('Y-m-d');
        
        // Get sales for this day
        $stmt = $conn->prepare("SELECT COALESCE(SUM(total), 0) as sales 
            FROM orders 
            WHERE DATE(created_at) = ? AND status IN ('confirmed', 'dispatched')");
        $stmt->bind_param('s', $day);
        $stmt->execute();
        $sales = floatval($stmt->get_result()->fetch_assoc()['sales']);
        $stmt->close();
        
        // Get raw material cost for this day
        $stmt = $conn->prepare("SELECT COALESCE(SUM(total_cost), 0) as cost 
            FROM raw_material_purchases 
            WHERE purchase_date = ?");
        $stmt->bind_param('s', $day);
        $stmt->execute();
        $cost = floatval($stmt->get_result()->fetch_assoc()['cost']);
        $stmt->close();
        
        $profit = $sales - $cost;
        
        $daily_data[] = [
            'date' => $day,
            'sales' => $sales,
            'raw_cost' => $cost,
            'profit' => $profit
        ];
    }
    
    echo json_encode(['success' => true, 'daily_data' => $daily_data]);
    exit;
}

elseif ($action === 'get_raw_purchases') {
    // Get list of raw material purchases
    if (!$isAdmin && !$isReceptionist) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $from_date = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $to_date = $_GET['to_date'] ?? date('Y-m-d');
    
    $stmt = $conn->prepare("SELECT rmp.*, s.name as supplier_name 
        FROM raw_material_purchases rmp 
        LEFT JOIN suppliers s ON rmp.supplier_id = s.id 
        WHERE rmp.purchase_date >= ? AND rmp.purchase_date <= ? 
        ORDER BY rmp.purchase_date DESC, rmp.id DESC");
    $stmt->bind_param('ss', $from_date, $to_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $purchases = [];
    while ($row = $result->fetch_assoc()) {
        $purchases[] = $row;
    }
    $stmt->close();
    
    echo json_encode(['success' => true, 'purchases' => $purchases]);
    exit;
}

elseif ($action === 'delete_raw_purchase') {
    // Delete a raw material purchase
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid purchase ID']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM raw_material_purchases WHERE id = ?");
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Purchase deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete']);
    }
    $stmt->close();
    exit;
}

elseif ($action === 'get_suppliers') {
    // Create table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        location VARCHAR(200) DEFAULT '',
        items_supplied TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $result = $conn->query("SELECT * FROM suppliers ORDER BY name ASC");
    $suppliers = [];
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
    echo json_encode(['success' => true, 'suppliers' => $suppliers]);
    exit;
}

elseif ($action === 'add_supplier') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $items_supplied = trim($_POST['items_supplied'] ?? '');
    
    if (!$name || !$phone) {
        echo json_encode(['success' => false, 'message' => 'Name and phone are required']);
        exit;
    }
    
    // Create table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        location VARCHAR(200) DEFAULT '',
        items_supplied TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $stmt = $conn->prepare("INSERT INTO suppliers (name, phone, location, items_supplied) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $name, $phone, $location, $items_supplied);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Supplier added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add supplier']);
    }
    $stmt->close();
    exit;
}

elseif ($action === 'delete_supplier') {
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid supplier ID']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Supplier deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete supplier']);
    }
    $stmt->close();
    exit;
}

else {
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    $conn->close();


// ── Get Profit Data ──────────────────────────────────────────────


// NOTE: Profit analysis endpoint added above the else block - check around line 1517
