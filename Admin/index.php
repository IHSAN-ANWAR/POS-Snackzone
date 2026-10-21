    <?php
// Security: Disable error display in production
$isProduction = !in_array($_SERVER['HTTP_HOST'] ?? 'localhost', ['localhost', '127.0.0.1', '::1']);
if ($isProduction) {
    ini_set('display_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

session_start();
if (!isset($_SESSION['snackzone_admin'])) {
    header('Location: login.php');
    exit;
}

require 'config.php';
$conn = getDB();
$showChefStores = false;

// Create tables
$conn->query("CREATE TABLE IF NOT EXISTS menu_items (id INT AUTO_INCREMENT PRIMARY KEY, category VARCHAR(50) NOT NULL, title VARCHAR(150) NOT NULL, description TEXT NOT NULL, price DECIMAL(10,2) NOT NULL, image_path VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("ALTER TABLE menu_items ADD COLUMN IF NOT EXISTS discount_percent INT DEFAULT 0");
$conn->query("CREATE TABLE IF NOT EXISTS contact_messages (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT '', email VARCHAR(150) DEFAULT '', subject VARCHAR(100) NOT NULL, message TEXT NOT NULL, is_read TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, slug VARCHAR(100) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS notices (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, body TEXT NOT NULL, image_path VARCHAR(255) DEFAULT '', is_active TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS event_bookings (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, phone VARCHAR(20) NOT NULL, email VARCHAR(150) DEFAULT '', event_date DATE NOT NULL, event_time VARCHAR(20) NOT NULL, guests INT NOT NULL, menu_items TEXT NOT NULL, notes TEXT DEFAULT '', is_read TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS orders (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, phone VARCHAR(20) NOT NULL, address TEXT NOT NULL, items TEXT NOT NULL, total DECIMAL(10,2) DEFAULT 0, notes TEXT DEFAULT '', status ENUM('pending','confirmed','dispatched','cancelled') NOT NULL DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if ($showChefStores) {
    $conn->query("CREATE TABLE IF NOT EXISTS vendors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  cnic VARCHAR(20) NOT NULL UNIQUE,
  profile_image VARCHAR(255) DEFAULT '',
  status ENUM('pending','approved','rejected','suspended') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("CREATE TABLE IF NOT EXISTS vendor_stores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  store_name VARCHAR(200) NOT NULL,
  store_description TEXT DEFAULT '',
  store_address TEXT DEFAULT '',
  store_logo VARCHAR(255) DEFAULT '',
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY vendor_id (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS notes  TEXT          DEFAULT ''");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS total  DECIMAL(10,2) DEFAULT 0");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS prep_time VARCHAR(50) DEFAULT ''");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS token_number INT DEFAULT NULL");
// Ensure status column uses correct ENUM values (fixes old installs with VARCHAR or wrong ENUM)
$conn->query("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','confirmed','dispatched','cancelled') NOT NULL DEFAULT 'pending'");
// Fix any blank status records
$conn->query("UPDATE orders SET status='confirmed' WHERE (status='' OR status IS NULL) AND prep_time != ''");
$conn->query("UPDATE orders SET status='pending' WHERE status='' OR status IS NULL");

// Fetch data
$menuItems      = $conn->query("SELECT * FROM menu_items ORDER BY category ASC, price ASC");

// Build POS menu array (injected directly into JS � no fetch needed)
$menuItemsForPOS = [];
if ($menuItems && $menuItems->num_rows > 0) {
    $menuItems->data_seek(0);
    while ($row = $menuItems->fetch_assoc()) {
        $menuItemsForPOS[] = [
            'id'         => (int)$row['id'],
            'title'      => $row['title'],
            'category'   => $row['category'],
            'price'      => (float)$row['price'],
            'image_path' => $row['image_path'] ?? '',
        ];
    }
    $menuItems->data_seek(0); // reset for later use
}
$categoriesRes  = $conn->query("SELECT * FROM categories ORDER BY name ASC");
$categoriesList = [];
while ($cat = $categoriesRes->fetch_assoc()) $categoriesList[] = $cat;

$messages    = $conn->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
$unreadRes   = $conn->query("SELECT COUNT(*) as cnt FROM contact_messages WHERE is_read=0");
$unread      = $unreadRes->fetch_assoc()['cnt'];

$bookings       = $conn->query("SELECT * FROM event_bookings ORDER BY created_at DESC");
$unreadBookings = $conn->query("SELECT COUNT(*) as cnt FROM event_bookings WHERE is_read=0")->fetch_assoc()['cnt'];


$orders       = $conn->query("SELECT * FROM orders WHERE DATE(created_at) = CURDATE() ORDER BY created_at DESC");
$pendingCount = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='pending' AND DATE(created_at) = CURDATE()")->fetch_assoc()['c'];

// Users
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    phone          VARCHAR(20)  NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    email_verified TINYINT(1)   DEFAULT 0,
    email_otp      VARCHAR(6)   DEFAULT '',
    otp_expires    DATETIME     DEFAULT NULL,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
)");
$users      = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
$totalUsers = $users->num_rows;
$verifiedUsers = $conn->query("SELECT COUNT(*) as c FROM users WHERE email_verified=1")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Panel - Snack Zone</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  
  <!-- Inject POS menu data as global variable -->
  <script>
    window.__posMenuData = <?php echo json_encode($menuItemsForPOS); ?>;
  </script>
  
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    :root {
      --black: #000; --white: #fff;
      --gray-100: #f5f5f5; --gray-200: #eee; --gray-300: #d9d9d9;
      --gray-500: #888; --gray-700: #444;
      --green: #22c55e;
    }
    body { font-family:'Inter',sans-serif; background:#f8f9fa; color:#1a1a1a; overflow-x:hidden; }

    /* Token Modal Animations */
    @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
    @keyframes fadeOut { from { opacity:1; } to { opacity:0; } }
    @keyframes slideUp { from { transform:translateY(30px); opacity:0; } to { transform:translateY(0); opacity:1; } }

    /* Mobile Responsive POS */
    @media (min-width: 769px) {
      /* Desktop - ensure everything is visible */
      #mobile-cart-toggle { display: none !important; }
      #mobile-cart-close { display: none !important; }
    }
    
    /* Desktop: ensure cart section is visible and positioned correctly */
    @media (min-width: 769px) {
      #pos-cart-section {
        position: static !important;
        right: auto !important;
      }
      #mobile-cart-toggle { display: none !important; }
      #mobile-cart-header { display: none !important; }
    }
    
    @media (max-width: 768px) {
      #pos-container { flex-direction: column !important; }
      #pos-menu-section { flex: 1 !important; border-right: none !important; }
      #pos-cart-section { 
        position: fixed !important;
        top: 0 !important;
        right: -100% !important;
        width: 100% !important;
        height: 100vh !important;
        flex: none !important;
        z-index: 999 !important;
        transition: right 0.3s ease !important;
        box-shadow: -4px 0 24px rgba(0,0,0,0.2) !important;
      }
      #pos-cart-section.mobile-open {
        right: 0 !important;
      }
      #mobile-cart-toggle { display: flex !important; align-items: center; justify-content: center; }
      #mobile-cart-header { display: flex !important; }
      #pos-grid { 
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)) !important; 
        padding: 12px !important;
        gap: 12px !important;
      }
    }

    @media (max-width: 480px) {
      #pos-grid { 
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)) !important;
        padding: 10px !important;
      }
      .panel { padding: 0 !important; }
    }

    /* SIDEBAR */
    .sidebar {
      position:fixed; top:0; left:0; width:260px; height:100vh;
      background:linear-gradient(180deg, #1a1a1a 0%, #0a0a0a 100%);
      display:flex; flex-direction:column; z-index:100;
      border-right:1px solid rgba(255,255,255,0.08);
      box-shadow:4px 0 20px rgba(0,0,0,0.15);
    }
    .sidebar-logo {
      display:flex; align-items:center; gap:0.8rem;
      padding:1.6rem 1.5rem;
      border-bottom:1px solid rgba(255,255,255,0.08);
      text-decoration:none; transition:transform 0.2s;
      background:rgba(255,255,255,0.02);
    }
    .sidebar-logo:hover { transform:translateX(4px); }
    .sidebar-logo-icon {
      width:36px; height:36px; 
      background:linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      display:flex; align-items:center; justify-content:center;
      font-size:1.1rem; color:#fff; flex-shrink:0;
      border-radius:8px;
      box-shadow:0 4px 12px rgba(59,130,246,0.3);
    }
    .sidebar-logo-text { font-size:1.1rem; font-weight:900; color:#fff; text-transform:uppercase; letter-spacing:-0.02em; line-height:1.1; }
    .sidebar-logo-text small { display:block; font-size:0.65rem; font-weight:600; letter-spacing:0.12em; color:rgba(255,255,255,0.4); margin-top:2px; }
    .sidebar-nav { display:flex; flex-direction:column; padding:1rem 0.75rem; gap:4px; flex:1; overflow-y:auto; }
    .nav-btn {
      display:flex; align-items:center; gap:0.85rem; padding:0.75rem 1rem;
      color:rgba(255,255,255,0.6); font-size:0.85rem; font-weight:600;
      cursor:pointer; border:none; background:transparent; width:100%; text-align:left;
      transition:all 0.2s ease; font-family:'Inter',sans-serif;
      border-radius:10px;
    }
    .nav-btn:hover { background:rgba(255,255,255,0.08); color:#fff; transform:translateX(4px); }
    .nav-btn.active { 
      background:linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color:#fff;
      box-shadow:0 4px 12px rgba(59,130,246,0.3);
    }
    .nav-btn i { width:20px; text-align:center; font-size:1rem; opacity:0.9; }
    .badge {
      margin-left:auto; background:#ef4444; color:#fff;
      font-size:0.7rem; font-weight:700; padding:2px 8px;
      border-radius:12px;
      box-shadow:0 2px 4px rgba(239,68,68,0.3);
    }
    .nav-btn.active .badge { background:rgba(255,255,255,0.2); color:#fff; }
    
    /* VENDOR STATUS BADGES */
    .vendor-badge {
      display:inline-flex;
      align-items:center;
      gap:0.4rem;
      padding:0.4rem 0.8rem;
      border-radius:8px;
      font-size:0.75rem;
      font-weight:700;
      text-transform:uppercase;
      letter-spacing:0.03em;
      font-family:'Inter',sans-serif;
    }
    .vendor-badge-pending {
      background:#fef3c7;
      color:#92400e;
      border:1px solid #fbbf24;
    }
    .vendor-badge-approved {
      background:#d1fae5;
      color:#065f46;
      border:1px solid #10b981;
    }
    .vendor-badge-rejected {
      background:#fee2e2;
      color:#991b1b;
      border:1px solid #ef4444;
    }
    .vendor-badge-suspended {
      background:#f3f4f6;
      color:#374151;
      border:1px solid #9ca3af;
    }
    
    .sidebar-footer { padding:1rem 0.75rem; border-top:1px solid rgba(255,255,255,0.08); }

    /* MAIN */
    .main { margin-left:240px; padding:1.8rem; min-height:100vh; }

    /* TOP BAR */
    .topbar {
      display:flex; align-items:center; justify-content:space-between;
      margin-bottom:2rem; background:#fff; padding:1.2rem 1.6rem;
      border:1px solid rgba(0,0,0,0.06);
      border-radius:16px;
      box-shadow:0 2px 8px rgba(0,0,0,0.04);
    }
    .topbar h1 { 
      font-size:1.1rem; 
      font-weight:900; 
      text-transform:uppercase; 
      letter-spacing:0.04em; 
      color:#1a1a1a;
      display:flex;
      align-items:center;
      gap:0.5rem;
    }
    .topbar h1::before {
      content:'';
      width:4px;
      height:24px;
      background:linear-gradient(180deg, #3b82f6, #2563eb);
      border-radius:2px;
    }
    .topbar p  { 
      font-size:0.75rem; 
      color:#6b7280; 
      margin-top:4px; 
      font-weight:500;
    }
    .topbar-right { 
      font-size:0.75rem; 
      font-weight:700; 
      letter-spacing:0.06em; 
      text-transform:uppercase; 
      color:#6b7280; 
      display:flex;
      align-items:center;
      gap:0.5rem;
      background:#f9fafb;
      padding:0.6rem 1rem;
      border-radius:8px;
    }
    .topbar-right strong { color:#1a1a1a; }

    /* STATS - Modern Cards */
    .stats { display:grid; grid-template-columns:repeat(5,1fr); gap:1.2rem; margin-bottom:2rem; }
    .stat-card { 
      background:#fff; 
      border:1px solid rgba(0,0,0,0.06);
      border-radius:16px;
      padding:1.5rem; 
      display:flex; 
      align-items:center; 
      gap:1.2rem;
      box-shadow:0 1px 3px rgba(0,0,0,0.06);
      transition:all 0.2s;
    }
    .stat-card:hover {
      box-shadow:0 4px 12px rgba(0,0,0,0.08);
      transform:translateY(-2px);
    }
    .stat-icon { 
      width:50px; 
      height:50px; 
      background:linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      display:flex; 
      align-items:center; 
      justify-content:center; 
      font-size:1.3rem; 
      flex-shrink:0; 
      color:#fff;
      border-radius:12px;
      box-shadow:0 4px 12px rgba(59,130,246,0.25);
    }
    /* Stat card color variations */
    .stat-card:nth-child(1) .stat-icon { background:linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%); box-shadow:0 4px 12px rgba(0,0,0,0.2); }
    .stat-card:nth-child(2) .stat-icon { background:linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); box-shadow:0 4px 12px rgba(59,130,246,0.3); }
    .stat-card:nth-child(3) .stat-icon { background:linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow:0 4px 12px rgba(16,185,129,0.3); }
    .stat-card:nth-child(4) .stat-icon { background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%); box-shadow:0 4px 12px rgba(245,158,11,0.3); }
    .stat-card:nth-child(5) .stat-icon { background:linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); box-shadow:0 4px 12px rgba(99,102,241,0.3); }
    
    .stat-num  { font-size:2rem; font-weight:900; line-height:1; color:#1a1a1a; }
    .stat-label{ font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#6b7280; margin-top:4px; }

    /* PANEL */
    .panel { display:none; }
    .panel.active { display:block; }

    /* CARD */
    .card { 
      background:#fff; 
      border:1px solid rgba(0,0,0,0.08); 
      padding:1.8rem; 
      margin-bottom:1.2rem; 
      border-radius:16px;
      box-shadow:0 1px 3px rgba(0,0,0,0.06);
      transition:all 0.3s;
    }
    .card:hover {
      box-shadow:0 4px 16px rgba(0,0,0,0.1);
    }
    .card-title {
      font-size:0.85rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em;
      margin-bottom:1.6rem; padding-bottom:1rem; 
      border-bottom:2px solid rgba(0,0,0,0.08);
      display:flex; align-items:center; gap:0.6rem; color:#1a1a1a;
      position:relative;
    }
    .card-title::after {
      content:'';
      position:absolute;
      bottom:-2px;
      left:0;
      width:60px;
      height:2px;
      background:linear-gradient(90deg, #3b82f6, #2563eb);
    }
    .card-title i {
      color:#3b82f6;
      font-size:1.1rem;
    }

    /* FORM - Modern Rounded Inputs */
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.4rem; }
    .form-group { display:flex; flex-direction:column; gap:0.5rem; }
    .form-group.full { grid-column:1/-1; }
    .form-group label { 
      font-size:0.75rem; 
      font-weight:700; 
      text-transform:uppercase; 
      letter-spacing:0.08em; 
      color:#374151;
      display:flex;
      align-items:center;
      gap:0.3rem;
    }
    .form-group input, 
    .form-group select, 
    .form-group textarea {
      padding:0.9rem 1.2rem; 
      border:2px solid rgba(0,0,0,0.08);
      border-radius:12px;
      font-family:'Inter',sans-serif; 
      font-size:0.9rem;
      background:#fff;
      transition:all 0.2s;
      outline:none;
    }
    .form-group input:hover,
    .form-group select:hover,
    .form-group textarea:hover {
      border-color:rgba(0,0,0,0.15);
    }
    .form-group input:focus, 
    .form-group select:focus, 
    .form-group textarea:focus {
      border-color:#3b82f6;
      box-shadow:0 0 0 4px rgba(59,130,246,0.1);
    }
    .form-group textarea { resize:vertical; min-height:110px; }
    .form-group input[type="file"] { 
      padding:0.75rem 1rem;
      cursor:pointer;
      border-style:dashed;
    }
    .form-group input[type="file"]:hover {
      background:#f8f9fa;
      border-color:#3b82f6;
    }
      border-radius:0; font-family:'Inter',sans-serif;
      font-size:0.88rem; outline:none; transition:border-color 0.2s; background:#fff;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:#000; }
    .form-group textarea { resize:vertical; }

    .img-preview-wrap {
      border:2px dashed rgba(0,0,0,0.2); 
      height:160px;
      display:flex; 
      align-items:center; 
      justify-content:center;
      overflow:hidden; 
      cursor:pointer; 
      transition:all 0.3s; 
      background:#f8f9fa;
      border-radius:12px;
      position:relative;
    }
    .img-preview-wrap:hover { 
      border-color:#3b82f6; 
      border-style:solid; 
      background:#fff;
      box-shadow:0 0 0 4px rgba(59,130,246,0.1);
    }
    .img-preview-wrap img { 
      width:100%; 
      height:100%; 
      object-fit:cover; 
    }
    .img-preview-wrap .placeholder { 
      color:#9ca3af; 
      font-size:0.85rem; 
      text-align:center; 
      font-weight:600;
    }
    .img-preview-wrap .placeholder i { 
      font-size:2.5rem; 
      display:block; 
      margin-bottom:0.8rem; 
      color:#3b82f6;
      opacity:0.6;
    }
    .img-preview-wrap:hover .placeholder i {
      opacity:1;
      transform:scale(1.1);
      transition:all 0.3s;
    }

    .btn-submit {
      background:linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color:#fff; 
      padding:0.9rem 2.5rem;
      border:none; 
      font-family:'Inter',sans-serif;
      font-size:0.8rem; 
      font-weight:700; 
      text-transform:uppercase; 
      letter-spacing:0.08em;
      cursor:pointer; 
      transition:all 0.3s; 
      margin-top:0.8rem;
      border-radius:10px;
      box-shadow:0 4px 12px rgba(59,130,246,0.3);
      display:inline-flex;
      align-items:center;
      gap:0.6rem;
    }
    .btn-submit:hover { 
      transform:translateY(-2px); 
      box-shadow:0 6px 20px rgba(59,130,246,0.4);
    }
    .btn-submit:active {
      transform:translateY(0);
      box-shadow:0 2px 8px rgba(59,130,246,0.3);
    }
    .btn-submit:disabled { 
      opacity:0.5; 
      cursor:not-allowed; 
      transform:none; 
      box-shadow:none;
    }

    /* MENU ITEMS GRID */
    .items-grid { 
      display: grid; 
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
      gap: 1rem; 
    }
    @media (max-width: 768px) {
      .items-grid { grid-template-columns: 1fr; }
    }
    .item-card { 
      overflow: hidden; 
      border: 1px solid rgba(0,0,0,0.08); 
      border-radius: 10px;
      transition: all 0.3s; 
      background: #fff;
      display: flex;
      flex-direction: column;
      box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    }
    .item-card:hover { 
      box-shadow: 0 8px 24px rgba(0,0,0,0.12); 
      border-color: rgba(0,0,0,0.12);
      transform: translateY(-4px);
    }
    .item-card img { 
      width: 100%; 
      height: 180px; 
      object-fit: cover; 
      display: block;
      background: #f5f5f5;
      transition: transform 0.3s;
    }
    .item-card:hover img {
      transform: scale(1.03);
    }
    .item-card-body { 
      padding: 1rem 1.2rem; 
      flex: 1; 
      display: flex;
      flex-direction: column;
      gap: 0.4rem;
    }
    .item-card-body h4 { 
      font-size: 0.88rem; 
      font-weight: 800; 
      margin: 0; 
      line-height: 1.3;
      color: #000;
    }
    .item-card-body p  { 
      font-size: 0.75rem; 
      color: #888; 
      margin: 0; 
      line-height: 1.4; 
      display: -webkit-box; 
      -webkit-line-clamp: 2; 
      -webkit-box-orient: vertical; 
      overflow: hidden; 
    }
    .item-card-footer { 
      display: flex; 
      align-items: center; 
      justify-content: space-between; 
      padding: 0.85rem 1.2rem; 
      border-top: 1px solid rgba(0,0,0,0.06);
      gap: 0.6rem;
      flex-wrap: wrap;
    }
    .item-price { 
      font-weight: 900; 
      color: #e05c00; 
      font-size: 0.92rem;
    }
    .item-cat { 
      font-size: 0.6rem; 
      font-weight: 700; 
      padding: 3px 10px; 
      background: #000; 
      color: #fff; 
      text-transform: uppercase; 
      letter-spacing: 0.06em; 
      border-radius: 4px;
    }
    .item-actions { 
      display: flex; 
      gap: 6px; 
      width: 100%; 
      margin-top: 0.5rem; 
    }
    .btn-del { 
      background: #dc2626; 
      color: #fff; 
      border: none; 
      padding: 7px 12px; 
      cursor: pointer; 
      font-size: 0.72rem; 
      font-weight: 700; 
      transition: all 0.2s; 
      font-family: 'Inter', sans-serif; 
      border-radius: 6px;
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 4px;
    }
    .btn-del:hover { 
      background: #b91c1c;
      transform: translateY(-1px);
      box-shadow: 0 3px 8px rgba(220,38,38,0.3);
    }
    .btn-edit { 
      background: #fff; 
      color: #000; 
      border: 1px solid rgba(0,0,0,0.15); 
      padding: 7px 12px; 
      cursor: pointer; 
      font-size: 0.72rem; 
      font-weight: 700; 
      transition: all 0.2s; 
      font-family: 'Inter', sans-serif; 
      border-radius: 6px;
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 4px;
    }
    .btn-edit:hover { 
      background: #000; 
      color: #fff;
      border-color: #000;
      transform: translateY(-1px);
      box-shadow: 0 3px 8px rgba(0,0,0,0.2);
    }

    /* TABLES - Modern Design */
    .msg-table { 
      width:100%; 
      border-collapse:separate;
      border-spacing:0;
      font-size:0.85rem;
    }
    .msg-table th { 
      background:linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%);
      color:#fff; 
      padding:1rem 1.2rem; 
      text-align:left; 
      font-size:0.75rem; 
      text-transform:uppercase; 
      letter-spacing:0.08em; 
      font-weight:700;
      border-bottom:2px solid #3b82f6;
    }
    .msg-table th:first-child { border-top-left-radius:12px; }
    .msg-table th:last-child { border-top-right-radius:12px; }
    .msg-table td { 
      padding:1rem 1.2rem; 
      border-bottom:1px solid rgba(0,0,0,0.06);
      vertical-align:middle;
      background:#fff;
    }
    .msg-table tr:hover td { 
      background:#f8f9fa;
    }
    .msg-table tr.unread td { 
      background:#fffbeb;
      border-left:3px solid #fbbf24;
    }
    .msg-table tr.unread:hover td { 
      background:#fef3c7;
    }
    .unread-dot { 
      display:inline-block; 
      width:8px; 
      height:8px; 
      background:#fbbf24;
      border-radius:50%; 
      margin-right:8px; 
      vertical-align:middle;
      animation:pulse 2s infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity:1; }
      50% { opacity:0.5; }
    }
    .msg-subject { 
      font-weight:700; 
      font-size:0.88rem; 
      margin-bottom:4px;
      color:#1a1a1a;
    }
    .msg-text { color:#888; font-size:0.75rem; }
    .msg-time { font-size:0.75rem; color:#888; white-space:nowrap; }
    .btn-read { background:#f5f5f5; color:#000; border:1px solid rgba(0,0,0,0.15); padding:4px 10px; cursor:pointer; font-size:0.7rem; font-weight:700; font-family:'Inter',sans-serif; margin-bottom:4px; display:block; }
    .btn-read:hover { background:#e5e5e5; }

    /* CATEGORY MANAGER */
    .cat-input-row { display:flex; gap:0.6rem; margin-bottom:0.8rem; }
    .cat-input-row input { 
      flex:1; 
      padding:0.8rem 1rem; 
      border:2px solid rgba(0,0,0,0.08); 
      border-radius:10px; 
      font-family:'Inter',sans-serif; 
      font-size:0.9rem; 
      outline:none;
      transition:all 0.2s;
    }
    .cat-input-row input:focus { 
      border-color:#3b82f6; 
      box-shadow:0 0 0 4px rgba(59,130,246,0.1);
    }
    .btn-cat-add { 
      background:linear-gradient(135deg, #3b82f6, #2563eb); 
      color:#fff; 
      border:none; 
      padding:0 1.6rem; 
      font-family:'Inter',sans-serif; 
      font-size:0.8rem; 
      font-weight:700; 
      text-transform:uppercase; 
      cursor:pointer; 
      border-radius:10px;
      transition:all 0.3s;
      box-shadow:0 4px 12px rgba(59,130,246,0.3);
    }
    .btn-cat-add:hover {
      transform:translateY(-2px);
      box-shadow:0 6px 16px rgba(59,130,246,0.4);
    }
    .cat-list { 
      display: grid; 
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
      gap: 0.8rem; 
    }
    @media (max-width: 768px) {
      .cat-list { grid-template-columns: 1fr; }
    }
    .cat-chip { 
      display:flex; 
      align-items:center; 
      justify-content:space-between; 
      background:#fff; 
      border:1px solid rgba(0,0,0,0.08); 
      padding:0.75rem 1rem; 
      gap:0.8rem;
      border-radius:12px;
      transition:all 0.3s;
      box-shadow:0 2px 4px rgba(0,0,0,0.04);
    }
    .cat-chip:hover { 
      border-color:rgba(0,0,0,0.15);
      box-shadow:0 4px 12px rgba(0,0,0,0.08);
      transform:translateY(-2px);
    }
    .cat-chip-banner { 
      width:64px; 
      height:40px; 
      object-fit:cover; 
      flex-shrink:0; 
      border:1px solid rgba(0,0,0,0.1); 
      background:#e5e5e5; 
      border-radius:8px;
    }
    .cat-chip-banner-placeholder { 
      width:64px; 
      height:40px; 
      flex-shrink:0; 
      background:#f3f4f6; 
      display:flex; 
      align-items:center; 
      justify-content:center; 
      font-size:0.65rem; 
      color:#9ca3af; 
      border:2px dashed rgba(0,0,0,0.15); 
      cursor:pointer; 
      border-radius:8px;
      transition:all 0.2s;
    }
    .cat-chip-banner-placeholder:hover {
      border-color:#3b82f6;
      color:#3b82f6;
      background:#eff6ff;
    }
    .cat-chip-name { font-size:0.9rem; font-weight:800; color:#1a1a1a; }
    .cat-chip-slug { font-size:0.75rem; color:#6b7280; margin-top:2px; }
    .btn-cat-del { 
      background:none; 
      border:none; 
      width:32px; 
      height:32px; 
      cursor:pointer; 
      color:#9ca3af; 
      font-size:0.95rem;
      border-radius:8px;
      transition:all 0.2s;
      display:flex;
      align-items:center;
      justify-content:center;
    }
    .btn-cat-del:hover { 
      color:#dc2626; 
      background:#fee2e2;
    }
    .cat-empty { 
      grid-column: 1 / -1;
      text-align:center; 
      padding:3rem 2rem; 
      color:#9ca3af; 
      font-size:0.9rem;
      background:#f9fafb;
      border-radius:12px;
      border:2px dashed rgba(0,0,0,0.1);
    }
    .cat-empty i {
      font-size: 2rem;
      display: block;
      margin-bottom: 0.5rem;
      opacity: 0.5;
    }
    .cat-banner-label { 
      font-size:0.7rem; 
      font-weight:700; 
      text-transform:uppercase; 
      letter-spacing:0.08em; 
      color:#374151; 
      margin-bottom:0.5rem; 
      display:block; 
    }
    .cat-banner-upload-wrap { 
      border:2px dashed rgba(0,0,0,0.2); 
      height:120px; 
      display:flex; 
      align-items:center; 
      justify-content:center; 
      cursor:pointer; 
      background:#f8f9fa; 
      overflow:hidden; 
      margin-bottom:0.7rem; 
      transition:all 0.3s; 
      border-radius:12px;
    }
    .cat-banner-upload-wrap:hover { 
      border-color:#3b82f6; 
      border-style:solid; 
      background:#fff;
      box-shadow:0 0 0 4px rgba(59,130,246,0.1);
    }
    .cat-banner-upload-wrap img { width:100%; height:100%; object-fit:cover; }
    .cat-banner-upload-placeholder { 
      color:#9ca3af; 
      font-size:0.85rem; 
      text-align:center; 
      font-weight:600;
    }
    .cat-banner-upload-placeholder i { 
      font-size:2rem; 
      display:block; 
      margin-bottom:0.5rem; 
      color:#3b82f6;
      opacity:0.6;
    }
    .cat-banner-upload-wrap:hover .cat-banner-upload-placeholder i {
      opacity:1;
      transform:scale(1.1);
    }

    /* ORDERS */
    .order-tabs { display:flex; gap:0.5rem; margin-bottom:1.2rem; flex-wrap:wrap; }
    .order-tab { 
      background:#fff; 
      border:2px solid rgba(0,0,0,0.08); 
      padding:0.65rem 1.3rem; 
      font-family:'Inter',sans-serif; 
      font-size:0.78rem; 
      font-weight:700; 
      cursor:pointer; 
      text-transform:uppercase; 
      letter-spacing:0.06em; 
      border-radius:10px;
      transition:all 0.2s;
      display:inline-flex;
      align-items:center;
      gap:0.5rem;
    }
    .order-tab:hover {
      border-color:rgba(0,0,0,0.15);
      background:#f9fafb;
    }
    .order-tab.active { 
      background:linear-gradient(135deg, #3b82f6, #2563eb); 
      color:#fff; 
      border-color:#3b82f6; 
      box-shadow:0 4px 12px rgba(59,130,246,0.3);
    }
    .order-tab-badge { 
      background:rgba(255,255,255,0.9); 
      color:#1a1a1a; 
      font-size:0.7rem; 
      padding:2px 8px; 
      font-weight:800; 
      border-radius:6px;
    }
    .order-tab.active .order-tab-badge {
      background:rgba(255,255,255,0.25);
      color:#fff;
    }
    .order-badge { 
      font-size:0.7rem; 
      font-weight:800; 
      text-transform:uppercase; 
      letter-spacing:0.06em; 
      padding:4px 12px; 
      color:#fff; 
      border-radius:6px;
      display:inline-block;
    }
    .order-badge-pending    { background:linear-gradient(135deg, #f59e0b, #d97706); }
    .order-badge-confirmed  { background:linear-gradient(135deg, #3b82f6, #2563eb); }
    .order-badge-dispatched { background:linear-gradient(135deg, #10b981, #059669); }
    .order-badge-cancelled  { background:linear-gradient(135deg, #6b7280, #4b5563); }
    .order-action-btn { 
      border:none; 
      padding:6px 12px; 
      cursor:pointer; 
      font-family:'Inter',sans-serif; 
      font-size:0.75rem; 
      font-weight:700; 
      text-transform:uppercase; 
      display:block; 
      width:100%; 
      margin-bottom:4px; 
      border-radius:6px;
      transition:all 0.2s;
    }
    .btn-print-bill {
      border: none;
      padding: 6px 12px;
      cursor: pointer;
      font-family: 'Inter', sans-serif;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      display: block;
      width: 100%;
      margin-top: 4px;
      background: #1a1a1a;
      color: #fff;
      border-radius: 6px;
      transition: all 0.2s;
      letter-spacing: 0.04em;
    }
    .btn-print-bill:hover {
      background: #374151;
      transform: translateY(-1px);
      box-shadow: 0 3px 8px rgba(0,0,0,0.2);
    }
    .order-btn-confirm  { 
      background:linear-gradient(135deg, #3b82f6, #2563eb); 
      color:#fff; 
      box-shadow:0 2px 6px rgba(59,130,246,0.3);
    }
    .order-btn-confirm:hover {
      transform:translateY(-1px);
      box-shadow:0 4px 10px rgba(59,130,246,0.4);
    }
    .order-btn-dispatch { 
      background:linear-gradient(135deg, #10b981, #059669); 
      color:#fff; 
      box-shadow:0 2px 6px rgba(16,185,129,0.3);
    }
    .order-btn-dispatch:hover {
      transform:translateY(-1px);
      box-shadow:0 4px 10px rgba(16,185,129,0.4);
    }
    .order-btn-cancel   { 
      background:linear-gradient(135deg, #dc2626, #b91c1c); 
      color:#fff; 
      box-shadow:0 2px 6px rgba(220,38,38,0.3);
    }
    .order-btn-cancel:hover {
      transform:translateY(-1px);
      box-shadow:0 4px 10px rgba(220,38,38,0.4);
    }

    /* MODAL */
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:999; align-items:center; justify-content:center; }
    .modal-overlay.open { display:flex; }
    .modal-box { background:#fff; width:90%; max-width:600px; max-height:90vh; overflow-y:auto; }
    .modal-header { display:flex; align-items:center; justify-content:space-between; padding:1.2rem 1.4rem; border-bottom:2px solid #000; }
    .modal-header h3 { font-size:0.85rem; font-weight:900; text-transform:uppercase; letter-spacing:0.08em; }
    .btn-modal-close { background:none; border:none; font-size:1.2rem; cursor:pointer; color:#888; }
    .btn-modal-close:hover { color:#000; }
    .modal-box form { padding:1.4rem; }
    .modal-img-preview { width:100%; height:150px; overflow:hidden; cursor:pointer; background:#f5f5f5; border:1px dashed rgba(0,0,0,0.25); display:flex; align-items:center; justify-content:center; }
    .modal-img-preview img { width:100%; height:100%; object-fit:cover; }
    .modal-img-hint { font-size:0.7rem; color:#aaa; margin-top:4px; }

    /* TOAST */
    .toast { 
      position:fixed; 
      bottom:2rem; 
      right:2rem; 
      background:linear-gradient(135deg, #1a1a1a, #0a0a0a); 
      color:#fff; 
      padding:1rem 1.5rem; 
      font-size:0.85rem; 
      font-weight:600; 
      z-index:9999; 
      transform:translateY(100px); 
      opacity:0; 
      transition:all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55); 
      pointer-events:none; 
      display:flex; 
      align-items:center; 
      gap:0.7rem;
      border-radius:12px;
      box-shadow:0 8px 24px rgba(0,0,0,0.3);
      border:1px solid rgba(255,255,255,0.1);
      min-width:280px;
    }
    .toast.show { 
      transform:translateY(0); 
      opacity:1; 
    }
    .toast.error { 
      background:linear-gradient(135deg, #dc2626, #b91c1c); 
      border-color:rgba(255,255,255,0.15);
    }
    .toast i {
      font-size:1.2rem;
    }
    .toast::before {
      content:'';
      position:absolute;
      bottom:0;
      left:0;
      right:0;
      height:3px;
      background:rgba(255,255,255,0.3);
      animation:toastProgress 3s linear;
    }
    @keyframes toastProgress {
      from { width:100%; }
      to { width:0%; }
    }
x`  
    /* EMPTY STATE */
    .empty-state { 
      text-align:center; 
      padding:4rem 2rem; 
      color:#9ca3af; 
      background:#f9fafb;
      border-radius:16px;
      border:2px dashed rgba(0,0,0,0.1);
    }
    .empty-state i { 
      font-size:3.5rem; 
      display:block; 
      margin-bottom:1.2rem; 
      opacity:0.5;
      color:#3b82f6;
    }
    .empty-state p {
      font-size:0.9rem;
      font-weight:600;
      color:#6b7280;
    }

    /* USER VERIFICATION BADGES */
    .user-badge {
      display:inline-flex;
      align-items:center;
      gap:0.4rem;
      font-size:0.7rem;
      font-weight:800;
      padding:6px 14px;
      text-transform:uppercase;
      letter-spacing:0.06em;
      border-radius:8px;
      transition:all 0.3s;
      box-shadow:0 2px 6px rgba(0,0,0,0.15);
    }
    .user-badge i {
      font-size:0.75rem;
    }
    .user-badge-verified {
      background:linear-gradient(135deg, #10b981, #059669);
      color:#fff;
    }
    .user-badge-verified:hover {
      transform:translateY(-1px);
      box-shadow:0 4px 12px rgba(16,185,129,0.4);
    }
    .user-badge-pending {
      background:linear-gradient(135deg, #f59e0b, #d97706);
      color:#fff;
    }
    .user-badge-pending:hover {
      transform:translateY(-1px);
      box-shadow:0 4px 12px rgba(245,158,11,0.4);
    }

    /* Toggle switch */
    .toggle-slider { position:absolute; inset:0; background:#ccc; border-radius:28px; transition:0.3s; }
    .toggle-slider:before { content:""; position:absolute; height:20px; width:20px; left:4px; bottom:4px; background:#fff; border-radius:50%; transition:0.3s; }
    #deliveryModalToggle:checked + .toggle-slider { background:#16a34a; }
    #deliveryModalToggle:checked + .toggle-slider:before { transform:translateX(26px); }

    /* Mobile Menu Button */
    .mobile-menu-btn {
      display: none;
      position: fixed;
      top: 1rem;
      left: 1rem;
      z-index: 101;
      background: linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%);
      color: #fff;
      border: none;
      width: 46px;
      height: 46px;
      border-radius: 12px;
      cursor: pointer;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      transition: all 0.3s;
    }
    .mobile-menu-btn:hover {
      background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%);
      transform: scale(1.05);
    }
    .mobile-menu-btn:active {
      transform: scale(0.95);
    }

    /* Sidebar Overlay for Mobile */
    .sidebar-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.5);
      z-index: 99;
      opacity: 0;
      transition: opacity 0.3s;
    }
    .sidebar-overlay.active {
      display: block;
      opacity: 1;
    }

    @media (max-width:768px) {
      .mobile-menu-btn {
        display: flex;
      }
      
      .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        transform: translateX(-100%);
        transition: transform 0.3s ease-in-out;
        z-index: 100;
      }
      
      .sidebar.active {
        transform: translateX(0);
        box-shadow: 8px 0 24px rgba(0,0,0,0.3);
      }
      
      .main {
        margin-left: 0;
        padding: 5rem 1rem 1rem 1rem;
      }
      
      .topbar {
        margin-top: 0;
      }
      
      .stats {
        grid-template-columns: 1fr 1fr;
      }
      
      .form-grid {
        grid-template-columns: 1fr;
      }
      
      .cat-manager-grid {
        grid-template-columns: 1fr;
      }
      
      .items-grid {
        grid-template-columns: 1fr;
      }
      
      .msg-table {
        font-size: 0.8rem;
      }
      
      .msg-table th,
      .msg-table td {
        padding: 0.75rem 0.8rem;
      }
      
      .order-tabs {
        gap: 0.3rem;
      }
      
      .order-tab {
        padding: 0.4rem 0.8rem;
        font-size: 0.7rem;
      }
    }
    
    @media (max-width:480px) {
      .stats {
        grid-template-columns: 1fr;
      }
      
      .sidebar {
        width: 85%;
      }
      
      .stat-card {
        padding: 1.2rem;
      }
    }
  </style>
</head>
<body>

  <!-- Mobile Menu Button -->
  <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu">
    <i class="fas fa-bars"></i>
  </button>

  <!-- Sidebar Overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <a href="../" class="sidebar-logo" title="Go to Homepage">
      <div class="sidebar-logo-icon"><i class="fas fa-burger"></i></div>
      <div class="sidebar-logo-text">
        SnackZone
        <small>Admin Panel</small>
      </div>
    </a>
    <nav class="sidebar-nav">
      <button class="nav-btn active" onclick="showPanel('dashboard', this)">
        <i class="fas fa-gauge-high"></i> Dashboard
      </button>
      <button class="nav-btn" onclick="showPanel('menu', this)">
        <i class="fas fa-utensils"></i> Menu Manager
      </button>
      <button class="nav-btn" onclick="showPanel('categories', this)">
        <i class="fas fa-tags"></i> Categories
      </button>
      <button class="nav-btn" onclick="showPanel('messages', this)">
        <i class="fas fa-envelope"></i> Messages
        <?php if($unread > 0): ?>
          <span class="badge" id="unreadBadge"><?= $unread ?></span>
        <?php endif; ?>
      </button>
      <button class="nav-btn" onclick="showPanel('bookings', this)">
        <i class="fas fa-calendar-check"></i> Bookings
        <?php if($unreadBookings > 0): ?>
          <span class="badge" id="unreadBookBadge"><?= $unreadBookings ?></span>
        <?php endif; ?>
      </button>
      <button class="nav-btn" onclick="showPanel('orders', this)">
        <i class="fas fa-bag-shopping"></i> Orders
        <?php if($pendingCount > 0): ?>
          <span class="badge" id="pendingOrderBadge"><?= $pendingCount ?></span>
        <?php endif; ?>
      </button>
      <button class="nav-btn" onclick="showPanel('users', this)">
        <i class="fas fa-users"></i> Users
        <span class="badge"><?= $totalUsers ?></span>
      </button>
      <?php if ($showChefStores): ?>
      <button class="nav-btn" onclick="showPanel('vendors', this)">
        <i class="fas fa-store"></i> Chef Stores
        <?php 
        $pendingVendors = $conn->query("SELECT COUNT(*) as cnt FROM vendors WHERE status='pending'")->fetch_assoc()['cnt'] ?? 0;
        if($pendingVendors > 0): 
        ?>
          <span class="badge"><?= $pendingVendors ?></span>
        <?php endif; ?>
      </button>
      <?php endif; ?>
      <button class="nav-btn" onclick="showPanel('slides', this)">
        <i class="fas fa-images"></i> Homepage Slides
      </button>
      <button class="nav-btn" onclick="showPanel('settings', this)">
        <i class="fas fa-cog"></i> Settings
      </button>
      <button class="nav-btn" onclick="showPanel('whatsapp', this)">
        <i class="fas fa-brands fa-whatsapp" style="color:#25D366"></i> WhatsApp
      </button>
      <button class="nav-btn" onclick="showPanel('receptionist', this)">
        <i class="fas fa-headset"></i> Receptionist
      </button>
      <button class="nav-btn" onclick="showPanel('daily', this)">
        <i class="fas fa-chart-bar"></i> Reports
      </button>
      <button class="nav-btn" onclick="showPanel('suppliers', this)">
        <i class="fas fa-truck"></i> Suppliers
      </button>
      <button class="nav-btn" onclick="showPanel('pos', this)">
        <i class="fas fa-cash-register"></i> POS
      </button>
      <button class="nav-btn" onclick="showPanel('profit', this)">
        <i class="fas fa-chart-line"></i> Profit Analysis
      </button>
    </nav>
    <div class="sidebar-footer">
      <a href="logout.php" class="nav-btn" style="color:rgba(255,255,255,0.35);text-decoration:none;"
         onclick="return confirm('Are you sure you want to logout?')">
        <i class="fas fa-right-from-bracket"></i> Logout
      </a>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">

    <!-- TOP BAR -->
    <div class="topbar">
      <div>
        <h1 id="pageTitle">Menu Manager</h1>
        <p id="pageSubtitle">Add, view and manage menu items</p>
      </div>
      <div class="topbar-right">
        <i class="fas fa-circle" style="color:#22c55e;font-size:0.55rem"></i>
        &nbsp;Snack Zone &nbsp;|&nbsp; <strong>Admin</strong>
      </div>
    </div>

    <!-- STATS -->
    <!-- PANEL: DASHBOARD -->
    <div class="panel active" id="panel-dashboard">
      <div class="stats" id="mainStats">
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-utensils"></i></div>
          <div>
            <div class="stat-num"><?= $menuItems->num_rows ?></div>
            <div class="stat-label">Menu Items</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-envelope"></i></div>
          <div>
            <div class="stat-num" id="unreadCount"><?= $unread ?></div>
            <div class="stat-label">Unread Messages</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-bag-shopping"></i></div>
          <div>
            <div class="stat-num"><?= $orders->num_rows ?></div>
            <div class="stat-label">Today's Orders</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#e67e00"><i class="fas fa-clock"></i></div>
          <div>
            <div class="stat-num" id="statPending"><?= $pendingCount ?></div>
            <div class="stat-label">Pending Today</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#2563eb"><i class="fas fa-users"></i></div>
          <div>
            <div class="stat-num"><?= $totalUsers ?></div>
            <div class="stat-label">Registered Users</div>
          </div>
        </div>
      </div>
    </div>

    <!-- PANEL: MENU MANAGER -->
    <div class="panel" id="panel-menu">
      <div class="card">
        <div class="card-title"><i class="fas fa-plus-circle"></i> Add New Menu Item</div>
        <form id="addItemForm" enctype="multipart/form-data">
          <div class="form-grid">
            <div class="form-group">
              <label>Item Title</label>
              <input type="text" name="title" placeholder="e.g. Zinger Burger" required />
            </div>
            <div class="form-group">
              <label>Category</label>
              <select name="category" id="addCategorySelect" required>
                <option value="">Select category...</option>
                <?php foreach ($categoriesList as $cat): ?>
                  <option value="<?= htmlspecialchars($cat['slug']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Price (Rs.)</label>
              <input type="number" name="price" placeholder="e.g. 350" min="1" required />
            </div>
            <div class="form-group">
              <label>Discount (%) <span style="font-weight:400; color:#999; text-transform:none;">(Optional)</span></label>
              <input type="number" name="discount_percent" placeholder="e.g. 10, 20, 50" min="0" max="99" value="0" />
            </div>
            <div class="form-group">
              <label>Item Image</label>
              <input type="file" name="image" id="imageInput" accept="image/*" required style="display:none" />
              <div class="img-preview-wrap" onclick="$('#imageInput').click()">
                <div class="placeholder" id="imgPlaceholder">
                  <i class="fas fa-cloud-upload-alt"></i>
                  Click to upload image
                </div>
                <img id="imgPreview" src="" alt="" style="display:none" />
              </div>
            </div>
            <div class="form-group full">
              <label>Description</label>
              <textarea name="description" rows="3" placeholder="Short description..." required></textarea>
            </div>
          </div>
          <button type="submit" class="btn-submit" id="addBtn">
            <i class="fas fa-plus"></i> Add to Menu
          </button>
        </form>
      </div>

      <div class="card">
        <div class="card-title"><i class="fas fa-th-large"></i> Current Menu Items</div>
        <div class="items-grid" id="itemsGrid">
          <?php
          $menuItems->data_seek(0);
          while($item = $menuItems->fetch_assoc()):
            $catName = ucfirst($item['category']);
            foreach ($categoriesList as $c) {
              if ($c['slug'] === $item['category']) { $catName = $c['name']; break; }
            }
          ?>
          <div class="item-card" id="item-<?= $item['id'] ?>">
            <div style="position:relative;">
              <img src="../<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" id="item-img-<?= $item['id'] ?>" />
              <?php if($item['discount_percent'] > 0): ?>
                <span style="position:absolute; top:8px; right:8px; background:linear-gradient(135deg,#dc2626,#b91c1c); color:#fff; font-size:0.7rem; font-weight:900; padding:4px 10px; border-radius:16px; box-shadow:0 2px 8px rgba(220,38,38,0.4);">
                  <?= $item['discount_percent'] ?>% OFF
                </span>
              <?php endif; ?>
            </div>
            <div class="item-card-body">
              <h4 id="item-title-<?= $item['id'] ?>"><?= htmlspecialchars($item['title']) ?></h4>
              <p id="item-desc-<?= $item['id'] ?>"><?= htmlspecialchars($item['description']) ?></p>
            </div>
            <div class="item-card-footer">
              <div style="display:flex; flex-direction:column; gap:4px;">
                <?php if($item['discount_percent'] > 0): 
                  $discountedPrice = $item['price'] * (1 - $item['discount_percent'] / 100);
                ?>
                  <span class="item-price" id="item-price-<?= $item['id'] ?>" style="color:#dc2626; font-size:1.05rem;">
                    Rs. <?= number_format($discountedPrice, 0) ?>
                  </span>
                  <span style="font-size:0.75rem; color:#999; text-decoration:line-through; font-weight:600;">
                    Rs. <?= number_format($item['price'], 0) ?>
                  </span>
                <?php else: ?>
                  <span class="item-price" id="item-price-<?= $item['id'] ?>">Rs. <?= number_format($item['price'], 0) ?></span>
                <?php endif; ?>
              </div>
              <span class="item-cat" id="item-cat-label-<?= $item['id'] ?>"><?= htmlspecialchars($catName) ?></span>
              <div class="item-actions">
                <button class="btn-edit" title="Add-ons" onclick="openAddonsModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['title'])) ?>')" style="background:#2563eb;">
                  <i class="fas fa-list-ul"></i>
                </button>
                <button class="btn-edit" onclick="openEditModal(<?= $item['id'] ?>, <?= htmlspecialchars(json_encode([
                  'title'            => $item['title'],
                  'description'      => $item['description'],
                  'price'            => $item['price'],
                  'discount_percent' => $item['discount_percent'] ?? 0,
                  'category'         => $item['category'],
                  'image_path'       => $item['image_path'],
                ]), ENT_QUOTES) ?>)">
                  <i class="fas fa-pen"></i>
                </button>
                <button class="btn-del" onclick="deleteItem(<?= $item['id'] ?>)">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
          </div>
          <?php endwhile; ?>
          <?php if($menuItems->num_rows === 0): ?>
          <div class="empty-state" style="grid-column:1/-1">
            <i class="fas fa-utensils"></i>
            No menu items yet. Add your first item above!
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- PANEL: MESSAGES -->
    <div class="panel" id="panel-messages">
      <div class="card">
        <div class="card-title"><i class="fas fa-inbox"></i> Contact Messages</div>
        <?php if($messages->num_rows === 0): ?>
          <div class="empty-state"><i class="fas fa-envelope-open"></i> No messages yet.</div>
        <?php else: ?>
        <div style="overflow-x:auto">
          <table class="msg-table">
            <thead>
              <tr><th>Name</th><th>Subject & Message</th><th>Contact</th><th>Time</th><th>Actions</th></tr>
            </thead>
            <tbody id="msgTableBody">
              <?php while($msg = $messages->fetch_assoc()): ?>
              <tr id="msg-<?= $msg['id'] ?>" class="<?= $msg['is_read'] ? '' : 'unread' ?>">
                <td>
                  <?php if(!$msg['is_read']): ?><span class="unread-dot"></span><?php endif; ?>
                  <strong><?= htmlspecialchars($msg['name']) ?></strong>
                </td>
                <td>
                  <div class="msg-subject"><?= htmlspecialchars($msg['subject']) ?></div>
                  <div class="msg-text"><?= htmlspecialchars(substr($msg['message'], 0, 100)) ?>...</div>
                </td>
                <td>
                  <?php if($msg['phone']): ?><div><i class="fas fa-phone" style="font-size:0.75rem"></i> <?= htmlspecialchars($msg['phone']) ?></div><?php endif; ?>
                  <?php if($msg['email']): ?><div><i class="fas fa-envelope" style="font-size:0.75rem"></i> <?= htmlspecialchars($msg['email']) ?></div><?php endif; ?>
                </td>
                <td class="msg-time"><?= date('d M Y', strtotime($msg['created_at'])) ?></td>
                <td>
                  <?php if(!$msg['is_read']): ?>
                  <button class="btn-read" onclick="markRead(<?= $msg['id'] ?>)"><i class="fas fa-check"></i> Read</button>
                  <?php endif; ?>
                  <button class="btn-del" onclick="deleteMsg(<?= $msg['id'] ?>)"><i class="fas fa-trash"></i></button>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- PANEL: BOOKINGS -->
    <div class="panel" id="panel-bookings">
      <div class="card">
        <div class="card-title"><i class="fas fa-calendar-check"></i> Event / Catering Bookings</div>
        <?php if($bookings->num_rows === 0): ?>
          <div class="empty-state"><i class="fas fa-calendar-xmark"></i> No bookings yet.</div>
        <?php else: ?>
        <div style="overflow-x:auto">
          <table class="msg-table">
            <thead>
              <tr><th>Customer</th><th>Event Details</th><th>Menu Selected</th><th>Notes</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php while($b = $bookings->fetch_assoc()): ?>
              <tr id="book-<?= $b['id'] ?>" class="<?= $b['is_read'] ? '' : 'unread' ?>">
                <td>
                  <?php if(!$b['is_read']): ?><span class="unread-dot"></span><?php endif; ?>
                  <strong><?= htmlspecialchars($b['name']) ?></strong><br>
                  <small><?= htmlspecialchars($b['phone']) ?></small>
                </td>
                <td>
                  <div><strong><?= date('d M Y', strtotime($b['event_date'])) ?></strong></div>
                  <div><?= htmlspecialchars($b['event_time']) ?></div>
                  <div><?= $b['guests'] ?> guests</div>
                </td>
                <td style="font-size:0.8rem;max-width:180px"><?= htmlspecialchars($b['menu_items']) ?></td>
                <td style="font-size:0.8rem;color:#888"><?= $b['notes'] ? htmlspecialchars($b['notes']) : '-' ?></td>
                <td class="msg-time"><?= date('d M Y', strtotime($b['created_at'])) ?></td>
                <td>
                  <?php if(!$b['is_read']): ?>
                  <button class="btn-read" onclick="markBookRead(<?= $b['id'] ?>)"><i class="fas fa-check"></i> Read</button>
                  <?php endif; ?>
                  <button class="btn-del" onclick="deleteBook(<?= $b['id'] ?>)"><i class="fas fa-trash"></i></button>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- PANEL: ORDERS -->
    <div class="panel" id="panel-orders">
      <div class="card">
        <div class="card-title">
          <i class="fas fa-calendar-day"></i> Today's Orders
        </div>
      </div>

      <div class="order-tabs" id="orderTabs">
        <button class="order-tab active" onclick="filterOrders('all', this)">All</button>
        <button class="order-tab" onclick="filterOrders('pending', this)">
          Pending <span class="order-tab-badge" id="badge-pending"><?= $pendingCount ?></span>
        </button>
        <button class="order-tab" onclick="filterOrders('confirmed', this)">Preparation</button>
        <button class="order-tab" onclick="filterOrders('dispatched', this)">Deliver</button>
        <button class="order-tab" onclick="filterOrders('cancelled', this)">Cancelled</button>
      </div>
      <div class="card" style="padding:0">
        <div style="overflow-x:auto">
          <table class="msg-table" id="ordersTable">
            <thead>
              <tr><th>#</th><th>Customer</th><th>Items</th><th>Total</th><th>Note</th><th>Time</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody id="ordersBody">
              <?php
              $orders->data_seek(0);
              while ($o = $orders->fetch_assoc()):
                $orderItems = json_decode($o['items'], true) ?? [];
                $itemsText  = implode(', ', array_map(fn($i) => $i['title'].' x'.(isset($i['qty']) ? $i['qty'] : 1), $orderItems));
                $statusCls  = [
                  'pending'    => 'order-badge-pending',
                  'confirmed'  => 'order-badge-confirmed',
                  'dispatched' => 'order-badge-dispatched',
                  'cancelled'  => 'order-badge-cancelled',
                ][$o['status']] ?? '';
              ?>
              <tr id="order-row-<?= $o['id'] ?>" data-status="<?= htmlspecialchars($o['status']) ?>">
                <td><strong>#<?= $o['id'] ?></strong></td>
                <td>
                  <strong><?= htmlspecialchars($o['name']) ?></strong><br>
                  <small style="color:#888"><?= htmlspecialchars($o['phone']) ?></small><br>
                  <small style="color:#aaa;font-size:0.72rem"><?= htmlspecialchars(substr($o['address'], 0, 40)) ?>...</small>
                </td>
                <td style="font-size:0.78rem;max-width:200px;white-space:normal"><?= htmlspecialchars($itemsText) ?></td>
                <td><strong>Rs. <?= number_format($o['total'], 0) ?></strong></td>
                <td style="font-size:0.78rem;color:#888"><?= $o['notes'] ? htmlspecialchars($o['notes']) : '-' ?></td>
                <td class="msg-time"><?= date('d M<br>h:i A', strtotime($o['created_at'])) ?></td>
                <td>
                  <span class="order-badge <?= $statusCls ?>" id="order-status-<?= $o['id'] ?>">
                    <?= ucfirst($o['status']) ?>
                  </span>
                </td>
                <td>
                  <div style="display:flex;flex-direction:column;gap:3px;min-width:100px">
                    <?php if ($o['status'] === 'pending'): ?>
                      <button class="order-action-btn order-btn-confirm" onclick="updateOrder(<?= $o['id'] ?>, 'confirmed')"><i class="fas fa-check"></i> Confirm</button>
                      <button class="order-action-btn order-btn-cancel"  onclick="updateOrder(<?= $o['id'] ?>, 'cancelled')"><i class="fas fa-times"></i> Cancel</button>
                      <button class="btn-print-bill"
                        onclick="printOnlineBill(<?= $o['id'] ?>, <?= htmlspecialchars(json_encode($o['name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['phone']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['address']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['items']), ENT_QUOTES) ?>, <?= $o['total'] ?>, <?= htmlspecialchars(json_encode($o['notes'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['created_at']), ENT_QUOTES) ?>)">
                        <i class="fas fa-receipt"></i> Print Bill
                      </button>
                    <?php elseif ($o['status'] === 'confirmed'): ?>
                      <button class="order-action-btn order-btn-dispatch" onclick="updateOrder(<?= $o['id'] ?>, 'dispatched')"><i class="fas fa-motorcycle"></i> Deliver</button>
                      <button class="order-action-btn order-btn-cancel"   onclick="updateOrder(<?= $o['id'] ?>, 'cancelled')"><i class="fas fa-times"></i> Cancel</button>
                      <div style="margin-top:6px;display:flex;gap:4px;align-items:center;">
                        <input type="text" id="pt-<?= $o['id'] ?>"
                          value="<?= htmlspecialchars($o['prep_time'] ?? '') ?>"
                          placeholder="e.g. 30-45 mins"
                          style="flex:1;font-size:0.68rem;padding:4px 6px;border:1px solid rgba(0,0,0,0.2);font-family:'Inter',sans-serif;min-width:0;" />
                        <button class="btn-edit" style="font-size:0.65rem;padding:4px 8px;white-space:nowrap"
                          onclick="updatePrepTime(<?= $o['id'] ?>)">
                          <i class="fas fa-clock"></i> Set
                        </button>
                      </div>
                      <button class="btn-print-bill"
                        onclick="printOnlineBill(<?= $o['id'] ?>, <?= htmlspecialchars(json_encode($o['name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['phone']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['address']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['items']), ENT_QUOTES) ?>, <?= $o['total'] ?>, <?= htmlspecialchars(json_encode($o['notes'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['created_at']), ENT_QUOTES) ?>)">
                        <i class="fas fa-receipt"></i> Print Bill
                      </button>
                    <?php elseif ($o['status'] === 'dispatched'): ?>
                      <span style="font-size:0.72rem;font-weight:700;color:#16a34a">On the way</span>
                      <button class="btn-del" style="font-size:0.68rem;padding:4px 10px;margin-top:4px;background:#555"
                        onclick="deleteOrder(<?= $o['id'] ?>)"><i class="fas fa-trash"></i></button>
                      <button class="btn-print-bill"
                        onclick="printOnlineBill(<?= $o['id'] ?>, <?= htmlspecialchars(json_encode($o['name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['phone']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['address']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['items']), ENT_QUOTES) ?>, <?= $o['total'] ?>, <?= htmlspecialchars(json_encode($o['notes'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['created_at']), ENT_QUOTES) ?>)">
                        <i class="fas fa-receipt"></i> Print Bill
                      </button>
                    <?php elseif ($o['status'] === 'cancelled'): ?>
                      <span style="font-size:0.72rem;font-weight:700;color:#bbb">Cancelled</span>
                      <button class="btn-del" style="font-size:0.68rem;padding:4px 10px;margin-top:4px;background:#555"
                        onclick="deleteOrder(<?= $o['id'] ?>)"><i class="fas fa-trash"></i></button>
                      <button class="btn-print-bill"
                        onclick="printOnlineBill(<?= $o['id'] ?>, <?= htmlspecialchars(json_encode($o['name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['phone']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['address']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['items']), ENT_QUOTES) ?>, <?= $o['total'] ?>, <?= htmlspecialchars(json_encode($o['notes'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['created_at']), ENT_QUOTES) ?>)">
                        <i class="fas fa-receipt"></i> Print Bill
                      </button>
                    <?php else: ?>
                      <button class="btn-del" style="font-size:0.68rem;padding:4px 10px;background:#555"
                        onclick="deleteOrder(<?= $o['id'] ?>)"><i class="fas fa-trash"></i></button>
                      <button class="btn-print-bill"
                        onclick="printOnlineBill(<?= $o['id'] ?>, <?= htmlspecialchars(json_encode($o['name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['phone']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['address']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['items']), ENT_QUOTES) ?>, <?= $o['total'] ?>, <?= htmlspecialchars(json_encode($o['notes'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($o['created_at']), ENT_QUOTES) ?>)">
                        <i class="fas fa-receipt"></i> Print Bill
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endwhile; ?>
              <?php if ($orders->num_rows === 0): ?>
              <tr><td colspan="8"><div class="empty-state"><i class="fas fa-calendar-day"></i><p>No orders received today</p></div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PANEL: CATEGORIES -->
    <div class="panel" id="panel-categories">
      <div class="card">
        <div class="card-title"><i class="fas fa-tags"></i> Category Manager</div>
        
        <!-- Add new category section - full width -->
        <div style="max-width:600px">
          <p style="font-size:0.85rem;color:#888;margin-bottom:0.8rem">Add a category with an optional banner image shown on the home page.</p>
          <div class="cat-input-row">
            <input type="text" id="newCatName" placeholder="e.g. Sandwiches, Drinks..." maxlength="60" />
            <button class="btn-cat-add" onclick="addCategory()"><i class="fas fa-plus"></i> Add</button>
          </div>
          <!-- Banner upload for new category -->
          <span class="cat-banner-label">Banner Image <span style="color:#aaa;font-weight:400">(optional � shown on home page)</span></span>
          <input type="file" id="newCatBanner" accept="image/*" style="display:none" />
          <div class="cat-banner-upload-wrap" id="newCatBannerWrap" onclick="$('#newCatBanner').click()">
            <div class="cat-banner-upload-placeholder" id="newCatBannerPlaceholder">
              <i class="fas fa-image"></i>Click to upload banner
            </div>
            <img id="newCatBannerPreview" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;" />
          </div>
        </div>

        <!-- Divider -->
        <div style="height:1px;background:rgba(0,0,0,0.08);margin:2rem 0"></div>

        <!-- Existing categories list - bottom section -->
        <div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
            <h3 style="font-size:1rem;font-weight:700;color:#000;margin:0">
              Existing Categories
            </h3>
            <span id="catCount" style="font-size:0.85rem;font-weight:600;color:#888;background:#f5f5f5;padding:4px 12px;border-radius:20px">
              <?= count($categoriesList) ?> categories
            </span>
          </div>
          <div class="cat-list" id="catList">
            <?php if (empty($categoriesList)): ?>
              <div class="cat-empty" id="catEmpty"><i class="fas fa-tag"></i> No categories yet.</div>
            <?php else: ?>
              <?php
              // Ensure banner_image column
              $conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS banner_image VARCHAR(255) DEFAULT ''");
              foreach ($categoriesList as $cat):
                $bannerSrc = !empty($cat['banner_image']) ? '../' . $cat['banner_image'] : '';
              ?>
              <div class="cat-chip" id="cat-<?= $cat['id'] ?>">
                <!-- Banner thumbnail � click to change -->
                <div style="flex-shrink:0">
                  <input type="file" accept="image/*" id="bannerFile-<?= $cat['id'] ?>" style="display:none"
                    onchange="uploadBanner(<?= $cat['id'] ?>, this)" />
                  <?php if ($bannerSrc): ?>
                    <img src="<?= htmlspecialchars($bannerSrc) ?>" alt="banner"
                      class="cat-chip-banner" id="bannerImg-<?= $cat['id'] ?>"
                      onclick="$('#bannerFile-<?= $cat['id'] ?>').click()" title="Click to change banner" />
                  <?php else: ?>
                    <div class="cat-chip-banner-placeholder" id="bannerImg-<?= $cat['id'] ?>"
                      onclick="$('#bannerFile-<?= $cat['id'] ?>').click()" title="Click to add banner">
                      <i class="fas fa-image"></i>
                    </div>
                  <?php endif; ?>
                </div>
                <div style="flex:1;min-width:0">
                  <div class="cat-chip-name"><?= htmlspecialchars($cat['name']) ?></div>
                  <div class="cat-chip-slug">slug: <?= htmlspecialchars($cat['slug']) ?></div>
                </div>
                <button class="btn-cat-del" onclick="deleteCategory(<?= $cat['id'] ?>, this)" title="Delete"><i class="fas fa-times"></i></button>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- PANEL: USERS -->
    <div class="panel" id="panel-users">
      <div class="card">
        <div class="card-title"><i class="fas fa-users"></i> Registered Users
          <span style="margin-left:auto;font-size:0.72rem;font-weight:600;color:#888;">
            <?= $verifiedUsers ?> verified / <?= $totalUsers ?> total
          </span>
        </div>
        <?php $users->data_seek(0); if($users->num_rows === 0): ?>
          <div class="empty-state"><i class="fas fa-users"></i> No registered users yet.</div>
        <?php else: ?>
        <div style="overflow-x:auto">
          <table class="msg-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Verified</th>
                <th>Joined</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="usersTableBody">
              <?php while($u = $users->fetch_assoc()): ?>
              <tr id="user-<?= $u['id'] ?>">
                <td><strong>#<?= $u['id'] ?></strong></td>
                <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                <td style="font-size:0.8rem"><?= htmlspecialchars($u['email']) ?></td>
                <td style="font-size:0.8rem"><?= htmlspecialchars($u['phone']) ?></td>
                <td>
                  <?php if($u['email_verified']): ?>
                    <span class="user-badge user-badge-verified">
                      <i class="fas fa-check-circle"></i> Verified
                    </span>
                  <?php else: ?>
                    <span class="user-badge user-badge-pending">
                      <i class="fas fa-clock"></i> Pending
                    </span>
                  <?php endif; ?>
                </td>
                <td class="msg-time"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td>
                  <button class="btn-del" onclick="deleteUser(<?= $u['id'] ?>)">
                    <i class="fas fa-trash"></i>
                  </button>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($showChefStores): ?>
    <!-- PANEL: CHEF STORES (VENDORS) -->
    <div class="panel" id="panel-vendors">
      <div class="card">
        <div class="card-title">
          <i class="fas fa-store"></i> Chef Store Applications
          <span style="margin-left:auto;font-size:0.72rem;font-weight:600;color:#888;">
            <?= $pendingVendors ?> pending approval
          </span>
        </div>
        
        <?php 
        $vendors = $conn->query("SELECT v.*, vs.store_name, vs.is_active 
                                FROM vendors v 
                                LEFT JOIN vendor_stores vs ON v.id = vs.vendor_id 
                                ORDER BY v.created_at DESC");
        ?>
        
        <?php if($vendors->num_rows === 0): ?>
          <div class="empty-state">
            <i class="fas fa-store"></i> No chef applications yet.
          </div>
        <?php else: ?>
        <div style="overflow-x:auto">
          <table class="msg-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Chef</th>
                <th>Store Name</th>
                <th>CNIC</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Status</th>
                <th>Applied</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while($v = $vendors->fetch_assoc()): ?>
              <tr id="vendor-<?= $v['id'] ?>">
                <td><strong>#<?= $v['id'] ?></strong></td>
                <td>
                  <div style="display:flex;align-items:center;gap:0.6rem;">
                    <?php if($v['profile_image']): ?>
                      <img src="<?= htmlspecialchars($v['profile_image']) ?>" 
                           style="width:40px;height:40px;border-radius:50%;object-fit:cover;" 
                           alt="<?= htmlspecialchars($v['name']) ?>" />
                    <?php else: ?>
                      <div style="width:40px;height:40px;border-radius:50%;background:#4169e1;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;">
                        <?= strtoupper(substr($v['name'], 0, 1)) ?>
                      </div>
                    <?php endif; ?>
                    <strong><?= htmlspecialchars($v['name']) ?></strong>
                  </div>
                </td>
                <td><strong><?= htmlspecialchars($v['store_name'] ?? 'N/A') ?></strong></td>
                <td style="font-family:monospace;font-size:0.85rem"><?= htmlspecialchars($v['cnic']) ?></td>
                <td style="font-size:0.8rem"><?= htmlspecialchars($v['phone']) ?></td>
                <td style="font-size:0.8rem"><?= htmlspecialchars($v['email']) ?></td>
                <td>
                  <span class="vendor-badge vendor-badge-<?= $v['status'] ?>" id="vendor-status-<?= $v['id'] ?>">
                    <?php if($v['status'] === 'pending'): ?>
                      <i class="fas fa-clock"></i> Pending
                    <?php elseif($v['status'] === 'approved'): ?>
                      <i class="fas fa-check-circle"></i> Approved
                    <?php elseif($v['status'] === 'rejected'): ?>
                      <i class="fas fa-times-circle"></i> Rejected
                    <?php else: ?>
                      <i class="fas fa-ban"></i> Suspended
                    <?php endif; ?>
                  </span>
                </td>
                <td class="msg-time"><?= date('d M Y', strtotime($v['created_at'])) ?></td>
                <td>
                  <div style="display:flex;gap:0.4rem;">
                    <?php if($v['status'] === 'pending'): ?>
                      <button class="btn-edit" onclick="approveVendor(<?= $v['id'] ?>)" 
                              style="background:#10b981;padding:6px 10px;" 
                              title="Approve">
                        <i class="fas fa-check"></i>
                      </button>
                      <button class="btn-del" onclick="rejectVendor(<?= $v['id'] ?>)" 
                              title="Reject">
                        <i class="fas fa-times"></i>
                      </button>
                    <?php elseif($v['status'] === 'approved'): ?>
                      <button class="btn-edit" onclick="suspendVendor(<?= $v['id'] ?>)" 
                              style="background:#f59e0b;" 
                              title="Suspend">
                        <i class="fas fa-ban"></i>
                      </button>
                    <?php else: ?>
                      <button class="btn-edit" onclick="approveVendor(<?= $v['id'] ?>)" 
                              style="background:#10b981;" 
                              title="Re-approve">
                        <i class="fas fa-redo"></i>
                      </button>
                    <?php endif; ?>
                    <button class="btn-edit" onclick="viewVendorDetails(<?= $v['id'] ?>)" 
                            style="background:#4169e1;" 
                            title="View Details">
                      <i class="fas fa-eye"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  <!-- PANEL: HOMEPAGE SLIDES -->
  <div class="panel" id="panel-slides" style="display:none">
    <div class="card">
      <div class="card-title"><i class="fas fa-plus-circle"></i> Add New Slide</div>
      <form id="addSlideForm" enctype="multipart/form-data">
        <div class="form-grid">
          <div class="form-group">
            <label>Slide Label (optional)</label>
            <input type="text" name="label" id="slideLabel" placeholder="e.g. Zinger Burger" />
          </div>
          <div class="form-group">
            <label>Slide Image</label>
            <input type="file" name="image" id="slideImageInput" accept="image/*" style="display:none" required />
            <div class="img-preview-wrap" id="slideImgPreviewWrap" onclick="$('#slideImageInput').click()">
              <div class="placeholder"><i class="fas fa-image"></i> Click to upload image</div>
            </div>
          </div>
        </div>
        <button type="submit" class="btn-submit" id="addSlideBtn">
          <i class="fas fa-upload"></i> Upload Slide
        </button>
      </form>
    </div>

    <div class="card">
      <div class="card-title"><i class="fas fa-images"></i> Current Slides</div>
      <div id="slidesGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;">
        <div class="empty-state"><i class="fas fa-image"></i> Loading slides...</div>
      </div>
    </div>
  </div>

  <!-- PANEL: SETTINGS -->
  <div class="panel" id="panel-settings" style="display:none">
    
    <!-- Site Branding Card -->
    <div class="card">
      <div class="card-title"><i class="fas fa-palette"></i> Site Branding</div>
      <div class="form-grid">
        <div class="form-group">
          <label><i class="fas fa-heading"></i> Site Name</label>
          <input type="text" id="siteNameInput" placeholder="e.g. SNACKZONE" maxlength="50" />
          <p style="font-size:0.72rem;color:#9ca3af;margin-top:0.4rem">
            This will appear in navbar, hero section, and page titles
          </p>
        </div>
        <div class="form-group">
          <label><i class="fas fa-map-marker-alt"></i> Address</label>
          <input type="text" id="siteAddressInput" placeholder="e.g. 6th Rd, Satellite Town, Rawalpindi" maxlength="200" />
          <p style="font-size:0.72rem;color:#9ca3af;margin-top:0.4rem">
            Displays in footer and contact page
          </p>
        </div>
        <div class="form-group">
          <label><i class="fas fa-phone"></i> Phone Number</label>
          <input type="text" id="sitePhoneInput" placeholder="e.g. 0300 1234567" maxlength="20" />
          <p style="font-size:0.72rem;color:#9ca3af;margin-top:0.4rem">
            Contact number shown in footer (can include spaces/dashes)
          </p>
        </div>
      </div>
      <button onclick="saveSiteBranding()" class="btn-submit" id="saveBrandingBtn" style="margin-top:0.5rem">
        <i class="fas fa-save"></i> Save Branding
      </button>
    </div>

    <!-- Delivery Modal Settings Card -->
    <div class="card">
      <div class="card-title"><i class="fas fa-cog"></i> Delivery Modal Settings</div>
      <div style="padding:1rem 1.4rem">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem;background:#f9f9f9;border:1px solid rgba(0,0,0,0.08);border-radius:8px;margin-bottom:1rem">
          <div>
            <p style="font-weight:700;font-size:0.9rem;margin:0 0 4px;color:#000">Enable Delivery Modal on Homepage</p>
            <p style="font-size:0.75rem;color:#888;margin:0">When enabled, users will see pickup/delivery choice on first visit</p>
          </div>
          <label style="position:relative;display:inline-block;width:54px;height:28px;cursor:pointer">
            <input type="checkbox" id="deliveryModalToggle" style="opacity:0;width:0;height:0" />
            <span style="position:absolute;inset:0;background:#ccc;border-radius:28px;transition:0.3s" class="toggle-slider"></span>
          </label>
        </div>
        <div class="form-group">
          <label>Pickup Location (shown to users)</label>
          <input type="text" id="pickupLocationInput" placeholder="e.g. Snack Zone, Main Street, Karachi" style="width:100%;padding:0.7rem 1rem;border:1px solid rgba(0,0,0,0.15);font-family:'Inter',sans-serif;font-size:0.85rem" />
        </div>
        <button onclick="saveDeliveryModalSettings()" class="btn-submit" id="saveSettingsBtn" style="margin-top:1rem">
          <i class="fas fa-save"></i> Save Settings
        </button>
      </div>
    </div>
  </div>

  <!-- PANEL: WHATSAPP -->
  <div class="panel" id="panel-whatsapp" style="display:none">
    <div class="card" style="max-width:520px">
      <div class="card-title">
        <i class="fas fa-brands fa-whatsapp" style="color:#25D366"></i> WhatsApp Settings
      </div>

      <!-- On/Off Toggle -->
      <div class="form-group" style="margin-bottom:1.5rem">
        <label style="display:flex;align-items:center;justify-content:space-between;cursor:pointer">
          <span><i class="fas fa-toggle-on"></i> Floating WhatsApp Button</span>
          <div id="waBtnToggle" onclick="toggleWaButton()" style="
            width:52px;height:28px;border-radius:28px;background:#e5e7eb;
            position:relative;cursor:pointer;transition:background 0.3s;flex-shrink:0">
            <div id="waBtnThumb" style="
              position:absolute;top:3px;left:3px;
              width:22px;height:22px;border-radius:50%;
              background:#fff;box-shadow:0 2px 6px rgba(0,0,0,0.2);
              transition:left 0.3s"></div>
          </div>
        </label>
        <p style="font-size:0.75rem;color:#9ca3af;margin-top:0.4rem">
          Turn off to hide the floating WhatsApp icon from the website
        </p>
      </div>

      <!-- Input -->
      <div class="form-group" style="margin-bottom:1rem">
        <label>
          <i class="fas fa-phone"></i> New WhatsApp Number
          <span style="font-weight:400;color:#9ca3af;text-transform:none;font-size:0.75rem">
            (international format, no + or spaces)
          </span>
        </label>
        <div style="position:relative">
          <span style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);
                color:#25D366;font-weight:900;font-size:0.95rem">+</span>
          <input type="text" id="waNumberInput" placeholder="923045860472" maxlength="15"
                 style="padding-left:2rem;width:100%" />
        </div>
        <p style="font-size:0.73rem;color:#9ca3af;margin-top:0.4rem">
          Pakistan example: <strong>923001234567</strong> &nbsp;(92 + number without leading 0)
        </p>
      </div>

      <button onclick="saveWaNumber()" class="btn-submit" id="saveWaBtn"
              style="background:linear-gradient(135deg,#25D366,#128C7E);
                     box-shadow:0 4px 12px rgba(37,211,102,0.35)">
        <i class="fas fa-brands fa-whatsapp"></i> Save Number
      </button>
    </div>
  </div>

  <!-- PANEL: RECEPTIONIST MANAGEMENT -->
  <div class="panel" id="panel-receptionist" style="display:none">

    <!-- Current receptionist status card -->
    <div class="card" id="receptCard">
      <div class="card-title"><i class="fas fa-headset"></i> Receptionist Account Status</div>
      <div id="receptStatus">
        <div style="display:flex;align-items:center;gap:1rem;padding:1.2rem;
            background:#fef3c7;border:2px solid #f59e0b;border-radius:12px">
          <div style="width:48px;height:48px;background:#fbbf24;border-radius:50%;
              display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#fff">
            <i class="fas fa-spinner fa-pulse"></i>
          </div>
          <div>
            <p style="font-weight:800;font-size:0.9rem;color:#92400e;margin:0">? Loading receptionist status...</p>
            <p style="font-size:0.78rem;color:#b45309;margin:4px 0 0">Please wait while we check the database</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Create / Update form -->
    <div class="card">
      <div class="card-title" id="receptFormTitle"><i class="fas fa-user-plus"></i> Receptionist Account Form</div>
      <div class="form-grid">
        <div class="form-group">
          <label><i class="fas fa-user"></i> Username</label>
          <input type="text" id="receptUsername" placeholder="e.g. receptionist1" 
                 minlength="3" maxlength="30" autocomplete="off" />
        </div>
        <div class="form-group">
          <label><i class="fas fa-lock"></i> Password <span id="receptPwHint" style="font-weight:400;color:#9ca3af;text-transform:none;font-size:0.75rem">(minimum 6 characters)</span></label>
          <div style="position:relative;width:100%">
            <input type="password" id="receptPassword" placeholder="Enter a strong password" autocomplete="new-password"
                   style="padding-right:3rem;width:100%" />
            <i class="fas fa-eye" id="toggleReceptPw"
               style="position:absolute;right:0.9rem;top:50%;transform:translateY(-50%);
                      color:#9ca3af;cursor:pointer;font-size:0.88rem;transition:color 0.2s;z-index:2"
               onclick="togglePw('receptPassword','toggleReceptPw')"></i>
          </div>
        </div>
      </div>
      <div id="receptFormError" style="display:none;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;
           padding:0.9rem 1.2rem;border-radius:10px;font-size:0.85rem;font-weight:700;margin-top:1rem;
           display:flex;align-items:center;gap:0.6rem;box-shadow:0 4px 12px rgba(239,68,68,0.3)">
        <i class="fas fa-circle-xmark"></i> <span id="receptFormErrorMsg"></span>
      </div>
      <div style="display:flex;gap:0.8rem;align-items:center;margin-top:1.2rem;flex-wrap:wrap">
        <button class="btn-submit" id="receptSaveBtn" onclick="saveReceptionist()">
          <i class="fas fa-save"></i> <span id="receptSaveBtnText">Save Account</span>
        </button>
        <button id="receptDeleteBtn" onclick="deleteReceptionist()"
                style="display:none;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;
                       padding:0.9rem 2rem;border:none;border-radius:10px;font-family:'Inter',sans-serif;
                       font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;
                       cursor:pointer;transition:all 0.3s;box-shadow:0 4px 12px rgba(220,38,38,0.3);
                       display:inline-flex;align-items:center;gap:0.5rem">
          <i class="fas fa-trash"></i> Delete Account
        </button>
      </div>
    </div>
  </div>

  <!-- PANEL: DAILY REPORT -->
  <div class="panel" id="panel-daily" style="display:none">

    <!-- Sub-tabs -->
    <div style="display:flex;gap:0;background:#fff;border-radius:12px;padding:6px;box-shadow:0 2px 8px rgba(0,0,0,0.06);margin-bottom:1.5rem;width:fit-content">
      <button id="rtab-daily" onclick="showReportTab('daily')" style="padding:8px 22px;border:none;border-radius:8px;font-size:0.8rem;font-weight:700;cursor:pointer;background:#1a1a1a;color:#fff;transition:all 0.2s">
        <i class="fas fa-chart-line"></i> Daily
      </button>
      <button id="rtab-weekly" onclick="showReportTab('weekly')" style="padding:8px 22px;border:none;border-radius:8px;font-size:0.8rem;font-weight:700;cursor:pointer;background:transparent;color:#6b7280;transition:all 0.2s">
        <i class="fas fa-calendar-week"></i> Weekly
      </button>
      <button id="rtab-custom" onclick="showReportTab('custom')" style="padding:8px 22px;border:none;border-radius:8px;font-size:0.8rem;font-weight:700;cursor:pointer;background:transparent;color:#6b7280;transition:all 0.2s">
        <i class="fas fa-calendar-range"></i> Custom
      </button>
    </div>

    <!-- -- DAILY SUB-PANEL -- -->
    <div id="rview-daily">

      <div class="card">
        <div class="card-title">
          <i class="fas fa-chart-line"></i> Daily Report
          <span id="dailyShiftDate" style="font-size:0.8rem;font-weight:500;color:#888;margin-left:8px"></span>
          <button onclick="loadDailyReport()" style="margin-left:auto;background:#f5f5f5;border:none;padding:6px 12px;border-radius:8px;cursor:pointer;font-size:0.75rem;font-weight:700">
            <i class="fas fa-rotate-right"></i> Refresh
          </button>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:8px">
          <div style="background:#f0fdf4;border-radius:12px;padding:16px;text-align:center">
            <div style="font-size:0.7rem;font-weight:800;text-transform:uppercase;color:#16a34a;letter-spacing:0.06em;margin-bottom:6px"><i class="fas fa-money-bill-wave"></i> Total Sales</div>
            <div id="dailySales" style="font-size:1.6rem;font-weight:900;color:#16a34a">Rs. 0</div>
          </div>
          <div style="background:#fff7ed;border-radius:12px;padding:16px;text-align:center">
            <div style="font-size:0.7rem;font-weight:800;text-transform:uppercase;color:#ea580c;letter-spacing:0.06em;margin-bottom:6px"><i class="fas fa-boxes-stacked"></i> Material Cost</div>
            <div id="dailyMaterialCost" style="font-size:1.6rem;font-weight:900;color:#ea580c">Rs. 0</div>
          </div>
          <div style="background:#f9fafb;border-radius:12px;padding:16px;text-align:center">
            <div style="font-size:0.7rem;font-weight:800;text-transform:uppercase;color:#6b7280;letter-spacing:0.06em;margin-bottom:6px"><i class="fas fa-bag-shopping"></i> Orders</div>
            <div id="dailyOrders" style="font-size:1.6rem;font-weight:900;color:#6b7280">0</div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-title"><i class="fas fa-boxes-stacked"></i> Add Raw Material</div>
        <input type="hidden" id="currentShiftId" value="" />
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:12px;align-items:end">
          <div class="form-group" style="margin:0"><label>Item Name</label><input type="text" id="matName" placeholder="e.g. Chicken, Mayo..." /></div>
          <div class="form-group" style="margin:0"><label>Quantity</label><input type="number" id="matQty" placeholder="e.g. 3" min="0" step="0.01" oninput="calcMatTotal()" /></div>
          <div class="form-group" style="margin:0"><label>Unit</label>
            <select id="matUnit"><option>kg</option><option>liters</option><option>pieces</option><option>grams</option><option>packets</option></select>
          </div>
          <div class="form-group" style="margin:0"><label>Total Cost (Rs.)</label><input type="number" id="matCost" placeholder="e.g. 1500" min="0" step="0.01" oninput="calcMatTotal()" /></div>
          <button onclick="addMaterial()" class="btn-submit" style="margin:0;padding:10px 20px;white-space:nowrap"><i class="fas fa-plus"></i> Add</button>
        </div>
        <div id="matTotalPreview" style="font-size:0.85rem;color:#6b7280;margin-top:8px"></div>
      </div>

      <div class="card">
        <div class="card-title"><i class="fas fa-list"></i> Today's Raw Materials</div>
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;font-size:0.85rem">
            <thead>
              <tr style="background:#f9fafb;border-bottom:2px solid rgba(0,0,0,0.08)">
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Item</th>
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Qty</th>
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Unit</th>
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Total Cost</th>
                <th style="padding:10px 12px;text-align:center;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Del</th>
              </tr>
            </thead>
            <tbody id="materialsTableBody">
              <tr><td colspan="5" style="text-align:center;padding:24px;color:#aaa">No materials added yet</td></tr>
            </tbody>
            <tfoot>
              <tr style="border-top:2px solid rgba(0,0,0,0.1);background:#f9fafb">
                <td colspan="3" style="padding:10px 12px;font-weight:800;font-size:0.82rem">TOTAL MATERIAL COST</td>
                <td id="matTotalFooter" style="padding:10px 12px;font-weight:900;color:#ea580c">Rs. 0</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-title" style="cursor:pointer" onclick="toggleHistory()">
          <i class="fas fa-history"></i> Shift History (Last 30 Days)
          <i class="fas fa-chevron-down" id="historyChevron" style="margin-left:auto;transition:transform 0.2s"></i>
        </div>
        <div id="historyTableWrap" style="display:none;overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;font-size:0.85rem">
            <thead>
              <tr style="background:#f9fafb;border-bottom:2px solid rgba(0,0,0,0.08)">
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Date</th>
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Sales</th>
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Material</th>
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Profit</th>
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Orders</th>
              </tr>
            </thead>
            <tbody id="historyTableBody">
              <tr><td colspan="5" style="text-align:center;padding:24px;color:#aaa">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- end rview-daily -->

    <!-- -- WEEKLY SUB-PANEL -- -->
    <div id="rview-weekly" style="display:none">

      <div class="card">
        <div class="card-title">
          <i class="fas fa-calendar-week"></i> Weekly Report
          <span id="weeklyDateRange" style="font-size:0.8rem;font-weight:500;color:#888;margin-left:8px"></span>
          <button onclick="loadWeeklyReport()" style="margin-left:auto;background:#f5f5f5;border:none;padding:6px 12px;border-radius:8px;cursor:pointer;font-size:0.75rem;font-weight:700">
            <i class="fas fa-rotate-right"></i> Refresh
          </button>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:8px">
          <div style="background:#f0fdf4;border-radius:12px;padding:16px;text-align:center">
            <div style="font-size:0.7rem;font-weight:800;text-transform:uppercase;color:#16a34a;letter-spacing:0.06em;margin-bottom:6px"><i class="fas fa-money-bill-wave"></i> Total Sales</div>
            <div id="weeklySales" style="font-size:1.6rem;font-weight:900;color:#16a34a">Rs. 0</div>
          </div>
          <div style="background:#fff7ed;border-radius:12px;padding:16px;text-align:center">
            <div style="font-size:0.7rem;font-weight:800;text-transform:uppercase;color:#ea580c;letter-spacing:0.06em;margin-bottom:6px"><i class="fas fa-boxes-stacked"></i> Material Cost</div>
            <div id="weeklyMaterialCost" style="font-size:1.6rem;font-weight:900;color:#ea580c">Rs. 0</div>
          </div>
          <div style="background:#eff6ff;border-radius:12px;padding:16px;text-align:center">
            <div style="font-size:0.7rem;font-weight:800;text-transform:uppercase;color:#2563eb;letter-spacing:0.06em;margin-bottom:6px"><i class="fas fa-chart-line"></i> Gross Profit</div>
            <div id="weeklyProfit" style="font-size:1.6rem;font-weight:900;color:#2563eb">Rs. 0</div>
          </div>
          <div style="background:#f9fafb;border-radius:12px;padding:16px;text-align:center">
            <div style="font-size:0.7rem;font-weight:800;text-transform:uppercase;color:#6b7280;letter-spacing:0.06em;margin-bottom:6px"><i class="fas fa-bag-shopping"></i> Orders</div>
            <div id="weeklyOrders" style="font-size:1.6rem;font-weight:900;color:#6b7280">0</div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-title"><i class="fas fa-table-list"></i> Day-by-Day Breakdown</div>
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;font-size:0.85rem">
            <thead>
              <tr style="background:#f9fafb;border-bottom:2px solid rgba(0,0,0,0.08)">
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Date</th>
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Sales</th>
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Material</th>
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Profit</th>
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Orders</th>
              </tr>
            </thead>
            <tbody id="weeklyBreakdownBody">
              <tr><td colspan="5" style="text-align:center;padding:24px;color:#aaa">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-title"><i class="fas fa-boxes-stacked"></i> This Week's Materials</div>
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;font-size:0.85rem">
            <thead>
              <tr style="background:#f9fafb;border-bottom:2px solid rgba(0,0,0,0.08)">
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Date</th>
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Item</th>
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Qty</th>
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Unit</th>
                <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Cost</th>
              </tr>
            </thead>
            <tbody id="weeklyMaterialsBody">
              <tr><td colspan="5" style="text-align:center;padding:24px;color:#aaa">No materials this week</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- end rview-weekly -->

    <!-- -- CUSTOM SUB-PANEL -- -->
    <div id="rview-custom" style="display:none">

      <div class="card">
        <div class="card-title"><i class="fas fa-calendar-range"></i> Custom Date Range Report</div>
        <p style="font-size:0.82rem;color:#888;margin-bottom:1.2rem">Select any date range to view sales, material costs and inventory expenses.</p>
        <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end;margin-bottom:1rem">
          <div class="form-group" style="margin:0">
            <label><i class="fas fa-calendar-day"></i> From Date</label>
            <input type="date" id="customFromDate" style="width:100%;padding:10px 12px;border:2px solid rgba(0,0,0,0.1);border-radius:8px;font-family:inherit;font-size:0.9rem;outline:none" />
          </div>
          <div class="form-group" style="margin:0">
            <label><i class="fas fa-calendar-day"></i> To Date</label>
            <input type="date" id="customToDate" style="width:100%;padding:10px 12px;border:2px solid rgba(0,0,0,0.1);border-radius:8px;font-family:inherit;font-size:0.9rem;outline:none" />
          </div>
          <button onclick="loadCustomReport()" class="btn-submit" style="margin:0;padding:10px 24px;white-space:nowrap">
            <i class="fas fa-magnifying-glass"></i> Search
          </button>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:1.5rem">
          <button onclick="setPreset(7)"  style="background:#f5f5f5;border:none;padding:6px 14px;border-radius:20px;font-size:0.75rem;font-weight:700;cursor:pointer">Last 7 Days</button>
          <button onclick="setPreset(15)" style="background:#f5f5f5;border:none;padding:6px 14px;border-radius:20px;font-size:0.75rem;font-weight:700;cursor:pointer">Last 15 Days</button>
          <button onclick="setPreset(30)" style="background:#f5f5f5;border:none;padding:6px 14px;border-radius:20px;font-size:0.75rem;font-weight:700;cursor:pointer">Last 30 Days</button>
          <button onclick="setPreset(0,'month')" style="background:#f5f5f5;border:none;padding:6px 14px;border-radius:20px;font-size:0.75rem;font-weight:700;cursor:pointer">This Month</button>
        </div>

        <div id="customStatsWrap" style="display:none">
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:1.5rem">
            <div style="background:#f0fdf4;border-radius:12px;padding:16px;text-align:center">
              <div style="font-size:0.7rem;font-weight:800;text-transform:uppercase;color:#16a34a;letter-spacing:0.06em;margin-bottom:6px"><i class="fas fa-money-bill-wave"></i> Total Sales</div>
              <div id="customSales" style="font-size:1.6rem;font-weight:900;color:#16a34a">Rs. 0</div>
            </div>
            <div style="background:#fff7ed;border-radius:12px;padding:16px;text-align:center">
              <div style="font-size:0.7rem;font-weight:800;text-transform:uppercase;color:#ea580c;letter-spacing:0.06em;margin-bottom:6px"><i class="fas fa-boxes-stacked"></i> Material Cost</div>
              <div id="customMaterialCost" style="font-size:1.6rem;font-weight:900;color:#ea580c">Rs. 0</div>
            </div>
            <div style="background:#eff6ff;border-radius:12px;padding:16px;text-align:center">
              <div style="font-size:0.7rem;font-weight:800;text-transform:uppercase;color:#2563eb;letter-spacing:0.06em;margin-bottom:6px"><i class="fas fa-chart-line"></i> Gross Profit</div>
              <div id="customProfit" style="font-size:1.6rem;font-weight:900;color:#2563eb">Rs. 0</div>
            </div>
            <div style="background:#f9fafb;border-radius:12px;padding:16px;text-align:center">
              <div style="font-size:0.7rem;font-weight:800;text-transform:uppercase;color:#6b7280;letter-spacing:0.06em;margin-bottom:6px"><i class="fas fa-bag-shopping"></i> Orders</div>
              <div id="customOrders" style="font-size:1.6rem;font-weight:900;color:#6b7280">0</div>
            </div>
          </div>
          <div style="font-weight:800;font-size:0.9rem;margin-bottom:1rem;padding-bottom:8px;border-bottom:2px solid rgba(0,0,0,0.08)"><i class="fas fa-boxes-stacked"></i> Materials / Inventory in This Period</div>
          <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:0.85rem">
              <thead>
                <tr style="background:#f9fafb;border-bottom:2px solid rgba(0,0,0,0.08)">
                  <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Date</th>
                  <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Item</th>
                  <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Qty</th>
                  <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Unit</th>
                  <th style="padding:10px 12px;text-align:left;font-size:0.72rem;text-transform:uppercase;color:#6b7280">Cost</th>
                </tr>
              </thead>
              <tbody id="customMaterialsBody">
                <tr><td colspan="5" style="text-align:center;padding:24px;color:#aaa">No materials found</td></tr>
              </tbody>
              <tfoot>
                <tr style="border-top:2px solid rgba(0,0,0,0.1);background:#f9fafb">
                  <td colspan="4" style="padding:10px 12px;font-weight:800;font-size:0.82rem">TOTAL MATERIAL COST</td>
                  <td id="customMatTotalFooter" style="padding:10px 12px;font-weight:900;color:#ea580c">Rs. 0</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
        <div id="customNoData" style="text-align:center;padding:3rem;color:#aaa;display:none">
          <i class="fas fa-calendar-xmark" style="font-size:2rem;margin-bottom:0.5rem;display:block"></i>
          No data found for selected date range
        </div>
      </div>

    </div><!-- end rview-custom -->

  </div><!-- end panel-daily -->
<!-- ======= POS PANEL ======= -->
<div id="panel-pos" class="panel" style="display:none">
  <div id="pos-container" style="display:flex;height:calc(100vh - 140px);gap:0;border-radius:16px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.12);background:#fff">

    <!-- LEFT: Menu (60% desktop, 100% mobile) -->
    <div id="pos-menu-section" style="flex:0 0 60%;display:flex;flex-direction:column;background:#fff;overflow:hidden;border-right:3px solid #f0f0f0">
      <!-- Top bar -->
      <div style="background:linear-gradient(to bottom, #fff 0%, #f8f9fa 100%);padding:16px;border-bottom:2px solid #e5e7eb">
        <div style="position:relative;margin-bottom:12px">
          <i class="fas fa-search" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:0.9rem"></i>
          <input id="pos-search" type="text" placeholder="Search menu items..." oninput="posSearch()"
            style="width:100%;padding:11px 14px 11px 38px;border:2px solid #e5e7eb;border-radius:10px;font-size:0.88rem;outline:none;font-family:inherit;box-sizing:border-box;transition:all 0.2s"
            onfocus="this.style.borderColor='#3b82f6';this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)'"
            onblur="this.style.borderColor='#e5e7eb';this.style.boxShadow='none'">
        </div>
        <div id="pos-cats" style="display:flex;gap:8px;overflow-x:auto;padding-bottom:2px;scrollbar-width:thin;scrollbar-color:#d1d5db #f3f4f6"></div>
      </div>
      <!-- Grid -->
      <div id="pos-grid" style="flex:1;overflow-y:auto;padding:16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:14px;align-content:start;background:#f8f9fa">
        <!-- Menu items will be loaded here by JavaScript -->
      </div>
      
      <!-- Mobile Cart Toggle Button (only visible on mobile) -->
      <button id="mobile-cart-toggle" onclick="toggleMobileCart()" 
        style="display:none;position:fixed;bottom:20px;right:20px;width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg, #16a34a 0%, #15803d 100%);color:#fff;border:none;box-shadow:0 8px 24px rgba(22,163,74,0.4);cursor:pointer;z-index:1000;font-size:1.2rem">
        <i class="fas fa-shopping-cart"></i>
        <span id="mobile-cart-count" style="position:absolute;top:-5px;right:-5px;background:#ef4444;color:#fff;border-radius:50%;width:24px;height:24px;display:none;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700"></span>
      </button>
    </div>

    <!-- RIGHT: Cart + Bill (40% desktop, overlay on mobile) -->
    <div id="pos-cart-section" style="flex:0 0 40%;display:flex;flex-direction:column;background:#fff">
      
      <!-- Mobile Close Header (only visible on mobile) -->
      <div id="mobile-cart-header" style="display:none;background:#f8f9fa;padding:12px 16px;border-bottom:2px solid #e5e7eb;align-items:center;justify-content:space-between">
        <h3 style="margin:0;font-size:1rem;font-weight:700;color:#111">Your Cart</h3>
        <button onclick="toggleMobileCart()" style="background:none;border:none;font-size:1.3rem;color:#6b7280;cursor:pointer;padding:4px 8px">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <!-- Header tabs -->
    <div style="flex:0 0 40%;display:flex;flex-direction:column;background:#fff">
      
      <!-- Header tabs -->
      <div style="display:flex;border-bottom:2px solid #e5e7eb;background:#f9fafb;position:relative">
        <!-- Mobile Close Button (only visible on mobile) -->
        <button onclick="toggleMobileCart()" 
          style="display:none;position:absolute;left:10px;top:50%;transform:translateY(-50%);background:#f3f4f6;border:none;width:36px;height:36px;border-radius:8px;cursor:pointer;z-index:10"
          id="mobile-cart-close">
          <i class="fas fa-times" style="font-size:1.1rem;color:#111"></i>
        </button>
        
        <button id="pos-tab-walkin" onclick="posSwitchTab('walkin')"
          style="flex:1;padding:16px 12px;border:none;background:#111;color:#fff;font-weight:800;font-size:0.72rem;cursor:pointer;font-family:inherit;letter-spacing:0.05em;text-transform:uppercase;transition:all 0.2s;position:relative">
          <i class="fas fa-cash-register" style="margin-right:5px;font-size:0.85rem"></i>
          <span>Walk-in</span>
        </button>
        <button id="pos-tab-online" onclick="posSwitchTab('online')"
          style="flex:1;padding:16px 12px;border:none;background:#f3f4f6;color:#6b7280;font-weight:800;font-size:0.72rem;cursor:pointer;font-family:inherit;letter-spacing:0.05em;text-transform:uppercase;transition:all 0.2s">
          <i class="fas fa-globe" style="margin-right:5px;font-size:0.85rem"></i>
          <span>Online</span>
        </button>
        <button id="pos-tab-history" onclick="posSwitchTab('history')"
          style="flex:1;padding:16px 12px;border:none;background:#f3f4f6;color:#6b7280;font-weight:800;font-size:0.72rem;cursor:pointer;font-family:inherit;letter-spacing:0.05em;text-transform:uppercase;transition:all 0.2s">
          <i class="fas fa-history" style="margin-right:5px;font-size:0.85rem"></i>
          <span>History</span>
        </button>
      </div>

      <!-- WALK-IN SECTION -->
      <div id="pos-walkin-section" style="display:flex;flex-direction:column;flex:1">
      <!-- Customer form -->
      <div style="padding:14px 16px;border-bottom:2px solid #e5e7eb;background:linear-gradient(to bottom, #fff 0%, #fafbfc 100%)">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
          <input id="pos-cname" type="text" placeholder="Customer Name"
            style="padding:10px 12px;border:2px solid #e5e7eb;border-radius:8px;font-size:0.85rem;outline:none;font-family:inherit;transition:all 0.2s"
            onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
          <input id="pos-cphone" type="text" placeholder="Phone"
            style="padding:10px 12px;border:2px solid #e5e7eb;border-radius:8px;font-size:0.85rem;outline:none;font-family:inherit;transition:all 0.2s"
            onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
        </div>
        <div id="pos-address-row" style="margin-bottom:10px;display:none">
          <input id="pos-caddress" type="text" placeholder="Delivery Address"
            style="width:100%;padding:10px 12px;border:2px solid #e5e7eb;border-radius:8px;font-size:0.85rem;outline:none;font-family:inherit;box-sizing:border-box;transition:all 0.2s"
            onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <label style="font-size:0.8rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;padding:6px 12px;border:2px solid #e5e7eb;border-radius:6px;transition:all 0.2s"
            onmouseover="this.style.borderColor='#3b82f6';this.style.background='#eff6ff'" 
            onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='#e5e7eb';this.style.background='transparent'}">
            <input type="radio" name="pos-type" value="Dine-in" checked onchange="posTypeChange(this.value);this.parentElement.style.borderColor='#3b82f6';this.parentElement.style.background='#eff6ff'"> 
            <i class="fas fa-utensils" style="font-size:0.75rem"></i> Dine-in
          </label>
          <label style="font-size:0.8rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;padding:6px 12px;border:2px solid #e5e7eb;border-radius:6px;transition:all 0.2s"
            onmouseover="this.style.borderColor='#3b82f6';this.style.background='#eff6ff'" 
            onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='#e5e7eb';this.style.background='transparent'}">
            <input type="radio" name="pos-type" value="Takeaway" onchange="posTypeChange(this.value);this.parentElement.style.borderColor='#3b82f6';this.parentElement.style.background='#eff6ff'"> 
            <i class="fas fa-shopping-bag" style="font-size:0.75rem"></i> Takeaway
          </label>
          <label style="font-size:0.8rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;padding:6px 12px;border:2px solid #e5e7eb;border-radius:6px;transition:all 0.2s"
            onmouseover="this.style.borderColor='#3b82f6';this.style.background='#eff6ff'" 
            onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='#e5e7eb';this.style.background='transparent'}">
            <input type="radio" name="pos-type" value="Delivery" onchange="posTypeChange(this.value);this.parentElement.style.borderColor='#3b82f6';this.parentElement.style.background='#eff6ff'"> 
            <i class="fas fa-motorcycle" style="font-size:0.75rem"></i> Delivery
          </label>
        </div>
      </div>

      <!-- Cart items -->
      <div id="pos-cart" style="flex:1;overflow-y:auto;padding:12px 16px;background:#fafbfc">
        <div id="pos-cart-empty" style="text-align:center;padding:40px 0;color:#ccc">
          <i class="fas fa-shopping-basket" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:0.3"></i>
          <span style="font-size:0.88rem;font-weight:600">Cart is empty</span>
          <p style="font-size:0.75rem;margin-top:6px">Add items from menu</p>
        </div>
      </div>

      <!-- Totals -->
      <div style="padding:14px 16px;border-top:3px solid #e5e7eb;background:#fff;box-shadow:0 -4px 12px rgba(0,0,0,0.05)">
        <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:#6b7280;margin-bottom:6px;font-weight:600">
          <span>Subtotal</span><span id="pos-sub" style="color:#111">Rs. 0</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-weight:900;font-size:1.25rem;background:linear-gradient(135deg, #111 0%, #1f1f1f 100%);color:#fff;padding:14px 16px;border-radius:10px;margin-bottom:12px;box-shadow:0 4px 12px rgba(0,0,0,0.2)">
          <span>TOTAL</span><span id="pos-total">Rs. 0</span>
        </div>
        <div style="display:flex;gap:10px">
          <button onclick="posPrint()" style="flex:1;padding:12px;background:linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);color:#fff;border:none;border-radius:10px;font-weight:700;font-size:0.82rem;cursor:pointer;font-family:inherit;transition:all 0.2s;box-shadow:0 4px 12px rgba(37,99,235,0.3)"
            onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(37,99,235,0.4)'" 
            onmouseout="this.style.transform='none';this.style.boxShadow='0 4px 12px rgba(37,99,235,0.3)'">
            <i class="fas fa-print" style="margin-right:6px"></i>Print Bill
          </button>
          <button onclick="posSave()" style="flex:1;padding:12px;background:linear-gradient(135deg, #16a34a 0%, #15803d 100%);color:#fff;border:none;border-radius:10px;font-weight:700;font-size:0.82rem;cursor:pointer;font-family:inherit;transition:all 0.2s;box-shadow:0 4px 12px rgba(22,163,74,0.3)"
            onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(22,163,74,0.4)'" 
            onmouseout="this.style.transform='none';this.style.boxShadow='0 4px 12px rgba(22,163,74,0.3)'">
            <i class="fas fa-save" style="margin-right:6px"></i>Save Order
          </button>
        </div>
      </div>
      </div>
      <!-- END WALK-IN SECTION -->

      <!-- ONLINE ORDERS SECTION -->
      <div id="pos-online-section" style="display:none;flex-direction:column;flex:1;overflow:hidden">
        <div style="padding:14px;background:#f8f9fa;border-bottom:1px solid #e5e7eb">
          <div style="font-size:0.85rem;font-weight:700;color:#111;margin-bottom:4px">
            <i class="fas fa-clock" style="margin-right:6px;color:#f59e0b"></i>Pending Online Orders
          </div>
          <div style="font-size:0.72rem;color:#6b7280">Click on an order to load it into cart</div>
        </div>
        <div id="pos-online-orders-list" style="flex:1;overflow-y:auto;padding:14px;background:#f8f9fa">
          <div style="text-align:center;padding:40px 20px;color:#aaa">
            <i class="fas fa-spinner fa-spin" style="font-size:2rem;display:block;margin-bottom:10px"></i>
            Loading orders...
          </div>
        </div>
      </div>
      <!-- END ONLINE ORDERS SECTION -->

      <!-- BILL HISTORY SECTION -->
      <div id="pos-history-section" style="display:none;flex-direction:column;flex:1;overflow:hidden">
        <div style="padding:14px;background:#f8f9fa;border-bottom:1px solid #e5e7eb">
          <div style="font-size:0.85rem;font-weight:700;color:#111;margin-bottom:4px">
            <i class="fas fa-receipt" style="margin-right:6px;color:#3b82f6"></i>Today's Bill History
          </div>
          <div style="font-size:0.72rem;color:#6b7280">All saved orders from today</div>
        </div>
        <div id="pos-history-list" style="flex:1;overflow-y:auto;padding:14px;background:#f8f9fa">
          <div style="text-align:center;padding:40px 20px;color:#aaa">
            <i class="fas fa-spinner fa-spin" style="font-size:2rem;display:block;margin-bottom:10px"></i>
            Loading history...
          </div>
        </div>
      </div>
      <!-- END BILL HISTORY SECTION -->
        </div>
      </div>

    </div>
  </div>
</div>
<!-- end panel-pos -->

<!-- ======= PROFIT ANALYSIS PANEL ======= -->
<div id="panel-profit" class="panel" style="display:none">
  
  <!-- Stats Cards - Matching Dashboard Style -->
  <div class="stats" style="grid-template-columns:repeat(4,1fr);margin-bottom:2rem">
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%)">
        <i class="fas fa-money-bill-wave"></i>
      </div>
      <div>
        <div class="stat-label">Total Sales</div>
        <div class="stat-num" id="profit-total-sales">Rs. 0</div>
        <div class="stat-desc">Completed orders</div>
      </div>
    </div>
    
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg, #dc2626 0%, #b91c1c 100%)">
        <i class="fas fa-shopping-basket"></i>
      </div>
      <div>
        <div class="stat-label">Raw Material Cost</div>
        <div class="stat-num" id="profit-raw-cost">Rs. 0</div>
        <div class="stat-desc">Purchase expenses</div>
      </div>
    </div>
    
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%)">
        <i class="fas fa-chart-line"></i>
      </div>
      <div>
        <div class="stat-label">Net Profit</div>
        <div class="stat-num" id="profit-net-profit" style="color:#10b981">Rs. 0</div>
        <div class="stat-desc" id="profit-margin-desc">0% margin</div>
      </div>
    </div>
    
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)">
        <i class="fas fa-receipt"></i>
      </div>
      <div>
        <div class="stat-label">Total Orders</div>
        <div class="stat-num" id="profit-order-count">0</div>
        <div class="stat-desc">Completed</div>
      </div>
    </div>
  </div>

  <!-- Filter Section -->
  <div style="background:#fff;border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:1.5rem;margin-bottom:2rem;box-shadow:0 2px 8px rgba(0,0,0,0.04)">
    <div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
      <div style="flex:1;min-width:200px">
        <label style="display:block;font-size:0.75rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem">
          <i class="fas fa-calendar-alt" style="margin-right:6px"></i>Date Range
        </label>
        <select id="profit-filter-select" onchange="handleProfitFilterChange()" 
          style="width:100%;padding:0.7rem 1rem;border:2px solid #e5e7eb;border-radius:10px;font-size:0.9rem;background:#fff;font-weight:600;cursor:pointer;transition:all 0.2s;font-family:inherit">
          <option value="today">Today</option>
          <option value="week">This Week</option>
          <option value="month">This Month</option>
          <option value="custom">Custom Range</option>
        </select>
      </div>
      <div id="profit-custom-dates" style="display:none;flex:2;min-width:400px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div>
            <label style="display:block;font-size:0.75rem;font-weight:700;color:#6b7280;margin-bottom:0.5rem">From Date</label>
            <input type="date" id="profit-from-date" 
              style="width:100%;padding:0.7rem 1rem;border:2px solid #e5e7eb;border-radius:10px;font-size:0.9rem;font-family:inherit" />
          </div>
          <div>
            <label style="display:block;font-size:0.75rem;font-weight:700;color:#6b7280;margin-bottom:0.5rem">To Date</label>
            <input type="date" id="profit-to-date" 
              style="width:100%;padding:0.7rem 1rem;border:2px solid #e5e7eb;border-radius:10px;font-size:0.9rem;font-family:inherit" />
          </div>
        </div>
      </div>
      <button onclick="loadProfitSummary()" 
        style="padding:0.7rem 1.8rem;background:linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%);color:#fff;border:none;border-radius:10px;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,0.2);transition:all 0.2s;font-family:inherit;font-size:0.9rem"
        onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
        <i class="fas fa-sync-alt" style="margin-right:8px"></i>Refresh
      </button>
    </div>
  </div>

  <!-- Daily Breakdown Table -->
  <div style="background:#fff;border:1px solid rgba(0,0,0,0.06);border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.04)">
    <div style="padding:1.5rem;border-bottom:2px solid #f3f4f6;background:linear-gradient(to bottom, #fff 0%, #f9fafb 100%)">
      <h3 style="margin:0;font-size:1.1rem;color:#1a1a1a;font-weight:700;display:flex;align-items:center;gap:10px">
        <i class="fas fa-table"></i> Daily Profit Breakdown
      </h3>
    </div>
    <div id="profit-daily-table" style="overflow-x:auto">
      <div style="text-align:center;padding:3rem;color:#9ca3af">
        <i class="fas fa-spinner fa-spin" style="font-size:2rem;display:block;margin-bottom:1rem"></i>
        <span style="font-size:0.9rem;font-weight:600">Loading data...</span>
      </div>
    </div>
  </div>
  
</div>
<!-- end panel-profit -->

<!-- ======= SUPPLIERS PANEL ======= -->
<div id="panel-suppliers" class="panel" style="display:none">
  
  <!-- Add Supplier Section -->
  <div style="background:#fff;border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:2rem;margin-bottom:2rem;box-shadow:0 2px 8px rgba(0,0,0,0.04)">
    <h3 style="margin:0 0 1.5rem 0;font-size:1.1rem;color:#1a1a1a;font-weight:700;display:flex;align-items:center;gap:10px">
      <i class="fas fa-plus-circle"></i> Add New Supplier
    </h3>
    
    <form id="addSupplierForm" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem">
      <div>
        <label style="display:block;font-size:0.8rem;font-weight:700;color:#6b7280;margin-bottom:0.5rem">
          <i class="fas fa-user"></i> Supplier Name *
        </label>
        <input type="text" id="supplier-name" placeholder="e.g. ABC Traders" required
          style="width:100%;padding:0.7rem 1rem;border:2px solid #e5e7eb;border-radius:8px;font-size:0.9rem" />
      </div>
      
      <div>
        <label style="display:block;font-size:0.8rem;font-weight:700;color:#6b7280;margin-bottom:0.5rem">
          <i class="fas fa-phone"></i> Phone Number *
        </label>
        <input type="tel" id="supplier-phone" placeholder="0300 1234567" required
          style="width:100%;padding:0.7rem 1rem;border:2px solid #e5e7eb;border-radius:8px;font-size:0.9rem" />
      </div>
      
      <div>
        <label style="display:block;font-size:0.8rem;font-weight:700;color:#6b7280;margin-bottom:0.5rem">
          <i class="fas fa-map-marker-alt"></i> Location
        </label>
        <input type="text" id="supplier-location" placeholder="e.g. Saddar, Rawalpindi"
          style="width:100%;padding:0.7rem 1rem;border:2px solid #e5e7eb;border-radius:8px;font-size:0.9rem" />
      </div>
      
      <div>
        <label style="display:block;font-size:0.8rem;font-weight:700;color:#6b7280;margin-bottom:0.5rem">
          <i class="fas fa-boxes"></i> Items Supplied
        </label>
        <input type="text" id="supplier-items" placeholder="e.g. Chicken, Oil, Flour"
          style="width:100%;padding:0.7rem 1rem;border:2px solid #e5e7eb;border-radius:8px;font-size:0.9rem" />
      </div>
      
      <div style="display:flex;align-items:flex-end">
        <button type="submit"
          style="width:100%;padding:0.7rem 1.5rem;background:#000;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;transition:all 0.2s"
          onmouseover="this.style.background='#333'" onmouseout="this.style.background='#000'">
          <i class="fas fa-save"></i> Add Supplier
        </button>
      </div>
    </form>
  </div>

  <!-- Suppliers List -->
  <div style="background:#fff;border:1px solid rgba(0,0,0,0.06);border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.04)">
    <div style="padding:1.5rem;border-bottom:2px solid #f3f4f6;background:linear-gradient(to bottom, #fff 0%, #f9fafb 100%)">
      <h3 style="margin:0;font-size:1.1rem;color:#1a1a1a;font-weight:700;display:flex;align-items:center;gap:10px">
        <i class="fas fa-list"></i> All Suppliers
      </h3>
    </div>
    <div id="suppliers-list" style="overflow-x:auto">
      <div style="text-align:center;padding:3rem;color:#9ca3af">
        <i class="fas fa-spinner fa-spin" style="font-size:2rem;display:block;margin-bottom:1rem"></i>
        <span style="font-size:0.9rem;font-weight:600">Loading suppliers...</span>
      </div>
    </div>
  </div>
  
</div>
<!-- end panel-suppliers -->

  </main>

  <!-- EDIT MODAL -->
  <div class="modal-overlay" id="editModal">
    <div class="modal-box">
      <div class="modal-header">
        <h3><i class="fas fa-pen"></i> Edit Menu Item</h3>
        <button class="btn-modal-close" onclick="closeEditModal()"><i class="fas fa-times"></i></button>
      </div>
      <form id="editItemForm" enctype="multipart/form-data">
        <input type="hidden" id="editItemId" name="id" />
        <div class="form-grid">
          <div class="form-group">
            <label>Item Title</label>
            <input type="text" name="title" id="editTitle" required />
          </div>
          <div class="form-group">
            <label>Category</label>
            <select name="category" id="editCategory" required>
              <option value="">Select category...</option>
              <?php foreach ($categoriesList as $cat): ?>
                <option value="<?= htmlspecialchars($cat['slug']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Price (Rs.)</label>
            <input type="number" name="price" id="editPrice" min="1" required />
          </div>
          <div class="form-group">
            <label>Discount (%) <span style="font-weight:400; color:#999; text-transform:none;">(Optional)</span></label>
            <input type="number" name="discount_percent" id="editDiscount" placeholder="0-99" min="0" max="99" value="0" />
          </div>
          <div class="form-group">
            <label>Item Image (blank = keep current)</label>
            <input type="file" name="image" id="editImageInput" accept="image/*" style="display:none" />
            <div class="modal-img-preview" onclick="$('#editImageInput').click()">
              <img id="editImgPreview" src="" alt="" />
            </div>
            <div class="modal-img-hint"><i class="fas fa-camera"></i> Click image to change</div>
          </div>
          <div class="form-group full">
            <label>Description</label>
            <textarea name="description" id="editDescription" rows="3" required></textarea>
          </div>
        </div>
        <div style="display:flex;gap:0.8rem;margin-top:0.5rem">
          <button type="submit" class="btn-submit" id="editSaveBtn" style="flex:1"><i class="fas fa-save"></i> Save Changes</button>
          <button type="button" onclick="closeEditModal()" style="padding:0.8rem 1.5rem;border:1px solid rgba(0,0,0,0.18);background:#fff;font-family:'Inter',sans-serif;font-size:0.9rem;font-weight:600;cursor:pointer;color:#888;">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ADDONS MODAL -->
  <div class="modal-overlay" id="addonsModal">
    <div class="modal-box" style="max-width:600px;border-radius:16px;overflow:hidden;">
      <!-- Header -->
      <div class="modal-header" style="background:#4169e1;color:#fff;padding:1.5rem 1.8rem;position:relative;">
        <div style="display:flex;align-items:center;gap:12px;">
          <div style="width:40px;height:40px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-list-ul" style="font-size:1.1rem;"></i>
          </div>
          <div>
            <h3 style="margin:0;font-size:1.3rem;font-weight:900;letter-spacing:-0.01em;">ADD-ONS</h3>
            <p style="margin:4px 0 0;font-size:0.85rem;opacity:0.9;font-weight:600;" id="addonsItemTitle"></p>
          </div>
        </div>
        <button class="btn-modal-close" onclick="closeAddonsModal()" style="position:absolute;top:1.2rem;right:1.2rem;width:36px;height:36px;background:rgba(255,255,255,0.2);border:none;border-radius:50%;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.2s;">
          <i class="fas fa-times" style="font-size:1rem;"></i>
        </button>
      </div>

      <div style="padding:1.8rem 1.8rem 2rem;">
        <!-- Add new addon form -->
        <div style="background:#f8f9fa;border:2px dashed rgba(65,105,225,0.2);border-radius:12px;padding:1.2rem;margin-bottom:1.5rem;">
          <div style="display:flex;gap:0.8rem;align-items:flex-end;flex-wrap:wrap;">
            <div style="flex:2;min-width:200px;">
              <label style="font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.1em;color:#6b7280;display:block;margin-bottom:6px;">
                ADD-ON NAME
              </label>
              <input type="text" id="addonName" placeholder="e.g. Soft Drink, Extra Sauce"
                style="width:100%;padding:0.75rem 1rem;border:1.5px solid rgba(0,0,0,0.12);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.9rem;outline:none;transition:border 0.2s;"
                onfocus="this.style.borderColor='#4169e1'"
                onblur="this.style.borderColor='rgba(0,0,0,0.12)'" />
            </div>
            <div style="flex:1;min-width:120px;">
              <label style="font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.1em;color:#6b7280;display:block;margin-bottom:6px;">
                PRICE (RS.)
              </label>
              <input type="number" id="addonPrice" placeholder="0" min="0" step="1"
                style="width:100%;padding:0.75rem 1rem;border:1.5px solid rgba(0,0,0,0.12);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.9rem;outline:none;transition:border 0.2s;"
                onfocus="this.style.borderColor='#4169e1'"
                onblur="this.style.borderColor='rgba(0,0,0,0.12)'" />
            </div>
            <button onclick="addAddon()" style="background:#4169e1;color:#fff;border:none;padding:0.75rem 1.5rem;font-family:'Inter',sans-serif;font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;cursor:pointer;border-radius:8px;display:flex;align-items:center;gap:6px;transition:transform 0.2s,box-shadow 0.2s;box-shadow:0 4px 12px rgba(65,105,225,0.3);"
              onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(65,105,225,0.4)';this.style.background='#3557c7'"
              onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 12px rgba(65,105,225,0.3)';this.style.background='#4169e1'">
              <i class="fas fa-plus"></i> ADD
            </button>
          </div>
        </div>

        <!-- Addons list -->
        <div style="margin-bottom:8px;">
          <p style="font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.1em;color:#6b7280;margin:0 0 10px;">
            CURRENT ADD-ONS
          </p>
        </div>
        <div id="addonsList" style="display:flex;flex-direction:column;gap:0.6rem;min-height:80px;max-height:300px;overflow-y:auto;">
          <div class="empty-state" style="padding:2rem;text-align:center;color:#9ca3af;">
            <i class="fas fa-list" style="font-size:2rem;opacity:0.3;margin-bottom:0.5rem;display:block;"></i>
            <p style="margin:0;font-size:0.85rem;font-weight:600;">No add-ons yet</p>
            <p style="margin:4px 0 0;font-size:0.75rem;opacity:0.7;">Add your first add-on above</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>

  <script>
    // Mobile Menu Toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function toggleSidebar() {
      sidebar.classList.toggle('active');
      sidebarOverlay.classList.toggle('active');
      
      // Update button icon
      const icon = mobileMenuBtn.querySelector('i');
      if (sidebar.classList.contains('active')) {
        icon.classList.remove('fa-bars');
        icon.classList.add('fa-times');
      } else {
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
      }
    }

    function closeSidebar() {
      sidebar.classList.remove('active');
      sidebarOverlay.classList.remove('active');
      const icon = mobileMenuBtn.querySelector('i');
      icon.classList.remove('fa-times');
      icon.classList.add('fa-bars');
    }

    mobileMenuBtn.addEventListener('click', toggleSidebar);
    sidebarOverlay.addEventListener('click', closeSidebar);

    // Close sidebar when clicking on any nav button (mobile)
    document.querySelectorAll('.nav-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
          closeSidebar();
        }
      });
    });

    let allCategories = <?= json_encode($categoriesList) ?>;
    let currentAddonItemId = null;

    function showPanel(name, el) {
      $('.panel').removeClass('active').hide();
      $('.nav-btn').removeClass('active');
      $('#panel-' + name).addClass('active').show();
      $(el).addClass('active');

      // Hide stats bar on daily report, show on all others
      if (name === 'daily') {
        $('#mainStats').hide();
      } else {
        $('#mainStats').show();
      }      const titles = {
        dashboard:    ['Dashboard',             'Overview of your restaurant stats'],
        menu:         ['Menu Manager',          'Add, view and manage menu items'],
        categories:   ['Category Manager',      'Create and delete menu categories'],
        messages:     ['Messages',              'Contact form submissions from users'],
        bookings:     ['Event Bookings',        'Catering and event booking requests'],
        orders:       ['Today\'s Orders',       'View and manage orders received today'],
        users:        ['Users',                 'View and manage registered users'],
        slides:       ['Homepage Slides',       'Upload and manage homepage carousel images'],
        receptionist: ['Receptionist Account',  'Create and manage the receptionist login'],
        whatsapp:     ['WhatsApp Number',       'Set the WhatsApp number shown on your website'],
        daily:        ['Reports',               'Daily, Weekly and Custom date reports'],
        weekly:       ['Reports',               'Daily, Weekly and Custom date reports'],
        custom:       ['Reports',               'Daily, Weekly and Custom date reports'],
        pos:          ['Point of Sale',         'Walk-in orders, billing and receipt printing'],
      };
      if (titles[name]) {
        $('#pageTitle').text(titles[name][0]);
        $('#pageSubtitle').text(titles[name][1]);
      }
      if (name === 'slides') loadSlides();
      if (name === 'pos') loadPOS();
      if (name === 'profit') loadProfitSummary();
      if (name === 'suppliers') loadSuppliers();
      if (name === 'receptionist') {        console.log('?? Receptionist panel opened - calling loadReceptionist()');
        // Small delay to ensure panel is visible
        setTimeout(() => loadReceptionist(), 100);
      }
      if (name === 'whatsapp') loadWaNumber();
      if (name === 'daily') loadDailyReport();
      if (name === 'weekly') loadWeeklyReport();
      if (name === 'custom') initCustomReport();    }

    // -- Slides --------------------------------------------
    const SITE_ROOT = '<?= rtrim(str_replace('/Admin', '', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].(dirname(dirname($_SERVER['SCRIPT_NAME'])))), '/') ?>/';

    function loadSlides() {
      $.getJSON('get_slides.php', function(res) {
        const grid = $('#slidesGrid');
        grid.empty();
        if (!res.slides || res.slides.length === 0) {
          grid.html('<div class="empty-state"><i class="fas fa-image"></i> No slides yet. Upload one above.</div>');
          return;
        }
        res.slides.forEach(function(s) {
          const imgUrl = SITE_ROOT + s.image_path;
          grid.append(`
            <div id="slide-card-${s.id}" style="border:1px solid rgba(0,0,0,0.1);overflow:hidden;background:#fff;">
              <img src="${imgUrl}" alt="${s.label}" style="width:100%;height:140px;object-fit:cover;display:block;" />
              <div style="padding:0.7rem 0.9rem;display:flex;align-items:center;justify-content:space-between;gap:0.5rem;">
                <span style="font-size:0.8rem;font-weight:700;color:#000;">${s.label || '<span style="color:#bbb;font-style:italic">No label</span>'}</span>
                <button class="btn-del" onclick="deleteSlide(${s.id})" title="Delete">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
          `);
        });
      });
    }

    // Load slides when panel is opened
    $(document).on('click', '[onclick*="showPanel(\'slides"]', function() {
      setTimeout(loadSlides, 100);
    });

    // Image preview for slide upload
    $('#slideImageInput').on('change', function() {
      const file = this.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = e => {
        $('#slideImgPreviewWrap').html('<img src="' + e.target.result + '" />');
      };
      reader.readAsDataURL(file);
    });

    // Add slide form submit
    $('#addSlideForm').on('submit', function(e) {
      e.preventDefault();
      if (!$('#slideImageInput')[0].files[0]) {
        showToast('Please select an image.', 'error');
        return;
      }
      const fd = new FormData(this);
      fd.append('action', 'add_slide');
      $('#addSlideBtn').prop('disabled', true).text('Uploading�');
      $.ajax({
        url: 'action.php', type: 'POST', data: fd,
        contentType: false, processData: false,
        success: function(res) {
          if (res.success) {
            showToast('Slide added!');
            $('#addSlideForm')[0].reset();
            $('#slideImgPreviewWrap').html('<div class="placeholder"><i class="fas fa-image"></i> Click to upload image</div>');
            loadSlides();
          } else {
            showToast(res.message || 'Upload failed.', 'error');
          }
        },
        error: function() { showToast('Server error.', 'error'); },
        complete: function() { $('#addSlideBtn').prop('disabled', false).html('<i class="fas fa-upload"></i> Upload Slide'); }
      });
    });

    function deleteSlide(id) {
      if (!confirm('Delete this slide?')) return;
      $.post('action.php', { action: 'delete_slide', id: id }, function(res) {
        if (res.success) {
          $('#slide-card-' + id).remove();
          if ($('#slidesGrid').children().length === 0) {
            $('#slidesGrid').html('<div class="empty-state"><i class="fas fa-image"></i> No slides yet.</div>');
          }
          showToast('Slide deleted.');
        } else {
          showToast('Could not delete.', 'error');
        }
      }, 'json');
    }

    // -- Addons Modal --------------------------------------
    function openAddonsModal(itemId, itemTitle) {
      currentAddonItemId = itemId;
      $('#addonsItemTitle').text(itemTitle);
      $('#addonName').val('');
      $('#addonPrice').val('');
      loadAddons(itemId);
      $('#addonsModal').addClass('open');
      $('body').css('overflow', 'hidden');
    }
    function closeAddonsModal() {
      $('#addonsModal').removeClass('open');
      $('body').css('overflow', '');
      currentAddonItemId = null;
    }
    $('#addonsModal').on('click', function(e) { if ($(e.target).is('#addonsModal')) closeAddonsModal(); });

    function loadAddons(itemId) {
      $('#addonsList').html('<div style="text-align:center;padding:1.5rem;color:#9ca3af;font-size:0.85rem"><i class="fas fa-spinner fa-spin"></i> Loading�</div>');
      $.get('action.php', { action: 'get_addons', item_id: itemId }, function(res) {
        if (!res.addons || res.addons.length === 0) {
          $('#addonsList').html('<div class="empty-state" style="padding:2rem;text-align:center;color:#9ca3af;"><i class="fas fa-list" style="font-size:2rem;opacity:0.3;margin-bottom:0.5rem;display:block;"></i><p style="margin:0;font-size:0.85rem;font-weight:600;">No add-ons yet</p><p style="margin:4px 0 0;font-size:0.75rem;opacity:0.7;">Add your first add-on above</p></div>');
          return;
        }
        let html = '';
        res.addons.forEach(function(a) {
          html += `<div id="addon-row-${a.id}" style="display:flex;align-items:center;justify-content:space-between;padding:0.9rem 1.1rem;background:#fff;border:1.5px solid rgba(0,0,0,0.08);border-radius:8px;transition:all 0.2s;" onmouseover="this.style.borderColor='#4169e1';this.style.boxShadow='0 2px 8px rgba(65,105,225,0.15)'" onmouseout="this.style.borderColor='rgba(0,0,0,0.08)';this.style.boxShadow='none'">
            <span style="font-size:0.9rem;font-weight:700;color:#1f2937;">${a.name}</span>
            <div style="display:flex;align-items:center;gap:1rem;">
              <span style="font-size:0.95rem;font-weight:900;color:#f97316;background:#fff7ed;padding:4px 12px;border-radius:6px;">Rs. ${parseFloat(a.price).toLocaleString()}</span>
              <button onclick="deleteAddon(${a.id})" style="background:#ef4444;color:#fff;border:none;padding:6px 12px;cursor:pointer;border-radius:6px;font-size:0.75rem;font-family:'Inter',sans-serif;font-weight:700;transition:all 0.2s;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </div>`;
        });
        $('#addonsList').html(html);
      }, 'json');
    }

    function addAddon() {
      const name  = $('#addonName').val().trim();
      const price = parseFloat($('#addonPrice').val()) || 0;
      if (!name) { showToast('Addon name required', 'error'); return; }
      if (!currentAddonItemId) return;
      $.post('action.php', { action: 'add_addon', item_id: currentAddonItemId, name: name, price: price }, function(res) {
        if (res.success) {
          showToast('Add-on added!');
          $('#addonName').val('');
          $('#addonPrice').val('');
          loadAddons(currentAddonItemId);
        } else {
          showToast(res.message || 'Error', 'error');
        }
      }, 'json');
    }

    function deleteAddon(id) {
      if (!confirm('Delete this add-on?')) return;
      $.post('action.php', { action: 'delete_addon', id: id }, function(res) {
        if (res.success) {
          $('#addon-row-' + id).remove();
          if ($('#addonsList').children().length === 0) {
            $('#addonsList').html('<div class="empty-state" style="padding:1.5rem"><i class="fas fa-list"></i> No add-ons yet.</div>');
          }
          showToast('Deleted.');
        } else showToast('Error', 'error');
      }, 'json');
    }

    // Enter key in addon name field
    $('#addonName').on('keydown', function(e) { if (e.key === 'Enter') addAddon(); });

    // -- Site Branding Settings -----------------------------
    function loadSiteBranding() {
      $.get('action.php', { action: 'get_site_settings' }, function(res) {
        if (res.success) {
          if (res.site_name) $('#siteNameInput').val(res.site_name);
          if (res.site_address) $('#siteAddressInput').val(res.site_address);
          if (res.site_phone) $('#sitePhoneInput').val(res.site_phone);
        }
      }, 'json');
    }

    function saveSiteBranding() {
      const siteName = $('#siteNameInput').val().trim();
      const siteAddress = $('#siteAddressInput').val().trim();
      const sitePhone = $('#sitePhoneInput').val().trim();
      
      if (!siteName) {
        showToast('Site name is required', 'error');
        return;
      }
      
      if (siteName.length < 2 || siteName.length > 50) {
        showToast('Site name must be between 2 and 50 characters', 'error');
        return;
      }
      
      const $btn = $('#saveBrandingBtn');
      $btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
      
      $.post('action.php', { 
        action: 'save_site_settings',
        site_name: siteName,
        site_address: siteAddress,
        site_phone: sitePhone
      }, function(res) {
        if (res.success) {
          showToast('Branding saved successfully!', 'success');
        } else {
          showToast(res.message || 'Error saving', 'error');
        }
        $btn.html('<i class="fas fa-save"></i> Save Branding').prop('disabled', false);
      }, 'json').fail(function() {
        showToast('Network error', 'error');
        $btn.html('<i class="fas fa-save"></i> Save Branding').prop('disabled', false);
      });
    }

    // -- Delivery Modal Settings ----------------------------
    function loadDeliveryModalSettings() {
      $.get('action.php', { action: 'get_delivery_modal_settings' }, function(res) {
        if (res.success) {
          $('#deliveryModalToggle').prop('checked', res.is_enabled);
          $('#pickupLocationInput').val(res.pickup_location);
        }
      }, 'json');
    }
    function saveDeliveryModalSettings() {
      const $btn = $('#saveSettingsBtn');
      $btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
      $.post('action.php', {
        action: 'update_delivery_modal_settings',
        is_enabled: $('#deliveryModalToggle').is(':checked') ? 1 : 0,
        pickup_location: $('#pickupLocationInput').val().trim()
      }, function(res) {
        if (res.success) {
          showToast('Settings saved!');
        } else {
          showToast('Error saving', 'error');
        }
        $btn.html('<i class="fas fa-save"></i> Save Settings').prop('disabled', false);
      }, 'json');
    }
    // Load settings when panel opens
    $(document).on('click', '[onclick*="showPanel(\'settings"]', function() {
      setTimeout(loadSiteBranding, 50);
      setTimeout(loadDeliveryModalSettings, 100);
      setTimeout(loadWaNumber, 100);
    });

    // -- WhatsApp Number Settings ---------------------------
    function loadWaNumber() {
      $.get('action.php', { action: 'get_site_settings' }, function(res) {
        if (res.success && res.whatsapp_number) {
          $('#waNumberInput').val(res.whatsapp_number);
        }
        // Toggle state
        var enabled = res.wa_button_enabled !== false;
        setWaToggleUI(enabled);
      }, 'json');
    }

    var waButtonEnabled = true;
    function setWaToggleUI(enabled) {
      waButtonEnabled = enabled;
      if (enabled) {
        $('#waBtnToggle').css('background', '#25D366');
        $('#waBtnThumb').css('left', '27px');
      } else {
        $('#waBtnToggle').css('background', '#e5e7eb');
        $('#waBtnThumb').css('left', '3px');
      }
    }
    function toggleWaButton() {
      var newVal = waButtonEnabled ? 0 : 1;
      $.post('action.php', { action: 'save_site_settings', wa_button_enabled: newVal }, function(res) {
        if (res.success) {
          setWaToggleUI(newVal === 1);
          showToast(newVal ? 'WhatsApp button is now visible on website' : 'WhatsApp button hidden from website', 'success');
        } else {
          showToast(res.message || 'Error saving', 'error');
        }
      }, 'json').fail(function(xhr) {
        showToast('Network error: ' + xhr.responseText.substring(0, 100), 'error');
      });
    }

    function saveWaNumber() {
      var num = $('#waNumberInput').val().trim();
      if (!num) { showToast('Please enter a number', 'error'); return; }
      if (!/^[0-9]{10,15}$/.test(num)) {
        showToast('Invalid number � digits only, 10-15 chars (e.g. 923045860472)', 'error');
        return;
      } 
      var $btn = $('#saveWaBtn');
      $btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
      $.post('action.php', { action: 'save_site_settings', whatsapp_number: num }, function(res) {
        if (res.success) {
          showToast('WhatsApp number saved!', 'success');
        } else {
          showToast(res.message || 'Error saving', 'error');
        }
        $btn.html('<i class="fas fa-brands fa-whatsapp"></i> Save Number').prop('disabled', false);
      }, 'json').fail(function() {
        showToast('Network error', 'error');
        $btn.html('<i class="fas fa-brands fa-whatsapp"></i> Save Number').prop('disabled', false);
      });
    }

    function showToast(msg, type = 'success') {
      const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark';
      $('#toast').removeClass('success error').addClass(type + ' show')
        .html('<i class="fas ' + icon + '"></i> ' + msg);
      setTimeout(() => $('#toast').removeClass('show'), 3000);
    }

    // Image preview (add form)
    $('#imageInput').on('change', function() {
      const file = this.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = e => { $('#imgPreview').attr('src', e.target.result).show(); $('#imgPlaceholder').hide(); };
      reader.readAsDataURL(file);
    });

    // Add menu item
    $('#addItemForm').on('submit', function(e) {
      e.preventDefault();
      const $btn = $('#addBtn');
      $btn.html('<i class="fas fa-spinner fa-spin"></i> Adding...').prop('disabled', true);
      const formData = new FormData(this);
      formData.append('action', 'add_item');
      $.ajax({
        url: 'action.php', type: 'POST', data: formData, processData: false, contentType: false,
        success: function(res) {
          if (res.success) { showToast('Item added!'); setTimeout(() => location.reload(), 1000); }
          else { showToast(res.message || 'Error', 'error'); $btn.html('<i class="fas fa-plus"></i> Add to Menu').prop('disabled', false); }
        },
        error: function() { showToast('Server error.', 'error'); $btn.html('<i class="fas fa-plus"></i> Add to Menu').prop('disabled', false); }
      });
    });

    // Delete menu item
    function deleteItem(id) {
      if (!confirm('Delete this item?')) return;
      $.post('action.php', { action: 'delete_item', id: id }, function(res) {
        if (res.success) { $('#item-' + id).fadeOut(400, function() { $(this).remove(); }); showToast('Item deleted.'); }
        else showToast('Could not delete.', 'error');
      });
    }

    // Edit modal
    function openEditModal(id, data) {
      $('#editItemId').val(id);
      $('#editTitle').val(data.title);
      $('#editDescription').val(data.description);
      $('#editPrice').val(parseFloat(data.price));
      $('#editDiscount').val(parseInt(data.discount_percent || 0));
      $('#editCategory').val(data.category);
      $('#editImgPreview').attr('src', '../' + data.image_path);
      $('#editImageInput').val('');
      $('#editModal').addClass('open');
      $('body').css('overflow', 'hidden');
    }
    function closeEditModal() { $('#editModal').removeClass('open'); $('body').css('overflow', ''); }
    $('#editModal').on('click', function(e) { if ($(e.target).is('#editModal')) closeEditModal(); });
    $('#editImageInput').on('change', function() {
      const file = this.files[0]; if (!file) return;
      const reader = new FileReader();
      reader.onload = e => $('#editImgPreview').attr('src', e.target.result);
      reader.readAsDataURL(file);
    });
    $('#editItemForm').on('submit', function(e) {
      e.preventDefault();
      const $btn = $('#editSaveBtn');
      $btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
      const formData = new FormData(this);
      formData.append('action', 'edit_item');
      $.ajax({
        url: 'action.php', type: 'POST', data: formData, processData: false, contentType: false,
        success: function(res) {
          if (res.success) {
            const id = $('#editItemId').val();
            const catObj = allCategories.find(c => c.slug === $('#editCategory').val());
            const catName = catObj ? catObj.name : $('#editCategory').val();
            $('#item-title-' + id).text($('#editTitle').val());
            $('#item-desc-' + id).text($('#editDescription').val());
            $('#item-price-' + id).text('Rs. ' + parseInt($('#editPrice').val()).toLocaleString());
            $('#item-cat-label-' + id).text(catName);
            if (res.image_path) $('#item-img-' + id).attr('src', '../' + res.image_path);
            closeEditModal(); showToast('Item updated!');
          } else showToast(res.message || 'Error', 'error');
          $btn.html('<i class="fas fa-save"></i> Save Changes').prop('disabled', false);
        },
        error: function() { showToast('Server error.', 'error'); $btn.html('<i class="fas fa-save"></i> Save Changes').prop('disabled', false); }
      });
    });

    // Categories
    function addCategory() {
      const name = $('#newCatName').val().trim();
      if (!name) { showToast('Category name required.', 'error'); return; }

      const fd = new FormData();
      fd.append('action', 'add_category');
      fd.append('name', name);
      const bannerFile = $('#newCatBanner')[0].files[0];
      if (bannerFile) fd.append('banner', bannerFile);

      $.ajax({
        url: 'action.php', type: 'POST', data: fd,
        processData: false, contentType: false,
        success: function(res) {
          if (res.success) {
            $('#catEmpty').remove();
            const bannerHtml = res.banner_image
              ? `<img src="../${res.banner_image}" class="cat-chip-banner" id="bannerImg-${res.id}" onclick="$('#bannerFile-${res.id}').click()" title="Click to change banner" />`
              : `<div class="cat-chip-banner-placeholder" id="bannerImg-${res.id}" onclick="$('#bannerFile-${res.id}').click()" title="Click to add banner"><i class="fas fa-image"></i></div>`;
            $('#catList').append(`
              <div class="cat-chip" id="cat-${res.id}">
                <div style="flex-shrink:0">
                  <input type="file" accept="image/*" id="bannerFile-${res.id}" style="display:none" onchange="uploadBanner(${res.id}, this)" />
                  ${bannerHtml}
                </div>
                <div style="flex:1;min-width:0">
                  <div class="cat-chip-name">${res.name}</div>
                  <div class="cat-chip-slug">slug: ${res.slug}</div>
                </div>
                <button class="btn-cat-del" onclick="deleteCategory(${res.id}, this)"><i class="fas fa-times"></i></button>
              </div>`);
            const opt = `<option value="${res.slug}">${res.name}</option>`;
            $('#addCategorySelect, #editCategory').append(opt);
            allCategories.push({ id: res.id, name: res.name, slug: res.slug, banner_image: res.banner_image || '' });
            $('#catCount').text($('#catList .cat-chip').length + ' categories');
            $('#newCatName').val('');
            // Reset banner preview
            $('#newCatBannerPreview').hide().attr('src','');
            $('#newCatBannerPlaceholder').show();
            $('#newCatBanner').val('');
            showToast('Category added!');
          } else showToast(res.message || 'Error', 'error');
        }
      });
    }

    // Banner image preview for new category
    $('#newCatBanner').on('change', function() {
      const file = this.files[0]; if (!file) return;
      const reader = new FileReader();
      reader.onload = e => {
        $('#newCatBannerPreview').attr('src', e.target.result).show();
        $('#newCatBannerPlaceholder').hide();
      };
      reader.readAsDataURL(file);
    });

    // Upload banner for existing category
    function uploadBanner(id, input) {
      const file = input.files[0]; if (!file) return;
      const fd = new FormData();
      fd.append('action', 'update_category_banner');
      fd.append('id', id);
      fd.append('banner', file);
      $.ajax({
        url: 'action.php', type: 'POST', data: fd,
        processData: false, contentType: false,
        success: function(res) {
          if (res.success) {
            const $wrap = $('#bannerImg-' + id);
            if ($wrap.is('img')) {
              $wrap.attr('src', '../' + res.banner_image);
            } else {
              $wrap.replaceWith(`<img src="../${res.banner_image}" class="cat-chip-banner" id="bannerImg-${id}" onclick="$('#bannerFile-${id}').click()" title="Click to change banner" />`);
            }
            showToast('Banner updated!');
          } else showToast(res.message || 'Upload failed', 'error');
        }
      });
    }

    $('#newCatName').on('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); addCategory(); } });

    function deleteCategory(id, btn) {
      const $chip = $('#cat-' + id);
      const catName = $chip.find('.cat-chip-name').text().trim();
      const slug = $chip.find('.cat-chip-slug').text().replace('slug: ', '').trim();
      if (!confirm(`Delete "${catName}" category? All its menu items will also be deleted!`)) return;
      $.post('action.php', { action: 'delete_category', id: id }, function(res) {
        if (res.success) {
          $chip.fadeOut(300, function() {
            $(this).remove();
            $('#catCount').text($('#catList .cat-chip').length + ' categories');
            if ($('#catList .cat-chip').length === 0) $('#catList').html('<div class="cat-empty" id="catEmpty"><i class="fas fa-tag"></i> No categories yet.</div>');
          });
          $(`#addCategorySelect option[value="${slug}"], #editCategory option[value="${slug}"]`).remove();
          allCategories = allCategories.filter(c => c.id != id);
          setTimeout(() => location.reload(), 600);
          showToast((res.deleted_items || 0) > 0 ? `Category deleted with ${res.deleted_items} item(s).` : 'Category deleted.');
        } else showToast(res.message || 'Error', 'error');
      });
    }

    // Messages
    function markRead(id) {
      $.post('action.php', { action: 'mark_read', id: id }, function(res) {
        if (res.success) {
          const $row = $('#msg-' + id);
          $row.removeClass('unread').find('.unread-dot, .btn-read').remove();
          let c = Math.max(0, parseInt($('#unreadCount').text()) - 1);
          $('#unreadCount').text(c);
          if (c === 0) $('#unreadBadge').remove(); else $('#unreadBadge').text(c);
          showToast('Marked as read.');
        }
      });
    }
    function deleteMsg(id) {
      if (!confirm('Delete this message?')) return;
      $.post('action.php', { action: 'delete_message', id: id }, function(res) {
        if (res.success) { $('#msg-' + id).fadeOut(400, function() { $(this).remove(); }); showToast('Message deleted.'); }
        else showToast('Could not delete.', 'error');
      });
    }

    // Bookings
    function markBookRead(id) {
      $.post('action.php', { action: 'mark_booking_read', id: id }, function(res) {
        if (res.success) {
          $('#book-' + id).removeClass('unread').find('.unread-dot, .btn-read').remove();
          let c = parseInt($('#unreadBookBadge').text()) - 1;
          if (c <= 0) $('#unreadBookBadge').remove(); else $('#unreadBookBadge').text(c);
          showToast('Marked as read.');
        }
      });
    }
    function deleteBook(id) {
      if (!confirm('Delete this booking?')) return;
      $.post('action.php', { action: 'delete_booking', id: id }, function(res) {
        if (res.success) { $('#book-' + id).fadeOut(400, function() { $(this).remove(); }); showToast('Booking deleted.'); }
        else showToast('Could not delete.', 'error');
      });
    }

    // Users
    function deleteUser(id) {
      if (!confirm('Delete this user? This cannot be undone.')) return;
      $.post('action.php', { action: 'delete_user', id: id }, function(res) {
        if (res.success) {
          $('#user-' + id).fadeOut(400, function() { $(this).remove(); });
          showToast('User deleted.');
        } else showToast(res.message || 'Could not delete.', 'error');
      });
    }

    // Orders
    function filterOrders(status, btn) {
      $('.order-tab').removeClass('active');
      $(btn).addClass('active');
      const rows = $('#ordersBody tr[data-status]');
      if (status === 'all') rows.show();
      else { rows.hide(); rows.filter('[data-status="' + status + '"]').show(); }
    }

    // -- Update prep time only -----------------------------
    function updatePrepTime(id) {
      const prepTime = $('#pt-' + id).val().trim();
      if (!prepTime) { showToast('Please enter a time.', 'error'); return; }
      $.post('action.php', { action: 'update_order_status', id: id, status: 'confirmed', prep_time: prepTime }, function(res) {
        if (res.success) {
          showToast('Prep time updated: ' + prepTime);
        } else {
          showToast(res.message || 'Error.', 'error');
        }
      }, 'json');
    }

    function deleteOrder(id) {
      if (!confirm('Delete order #' + id + '? This cannot be undone.')) return;
      $.post('action.php', { action: 'delete_order', id: id }, function(res) {
        if (res.success) {
          $('#order-row-' + id).fadeOut(400, function() { $(this).remove(); });
          showToast('Order #' + id + ' deleted.');
        } else {
          showToast(res.message || 'Could not delete.', 'error');
        }
      }, 'json').fail(function() { showToast('Server error.', 'error'); });
    }

    // -- Prep time modal -----------------------------------
    function showPrepModal(id) {
      // Remove old if exists
      $('#prepModal').remove();
      const modal = `
        <div id="prepModal" style="
          position:fixed;inset:0;background:rgba(0,0,0,0.55);
          z-index:9999;display:flex;align-items:center;justify-content:center;">
          <div style="background:#fff;padding:1.8rem;max-width:360px;width:90%;border-top:4px solid #000;">
            <p style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.1em;color:#888;margin-bottom:0.5rem;">
              Order #${id}
            </p>
            <h3 style="font-size:1rem;font-weight:900;text-transform:uppercase;margin-bottom:1rem;">
              Estimated Prep Time
            </h3>
            <p style="font-size:0.82rem;color:#555;margin-bottom:1rem;">
              Customer will see this in their order status.
            </p>
            <input id="prepTimeInput" type="text" placeholder="e.g. 30-45 mins"
              style="width:100%;padding:0.7rem 0.9rem;border:1.5px solid rgba(0,0,0,0.2);
                     font-family:'Inter',sans-serif;font-size:0.9rem;outline:none;margin-bottom:1rem;box-sizing:border-box;" />
            <div style="display:flex;gap:0.6rem;">
              <button onclick="confirmWithTime(${id})"
                style="flex:1;background:#000;color:#fff;border:none;padding:0.7rem;
                       font-family:'Inter',sans-serif;font-size:0.76rem;font-weight:700;
                       text-transform:uppercase;letter-spacing:0.08em;cursor:pointer;">
                <i class="fas fa-check"></i> Confirm Order
              </button>
              <button onclick="$('#prepModal').remove()"
                style="background:#f5f5f5;color:#555;border:1px solid rgba(0,0,0,0.15);
                       padding:0.7rem 1rem;font-family:'Inter',sans-serif;font-size:0.76rem;
                       font-weight:700;cursor:pointer;">
                Cancel
              </button>
            </div>
          </div>
        </div>`;
      $('body').append(modal);
      $('#prepTimeInput').focus();
      // Close on overlay click
      $('#prepModal').on('click', function(e) {
        if ($(e.target).is('#prepModal')) $(this).remove();
      });
      // Enter key submits
      $('#prepTimeInput').on('keydown', function(e) {
        if (e.key === 'Enter') confirmWithTime(id);
      });
    }

    function confirmWithTime(id) {
      const prepTime = $('#prepTimeInput').val().trim();
      $('#prepModal').remove();
      doUpdateOrder(id, 'confirmed', prepTime);
    }

    function updateOrder(id, status) {
      if (status === 'confirmed') {
        showPrepModal(id);
        return;
      }
      const msgs = { dispatched: 'Mark as delivered?', cancelled: 'Cancel this order?' };
      if (!confirm(msgs[status] || 'Update?')) return;
      doUpdateOrder(id, status, '');
    }

    function doUpdateOrder(id, status, prepTime) {
      $.post('action.php', { action: 'update_order_status', id: id, status: status, prep_time: prepTime }, function(res) {
        if (res.success) {
          const cls = { confirmed: 'order-badge-confirmed', dispatched: 'order-badge-dispatched', cancelled: 'order-badge-cancelled' };
          $('#order-status-' + id)
            .text(status.charAt(0).toUpperCase() + status.slice(1))
            .removeClass('order-badge-pending order-badge-confirmed order-badge-dispatched order-badge-cancelled')
            .addClass(cls[status] || '');
          $('#order-row-' + id).attr('data-status', status);

          // Show prep time under confirmed badge
          if (status === 'confirmed' && prepTime) {
            $('#order-status-' + id).after(
              `<br><span style="font-size:0.68rem;color:#2563eb;font-weight:700;margin-top:3px;display:block;">
                <i class="fas fa-clock"></i> ${prepTime}
              </span>`
            );
          }

          let actionHtml = '';
          if (status === 'confirmed') {
            actionHtml = `<button class="order-action-btn order-btn-dispatch" onclick="updateOrder(${id},'dispatched')"><i class="fas fa-motorcycle"></i> Deliver</button><button class="order-action-btn order-btn-cancel" onclick="updateOrder(${id},'cancelled')"><i class="fas fa-times"></i> Cancel</button>`;
          } else if (status === 'dispatched') {
            actionHtml = '<span style="font-size:0.72rem;font-weight:700;color:#16a34a">On the way</span><br><button class="btn-del" style="font-size:0.68rem;padding:4px 10px;margin-top:4px;background:#555" onclick="deleteOrder(' + id + ')"><i class="fas fa-trash"></i></button>';
          } else if (status === 'cancelled') {
            actionHtml = '<span style="font-size:0.72rem;font-weight:700;color:#bbb">Cancelled</span><br><button class="btn-del" style="font-size:0.68rem;padding:4px 10px;margin-top:4px;background:#555" onclick="deleteOrder(' + id + ')"><i class="fas fa-trash"></i></button>';
          }
          $('#order-row-' + id + ' td:last-child div').html(actionHtml);

          if (status !== 'pending') {
            let p = Math.max(0, (parseInt($('#badge-pending').text()) || 0) - 1);
            $('#badge-pending').text(p);
            $('#statPending').text(p);
            if (p === 0) $('#pendingOrderBadge').remove(); else $('#pendingOrderBadge').text(p);
          }
          const toasts = { confirmed: 'Order confirmed!', dispatched: 'Order out for delivery!', cancelled: 'Order cancelled.' };
          showToast(toasts[status] || 'Updated.');
        } else showToast(res.message || 'Error.', 'error');
      }, 'json').fail(function() { showToast('Server error.', 'error'); });
    }

    // -- Vendor Management Functions ------------------------------------
    function approveVendor(vendorId) {
      if (!confirm('Approve this chef store application?')) return;
      
      fetch('action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=vendor_action&vendor_id=${vendorId}&status=approved`
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast('Chef store approved successfully!', 'success');
          document.getElementById('vendor-status-' + vendorId).className = 'vendor-badge vendor-badge-approved';
          document.getElementById('vendor-status-' + vendorId).innerHTML = '<i class="fas fa-check-circle"></i> Approved';
          // Refresh to update actions column
          setTimeout(() => location.reload(), 1500);
        } else {
          showToast(data.message || 'Failed to approve', 'error');
        }
      })
      .catch(() => showToast('Network error', 'error'));
    }

    function rejectVendor(vendorId) {
      if (!confirm('Reject this chef store application? This can be reversed later.')) return;
      
      fetch('action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=vendor_action&vendor_id=${vendorId}&status=rejected`
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast('Chef store rejected', 'error');
          document.getElementById('vendor-status-' + vendorId).className = 'vendor-badge vendor-badge-rejected';
          document.getElementById('vendor-status-' + vendorId).innerHTML = '<i class="fas fa-times-circle"></i> Rejected';
          // Refresh to update actions column
          setTimeout(() => location.reload(), 1500);
        } else {
          showToast(data.message || 'Failed to reject', 'error');
        }
      })
      .catch(() => showToast('Network error', 'error'));
    }

    function suspendVendor(vendorId) {
      if (!confirm('Suspend this chef store? They will not be able to accept orders.')) return;
      
      fetch('action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=vendor_action&vendor_id=${vendorId}&status=suspended`
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast('Chef store suspended', 'error');
          document.getElementById('vendor-status-' + vendorId).className = 'vendor-badge vendor-badge-suspended';
          document.getElementById('vendor-status-' + vendorId).innerHTML = '<i class="fas fa-ban"></i> Suspended';
          // Refresh to update actions column
          setTimeout(() => location.reload(), 1500);
        } else {
          showToast(data.message || 'Failed to suspend', 'error');
        }
      })
      .catch(() => showToast('Network error', 'error'));
    }

    function viewVendorDetails(vendorId) {
      fetch('action.php?action=get_vendor_details&vendor_id=' + vendorId)
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          const v = data.vendor;
          let html = `
            <div style="padding:1.5rem">
              <div style="display:flex;gap:1.5rem;margin-bottom:1.5rem">
                ${v.profile_image ? 
                  `<img src="${v.profile_image}" style="width:100px;height:100px;border-radius:12px;object-fit:cover;border:3px solid #4169e1" />` :
                  `<div style="width:100px;height:100px;border-radius:12px;background:#4169e1;display:flex;align-items:center;justify-content:center;color:#fff;font-size:2.5rem;font-weight:700">${v.name[0].toUpperCase()}</div>`
                }
                <div style="flex:1">
                  <h3 style="margin-bottom:0.5rem;font-size:1.3rem">${v.name}</h3>
                  <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:0.8rem">
                    <span class="vendor-badge vendor-badge-${v.status}">
                      ${v.status === 'pending' ? '<i class="fas fa-clock"></i> Pending' :
                        v.status === 'approved' ? '<i class="fas fa-check-circle"></i> Approved' :
                        v.status === 'rejected' ? '<i class="fas fa-times-circle"></i> Rejected' :
                        '<i class="fas fa-ban"></i> Suspended'}
                    </span>
                  </div>
                </div>
              </div>
              
              <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem">
                <div style="padding:1rem;background:#f8f9fa;border-radius:8px">
                  <div style="color:#888;font-size:0.75rem;margin-bottom:0.4rem">Store Name</div>
                  <strong>${v.store_name || 'N/A'}</strong>
                </div>
                <div style="padding:1rem;background:#f8f9fa;border-radius:8px">
                  <div style="color:#888;font-size:0.75rem;margin-bottom:0.4rem">CNIC Number</div>
                  <strong style="font-family:monospace">${v.cnic}</strong>
                </div>
                <div style="padding:1rem;background:#f8f9fa;border-radius:8px">
                  <div style="color:#888;font-size:0.75rem;margin-bottom:0.4rem">Phone</div>
                  <strong>${v.phone}</strong>
                </div>
                <div style="padding:1rem;background:#f8f9fa;border-radius:8px">
                  <div style="color:#888;font-size:0.75rem;margin-bottom:0.4rem">Email</div>
                  <strong style="word-break:break-all">${v.email}</strong>
                </div>
                <div style="padding:1rem;background:#f8f9fa;border-radius:8px">
                  <div style="color:#888;font-size:0.75rem;margin-bottom:0.4rem">Applied Date</div>
                  <strong>${new Date(v.created_at).toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'})}</strong>
                </div>
              </div>
            </div>
          `;
          
          // Show in a modal or alert (simple version - use alert for now)
          const detailsDiv = document.createElement('div');
          detailsDiv.innerHTML = html;
          detailsDiv.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.3);max-width:700px;width:90%;z-index:10000';
          
          const overlay = document.createElement('div');
          overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999';
          overlay.onclick = () => {
            document.body.removeChild(overlay);
            document.body.removeChild(detailsDiv);
          };
          
          const closeBtn = document.createElement('button');
          closeBtn.innerHTML = '<i class="fas fa-times"></i>';
          closeBtn.style.cssText = 'position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:1.5rem;cursor:pointer;color:#888;transition:color 0.2s';
          closeBtn.onmouseover = () => closeBtn.style.color = '#000';
          closeBtn.onmouseout = () => closeBtn.style.color = '#888';
          closeBtn.onclick = overlay.onclick;
          detailsDiv.appendChild(closeBtn);
          
          document.body.appendChild(overlay);
          document.body.appendChild(detailsDiv);
        } else {
          showToast(data.message || 'Failed to load details', 'error');
        }
      })
      .catch(() => showToast('Network error', 'error'));
    }y

    // ------------------------------------------------------
    // RECEPTIONIST MANAGEMENT
    // ------------------------------------------------------
    var receptExists = false; // Use 'var' for function scope, not 'let'

    function loadReceptionist() {
      console.log('?? Loading receptionist data...');
      console.log('Current URL:', window.location.href);
      console.log('jQuery version:', $.fn.jquery);
      
      // Show loading state
      $('#receptStatus').html(
        '<div style="display:flex;align-items:center;gap:1rem;padding:1rem;' +
        'background:#fef3c7;border:2px solid #f59e0b;border-radius:12px">' +
        '<div style="width:42px;height:42px;background:#fbbf24;border-radius:50%;' +
        'display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#fff">' +
        '<i class="fas fa-spinner fa-pulse"></i></div>' +
        '<div><p style="font-weight:700;font-size:0.88rem;color:#92400e;margin:0">Loading receptionist status...</p></div></div>'
      );
      
      console.log('?? Sending AJAX request...');
      
      $.ajax({
        url: 'action.php',
        type: 'POST',
        data: { action: 'get_receptionist' },
        dataType: 'json',
        timeout: 10000,
        beforeSend: function() {
          console.log('?? AJAX beforeSend triggered');
        },
        success: function(res) {
          console.log('? AJAX Success! Response:', res);
          
          if (!res || !res.success) {
            console.error('? Invalid response:', res);
            showReceptionistError();
            return;
          }
          
          receptExists = res.exists; // Now this will work
          
          if (res.exists) {
            console.log('?? Receptionist found:', res.username);
            showExistingReceptionist(res);
          } else {
            console.log('? No receptionist - showing create form');
            showNoReceptionist();
          }
        },
        error: function(xhr, status, error) {
          console.error('? AJAX Error:', {
            status: status,
            error: error,
            statusCode: xhr.status,
            response: xhr.responseText
          });
          showReceptionistError(error);
        },
        complete: function() {
          console.log('?? AJAX complete');
        }
      });
      
      console.log('?? AJAX request initiated');
    }
    
    function showExistingReceptionist(data) {
      // Show existing account card
      $('#receptStatus').html(
        '<div style="display:flex;align-items:center;gap:1rem;padding:1.2rem;' +
        'background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:2px solid #86efac;border-radius:12px;' +
        'box-shadow:0 4px 12px rgba(16,185,129,0.15)">' +
        '<div style="width:48px;height:48px;background:linear-gradient(135deg,#10b981,#059669);' +
        'border-radius:50%;display:flex;align-items:center;justify-content:center;' +
        'font-size:1.2rem;color:#fff;flex-shrink:0;box-shadow:0 4px 12px rgba(16,185,129,0.3)">' +
        '<i class="fas fa-user-check"></i></div>' +
        '<div style="flex:1">' +
        '<p style="font-weight:900;font-size:0.95rem;color:#065f46;margin:0">Receptionist Account Active</p>' +
        '<p style="font-size:0.82rem;color:#047857;margin:4px 0 0">Username: <strong>' + escHtml(data.username) + '</strong></p>' +
        '<p style="font-size:0.73rem;color:#6b7280;margin:3px 0 0"><i class="fas fa-calendar"></i> Created: ' + data.created_at + '</p>' +
        '</div>' +
        '<div style="display:flex;flex-direction:column;gap:0.5rem">' +
        '<a href="receptionist.php" target="_blank" ' +
        'style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;padding:8px 16px;' +
        'border-radius:8px;font-size:0.75rem;font-weight:700;text-decoration:none;' +
        'display:inline-flex;align-items:center;justify-content:center;gap:0.5rem;' +
        'box-shadow:0 3px 10px rgba(245,158,11,0.3);white-space:nowrap">' +
        '<i class="fas fa-external-link-alt"></i> Open Panel</a>' +
        '</div>' +
        '</div>'
      );
      
      // Switch form to "update" mode
      $('#receptFormTitle').html('<i class="fas fa-user-pen"></i> Update Receptionist Credentials');
      $('#receptSaveBtnText').text('Update Credentials');
      $('#receptPwHint').html('(leave blank to keep current password)');
      $('#receptDeleteBtn').show();
      $('#receptUsername').val(data.username);
      $('#receptPassword').val('');
      hideReceptError();
    }
    
    function showNoReceptionist() {
      // Show "no receptionist" state
      $('#receptStatus').html(
        '<div style="display:flex;align-items:center;gap:1rem;padding:1.2rem;' +
        'background:#f9fafb;border:2px dashed rgba(0,0,0,0.12);border-radius:12px">' +
        '<div style="width:48px;height:48px;background:#f3f4f6;border-radius:50%;' +
        'display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#9ca3af">' +
        '<i class="fas fa-user-slash"></i></div>' +
        '<div>' +
        '<p style="font-weight:800;font-size:0.9rem;color:#6b7280;margin:0">No Receptionist Account</p>' +
        '<p style="font-size:0.8rem;color:#9ca3af;margin:4px 0 0">Use the form below to create one</p>' +
        '</div></div>'
      );
      
      // Switch form to "create" mode
      $('#receptFormTitle').html('<i class="fas fa-user-plus"></i> Create Receptionist Account');
      $('#receptSaveBtnText').text('Create Account');
      $('#receptPwHint').html('(minimum 6 characters)');
      $('#receptDeleteBtn').hide();
      $('#receptUsername').val('');
      $('#receptPassword').val('');
      hideReceptError();
    }
    
    function showReceptionistError(errorMsg) {
      $('#receptStatus').html(
        '<div style="display:flex;align-items:center;gap:1rem;padding:1.2rem;' +
        'background:#fee2e2;border:2px solid #ef4444;border-radius:12px">' +
        '<div style="width:48px;height:48px;background:#ef4444;border-radius:50%;' +
        'display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#fff">' +
        '<i class="fas fa-exclamation-triangle"></i></div>' +
        '<div>' +
        '<p style="font-weight:800;font-size:0.9rem;color:#991b1b;margin:0">Error Loading Data</p>' +
        '<p style="font-size:0.8rem;color:#b91c1c;margin:4px 0 0">' + (errorMsg || 'Network error. Please refresh the page.') + '</p>' +
        '</div></div>'
      );
      
      receptExists = false;
      $('#receptFormTitle').html('<i class="fas fa-user-plus"></i> Create Receptionist Account');
      $('#receptSaveBtnText').text('Create Account');
      $('#receptPwHint').html('(minimum 6 characters)');
      $('#receptDeleteBtn').hide();
    }

    function saveReceptionist() {
      console.log('Saving receptionist...');
      
      const username = $('#receptUsername').val().trim();
      const password = $('#receptPassword').val().trim();

      // Validation
      if (!username) { 
        showReceptError('Username required!'); 
        return; 
      }
      if (username.length < 3) {
        showReceptError('Username must be at least 3 characters!');
        return;
      }
      if (!receptExists && !password) { 
        showReceptError('Password required for new account!'); 
        return; 
      }
      if (password && password.length < 6) {
        showReceptError('Password must be at least 6 characters!');
        return;
      }

      const $btn = $('#receptSaveBtn');
      $btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
      hideReceptError();

      const action = receptExists ? 'update_receptionist' : 'create_receptionist';
      console.log('Action:', action, 'Username:', username);

      $.ajax({
        url: 'action.php',
        type: 'POST',
        data: { 
          action: action, 
          username: username, 
          password: password 
        },
        dataType: 'json',
        timeout: 10000,
        success: function(res) {
          console.log('Save Response:', res);
          $btn.html('<i class="fas fa-save"></i> <span id="receptSaveBtnText">' +
            (receptExists ? 'Update Credentials' : 'Create Account') + '</span>')
            .prop('disabled', false);
            
          if (res.success) {
            showToast(res.message || 'Saved successfully!', 'success');
            // Reload to show updated status
            setTimeout(() => loadReceptionist(), 500);
          } else {
            showReceptError(res.message || 'Error occurred. Please try again.');
          }
        },
        error: function(xhr, status, error) {
          console.error('Save Error:', {
            status: status,
            error: error,
            response: xhr.responseText
          });
          
          $btn.html('<i class="fas fa-save"></i> <span id="receptSaveBtnText">Save</span>')
              .prop('disabled', false);
              
          let msg = 'Network error. Please try again.';
          try { 
            const r = JSON.parse(xhr.responseText); 
            if (r.message) msg = r.message; 
          } catch(e) {
            console.error('Failed to parse error response:', e);
          }
          showReceptError(msg);
        }
      });
    }

    function deleteReceptionist() {
      console.log('Attempting to delete receptionist...');
      
      if (!confirm('Are you sure you want to delete the receptionist account?\n\nThey will no longer be able to login.')) {
        console.log('Delete cancelled by user');
        return;
      }
      
      console.log('Sending delete request...');
      
      $.ajax({
        url: 'action.php',
        type: 'POST',
        data: { action: 'delete_receptionist' },
        dataType: 'json',
        timeout: 10000,
        success: function(res) {
          console.log('Delete Response:', res);
          
          if (res.success) {
            showToast('Receptionist account deleted successfully!', 'success');
            setTimeout(() => loadReceptionist(), 500);
          } else {
            showToast(res.message || 'Failed to delete account', 'error');
          }
        },
        error: function(xhr, status, error) {
          console.error('Delete Error:', {
            status: status,
            error: error,
            response: xhr.responseText
          });
          showToast('Network error during delete', 'error');
        }
      });
    }

    function showReceptError(msg) {
      $('#receptFormError').css('display','flex');
      $('#receptFormErrorMsg').text(msg);
    }
    function hideReceptError() {
      $('#receptFormError').hide();
    }

    function togglePw(inputId, iconId) {
      const inp  = document.getElementById(inputId);
      const icon = document.getElementById(iconId);
      if (inp.type === 'password') {
        inp.type = 'text';
        icon.classList.replace('fa-eye','fa-eye-slash');
      } else {
        inp.type = 'password';
        icon.classList.replace('fa-eye-slash','fa-eye');
      }
    }

    function escHtml(s) {
      return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : '';
    }

    // -- Daily Report ----------------------------------------
    var dailyRefreshTimer = null;

    function loadDailyReport() {
      $.get('action.php', { action: 'get_today_shift' }, function(res) {
        if (!res.success) { showToast(res.message || 'Error loading report', 'error'); return; }

        // Shift date label
        var sd = res.shift_date || '';
        $('#dailyShiftDate').text(sd ? '� ' + sd + ' (3PM ? 3PM)' : '');
        $('#currentShiftId').val(res.shift_id || '');

        // Stats
        $('#dailySales').text('Rs. ' + Number(res.sales || 0).toLocaleString());
        $('#dailyMaterialCost').text('Rs. ' + Number(res.material_cost || 0).toLocaleString());
        $('#dailyOrders').text(res.orders_count || 0);

        // Materials table
        renderMaterials(res.materials || []);

        // Auto-refresh every 60s
        clearInterval(dailyRefreshTimer);
        dailyRefreshTimer = setInterval(loadDailyReport, 60000);
      }, 'json').fail(function() { showToast('Network error loading report', 'error'); });
    }

    function renderMaterials(materials) {
      var total = 0;
      if (!materials.length) {
        $('#materialsTableBody').html('<tr><td colspan="6" style="text-align:center;padding:24px;color:#aaa">No materials added yet</td></tr>');
        $('#matTotalFooter').text('Rs. 0');
        return;
      }
      var html = '';
      materials.forEach(function(m) {
        var lineTotal = parseFloat(m.total_cost || 0);
        total += lineTotal;
        html += '<tr style="border-bottom:1px solid rgba(0,0,0,0.05)">' +
          '<td style="padding:10px 12px;font-weight:600">' + m.item_name + '</td>' +
          '<td style="padding:10px 12px">' + m.quantity + '</td>' +
          '<td style="padding:10px 12px;color:#888">' + m.unit + '</td>' +
          '<td style="padding:10px 12px;font-weight:700;color:#ea580c">Rs. ' + Number(m.total_cost).toLocaleString() + '</td>' +
          '<td style="padding:10px 12px;text-align:center">' +
          '<button onclick="deleteMaterial(' + m.id + ')" style="background:#fee2e2;color:#dc2626;border:none;padding:5px 10px;border-radius:6px;cursor:pointer;font-size:0.75rem;font-weight:700">' +
          '<i class="fas fa-trash"></i></button></td></tr>';
      });
      $('#materialsTableBody').html(html);
      $('#matTotalFooter').text('Rs. ' + Number(total).toLocaleString());
    }

    function calcMatTotal() {
      var cost = parseFloat($('#matCost').val()) || 0;
      $('#matTotalPreview').text(cost > 0 ? 'Total cost: Rs. ' + Number(cost).toLocaleString() : '');
    }

    function addMaterial() {
      var shiftId  = $('#currentShiftId').val();
      var name     = $('#matName').val().trim();
      var qty      = $('#matQty').val().trim();
      var unit     = $('#matUnit').val();
      var totalCost = parseFloat($('#matCost').val()) || 0;

      if (!name)       { showToast('Enter item name', 'error'); return; }
      if (!qty)        { showToast('Enter quantity', 'error'); return; }
      if (!totalCost)  { showToast('Enter total cost', 'error'); return; }

      $.post('action.php', {
        action: 'add_material',
        shift_id: shiftId,
        item_name: name,
        quantity: qty,
        unit: unit,
        total_cost: totalCost
      }, function(res) {
        if (res.success) {
          showToast('Material added!', 'success');
          $('#matName').val(''); $('#matQty').val('');
          $('#matCost').val(''); $('#matTotalPreview').text('');
          loadDailyReport();
        } else {
          showToast(res.message || 'Error adding material', 'error');
        }
      }, 'json').fail(function() { showToast('Network error', 'error'); });
    }

    function deleteMaterial(id) {
      if (!confirm('Delete this material entry?')) return;
      $.post('action.php', { action: 'delete_material', id: id }, function(res) {
        if (res.success) { showToast('Deleted!', 'success'); loadDailyReport(); }
        else showToast(res.message || 'Error', 'error');
      }, 'json');
    }

    var historyLoaded = false;
    function toggleHistory() {
      var wrap = $('#historyTableWrap');
      var chevron = $('#historyChevron');
      if (wrap.is(':visible')) {
        wrap.hide();
        chevron.css('transform', 'rotate(0deg)');
      } else {
        wrap.show();
        chevron.css('transform', 'rotate(180deg)');
        if (!historyLoaded) { loadShiftHistory(); historyLoaded = true; }
      }
    }

    function loadShiftHistory() {
      $.get('action.php', { action: 'get_shift_history' }, function(res) {
        if (!res.success || !res.shifts.length) {
          $('#historyTableBody').html('<tr><td colspan="5" style="text-align:center;padding:24px;color:#aaa">No history yet</td></tr>');
          return;
        }
        var html = '';
        res.shifts.forEach(function(s) {
          var profit = s.sales - s.material_cost;
          var pColor = profit >= 0 ? '#16a34a' : '#dc2626';
          html += '<tr style="border-bottom:1px solid rgba(0,0,0,0.05)">' +
            '<td style="padding:10px 12px;font-weight:600">' + s.shift_date + '</td>' +
            '<td style="padding:10px 12px;color:#16a34a;font-weight:700">Rs. ' + Number(s.sales).toLocaleString() + '</td>' +
            '<td style="padding:10px 12px;color:#ea580c;font-weight:700">Rs. ' + Number(s.material_cost).toLocaleString() + '</td>' +
            '<td style="padding:10px 12px;font-weight:700;color:' + pColor + '">Rs. ' + Number(profit).toLocaleString() + '</td>' +
            '<td style="padding:10px 12px;color:#6b7280">' + s.orders_count + '</td></tr>';
        });
        $('#historyTableBody').html(html);
      }, 'json');
    }

    // -- Report Sub-tabs -------------------------------------
    function showReportTab(tab) {
      ['daily','weekly','custom'].forEach(function(t) {
        $('#rview-' + t).hide();
        $('#rtab-' + t).css({ background: 'transparent', color: '#6b7280' });
      });
      $('#rview-' + tab).show();
      $('#rtab-' + tab).css({ background: '#1a1a1a', color: '#fff' });
      if (tab === 'weekly') loadWeeklyReport();
      if (tab === 'custom') initCustomReport();
    }

    // -- Weekly Report ----------------------------------------
    function loadWeeklyReport() {
      $.get('action.php', { action: 'get_weekly_report' }, function(res) {
        if (!res.success) { showToast(res.message || 'Error', 'error'); return; }

        $('#weeklyDateRange').text('� ' + res.from_date + ' to ' + res.to_date);
        $('#weeklySales').text('Rs. ' + Number(res.sales || 0).toLocaleString());
        $('#weeklyMaterialCost').text('Rs. ' + Number(res.material_cost || 0).toLocaleString());
        var profit = (res.sales || 0) - (res.material_cost || 0);
        $('#weeklyProfit').text('Rs. ' + Number(profit).toLocaleString());
        $('#weeklyProfit').css('color', profit >= 0 ? '#2563eb' : '#dc2626');
        $('#weeklyOrders').text(res.orders_count || 0);

        // Day breakdown
        var html = '';
        (res.days || []).forEach(function(d) {
          var p = d.sales - d.material_cost;
          html += '<tr style="border-bottom:1px solid rgba(0,0,0,0.05)">' +
            '<td style="padding:10px 12px;font-weight:600">' + d.shift_date + '</td>' +
            '<td style="padding:10px 12px;color:#16a34a;font-weight:700">Rs. ' + Number(d.sales).toLocaleString() + '</td>' +
            '<td style="padding:10px 12px;color:#ea580c;font-weight:700">Rs. ' + Number(d.material_cost).toLocaleString() + '</td>' +
            '<td style="padding:10px 12px;font-weight:700;color:' + (p>=0?'#16a34a':'#dc2626') + '">Rs. ' + Number(p).toLocaleString() + '</td>' +
            '<td style="padding:10px 12px;color:#6b7280">' + d.orders_count + '</td></tr>';
        });
        $('#weeklyBreakdownBody').html(html || '<tr><td colspan="5" style="text-align:center;padding:24px;color:#aaa">No data this week</td></tr>');

        // Materials list
        var mhtml = '';
        (res.materials || []).forEach(function(m) {
          mhtml += '<tr style="border-bottom:1px solid rgba(0,0,0,0.05)">' +
            '<td style="padding:10px 12px;color:#888">' + m.shift_date + '</td>' +
            '<td style="padding:10px 12px;font-weight:600">' + m.item_name + '</td>' +
            '<td style="padding:10px 12px">' + m.quantity + '</td>' +
            '<td style="padding:10px 12px;color:#888">' + m.unit + '</td>' +
            '<td style="padding:10px 12px;font-weight:700;color:#ea580c">Rs. ' + Number(m.total_cost).toLocaleString() + '</td></tr>';
        });
        $('#weeklyMaterialsBody').html(mhtml || '<tr><td colspan="5" style="text-align:center;padding:24px;color:#aaa">No materials this week</td></tr>');

      }, 'json').fail(function() { showToast('Network error', 'error'); });
    }

    // -- Custom Date Report ------------------------------------
    function initCustomReport() {
      // Set default dates: last 7 days
      var today = new Date();
      var from  = new Date(); from.setDate(today.getDate() - 7);
      $('#customToDate').val(today.toISOString().split('T')[0]);
      $('#customFromDate').val(from.toISOString().split('T')[0]);
    }

    function setPreset(days, type) {
      var today = new Date();
      var from  = new Date();
      if (type === 'month') {
        from = new Date(today.getFullYear(), today.getMonth(), 1);
      } else {
        from.setDate(today.getDate() - days + 1);
      }
      $('#customFromDate').val(from.toISOString().split('T')[0]);
      $('#customToDate').val(today.toISOString().split('T')[0]);
      loadCustomReport();
    }

    function loadCustomReport() {
      var from = $('#customFromDate').val();
      var to   = $('#customToDate').val();
      if (!from || !to) { showToast('Please select both dates', 'error'); return; }
      if (from > to)    { showToast('From date must be before To date', 'error'); return; }

      $.get('action.php', { action: 'get_custom_report', from_date: from, to_date: to }, function(res) {
        if (!res.success) { showToast(res.message || 'Error', 'error'); return; }

        $('#customStatsWrap').show();
        $('#customNoData').hide();

        $('#customSales').text('Rs. ' + Number(res.sales || 0).toLocaleString());
        $('#customMaterialCost').text('Rs. ' + Number(res.material_cost || 0).toLocaleString());
        var profit = (res.sales || 0) - (res.material_cost || 0);
        $('#customProfit').text('Rs. ' + Number(profit).toLocaleString());
        $('#customProfit').css('color', profit >= 0 ? '#2563eb' : '#dc2626');
        $('#customOrders').text(res.orders_count || 0);

        // Materials table
        var mhtml = '';
        var total = 0;
        (res.materials || []).forEach(function(m) {
          total += parseFloat(m.total_cost || 0);
          mhtml += '<tr style="border-bottom:1px solid rgba(0,0,0,0.05)">' +
            '<td style="padding:10px 12px;color:#888">' + m.shift_date + '</td>' +
            '<td style="padding:10px 12px;font-weight:600">' + m.item_name + '</td>' +
            '<td style="padding:10px 12px">' + m.quantity + '</td>' +
            '<td style="padding:10px 12px;color:#888">' + m.unit + '</td>' +
            '<td style="padding:10px 12px;font-weight:700;color:#ea580c">Rs. ' + Number(m.total_cost).toLocaleString() + '</td></tr>';
        });
        if (!mhtml) { $('#customNoData').show(); $('#customStatsWrap').hide(); }
        $('#customMaterialsBody').html(mhtml || '<tr><td colspan="5" style="text-align:center;padding:24px;color:#aaa">No materials found</td></tr>');
        $('#customMatTotalFooter').text('Rs. ' + Number(total).toLocaleString());

      }, 'json').fail(function() { showToast('Network error', 'error'); });
    }

// ============================================================
// POS � Point of Sale
// ============================================================
window.posItems   = [];   // all menu items fetched
window.posCart    = {};   // { itemId: {id, title, price, qty} }
window.posLoaded  = false;
window.posActiveCategory = 'all';

function loadPOS() {
  window.posLoaded = true;
  window.posItems = (typeof __posMenuData !== 'undefined') ? __posMenuData : [];
  
  // Ensure cart exists
  if (typeof window.posCart === 'undefined') {
    window.posCart = {};
  }
  
  console.log('[POS] loadPOS called, items:', window.posItems.length);
  console.log('[POS] First item structure:', window.posItems[0]);
  console.log('[POS] posCart initialized:', window.posCart);
  
  if (!window.posItems.length) {
    document.getElementById('pos-grid').innerHTML =
      '<div style="grid-column:1/-1;text-align:center;padding:40px 20px;color:#aaa">'
      + '<i class="fas fa-info-circle" style="font-size:2rem;display:block;margin-bottom:10px;opacity:0.4"></i>'
      + 'No menu items found.<br><small>Please add items in <strong>Menu Manager</strong> first.</small></div>';
    return;
  }
  window.posActiveCategory = 'all';
  
  // Ensure cart exists before rendering
  if (typeof window.posCart === 'undefined') {
    window.posCart = {};
  }
  
  posRenderCategoryTabs();
  posRenderItemGrid();
}

function posRenderCategoryTabs() {
  var cats = ['all'];
  window.posItems.forEach(function(it){ if (cats.indexOf(it.category) === -1) cats.push(it.category); });
  var html = '';
  cats.forEach(function(c){
    var isActive = c === window.posActiveCategory;
    var label = c === 'all' ? 'All' : c.replace(/_/g,' ').replace(/\b\w/g, function(l){ return l.toUpperCase(); });
    html += '<button onclick="posSetCategory(\'' + c + '\')" style="padding:6px 18px;border:none;border-radius:20px;cursor:pointer;font-size:0.78rem;font-weight:700;white-space:nowrap;transition:all 0.15s;' + (isActive ? 'background:#1a1a1a;color:#fff' : 'background:#f0f0f0;color:#555') + '">' + label + '</button>';
  });
  document.getElementById('pos-cats').innerHTML = html;
}

function posSetCategory(cat) {
  window.posActiveCategory = cat;
  posRenderCategoryTabs();
  posRenderItemGrid();
}

function posFilterItems() { posRenderItemGrid(); }
function posSearch()      { posRenderItemGrid(); }

function posSwitchTab(tab) {
  var w = document.getElementById('pos-tab-walkin');
  var o = document.getElementById('pos-tab-online');
  var h = document.getElementById('pos-tab-history');
  if (!w || !o || !h) return;
  
  var walkinSection = document.getElementById('pos-walkin-section');
  var onlineSection = document.getElementById('pos-online-section');
  var historySection = document.getElementById('pos-history-section');
  
  // Reset all tabs
  w.style.background='#f3f4f6'; w.style.color='#6b7280';
  o.style.background='#f3f4f6'; o.style.color='#6b7280';
  h.style.background='#f3f4f6'; h.style.color='#6b7280';
  if (walkinSection) walkinSection.style.display = 'none';
  if (onlineSection) onlineSection.style.display = 'none';
  if (historySection) historySection.style.display = 'none';
  
  if (tab === 'walkin') {
    w.style.background='#111'; w.style.color='#fff';
    if (walkinSection) walkinSection.style.display = 'flex';
  } else if (tab === 'online') {
    o.style.background='#111'; o.style.color='#fff';
    if (onlineSection) onlineSection.style.display = 'flex';
    loadPendingOrders();
  } else if (tab === 'history') {
    h.style.background='#111'; h.style.color='#fff';
    if (historySection) historySection.style.display = 'flex';
    loadBillHistory();
  }
}

function loadPendingOrders() {
  console.log('[POS] Loading pending orders...');
  fetch('action.php?action=get_pending_orders')
    .then(function(r) { 
      console.log('[POS] Pending orders response:', r);
      return r.json(); 
    })
    .then(function(data) {
      console.log('[POS] Pending orders data:', data);
      var container = document.getElementById('pos-online-orders-list');
      if (!container) {
        console.error('[POS] Container not found!');
        return;
      }
      
      if (!data.success || !data.orders || data.orders.length === 0) {
        container.innerHTML = '<div style="text-align:center;padding:40px 20px;color:#aaa">'
          + '<i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:10px;opacity:0.3"></i>'
          + 'No pending online orders</div>';
        return;
      }
      
      var html = '';
      data.orders.forEach(function(order) {
        var items = [];
        try { items = JSON.parse(order.items); } catch(e) {}
        var itemCount = items.reduce(function(sum, it) { return sum + parseInt(it.qty || 0); }, 0);
        
        html += '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px;margin-bottom:10px;cursor:pointer;transition:all 0.15s"'
          + ' onmouseover="this.style.boxShadow=\'0 4px 12px rgba(0,0,0,0.1)\'"'
          + ' onmouseout="this.style.boxShadow=\'none\'"'
          + ' onclick="loadOrderToCart(' + order.id + ',\'' + order.name.replace(/'/g,"\\'") + '\',\'' + order.phone.replace(/'/g,"\\'") + '\',\'' + order.address.replace(/'/g,"\\'") + '\',' + order.total + ',\'' + (order.notes || '').replace(/'/g,"\\'") + '\',\'' + order.created_at + '\')">'
          + '<div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:6px">'
          + '<div><div style="font-weight:700;font-size:0.9rem;color:#111">Order #' + order.id + '</div>'
          + '<div style="font-size:0.75rem;color:#6b7280;margin-top:2px">' + order.name + ' • ' + order.phone + '</div></div>'
          + '<div style="background:#fef3c7;color:#92400e;padding:4px 10px;border-radius:6px;font-size:0.7rem;font-weight:700">PENDING</div>'
          + '</div>'
          + '<div style="font-size:0.75rem;color:#6b7280;margin-bottom:6px">' + itemCount + ' items • Rs. ' + Number(order.total).toLocaleString() + '</div>'
          + '<div style="font-size:0.7rem;color:#9ca3af">' + new Date(order.created_at).toLocaleTimeString('en-PK', {hour:'2-digit',minute:'2-digit'}) + '</div>'
          + '</div>';
      });
      container.innerHTML = html;
    })
    .catch(function(err) {
      console.error('[POS] Error loading pending orders:', err);
      var container = document.getElementById('pos-online-orders-list');
      if (container) container.innerHTML = '<div style="text-align:center;padding:20px;color:#ef4444">Error loading orders. Check console.</div>';
    });
}

function loadOrderToCart(orderId, name, phone, address, total, notes, createdAt) {
  // First, get the order details with items
  fetch('action.php?action=get_order_details&order_id=' + orderId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.success || !data.order) {
        showToast('Failed to load order', 'error');
        return;
      }
      
      var order = data.order;
      var items = [];
      try { items = JSON.parse(order.items); } catch(e) {}
      
      // Clear current cart and load order items
      window.posCart = {};
      items.forEach(function(item) {
        var itemId = String(item.id);
        window.posCart[itemId] = {
          id: itemId,
          title: item.title,
          price: parseFloat(item.price),
          qty: parseInt(item.qty)
        };
      });
      
      // Fill customer details
      document.getElementById('pos-cname').value = name;
      document.getElementById('pos-cphone').value = phone;
      if (document.getElementById('pos-caddress')) {
        document.getElementById('pos-caddress').value = address;
      }
      
      // Store order ID for updating status later
      window.currentOnlineOrderId = orderId;
      
      // Switch to walk-in tab to show the cart
      posSwitchTab('walkin');
      
      // Render cart and grid
      posRenderCart();
      posRenderItemGrid();
      
      showToast('Order #' + orderId + ' loaded to cart', 'success');
    })
    .catch(function() {
      showToast('Error loading order', 'error');
    });
}

function posTypeChange(val) {
  var row = document.getElementById('pos-address-row');
  if (row) row.style.display = (val === 'Delivery') ? 'block' : 'none';
}

function posCalcTotal() {
  posRenderCart(); // Re-render cart to update totals with new discount
}

function posRenderItemGrid() {
  console.log('[POS] posRenderItemGrid called, posItems:', window.posItems);
  var search = (document.getElementById('pos-search').value || '').toLowerCase();
  var grid = document.getElementById('pos-grid');
  console.log('[POS] grid element:', grid);
  if (!grid) {
    console.error('[POS] grid element not found!');
    return;
  }
  var html = '';
  var shown = 0;

  for (var i = 0; i < window.posItems.length; i++) {
    var it = window.posItems[i];
    console.log('[POS] Processing item:', i, it);
    if (window.posActiveCategory !== 'all' && it.category !== window.posActiveCategory) continue;
    if (search && it.title && it.title.toLowerCase().indexOf(search) === -1) continue;
    shown++;
    
    // Safely check cart
    var itemIdStr = String(it.id);
    var inCart = 0;
    if (window.posCart && window.posCart[itemIdStr]) {
      inCart = window.posCart[itemIdStr].qty || 0;
    }
    
    var safeTitle = (it.title || 'Untitled').replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'&quot;');
    
    // Fix image path - add ../ if path doesn't start with http or ../
    var imagePath = it.image_path || '';
    if (imagePath && !imagePath.startsWith('http') && !imagePath.startsWith('../')) {
      imagePath = '../' + imagePath;
    }
    
    var imgHtml = imagePath
      ? '<img src="' + imagePath + '" style="width:100%;height:130px;object-fit:cover;display:block" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">'
        + '<div class="pos-img-placeholder" style="display:none;width:100%;height:130px;background:#f3f4f6;align-items:center;justify-content:center"><i class="fas fa-utensils" style="font-size:1.8rem;color:#d1d5db"></i></div>'
      : '<div class="pos-img-placeholder" style="display:flex;width:100%;height:130px;background:#f3f4f6;align-items:center;justify-content:center"><i class="fas fa-utensils" style="font-size:1.8rem;color:#d1d5db"></i></div>';

    var addBtn = inCart > 0
      ? '<div style="display:flex;align-items:center;justify-content:space-between;background:#111;border-radius:8px;padding:5px 8px;margin-top:8px">'
          + '<button onclick="posUpdateQty(' + it.id + ',-1)" style="background:none;border:none;color:#fff;font-size:1.1rem;line-height:1;cursor:pointer;width:24px;font-weight:900">-</button>'
          + '<span style="color:#fff;font-weight:800;font-size:0.85rem">' + inCart + '</span>'
          + '<button onclick="posUpdateQty(' + it.id + ',1)" style="background:none;border:none;color:#fff;font-size:1.1rem;line-height:1;cursor:pointer;width:24px;font-weight:900">+</button>'
          + '</div>'
      : '<button onclick="posAddToCart(' + it.id + ',\'' + safeTitle + '\',' + it.price + ')"'
          + ' style="width:100%;margin-top:8px;padding:7px 0;background:#111;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:0.78rem;cursor:pointer;font-family:inherit;letter-spacing:0.03em">'
          + '<i class="fas fa-plus" style="margin-right:4px;font-size:0.7rem"></i>Add</button>';

    html += '<div style="background:#fff;border-radius:12px;overflow:hidden;border:1px solid rgba(0,0,0,0.07);box-shadow:0 1px 4px rgba(0,0,0,0.06);transition:all 0.15s;cursor:default;display:flex;flex-direction:column;min-height:260px"'
          + ' onmouseover="this.style.boxShadow=\'0 4px 16px rgba(0,0,0,0.12)\';this.style.transform=\'translateY(-2px)\'"'
          + ' onmouseout="this.style.boxShadow=\'0 1px 4px rgba(0,0,0,0.06)\';this.style.transform=\'none\'">'
          + imgHtml
          + '<div style="padding:12px;flex:1;display:flex;flex-direction:column;justify-content:space-between">'
          + '<div style="font-weight:700;font-size:0.88rem;color:#111;line-height:1.4;margin-bottom:6px;min-height:38px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;word-break:break-word">' + it.title + '</div>'
          + '<div style="margin-top:auto">'
          + '<div style="font-weight:900;font-size:1.05rem;color:#e05c00;margin-bottom:4px">Rs. ' + Number(it.price).toLocaleString() + '</div>'
          + addBtn
          + '</div></div></div>';
  }

  if (!shown) {
    html = '<div style="grid-column:1/-1;text-align:center;padding:40px 20px;color:#aaa">'
         + '<i class="fas fa-search" style="font-size:2rem;display:block;margin-bottom:10px;opacity:0.3"></i>'
         + (search ? 'No items match &ldquo;' + search + '&rdquo;' : 'No items in this category')
         + '</div>';
  }
  console.log('[POS] About to set innerHTML, shown:', shown, 'html length:', html.length);
  grid.innerHTML = html;
  console.log('[POS] innerHTML set successfully');
}

function posAddToCart(itemId, title, price) {
  console.log('[POS] posAddToCart called:', itemId, title, price);
  console.log('[POS] posCart before:', window.posCart);
  itemId = String(itemId);
  if (window.posCart[itemId]) {
    window.posCart[itemId].qty++;
  } else {
    window.posCart[itemId] = { id: itemId, title: title, price: parseFloat(price), qty: 1 };
  }
  console.log('[POS] posCart after:', window.posCart);
  posRenderCart();
  posRenderItemGrid();
  updateMobileCartBadge();
  showToast(title + ' added', 'success');
}

function posUpdateQty(itemId, delta) {
  itemId = String(itemId);
  if (!window.posCart[itemId]) return;
  window.posCart[itemId].qty += delta;
  if (window.posCart[itemId].qty <= 0) delete window.posCart[itemId];
  posRenderCart();
  posRenderItemGrid();
  updateMobileCartBadge();
}

function posRenderCart() {
  console.log('[POS] posRenderCart called');
  var cart = document.getElementById('pos-cart');
  console.log('[POS] cart element:', cart);
  var keys = Object.keys(window.posCart);
  console.log('[POS] posCart keys:', keys);
  console.log('[POS] posCart:', window.posCart);
  var subtotal = 0;

  if (!keys.length) {
    cart.innerHTML = '<div id="pos-cart-empty" style="text-align:center;padding:30px 0;color:#ccc">'
      + '<i class="fas fa-shopping-basket" style="font-size:2rem;display:block;margin-bottom:8px"></i>'
      + '<span style="font-size:0.82rem">Cart is empty</span></div>';
    document.getElementById('pos-sub').innerText   = 'Rs. 0';
    document.getElementById('pos-total').innerText = 'Rs. 0';
    return;
  }

  var html = '';
  keys.forEach(function(k) {
    var it = window.posCart[k];
    var line = it.price * it.qty;
    subtotal += line;
    html += '<div style="display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid #f3f4f6">'
      + '<div style="flex:1;min-width:0">'
      + '<div style="font-size:0.8rem;font-weight:700;color:#111;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + it.title + '</div>'
      + '<div style="font-size:0.74rem;color:#6b7280">Rs.' + it.price + ' × ' + it.qty + '</div>'
      + '</div>'
      + '<div style="font-weight:800;font-size:0.82rem;color:#111;white-space:nowrap">Rs.' + Number(line).toLocaleString() + '</div>'
      + '<div style="display:flex;align-items:center;gap:2px">'
      + '<button onclick="posUpdateQty(\'' + k + '\',-1)" style="width:22px;height:22px;border:1px solid #e5e7eb;background:#fff;border-radius:5px;cursor:pointer;font-weight:900;font-size:0.9rem;line-height:1">−</button>'
      + '<span style="font-size:0.78rem;font-weight:700;min-width:18px;text-align:center">' + it.qty + '</span>'
      + '<button onclick="posUpdateQty(\'' + k + '\',1)" style="width:22px;height:22px;border:1px solid #e5e7eb;background:#fff;border-radius:5px;cursor:pointer;font-weight:900;font-size:0.9rem;line-height:1">+</button>'
      + '<button onclick="posRemoveItem(\'' + k + '\')" style="width:22px;height:22px;border:none;background:#fee2e2;border-radius:5px;cursor:pointer;color:#dc2626;font-size:0.7rem;margin-left:2px">'
      + '<i class="fas fa-trash"></i></button>'
      + '</div></div>';
  });
  console.log('[POS] Cart HTML generated, length:', html.length);
  cart.innerHTML = html;
  console.log('[POS] Cart innerHTML set');

  // Update totals (no discount)
  document.getElementById('pos-sub').innerText   = 'Rs. ' + Number(subtotal).toLocaleString();
  document.getElementById('pos-total').innerText = 'Rs. ' + Number(subtotal).toLocaleString();
  console.log('[POS] Cart totals updated - Subtotal:', subtotal);
}

function posRemoveItem(itemId) {
  delete window.posCart[itemId];
  posRenderCart();
  posRenderItemGrid();
  updateMobileCartBadge();
}

// posPrint / posSave — called by HTML buttons
function posPrint() {
  var keys = Object.keys(window.posCart);
  if (!keys.length) { showToast('Cart is empty', 'error'); return; }

  var name  = (document.getElementById('pos-cname').value  || '').trim() || 'Walk-in';
  var phone = (document.getElementById('pos-cphone').value || '').trim() || '—';
  var addr  = (document.getElementById('pos-caddress') ? document.getElementById('pos-caddress').value : '') || '';
  var type  = document.querySelector('input[name="pos-type"]:checked') ? document.querySelector('input[name="pos-type"]:checked').value : 'Walk-in';
  var now   = new Date();
  var ds    = now.toLocaleDateString('en-PK', {day:'2-digit',month:'short',year:'numeric'});
  var ts    = now.toLocaleTimeString('en-PK', {hour:'2-digit',minute:'2-digit'});

  var sub = 0; var rows = '';
  keys.forEach(function(k) {
    var it = window.posCart[k];
    var line = it.price * it.qty;
    sub += line;
    rows += '<tr><td>' + it.title + '</td>'
      + '<td style="text-align:center">' + it.qty + '</td>'
      + '<td style="text-align:right">Rs.' + Number(it.price).toLocaleString() + '</td>'
      + '<td style="text-align:right">Rs.' + Number(line).toLocaleString() + '</td></tr>';
  });
  var total = sub; // No discount
  var addrRow = addr ? '<p style="margin:2px 0;font-size:11px"><b>Address:</b> ' + addr + '</p>' : '';

  var html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Bill</title><style>'
    + 'body{font-family:"Courier New",monospace;font-size:12px;width:76mm;margin:0;padding:6px}'
    + 'h2{text-align:center;font-size:17px;letter-spacing:2px;margin:0 0 2px}.c{text-align:center}.s{font-size:10px}'
    + 'hr{border:none;border-top:1px dashed #000;margin:5px 0}'
    + 'table{width:100%;border-collapse:collapse;font-size:11px}'
    + 'th{font-size:10px;font-weight:bold;border-bottom:1px dashed #000;padding:2px 0;text-align:left}'
    + 'th:nth-child(2),th:nth-child(3),th:nth-child(4){text-align:right}'
    + 'td{padding:2px 0}.tot td{font-weight:bold;font-size:13px;border-top:1px dashed #000;padding-top:3px}'
    + '.tot td:last-child{text-align:right}@media print{body{width:76mm}}'
    + '</style></head><body onload="window.print()">'
    + '<h2>SNACK ZONE</h2>'
    + '<p class="c s" style="margin:1px 0">6th Road, Satellite Town, Rawalpindi</p>'
    + '<hr>'
    + '<p style="margin:2px 0;font-size:11px"><b>Date:</b> ' + ds + ' &nbsp;<b>Time:</b> ' + ts + '</p>'
    + '<p style="margin:2px 0;font-size:11px"><b>Type:</b> ' + type + '</p>'
    + '<p style="margin:2px 0;font-size:11px"><b>Customer:</b> ' + name + '</p>'
    + '<p style="margin:2px 0;font-size:11px"><b>Phone:</b> ' + phone + '</p>'
    + addrRow + '<hr>'
    + '<table><thead><tr><th>Item</th><th style="text-align:right">Qty</th><th style="text-align:right">Price</th><th style="text-align:right">Total</th></tr></thead>'
    + '<tbody>' + rows + '</tbody>'
    + '<tfoot><tr class="tot"><td colspan="3">TOTAL</td><td>Rs.' + Number(total).toLocaleString() + '</td></tr></tfoot></table>'
    + '<hr><p class="c s" style="margin-top:6px">Thank you! Please visit again.</p>'
    + '</body></html>';

  var w = window.open('','_blank','width=420,height=620');
  if (w) { w.document.write(html); w.document.close(); }
}

function posSave() {
  var keys = Object.keys(window.posCart);
  if (!keys.length) { showToast('Cart is empty', 'error'); return; }
  var name  = (document.getElementById('pos-cname').value  || '').trim() || 'Walk-in';
  var phone = (document.getElementById('pos-cphone').value || '').trim() || '0000000000';
  var addr  = (document.getElementById('pos-caddress') ? document.getElementById('pos-caddress').value : '').trim() || 'Walk-in';
  var type  = document.querySelector('input[name="pos-type"]:checked') ? document.querySelector('input[name="pos-type"]:checked').value : 'Dine-in';
  var sub   = 0; var items = [];
  keys.forEach(function(k){ var it=window.posCart[k]; sub+=it.price*it.qty; items.push({id:it.id,title:it.title,price:it.price,qty:it.qty}); });
  var total = sub; // No discount

  var body = new URLSearchParams({
    name: name, phone: phone,
    address: addr + ' [' + type + ']',
    items: JSON.stringify(items),
    total: total, notes: 'POS - ' + type, user_id: 0
  });
  fetch('save_order.php', {method:'POST', body: body})
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data.success) {
        // Show token number in a prominent way
        var tokenNum = data.token_number || '?';
        showTokenModal(tokenNum, data.order_id, name);
        
        // Clear cart
        window.posCart = {};
        document.getElementById('pos-cname').value  = '';
        document.getElementById('pos-cphone').value = '';
        if (document.getElementById('pos-caddress')) document.getElementById('pos-caddress').value = '';
        posRenderCart();
        posRenderItemGrid();
      } else { showToast(data.message || 'Save failed', 'error'); }
    })
    .catch(function(){ showToast('Network error', 'error'); });
}

function showTokenModal(tokenNum, orderId, customerName) {
  // Create modal overlay
  var overlay = document.createElement('div');
  overlay.id = 'token-modal-overlay';
  overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:10000;display:flex;align-items:center;justify-content:center;animation:fadeIn 0.2s';
  
  var modal = document.createElement('div');
  modal.style.cssText = 'background:#fff;border-radius:20px;padding:40px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3);max-width:400px;animation:slideUp 0.3s';
  
  modal.innerHTML = 
    '<div style="font-size:3rem;margin-bottom:10px">🎫</div>' +
    '<div style="font-size:1.1rem;font-weight:700;color:#111;margin-bottom:8px">Token Number</div>' +
    '<div style="font-size:5rem;font-weight:900;color:#f59e0b;margin:20px 0;line-height:1">' + tokenNum + '</div>' +
    '<div style="font-size:0.9rem;color:#6b7280;margin-bottom:8px">Order #' + orderId + '</div>' +
    '<div style="font-size:0.85rem;color:#111;font-weight:600;margin-bottom:20px">' + customerName + '</div>' +
    '<button onclick="closeTokenModal()" style="padding:12px 40px;background:#111;color:#fff;border:none;border-radius:10px;font-weight:700;font-size:0.9rem;cursor:pointer;font-family:inherit">OK</button>';
  
  overlay.appendChild(modal);
  document.body.appendChild(overlay);
  
  // Auto-close after 5 seconds
  setTimeout(function() {
    if (document.getElementById('token-modal-overlay')) {
      closeTokenModal();
    }
  }, 5000);
}

function closeTokenModal() {
  var overlay = document.getElementById('token-modal-overlay');
  if (overlay) {
    overlay.style.animation = 'fadeOut 0.2s';
    setTimeout(function() {
      if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
    }, 200);
  }
}

function toggleMobileCart() {
  var cartSection = document.getElementById('pos-cart-section');
  if (cartSection) {
    cartSection.classList.toggle('mobile-open');
  }
}

function updateMobileCartBadge() {
  var keys = Object.keys(window.posCart);
  var count = keys.reduce(function(sum, k) { return sum + (window.posCart[k].qty || 0); }, 0);
  var badge = document.getElementById('mobile-cart-count');
  if (badge) {
    if (count > 0) {
      badge.style.display = 'flex';
      badge.textContent = count;
    } else {
      badge.style.display = 'none';
    }
  }
}

function loadBillHistory() {
  console.log('[POS] Loading bill history...');
  fetch('action.php?action=get_bill_history')
    .then(function(r) { 
      console.log('[POS] Bill history response:', r);
      return r.json(); 
    })
    .then(function(data) {
      console.log('[POS] Bill history data:', data);
      var container = document.getElementById('pos-history-list');
      if (!container) {
        console.error('[POS] History container not found!');
        return;
      }
      
      if (!data.success || !data.orders || data.orders.length === 0) {
        container.innerHTML = '<div style="text-align:center;padding:40px 20px;color:#aaa">'
          + '<i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:10px;opacity:0.3"></i>'
          + 'No bills saved today</div>';
        return;
      }
      
      var html = '<div style="display:grid;gap:10px">';
      data.orders.forEach(function(order) {
        var items = [];
        try { items = JSON.parse(order.items); } catch(e) {}
        var itemCount = items.reduce(function(sum, it) { return sum + parseInt(it.qty || 0); }, 0);
        var time = new Date(order.created_at).toLocaleTimeString('en-PK', {hour:'2-digit',minute:'2-digit'});
        var tokenBadge = order.token_number 
          ? '<div style="background:#fef3c7;color:#92400e;padding:4px 10px;border-radius:6px;font-size:0.75rem;font-weight:700">Token #' + order.token_number + '</div>'
          : '';
        
        var statusColor = order.status === 'confirmed' ? '#16a34a' : order.status === 'pending' ? '#f59e0b' : '#6b7280';
        
        html += '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px;transition:all 0.15s"'
          + ' onmouseover="this.style.boxShadow=\'0 4px 12px rgba(0,0,0,0.1)\'"'
          + ' onmouseout="this.style.boxShadow=\'none\'">'
          + '<div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px">'
          + '<div style="flex:1">'
          + '<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">'
          + '<div style="font-weight:700;font-size:0.9rem;color:#111">Order #' + order.id + '</div>'
          + tokenBadge
          + '</div>'
          + '<div style="font-size:0.75rem;color:#6b7280">' + order.name + ' • ' + order.phone + '</div>'
          + '<div style="font-size:0.7rem;color:#9ca3af;margin-top:2px">' + time + '</div>'
          + '</div>'
          + '<div style="text-align:right">'
          + '<div style="font-weight:800;font-size:0.95rem;color:#111">Rs. ' + Number(order.total).toLocaleString() + '</div>'
          + '<div style="font-size:0.7rem;color:' + statusColor + ';font-weight:600;margin-top:2px">' + order.status.toUpperCase() + '</div>'
          + '</div>'
          + '</div>'
          + '<div style="font-size:0.75rem;color:#6b7280;margin-bottom:10px">' + itemCount + ' items</div>'
          + '<button onclick="reprintBill(' + order.id + ')" style="width:100%;padding:8px;background:#2563eb;color:#fff;border:none;border-radius:6px;font-weight:600;font-size:0.75rem;cursor:pointer;font-family:inherit">'
          + '<i class="fas fa-print" style="margin-right:4px"></i>Reprint Bill</button>'
          + '</div>';
      });
      html += '</div>';
      container.innerHTML = html;
    })
    .catch(function(err) {
      console.error('[POS] Error loading bill history:', err);
      var container = document.getElementById('pos-history-list');
      if (container) container.innerHTML = '<div style="text-align:center;padding:20px;color:#ef4444">Error loading history. Check console.</div>';
    });
}

function reprintBill(orderId) {
  fetch('action.php?action=get_order_details&order_id=' + orderId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.success || !data.order) {
        showToast('Failed to load order', 'error');
        return;
      }
      
      var order = data.order;
      var items = [];
      try { items = JSON.parse(order.items); } catch(e) {}
      
      var now = new Date(order.created_at);
      var ds = now.toLocaleDateString('en-PK', {day:'2-digit',month:'short',year:'numeric'});
      var ts = now.toLocaleTimeString('en-PK', {hour:'2-digit',minute:'2-digit'});
      
      var rows = '';
      var sub = 0;
      items.forEach(function(item) {
        var line = parseFloat(item.price) * parseInt(item.qty);
        sub += line;
        rows += '<tr><td>' + item.title + '</td>'
          + '<td style="text-align:center">' + item.qty + '</td>'
          + '<td style="text-align:right">Rs.' + Number(item.price).toLocaleString() + '</td>'
          + '<td style="text-align:right">Rs.' + Number(line).toLocaleString() + '</td></tr>';
      });
      
      var tokenRow = order.token_number 
        ? '<p style="margin:8px 0;font-size:14px;text-align:center;font-weight:bold;border:2px dashed #000;padding:8px;background:#fff9db">TOKEN #' + order.token_number + '</p>'
        : '';
      
      var html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Bill #' + order.id + '</title><style>'
        + 'body{font-family:"Courier New",monospace;font-size:12px;width:76mm;margin:0;padding:6px}'
        + 'h2{text-align:center;font-size:17px;letter-spacing:2px;margin:0 0 2px}.c{text-align:center}.s{font-size:10px}'
        + 'hr{border:none;border-top:1px dashed #000;margin:5px 0}'
        + 'table{width:100%;border-collapse:collapse;font-size:11px}'
        + 'th{font-size:10px;font-weight:bold;border-bottom:1px dashed #000;padding:2px 0;text-align:left}'
        + 'th:nth-child(2),th:nth-child(3),th:nth-child(4){text-align:right}'
        + 'td{padding:2px 0}.tot td{font-weight:bold;font-size:13px;border-top:1px dashed #000;padding-top:3px}'
        + '.tot td:last-child{text-align:right}@media print{body{width:76mm}}'
        + '</style></head><body onload="window.print()">'
        + '<h2>SNACK ZONE</h2>'
        + '<p class="c s" style="margin:1px 0">6th Road, Satellite Town, Rawalpindi</p>'
        + '<hr>'
        + tokenRow
        + '<p style="margin:2px 0;font-size:11px"><b>Order ID:</b> ' + order.id + '</p>'
        + '<p style="margin:2px 0;font-size:11px"><b>Date:</b> ' + ds + ' &nbsp;<b>Time:</b> ' + ts + '</p>'
        + '<p style="margin:2px 0;font-size:11px"><b>Customer:</b> ' + order.name + '</p>'
        + '<p style="margin:2px 0;font-size:11px"><b>Phone:</b> ' + order.phone + '</p>'
        + '<p style="margin:2px 0;font-size:11px"><b>Status:</b> ' + order.status.toUpperCase() + '</p><hr>'
        + '<table><thead><tr><th>Item</th><th style="text-align:right">Qty</th><th style="text-align:right">Price</th><th style="text-align:right">Total</th></tr></thead>'
        + '<tbody>' + rows + '</tbody>'
        + '<tfoot><tr class="tot"><td colspan="3">TOTAL</td><td>Rs.' + Number(order.total).toLocaleString() + '</td></tr></tfoot></table>'
        + '<hr><p class="c s" style="margin-top:6px">Thank you! Please visit again.</p>'
        + '<p class="c s">snackzone.pk</p></body></html>';
      
      var w = window.open('','_blank','width=420,height=620');
      if (w) { w.document.write(html); w.document.close(); }
      showToast('Reprinting bill #' + order.id, 'success');
    })
    .catch(function() {
      showToast('Error loading order', 'error');
    });
}

function printOnlineBill(orderId, name, phone, address, itemsJson, total, notes, createdAt) {
  var items = [];
  try { items = JSON.parse(itemsJson); } catch(e) {}

  var now = new Date(createdAt);
  var dateStr = now.toLocaleDateString('en-PK', { day:'2-digit', month:'short', year:'numeric' });
  var timeStr = now.toLocaleTimeString('en-PK', { hour:'2-digit', minute:'2-digit' });

  var rowsHtml = '';
  items.forEach(function(item) {
    var lineTotal = (parseFloat(item.price) * parseInt(item.qty)).toLocaleString();
    rowsHtml += '<tr>' +
      '<td style="padding:3px 0">' + item.title + '</td>' +
      '<td style="text-align:center;padding:3px 4px">' + item.qty + '</td>' +
      '<td style="text-align:right;padding:3px 0">Rs.' + parseFloat(item.price).toLocaleString() + '</td>' +
      '<td style="text-align:right;padding:3px 0">Rs.' + lineTotal + '</td>' +
      '</tr>';
  });

  var notesRow = notes ? '<p style="margin:4px 0;font-size:11px"><strong>Notes:</strong> ' + notes + '</p>' : '';

  var html = '<!DOCTYPE html><html><head><meta charset="UTF-8">' +
    '<title>Bill #' + orderId + '</title>' +
    '<style>' +
    '  * { margin:0; padding:0; box-sizing:border-box; }' +
    '  body { font-family: "Courier New", monospace; font-size:13px; width:80mm; padding:8px; }' +
    '  h2 { text-align:center; font-size:18px; letter-spacing:2px; margin-bottom:2px; }' +
    '  .center { text-align:center; }' +
    '  .small { font-size:11px; }' +
    '  hr { border:none; border-top:1px dashed #000; margin:6px 0; }' +
    '  table { width:100%; border-collapse:collapse; font-size:12px; }' +
    '  th { font-size:11px; font-weight:bold; text-align:left; padding:2px 0; border-bottom:1px dashed #000; }' +
    '  th:nth-child(2) { text-align:center; }' +
    '  th:nth-child(3), th:nth-child(4) { text-align:right; }' +
    '  .total-row td { font-weight:bold; border-top:1px dashed #000; padding-top:4px; font-size:14px; }' +
    '  .total-row td:last-child { text-align:right; }' +
    '  .footer { text-align:center; margin-top:8px; font-size:11px; }' +
    '  @media print { body { width:80mm; } }' +
    '</style></head>' +
    '<body onload="window.print()">' +
    '<h2>SNACK ZONE</h2>' +
    '<p class="center small" style="margin:2px 0">6th Road, Rawalpindi</p>' +
   
    '<hr>' +
    '<p class="small">Bill No: <strong>#' + orderId + '</strong></p>' +
    '<p class="small">Date: ' + dateStr + ' &nbsp; Time: ' + timeStr + '</p>' +
    '<hr>' +
    '<p class="small"><strong>Customer:</strong> ' + name + '</p>' +
    '<p class="small"><strong>Phone:</strong> ' + phone + '</p>' +
    '<p class="small"><strong>Address:</strong> ' + address + '</p>' +
    notesRow +
    '<hr>' +
    '<table>' +
    '  <thead><tr>' +
    '    <th>Item</th><th style="text-align:center">Qty</th>' +
    '    <th style="text-align:right">Price</th><th style="text-align:right">Total</th>' +
    '  </tr></thead>' +
    '  <tbody>' + rowsHtml + '</tbody>' +
    '  <tfoot><tr class="total-row">' +
    '    <td colspan="3">TOTAL</td>' +
    '    <td>Rs.' + parseFloat(total).toLocaleString() + '</td>' +
    '  </tr></tfoot>' +
    '</table>' +
    '<hr>' +
    '<p class="footer">Thank you for your order!</p>' +
    '</body></html>';

  var win = window.open('', '_blank', 'width=420,height=600');
  win.document.write(html);
  win.document.close();
}

function posPrintReceipt() {
  var keys = Object.keys(posCart);
  if (!keys.length) { showToast('Cart is empty', 'error'); return; }
  var subtotal = 0;
  var rows = '';
  keys.forEach(function(k){
    var it = posCart[k];
    var line = it.price * it.qty;
    subtotal += line;
    rows += '<tr><td>' + it.title + '</td><td style="text-align:center">x' + it.qty + '</td><td style="text-align:right">Rs.' + line + '</td></tr>';
  });
  var discount  = parseFloat(document.getElementById('pos-discount').value) || 0;
  var grand     = Math.max(0, subtotal - discount);
  var custName  = document.getElementById('pos-customer-name').value  || 'Walk-in';
  var orderType = document.querySelector('input[name="pos-order-type"]:checked').value;
  var now       = new Date();
  var dateStr   = now.toLocaleDateString();
  var timeStr   = now.toLocaleTimeString();

  var win = window.open('', '_blank', 'width=400,height=600');
  win.document.write('<!DOCTYPE html>\n<html><head><meta charset="utf-8">\n<title>Receipt</title>\n<style>\n  body { font-family: monospace; width: 80mm; margin: 0 auto; padding: 8px; font-size: 12px; }\n  h2 { text-align:center; margin:4px 0; font-size:16px; }\n  .center { text-align:center; }\n  hr { border:none; border-top:1px dashed #000; margin:6px 0; }\n  table { width:100%; border-collapse:collapse; }\n  td { padding:2px 0; }\n  .total-row td { font-weight:bold; border-top:1px dashed #000; padding-top:4px; }\n  @media print { body { width:80mm; } }\n</style>\n</head><body onload="window.print()">\n<h2>SNACK ZONE</h2>\n<p class="center" style="margin:2px 0">6th Road, Rawalpindi</p>\n<hr>\n<p class="center">Date: ' + dateStr + ' &nbsp; Time: ' + timeStr + '</p>\n<hr>\n<p>Customer: ' + custName + '</p>\n<p>Type: ' + orderType + '</p>\n<hr>\n<table>\n  <thead><tr><th style="text-align:left">Item</th><th>Qty</th><th style="text-align:right">Amount</th></tr></thead>\n  <tbody>' + rows + '</tbody>\n  <tfoot>\n    <tr><td colspan="2">Subtotal</td><td style="text-align:right">Rs. ' + subtotal + '</td></tr>\n    <tr><td colspan="2">Discount</td><td style="text-align:right">Rs. ' + discount + '</td></tr>\n    <tr class="total-row"><td colspan="2"><strong>TOTAL</strong></td><td style="text-align:right"><strong>Rs. ' + grand + '</strong></td></tr>\n  </tfoot>\n</table>\n<hr>\n<p class="center">Thank you for visiting!</p>\n</body></html>');
  win.document.close();
}

function posSaveOrder() {
  var keys = Object.keys(posCart);
  if (!keys.length) { showToast('Cart is empty', 'error'); return; }
  var custName  = document.getElementById('pos-customer-name').value.trim() || 'Walk-in';
  var custPhone = document.getElementById('pos-customer-phone').value.trim() || '';
  var orderType = document.querySelector('input[name="pos-order-type"]:checked').value;
  var discount  = parseFloat(document.getElementById('pos-discount').value) || 0;
  var subtotal  = 0;
  var items     = [];
  Object.values(posCart).forEach(function(it){
    subtotal += it.price * it.qty;
    items.push({ id: it.id, title: it.title, price: it.price, qty: it.qty });
  });
  var grand = Math.max(0, subtotal - discount);
  var fd = new FormData();
  fd.append('action',     'save_pos_order');
  fd.append('name',       custName);
  fd.append('phone',      custPhone); 
  fd.append('items',      JSON.stringify(items));
  fd.append('total',      grand);
  fd.append('discount',   discount);
  fd.append('order_type', orderType);
  fd.append('notes',      'POS order � ' + orderType);
  fetch('action.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data.success) {
        showToast('Order #' + data.order_id + ' saved!', 'success');
        window.posCart = {};
        posRenderCart();
        document.getElementById('pos-customer-name').value  = '';
        document.getElementById('pos-customer-phone').value = '';
        document.getElementById('pos-discount').value       = '0';
        document.querySelector('input[name="pos-order-type"][value="Dine-in"]').checked = true;
      } else {
        showToast(data.message || 'Failed to save order', 'error');
      }
    })
    .catch(function(){ showToast('Network error saving order', 'error'); });
}

  </script>
  <!-- POS menu data � injected directly as JS variable, most reliable method -->
  <script>var __posMenuData = <?= json_encode($menuItemsForPOS) ?>;</script>
</body>
</html>
                

<script>
// ======= PROFIT ANALYSIS FUNCTIONS =======
function handleProfitFilterChange() {
  var filter = document.getElementById('profit-filter-select').value;
  var customDiv = document.getElementById('profit-custom-dates');
  if (filter === 'custom') {
    customDiv.style.display = 'block';
    // Set default dates
    var today = new Date().toISOString().split('T')[0];
    document.getElementById('profit-to-date').value = today;
    var weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);
    document.getElementById('profit-from-date').value = weekAgo.toISOString().split('T')[0];
  } else {
    customDiv.style.display = 'none';
    loadProfitSummary();
  }
}

function loadProfitSummary() {
  var filter = document.getElementById('profit-filter-select').value;
  var url = 'action.php?action=get_profit_summary&filter=' + filter;
  
  if (filter === 'custom') {
    var fromDate = document.getElementById('profit-from-date').value;
    var toDate = document.getElementById('profit-to-date').value;
    if (!fromDate || !toDate) {
      showToast('Please select both dates', 'error');
      return;
    }
    url += '&from_date=' + fromDate + '&to_date=' + toDate;
  }
  
  // Show loading in daily table
  document.getElementById('profit-daily-table').innerHTML = '<div style="text-align:center;padding:3rem;color:#9ca3af">'
    + '<i class="fas fa-spinner fa-spin" style="font-size:2rem;display:block;margin-bottom:1rem"></i>'
    + '<span style="font-size:0.9rem;font-weight:600">Loading data...</span></div>';
  
  fetch(url)
    .then(r => {
      if (!r.ok) {
        throw new Error('HTTP error ' + r.status);
      }
      return r.text();
    })
    .then(text => {
      console.log('API Response:', text);
      try {
        var data = JSON.parse(text);
      } catch(e) {
        console.error('JSON Parse Error:', e, '\nResponse:', text);
        throw new Error('Invalid JSON response');
      }
      
      if (!data.success) {
        throw new Error(data.message || 'Failed to load data');
      }
      
      // Update summary cards
      document.getElementById('profit-total-sales').innerText = 'Rs. ' + Number(data.total_sales).toLocaleString();
      document.getElementById('profit-raw-cost').innerText = 'Rs. ' + Number(data.total_raw_cost).toLocaleString();
      document.getElementById('profit-net-profit').innerText = 'Rs. ' + Number(data.profit).toLocaleString();
      document.getElementById('profit-net-profit').style.color = data.profit >= 0 ? '#16a34a' : '#dc2626';
      document.getElementById('profit-margin-desc').innerText = data.profit_margin + '% margin';
      document.getElementById('profit-order-count').innerText = data.order_count;
      
      // Load daily breakdown
      loadProfitDailyBreakdown(data.from_date, data.to_date);
    })
    .catch(err => {
      console.error('Error loading profit summary:', err);
      showToast(err.message || 'Failed to load profit data. Please ensure the database table exists.', 'error');
      
      // Show error message in daily table
      document.getElementById('profit-daily-table').innerHTML = '<div style="text-align:center;padding:3rem;color:#dc2626">'
        + '<i class="fas fa-exclamation-triangle" style="font-size:2rem;display:block;margin-bottom:1rem"></i>'
        + '<div style="font-weight:700;margin-bottom:0.5rem">Error Loading Data</div>'
        + '<div style="font-size:0.85rem;color:#6b7280">Please run the migration SQL file first</div></div>';
    });
}

function loadProfitDailyBreakdown(fromDate, toDate) {
  fetch('action.php?action=get_profit_daily_breakdown&from_date=' + fromDate + '&to_date=' + toDate)
    .then(r => {
      if (!r.ok) throw new Error('HTTP error ' + r.status);
      return r.text();
    })
    .then(text => {
      console.log('Daily Breakdown Response:', text);
      try {
        var data = JSON.parse(text);
      } catch(e) {
        console.error('JSON Parse Error:', e, '\nResponse:', text);
        throw new Error('Invalid JSON response');
      }
      
      if (!data.success) {
        document.getElementById('profit-daily-table').innerHTML = '<div style="text-align:center;padding:2rem;color:#dc2626">'
          + '<i class="fas fa-exclamation-circle" style="margin-right:8px"></i>Failed to load breakdown: ' + (data.message || 'Unknown error') + '</div>';
        return;
      }
      
      if (!data.daily_data || data.daily_data.length === 0) {
        document.getElementById('profit-daily-table').innerHTML = '<div style="text-align:center;padding:3rem;color:#9ca3af">'
          + '<i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:1rem;opacity:0.3"></i>'
          + 'No data for this period</div>';
        return;
      }
      
      var html = '<table style="width:100%;border-collapse:collapse">'
        + '<thead><tr style="background:#f8f9fa;border-bottom:2px solid #e5e7eb">'
        + '<th style="padding:1rem;text-align:left;font-size:0.8rem;color:#6b7280;font-weight:700">DATE</th>'
        + '<th style="padding:1rem;text-align:right;font-size:0.8rem;color:#6b7280;font-weight:700">SALES</th>'
        + '<th style="padding:1rem;text-align:right;font-size:0.8rem;color:#6b7280;font-weight:700">RAW MATERIAL COST</th>'
        + '<th style="padding:1rem;text-align:right;font-size:0.8rem;color:#6b7280;font-weight:700">PROFIT</th>'
        + '</tr></thead><tbody>';
      
      data.daily_data.forEach(function(day) {
        var profitColor = day.profit >= 0 ? '#16a34a' : '#dc2626';
        var dateObj = new Date(day.date + 'T00:00:00');
        var formattedDate = dateObj.toLocaleDateString('en-PK', { day: '2-digit', month: 'short', year: 'numeric' });
        
        html += '<tr style="border-bottom:1px solid #e5e7eb">'
          + '<td style="padding:1rem;font-weight:700;color:#111">' + formattedDate + '</td>'
          + '<td style="padding:1rem;text-align:right;font-weight:700;color:#111">Rs. ' + Number(day.sales).toLocaleString() + '</td>'
          + '<td style="padding:1rem;text-align:right;font-weight:600;color:#dc2626">Rs. ' + Number(day.raw_cost).toLocaleString() + '</td>'
          + '<td style="padding:1rem;text-align:right;font-weight:800;color:' + profitColor + '">Rs. ' + Number(day.profit).toLocaleString() + '</td>'
          + '</tr>';
      });
      
      html += '</tbody></table>';
      document.getElementById('profit-daily-table').innerHTML = html;
    })
    .catch(err => {
      console.error('Error loading daily breakdown:', err);
      document.getElementById('profit-daily-table').innerHTML = '<div style="text-align:center;padding:2rem;color:#dc2626">'
        + '<i class="fas fa-exclamation-triangle" style="margin-right:8px"></i>' + (err.message || 'Network error') + '</div>';
    });
}

// ======= SUPPLIERS FUNCTIONS =======
function loadSuppliers() {
  fetch('action.php?action=get_suppliers')
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        document.getElementById('suppliers-list').innerHTML = '<div style="text-align:center;padding:2rem;color:#dc2626">Failed to load suppliers</div>';
        return;
      }
      
      if (!data.suppliers || data.suppliers.length === 0) {
        document.getElementById('suppliers-list').innerHTML = '<div style="text-align:center;padding:3rem;color:#9ca3af">'
          + '<i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:1rem;opacity:0.3"></i>'
          + 'No suppliers added yet</div>';
        return;
      }
      
      var html = '<table style="width:100%;border-collapse:collapse">'
        + '<thead><tr style="background:#f8f9fa;border-bottom:2px solid #e5e7eb">'
        + '<th style="padding:1rem;text-align:left;font-size:0.8rem;color:#6b7280;font-weight:700">NAME</th>'
        + '<th style="padding:1rem;text-align:left;font-size:0.8rem;color:#6b7280;font-weight:700">PHONE</th>'
        + '<th style="padding:1rem;text-align:left;font-size:0.8rem;color:#6b7280;font-weight:700">LOCATION</th>'
        + '<th style="padding:1rem;text-align:left;font-size:0.8rem;color:#6b7280;font-weight:700">ITEMS SUPPLIED</th>'
        + '<th style="padding:1rem;text-align:center;font-size:0.8rem;color:#6b7280;font-weight:700">ACTIONS</th>'
        + '</tr></thead><tbody>';
      
      data.suppliers.forEach(function(s) {
        html += '<tr style="border-bottom:1px solid #e5e7eb">'
          + '<td style="padding:1rem;font-weight:700;color:#111">' + (s.name || '') + '</td>'
          + '<td style="padding:1rem;color:#374151">' + (s.phone || '') + '</td>'
          + '<td style="padding:1rem;color:#6b7280">' + (s.location || '-') + '</td>'
          + '<td style="padding:1rem;color:#6b7280">' + (s.items_supplied || '-') + '</td>'
          + '<td style="padding:1rem;text-align:center">'
          + '<button onclick="deleteSupplier(' + s.id + ')" style="padding:6px 12px;background:#dc2626;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:0.8rem">'
          + '<i class="fas fa-trash"></i> Delete</button></td>'
          + '</tr>';
      });
      
      html += '</tbody></table>';
      document.getElementById('suppliers-list').innerHTML = html;
    })
    .catch(err => {
      console.error('Error loading suppliers:', err);
      document.getElementById('suppliers-list').innerHTML = '<div style="text-align:center;padding:2rem;color:#dc2626">Error loading data</div>';
    });
}

document.getElementById('addSupplierForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  var formData = new URLSearchParams({
    action: 'add_supplier',
    name: document.getElementById('supplier-name').value.trim(),
    phone: document.getElementById('supplier-phone').value.trim(),
    location: document.getElementById('supplier-location').value.trim(),
    items_supplied: document.getElementById('supplier-items').value.trim()
  });
  
  fetch('action.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('Supplier added successfully!', 'success');
        document.getElementById('addSupplierForm').reset();
        loadSuppliers();
      } else {
        showToast(data.message || 'Failed to add supplier', 'error');
      }
    })
    .catch(err => {
      console.error('Error adding supplier:', err);
      showToast('Network error. Please try again.', 'error');
    });
});

function deleteSupplier(id) {
  if (!confirm('Are you sure you want to delete this supplier?')) return;
  
  var formData = new URLSearchParams({ action: 'delete_supplier', id: id });
  
  fetch('action.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('Supplier deleted successfully!', 'success');
        loadSuppliers();
      } else {
        showToast(data.message || 'Failed to delete supplier', 'error');
      }
    })
    .catch(err => {
      console.error('Error deleting supplier:', err);
      showToast('Network error. Please try again.', 'error');
    });
}
</script>
