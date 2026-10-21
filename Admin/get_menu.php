<?php
require 'config.php';
setCORS();

// Cache headers — 2 min browser cache
header('Cache-Control: public, max-age=120, stale-while-revalidate=30');

$conn = getDB();

// Ensure schema is up to date (idempotent)
$conn->query("CREATE TABLE IF NOT EXISTS menu_items (
    id          INT            AUTO_INCREMENT PRIMARY KEY,
    category    VARCHAR(50)    NOT NULL,
    title       VARCHAR(150)   NOT NULL,
    description TEXT           NOT NULL,
    price       DECIMAL(10,2)  NOT NULL,
    image_path  VARCHAR(255)   NOT NULL DEFAULT '',
    tag         VARCHAR(50)    NOT NULL DEFAULT '',
    featured    TINYINT(1)     NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("ALTER TABLE menu_items ADD COLUMN IF NOT EXISTS tag      VARCHAR(50)  NOT NULL DEFAULT ''");
$conn->query("ALTER TABLE menu_items ADD COLUMN IF NOT EXISTS featured TINYINT(1)   NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE menu_items ADD COLUMN IF NOT EXISTS discount_percent INT NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE menu_items ADD COLUMN IF NOT EXISTS in_stock TINYINT(1) NOT NULL DEFAULT 1");

// Ensure categories table exists with banner_image column
$conn->query("CREATE TABLE IF NOT EXISTS categories (
    id           INT           AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100)  NOT NULL,
    slug         VARCHAR(100)  NOT NULL UNIQUE,
    banner_image VARCHAR(255)  DEFAULT '',
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS banner_image VARCHAR(255) DEFAULT ''");

// ── Fetch categories with banners ─────────────────────────
$catRes = $conn->query("SELECT id, name, slug, banner_image FROM categories ORDER BY name ASC");
$categoriesMap = []; // slug => category row
if ($catRes) {
    while ($c = $catRes->fetch_assoc()) {
        $categoriesMap[$c['slug']] = $c;
    }
}

// ── Query filters ─────────────────────────────────────────
$conditions = [];
$bindTypes  = '';
$bindValues = [];

if (!empty($_GET['featured'])) {
    $conditions[] = "featured = 1";
}
if (!empty($_GET['category'])) {
    $conditions[] = "category = ?";
    $bindTypes  .= 's';
    $bindValues[] = $_GET['category'];
}

$sql = "SELECT id, category, title, description,
               CAST(price AS DECIMAL(10,2)) AS price,
               image_path, tag, featured, discount_percent, in_stock, created_at
        FROM menu_items";
if ($conditions) $sql .= " WHERE " . implode(" AND ", $conditions);
$sql .= " ORDER BY category ASC, price ASC";

if ($bindValues) {
    $stmt2 = $conn->prepare($sql);
    $stmt2->bind_param($bindTypes, ...$bindValues);
    $stmt2->execute();
    $result = $stmt2->get_result();
    $stmt2->close();
} else {
    $result = $conn->query($sql);
}
if (!$result) {
    echo json_encode(['success' => false, 'message' => $conn->error, 'items' => [], 'categories' => []]);
    $conn->close();
    exit;
}
$items  = [];
while ($row = $result->fetch_assoc()) {
    $row['price']    = (float) $row['price'];
    $row['featured'] = (bool)  $row['featured'];
    $row['in_stock'] = (bool)  ($row['in_stock'] ?? 1);
    $row['discount_percent'] = (int) ($row['discount_percent'] ?? 0);
    $row['addons']   = [];
    $items[] = $row;
}

// ── Fetch addons for all items ────────────────────────────
$addonCheck = $conn->query("SHOW TABLES LIKE 'menu_item_addons'");
if ($addonCheck && $addonCheck->num_rows > 0) {
    $addonRes = $conn->query("SELECT id, item_id, name, price FROM menu_item_addons ORDER BY item_id ASC, id ASC");
    if ($addonRes) {
        $addonMap = [];
        while ($a = $addonRes->fetch_assoc()) {
            $a['price'] = (float)$a['price'];
            $addonMap[(int)$a['item_id']][] = $a;
        }
        foreach ($items as &$item) {
            $item['addons'] = $addonMap[(int)$item['id']] ?? [];
        }
        unset($item);
    }
}

// ── Build grouped-by-category structure ──────────────────
$grouped = [];
foreach ($items as $item) {
    $slug = $item['category'];
    if (!isset($grouped[$slug])) {
        $catInfo = $categoriesMap[$slug] ?? [
            'id'           => 0,
            'name'         => ucfirst(str_replace('_', ' ', $slug)),
            'slug'         => $slug,
            'banner_image' => '',
        ];
        $grouped[$slug] = [
            'id'           => (int) $catInfo['id'],
            'name'         => $catInfo['name'],
            'slug'         => $slug,
            'banner_image' => $catInfo['banner_image'] ?? '',
            'items'        => [],
        ];
    }
    $grouped[$slug]['items'][] = $item;
}

echo json_encode([
    'success'    => true,
    'items'      => $items,
    'categories' => array_values($grouped),
]);
$conn->close();
