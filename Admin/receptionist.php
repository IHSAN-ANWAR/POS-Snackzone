<?php
$isProduction = !in_array($_SERVER['HTTP_HOST'] ?? 'localhost', ['localhost', '127.0.0.1', '::1']);
if ($isProduction) { ini_set('display_errors', 0); error_reporting(0); }
else { ini_set('display_errors', 1); error_reporting(E_ALL); }

session_start();

// Admin should go to their own panel
if (isset($_SESSION['snackzone_admin'])) {
    header('Location: index.php'); exit;
}
// Must be a receptionist
if (!isset($_SESSION['snackzone_receptionist'])) {
    header('Location: login.php'); exit;
}

require 'config.php';
$conn = getDB();

// Ensure role column exists
$conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS role ENUM('admin','receptionist') NOT NULL DEFAULT 'admin'");

// Verify receptionist session is still valid
$receptId = intval($_SESSION['snackzone_receptionist']);
$stmt = $conn->prepare("SELECT id, username FROM admin_users WHERE id = ? AND role = 'receptionist'");
$stmt->bind_param('i', $receptId);
$stmt->execute();
$receptionist = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$receptionist) {
    session_destroy();
    header('Location: login.php'); exit;
}

// Ensure orders table + columns
$conn->query("CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    items TEXT NOT NULL,
    total DECIMAL(10,2) DEFAULT 0,
    notes TEXT DEFAULT '',
    status ENUM('pending','confirmed','dispatched','cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS prep_time VARCHAR(50) DEFAULT ''");

// Fetch stats
$pendingCount   = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='pending' AND DATE(created_at)=CURDATE()")->fetch_assoc()['c'];
$confirmedCount = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='confirmed' AND DATE(created_at)=CURDATE()")->fetch_assoc()['c'];
$todayCount     = $conn->query("SELECT COUNT(*) as c FROM orders WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'];

// Fetch all orders (newest first)
$orders = $conn->query("SELECT * FROM orders ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Receptionist Panel — Snack Zone</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Inter',sans-serif;background:#f0f4f8;color:#1a1a1a;overflow-x:hidden}

    /* SIDEBAR */
    .sidebar{position:fixed;top:0;left:0;width:240px;height:100vh;
      background:linear-gradient(180deg,#1a1a1a 0%,#0a0a0a 100%);
      display:flex;flex-direction:column;z-index:100;
      border-right:1px solid rgba(255,255,255,0.06);
      box-shadow:4px 0 20px rgba(0,0,0,0.2)}
    .sidebar-logo{display:flex;align-items:center;gap:.8rem;
      padding:1.5rem 1.2rem;border-bottom:1px solid rgba(255,255,255,0.07);
      text-decoration:none}
    .sidebar-logo-icon{width:36px;height:36px;
      background:linear-gradient(135deg,#f59e0b,#d97706);
      display:flex;align-items:center;justify-content:center;
      font-size:1rem;color:#fff;border-radius:8px;
      box-shadow:0 4px 10px rgba(245,158,11,.35)}
    .sidebar-logo-text{font-size:1rem;font-weight:900;color:#fff;text-transform:uppercase;letter-spacing:-.02em;line-height:1.1}
    .sidebar-logo-text small{display:block;font-size:.62rem;font-weight:600;letter-spacing:.1em;color:rgba(255,255,255,.4);margin-top:2px}

    .sidebar-user{margin:.8rem .75rem;background:rgba(245,158,11,.08);
      border:1px solid rgba(245,158,11,.2);border-radius:10px;
      padding:.7rem .9rem;display:flex;align-items:center;gap:.6rem}
    .sidebar-user-icon{width:32px;height:32px;background:rgba(245,158,11,.15);
      border-radius:50%;display:flex;align-items:center;justify-content:center;
      font-size:.85rem;color:#f59e0b;flex-shrink:0}
    .sidebar-user-name{font-size:.8rem;font-weight:700;color:#fff;
      white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .sidebar-user-role{font-size:.62rem;font-weight:700;color:#f59e0b;
      text-transform:uppercase;letter-spacing:.08em}

    .sidebar-nav{display:flex;flex-direction:column;padding:.5rem .75rem;gap:4px;flex:1}
    .nav-btn{display:flex;align-items:center;gap:.8rem;padding:.75rem 1rem;
      color:rgba(255,255,255,.6);font-size:.85rem;font-weight:600;
      cursor:pointer;border:none;background:transparent;width:100%;text-align:left;
      transition:all .2s;font-family:'Inter',sans-serif;border-radius:10px}
    .nav-btn.active{background:linear-gradient(135deg,#f59e0b,#d97706);
      color:#fff;box-shadow:0 4px 12px rgba(245,158,11,.35)}
    .nav-btn i{width:18px;text-align:center;font-size:.95rem}
    .nav-badge{margin-left:auto;background:#ef4444;color:#fff;
      font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:10px}
    .nav-btn.active .nav-badge{background:rgba(255,255,255,.25)}
    .sidebar-footer{padding:1rem .75rem;border-top:1px solid rgba(255,255,255,.07)}

    /* MAIN */
    .main{margin-left:240px;padding:1.8rem;min-height:100vh}

    /* TOPBAR */
    .topbar{display:flex;align-items:center;justify-content:space-between;
      margin-bottom:1.8rem;background:#fff;padding:1.1rem 1.5rem;
      border:1px solid rgba(0,0,0,.06);border-radius:14px;
      box-shadow:0 2px 8px rgba(0,0,0,.04)}
    .topbar h1{font-size:1rem;font-weight:900;text-transform:uppercase;
      letter-spacing:.04em;color:#1a1a1a;display:flex;align-items:center;gap:.5rem}
    .topbar h1::before{content:'';width:4px;height:22px;
      background:linear-gradient(180deg,#f59e0b,#d97706);border-radius:2px}
    .topbar p{font-size:.75rem;color:#6b7280;margin-top:3px;font-weight:500}
    .topbar-right{font-size:.75rem;font-weight:700;letter-spacing:.05em;
      text-transform:uppercase;color:#6b7280;
      display:flex;align-items:center;gap:.5rem;
      background:#fef9ee;padding:.55rem .9rem;border-radius:8px;
      border:1px solid rgba(245,158,11,.2)}

    /* STATS */
    .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:1.2rem;margin-bottom:1.8rem}
    .stat-card{background:#fff;border:1px solid rgba(0,0,0,.06);border-radius:14px;
      padding:1.4rem;display:flex;align-items:center;gap:1.1rem;
      box-shadow:0 1px 3px rgba(0,0,0,.05);transition:all .2s}
    .stat-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.08);transform:translateY(-2px)}
    .stat-icon{width:48px;height:48px;display:flex;align-items:center;justify-content:center;
      font-size:1.2rem;flex-shrink:0;color:#fff;border-radius:12px}
    .stat-num{font-size:2rem;font-weight:900;line-height:1;color:#1a1a1a}
    .stat-label{font-size:.72rem;font-weight:700;text-transform:uppercase;
      letter-spacing:.06em;color:#6b7280;margin-top:3px}

    /* CARD */
    .card{background:#fff;border:1px solid rgba(0,0,0,.07);padding:1.6rem;
      margin-bottom:1.2rem;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.05)}
    .card-title{font-size:.82rem;font-weight:800;text-transform:uppercase;
      letter-spacing:.07em;margin-bottom:1.4rem;padding-bottom:.9rem;
      border-bottom:2px solid rgba(0,0,0,.07);
      display:flex;align-items:center;gap:.6rem;color:#1a1a1a;position:relative}
    .card-title::after{content:'';position:absolute;bottom:-2px;left:0;
      width:50px;height:2px;background:linear-gradient(90deg,#f59e0b,#d97706)}
    .card-title i{color:#f59e0b;font-size:1rem}

    /* ORDER FILTER TABS */
    .order-tabs{display:flex;gap:.5rem;margin-bottom:1.2rem;flex-wrap:wrap}
    .order-tab{background:#fff;border:2px solid rgba(0,0,0,.08);
      padding:.6rem 1.2rem;font-family:'Inter',sans-serif;
      font-size:.76rem;font-weight:700;cursor:pointer;
      text-transform:uppercase;letter-spacing:.05em;border-radius:9px;
      transition:all .2s;display:inline-flex;align-items:center;gap:.4rem}
    .order-tab:hover{border-color:rgba(0,0,0,.15);background:#f9fafb}
    .order-tab.active{background:linear-gradient(135deg,#f59e0b,#d97706);
      color:#fff;border-color:#f59e0b;box-shadow:0 4px 10px rgba(245,158,11,.3)}
    .order-tab-badge{background:rgba(255,255,255,.85);color:#1a1a1a;
      font-size:.68rem;padding:2px 7px;font-weight:800;border-radius:5px}
    .order-tab.active .order-tab-badge{background:rgba(255,255,255,.25);color:#fff}

    /* TABLE */
    .orders-table{width:100%;border-collapse:separate;border-spacing:0;font-size:.84rem}
    .orders-table th{background:linear-gradient(135deg,#1a1a1a,#0a0a0a);
      color:#fff;padding:.9rem 1.1rem;text-align:left;
      font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;font-weight:700;
      border-bottom:2px solid #f59e0b}
    .orders-table th:first-child{border-top-left-radius:10px}
    .orders-table th:last-child{border-top-right-radius:10px}
    .orders-table td{padding:.9rem 1.1rem;border-bottom:1px solid rgba(0,0,0,.05);
      vertical-align:middle;background:#fff}
    .orders-table tr:hover td{background:#fef9ee}
    .orders-table tr:last-child td{border-bottom:none}

    /* STATUS BADGES */
    .order-badge{font-size:.68rem;font-weight:800;text-transform:uppercase;
      letter-spacing:.05em;padding:4px 11px;color:#fff;border-radius:6px;display:inline-block}
    .order-badge-pending{background:linear-gradient(135deg,#f59e0b,#d97706)}
    .order-badge-confirmed{background:linear-gradient(135deg,#3b82f6,#2563eb)}
    .order-badge-dispatched{background:linear-gradient(135deg,#10b981,#059669)}
    .order-badge-cancelled{background:linear-gradient(135deg,#6b7280,#4b5563)}

    /* ACTION BUTTONS */
    .action-btn{border:none;padding:6px 11px;cursor:pointer;
      font-family:'Inter',sans-serif;font-size:.72rem;font-weight:700;
      text-transform:uppercase;display:block;width:100%;margin-bottom:4px;
      border-radius:6px;transition:all .2s}
    .btn-confirm{background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;
      box-shadow:0 2px 6px rgba(59,130,246,.3)}
    .btn-confirm:hover{transform:translateY(-1px);box-shadow:0 4px 10px rgba(59,130,246,.4)}
    .btn-dispatch{background:linear-gradient(135deg,#10b981,#059669);color:#fff;
      box-shadow:0 2px 6px rgba(16,185,129,.3)}
    .btn-dispatch:hover{transform:translateY(-1px);box-shadow:0 4px 10px rgba(16,185,129,.4)}
    .btn-cancel{background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;
      box-shadow:0 2px 6px rgba(220,38,38,.3)}
    .btn-cancel:hover{transform:translateY(-1px);box-shadow:0 4px 10px rgba(220,38,38,.4)}

    /* EMPTY STATE */
    .empty-state{text-align:center;padding:3.5rem 2rem;color:#9ca3af;
      background:#f9fafb;border-radius:12px;border:2px dashed rgba(0,0,0,.08)}
    .empty-state i{font-size:3rem;display:block;margin-bottom:1rem;
      opacity:.4;color:#f59e0b}
    .empty-state p{font-size:.88rem;font-weight:600;color:#6b7280}

    /* ORDER DETAILS MODAL */
    .modal-overlay{display:none;position:fixed;inset:0;
      background:rgba(0,0,0,.55);z-index:999;
      align-items:center;justify-content:center}
    .modal-overlay.open{display:flex}
    .modal-box{background:#fff;width:90%;max-width:520px;max-height:90vh;
      overflow-y:auto;border-radius:16px;
      box-shadow:0 20px 60px rgba(0,0,0,.3);
      animation:modalIn .25s ease}
    @keyframes modalIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
    .modal-header{display:flex;align-items:center;justify-content:space-between;
      padding:1.2rem 1.5rem;
      background:linear-gradient(135deg,#1a1a1a,#0a0a0a);color:#fff;
      border-radius:16px 16px 0 0}
    .modal-header h3{font-size:.88rem;font-weight:900;text-transform:uppercase;letter-spacing:.07em}
    .btn-modal-close{background:none;border:none;color:rgba(255,255,255,.6);
      font-size:1.2rem;cursor:pointer;transition:color .2s}
    .btn-modal-close:hover{color:#fff}
    .modal-body{padding:1.5rem}
    .info-row{display:flex;gap:.5rem;margin-bottom:.7rem;align-items:flex-start;font-size:.85rem}
    .info-label{font-weight:700;color:#6b7280;min-width:80px;flex-shrink:0;
      font-size:.72rem;text-transform:uppercase}
    .info-val{color:#1a1a1a;font-weight:600;line-height:1.4}
    .modal-items{background:#f8f9fa;border-radius:10px;padding:1rem;margin:.8rem 0}
    .modal-items h4{font-size:.72rem;font-weight:800;text-transform:uppercase;
      letter-spacing:.06em;color:#6b7280;margin-bottom:.6rem}
    .modal-item-row{display:flex;justify-content:space-between;align-items:center;
      padding:.4rem 0;border-bottom:1px solid rgba(0,0,0,.06);font-size:.82rem}
    .modal-item-row:last-child{border-bottom:none}
    .modal-item-name{font-weight:600}
    .modal-item-price{color:#f59e0b;font-weight:800}
    .modal-total{font-size:.92rem;font-weight:900;color:#1a1a1a;padding:.5rem 0}
    .modal-total span{color:#f59e0b}

    /* CONFIRM ACTION MODAL */
    .confirm-overlay{display:none;position:fixed;inset:0;
      background:rgba(0,0,0,.6);z-index:1000;
      align-items:center;justify-content:center}
    .confirm-overlay.open{display:flex}
    .confirm-box{background:#fff;width:90%;max-width:400px;border-radius:14px;
      box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;
      animation:modalIn .2s ease}
    .confirm-header{padding:1.2rem 1.4rem;border-bottom:1px solid rgba(0,0,0,.07);
      display:flex;align-items:center;gap:.7rem}
    .confirm-icon{width:40px;height:40px;border-radius:50%;display:flex;
      align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
    .confirm-icon.c-confirm{background:#dbeafe;color:#2563eb}
    .confirm-icon.c-dispatch{background:#d1fae5;color:#059669}
    .confirm-icon.c-cancel{background:#fee2e2;color:#dc2626}
    .confirm-header h3{font-size:.9rem;font-weight:800;color:#1a1a1a}
    .confirm-header p{font-size:.78rem;color:#6b7280;margin-top:2px}
    .confirm-body{padding:1.2rem 1.4rem}
    .prep-label{font-size:.72rem;font-weight:700;text-transform:uppercase;
      letter-spacing:.07em;color:#374151;display:block;margin-bottom:.4rem}
    .prep-input{width:100%;padding:.65rem .9rem;
      border:2px solid rgba(0,0,0,.1);border-radius:8px;
      font-family:'Inter',sans-serif;font-size:.85rem;outline:none;transition:all .2s}
    .prep-input:focus{border-color:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.1)}
    .confirm-footer{padding:1rem 1.4rem;background:#f9fafb;
      border-top:1px solid rgba(0,0,0,.06);display:flex;gap:.6rem;justify-content:flex-end}
    .btn-dismiss{background:#f3f4f6;color:#374151;border:none;
      padding:.7rem 1.3rem;border-radius:8px;font-family:'Inter',sans-serif;
      font-size:.78rem;font-weight:700;cursor:pointer;transition:all .2s}
    .btn-dismiss:hover{background:#e5e7eb}
    .btn-do{border:none;padding:.7rem 1.5rem;border-radius:8px;
      font-family:'Inter',sans-serif;font-size:.78rem;font-weight:700;
      cursor:pointer;transition:all .2s;color:#fff}
    .btn-do.c-confirm{background:linear-gradient(135deg,#3b82f6,#2563eb);
      box-shadow:0 3px 10px rgba(59,130,246,.3)}
    .btn-do.c-dispatch{background:linear-gradient(135deg,#10b981,#059669);
      box-shadow:0 3px 10px rgba(16,185,129,.3)}
    .btn-do.c-cancel{background:linear-gradient(135deg,#dc2626,#b91c1c);
      box-shadow:0 3px 10px rgba(220,38,38,.3)}
    .btn-do:hover{transform:translateY(-1px)}

    /* TOAST */
    .toast{position:fixed;bottom:2rem;right:2rem;
      background:linear-gradient(135deg,#1a1a1a,#0a0a0a);
      color:#fff;padding:.9rem 1.4rem;font-size:.82rem;font-weight:600;
      z-index:9999;transform:translateY(100px);opacity:0;
      transition:all .3s cubic-bezier(.68,-.55,.265,1.55);
      pointer-events:none;display:flex;align-items:center;gap:.6rem;
      border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.25);min-width:260px}
    .toast.show{transform:translateY(0);opacity:1}
    .toast.t-success{background:linear-gradient(135deg,#10b981,#059669)}
    .toast.t-error{background:linear-gradient(135deg,#dc2626,#b91c1c)}

    /* LIVE BADGE */
    .live-badge{display:inline-flex;align-items:center;gap:.4rem;
      background:#fef3c7;color:#92400e;padding:4px 10px;border-radius:6px;
      font-size:.68rem;font-weight:800;text-transform:uppercase;
      letter-spacing:.06em;border:1px solid #fbbf24}
    .live-dot{width:7px;height:7px;background:#f59e0b;border-radius:50%;
      animation:livePulse 1.5s infinite}
    @keyframes livePulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.8)}}

    /* MOBILE */
    .mobile-menu-btn{display:none;position:fixed;top:1rem;left:1rem;z-index:101;
      background:linear-gradient(135deg,#1a1a1a,#0a0a0a);color:#fff;
      border:none;width:44px;height:44px;border-radius:10px;cursor:pointer;
      align-items:center;justify-content:center;font-size:1.1rem;
      box-shadow:0 4px 12px rgba(0,0,0,.3)}
    .sidebar-overlay{display:none;position:fixed;inset:0;
      background:rgba(0,0,0,.5);z-index:99;opacity:0;transition:opacity .3s}
    .sidebar-overlay.active{display:block;opacity:1}

    @media(max-width:768px){
      .mobile-menu-btn{display:flex}
      .sidebar{transform:translateX(-100%);transition:transform .3s ease}
      .sidebar.active{transform:translateX(0);box-shadow:8px 0 24px rgba(0,0,0,.3)}
      .main{margin-left:0;padding:5rem 1rem 1rem}
      .stats{grid-template-columns:1fr 1fr}
      .orders-table th,.orders-table td{padding:.7rem .7rem;font-size:.78rem}
    }
    @media(max-width:480px){
      .stats{grid-template-columns:1fr}
    }
  </style>
</head>
<body>

<button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu">
  <i class="fas fa-bars"></i>
</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <a href="../" class="sidebar-logo" title="Go to Homepage">
    <div class="sidebar-logo-icon"><i class="fas fa-headset"></i></div>
    <div class="sidebar-logo-text">
      SnackZone
      <small>Receptionist</small>
    </div>
  </a>

  <div class="sidebar-user">
    <div class="sidebar-user-icon"><i class="fas fa-user-tie"></i></div>
    <div>
      <div class="sidebar-user-name"><?= htmlspecialchars($receptionist['username']) ?></div>
      <div class="sidebar-user-role">Receptionist</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <button class="nav-btn active" id="navOrders" onclick="switchPanel('orders',this)">
      <i class="fas fa-bag-shopping"></i> Orders
      <?php if ($pendingCount > 0): ?>
        <span class="nav-badge" id="sidebarBadge"><?= $pendingCount ?></span>
      <?php endif; ?>
    </button>
    <button class="nav-btn" id="navChat" onclick="switchPanel('chat',this)">
      <i class="fas fa-comments"></i> Live Chat
      <span class="nav-badge" id="chatUnreadBadge" style="display:none">0</span>
    </button>
  </nav>

  <div class="sidebar-footer">
    <a href="logout.php" class="nav-btn" style="color:rgba(255,255,255,.35);text-decoration:none"
       onclick="return confirm('Logout karna chahte hain?')">
      <i class="fas fa-right-from-bracket"></i> Logout
    </a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div>
      <h1 id="pageTitle">Orders</h1>
      <p id="pageSubtitle">Customer orders dekhen aur confirm karen</p>
    </div>
    <div class="topbar-right">
      <div class="live-badge"><span class="live-dot"></span> Live</div>
      &nbsp;&nbsp;
      <i class="fas fa-circle" style="color:#22c55e;font-size:.5rem"></i>
      &nbsp;<?= htmlspecialchars($receptionist['username']) ?> &nbsp;|&nbsp; <strong>Receptionist</strong>
    </div>
  </div>

  <!-- STATS + ORDERS wrapped in panelOrders -->
  <div id="panelOrders">
  <!-- STATS -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg,#1a1a1a,#0a0a0a)">
        <i class="fas fa-bag-shopping"></i>
      </div>
      <div>
        <div class="stat-num" id="statTotal"><?= $todayCount ?></div>
        <div class="stat-label">Aaj ke Orders</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
        <i class="fas fa-clock"></i>
      </div>
      <div>
        <div class="stat-num" id="statPending"><?= $pendingCount ?></div>
        <div class="stat-label">Pending</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg,#10b981,#059669)">
        <i class="fas fa-check-circle"></i>
      </div>
      <div>
        <div class="stat-num" id="statConfirmed"><?= $confirmedCount ?></div>
        <div class="stat-label">Confirmed Aaj</div>
      </div>
    </div>
  </div>

  <!-- ORDERS CARD -->
  <div class="card">
    <div class="card-title">
      <i class="fas fa-receipt"></i> Orders
      <span id="refreshNote" style="margin-left:auto;font-size:.68rem;font-weight:600;color:#9ca3af;text-transform:none;letter-spacing:0">
        Har 30 second mein auto-refresh hoga
      </span>
    </div>

    <!-- TABS -->
    <div class="order-tabs">
      <button class="order-tab active" onclick="filterOrders('today',this)">
        <i class="fas fa-sun"></i> Aaj
        <span class="order-tab-badge" id="tab-today"><?= $todayCount ?></span>
      </button>
      <button class="order-tab" onclick="filterOrders('pending',this)">
        <i class="fas fa-clock"></i> Pending
        <span class="order-tab-badge" id="tab-pending"><?= $pendingCount ?></span>
      </button>
      <button class="order-tab" onclick="filterOrders('confirmed',this)">
        <i class="fas fa-check"></i> Confirmed
        <span class="order-tab-badge" id="tab-confirmed">0</span>
      </button>
      <button class="order-tab" onclick="filterOrders('dispatched',this)">
        <i class="fas fa-motorcycle"></i> Dispatched
        <span class="order-tab-badge" id="tab-dispatched">0</span>
      </button>
      <button class="order-tab" onclick="filterOrders('all',this)">
        <i class="fas fa-list"></i> Sab
        <span class="order-tab-badge" id="tab-all">0</span>
      </button>
    </div>

    <!-- TABLE -->
    <div id="tableWrap">
      <table class="orders-table" id="ordersTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Customer</th>
            <th>Items</th>
            <th>Total</th>
            <th>Status</th>
            <th>Waqt</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="ordersBody">
          <?php
          $orders->data_seek(0);
          while ($order = $orders->fetch_assoc()):
            $itemsParsed = [];
            if (!empty($order['items'])) {
              $decoded = json_decode($order['items'], true);
              if (is_array($decoded)) $itemsParsed = $decoded;
            }
            $names = array_map(function($it) {
              $n = $it['title'] ?? $it['name'] ?? 'Item';
              $q = $it['qty'] ?? $it['quantity'] ?? 1;
              return htmlspecialchars($n) . ' ×' . intval($q);
            }, array_slice($itemsParsed, 0, 2));
            $itemSummary = implode(', ', $names);
            if (count($itemsParsed) > 2) $itemSummary .= ' +' . (count($itemsParsed)-2) . ' more';
            if (!$itemSummary) $itemSummary = htmlspecialchars(substr($order['items'], 0, 60));
            $isToday = date('Y-m-d', strtotime($order['created_at'])) === date('Y-m-d') ? '1' : '0';
          ?>
          <tr class="order-row"
              data-status="<?= htmlspecialchars($order['status']) ?>"
              data-id="<?= $order['id'] ?>"
              data-today="<?= $isToday ?>">
            <td style="font-weight:800;color:#6b7280;font-size:.78rem">#<?= $order['id'] ?></td>
            <td>
              <div style="font-weight:700;font-size:.88rem"><?= htmlspecialchars($order['name']) ?></div>
              <div style="font-size:.73rem;color:#6b7280;margin-top:2px">
                <i class="fas fa-phone" style="font-size:.65rem"></i> <?= htmlspecialchars($order['phone']) ?>
              </div>
            </td>
            <td>
              <div style="font-size:.82rem;max-width:200px"><?= $itemSummary ?></div>
              <?php if (!empty($order['notes'])): ?>
                <div style="font-size:.7rem;color:#f59e0b;margin-top:3px">
                  <i class="fas fa-sticky-note"></i> <?= htmlspecialchars(substr($order['notes'],0,40)) ?>
                </div>
              <?php endif; ?>
              <button onclick='viewOrder(<?= htmlspecialchars(json_encode($order), ENT_QUOTES) ?>)'
                style="background:none;border:none;color:#3b82f6;font-size:.72rem;
                       font-weight:700;cursor:pointer;padding:3px 0;font-family:'Inter',sans-serif">
                <i class="fas fa-eye"></i> Details
              </button>
            </td>
            <td style="font-weight:900;color:#f59e0b;font-size:.95rem">
              Rs. <?= number_format($order['total'], 0) ?>
            </td>
            <td>
              <span class="order-badge order-badge-<?= $order['status'] ?>"
                    id="badge-<?= $order['id'] ?>">
                <?= ucfirst($order['status']) ?>
              </span>
              <?php if (!empty($order['prep_time'])): ?>
                <div style="font-size:.68rem;color:#6b7280;margin-top:3px">
                  <i class="fas fa-clock"></i> <?= htmlspecialchars($order['prep_time']) ?>
                </div>
              <?php endif; ?>
            </td>
            <td style="font-size:.76rem;color:#6b7280;white-space:nowrap">
              <?= date('h:i A', strtotime($order['created_at'])) ?><br>
              <span style="font-size:.68rem"><?= date('d M', strtotime($order['created_at'])) ?></span>
            </td>
            <td style="min-width:130px" id="actions-<?= $order['id'] ?>">
              <?php if ($order['status'] === 'pending'): ?>
                <button class="action-btn btn-confirm"
                  onclick="openConfirm(<?= $order['id'] ?>, 'confirmed', '<?= htmlspecialchars(addslashes($order['name'])) ?>')">
                  <i class="fas fa-check"></i> Confirm
                </button>
                <button class="action-btn btn-cancel"
                  onclick="openConfirm(<?= $order['id'] ?>, 'cancelled', '<?= htmlspecialchars(addslashes($order['name'])) ?>')">
                  <i class="fas fa-times"></i> Cancel
                </button>
              <?php elseif ($order['status'] === 'confirmed'): ?>
                <button class="action-btn btn-dispatch"
                  onclick="openConfirm(<?= $order['id'] ?>, 'dispatched', '<?= htmlspecialchars(addslashes($order['name'])) ?>')">
                  <i class="fas fa-motorcycle"></i> Dispatch
                </button>
                <button class="action-btn btn-cancel"
                  onclick="openConfirm(<?= $order['id'] ?>, 'cancelled', '<?= htmlspecialchars(addslashes($order['name'])) ?>')">
                  <i class="fas fa-times"></i> Cancel
                </button>
              <?php elseif ($order['status'] === 'dispatched'): ?>
                <span style="font-size:.75rem;color:#059669;font-weight:700">
                  <i class="fas fa-check-double"></i> Dispatched
                </span>
              <?php else: ?>
                <span style="font-size:.75rem;color:#6b7280;font-weight:700">
                  <i class="fas fa-ban"></i> Cancelled
                </span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
      <div class="empty-state" id="emptyState" style="display:none">
        <i class="fas fa-receipt"></i>
        <p>Is category mein koi order nahi</p>
      </div>
    </div>
  </div>
  </div><!-- /panelOrders -->

  <!-- ══════════════════════════════════════════════════
       LIVE CHAT PANEL
  ══════════════════════════════════════════════════ -->
  <div id="panelChat" style="display:none">
    <div style="display:grid;grid-template-columns:300px 1fr;gap:1.2rem;height:calc(100vh - 200px);min-height:500px">

      <!-- Sessions List -->
      <div class="card" style="display:flex;flex-direction:column;padding:0;overflow:hidden">
        <div style="padding:1rem 1.2rem;border-bottom:1px solid rgba(0,0,0,.07);
             background:#fff;display:flex;align-items:center;gap:.6rem">
          <i class="fas fa-comments" style="color:#f59e0b"></i>
          <span style="font-size:.82rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em">
            Active Chats
          </span>
          <span id="sessionCountBadge" style="margin-left:auto;background:#f59e0b;color:#fff;
               font-size:.68rem;font-weight:800;padding:2px 8px;border-radius:6px;display:none">0</span>
        </div>
        <div id="sessionsList" style="flex:1;overflow-y:auto;padding:.5rem">
          <div class="empty-state" style="padding:2rem">
            <i class="fas fa-comments"></i>
            <p>Koi active chat nahi</p>
          </div>
        </div>
      </div>

      <!-- Conversation Pane -->
      <div class="card" style="display:flex;flex-direction:column;padding:0;overflow:hidden" id="conversationPane">
        <!-- Empty state -->
        <div id="chatEmpty" style="flex:1;display:flex;flex-direction:column;align-items:center;
             justify-content:center;color:#9ca3af;padding:2rem;text-align:center">
          <i class="fas fa-comment-dots" style="font-size:3rem;margin-bottom:1rem;opacity:.3;color:#f59e0b"></i>
          <p style="font-size:.88rem;font-weight:600">Kisi customer ki chat select karen</p>
          <p style="font-size:.78rem;margin-top:.4rem">Left side se chat select karen reply karne ke liye</p>
        </div>

        <!-- Active conversation -->
        <div id="chatActive" style="display:none;flex-direction:column;height:100%">
          <!-- Chat header -->
          <div id="chatHeader" style="padding:.9rem 1.2rem;border-bottom:1px solid rgba(0,0,0,.07);
               background:linear-gradient(135deg,#1a1a1a,#0a0a0a);
               display:flex;align-items:center;gap:.8rem">
            <div style="width:36px;height:36px;border-radius:50%;background:rgba(245,158,11,.2);
                 border:2px solid rgba(245,158,11,.4);display:flex;align-items:center;
                 justify-content:center;font-size:.9rem;color:#f59e0b;flex-shrink:0">
              <i class="fas fa-user"></i>
            </div>
            <div style="flex:1;min-width:0">
              <p id="chatCustomerName" style="margin:0;color:#fff;font-weight:800;font-size:.88rem;
                 white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></p>
              <p id="chatOrderInfo" style="margin:0;color:rgba(255,255,255,.5);font-size:.7rem"></p>
            </div>
            <button onclick="closeActiveChat()" style="background:rgba(255,255,255,.1);border:none;
                 color:rgba(255,255,255,.7);font-size:.72rem;font-weight:700;padding:5px 10px;
                 border-radius:6px;cursor:pointer;font-family:'Inter',sans-serif;
                 text-transform:uppercase;letter-spacing:.06em;transition:all .2s"
                 onmouseover="this.style.background='rgba(220,38,38,.3)'"
                 onmouseout="this.style.background='rgba(255,255,255,.1)'">
              <i class="fas fa-times"></i> Close
            </button>
          </div>

          <!-- Messages -->
          <div id="chatMessages" style="flex:1;overflow-y:auto;padding:1rem;
               display:flex;flex-direction:column;gap:.6rem;background:#f8f9fa">
          </div>

          <!-- Input -->
          <div style="padding:.75rem;border-top:1px solid rgba(0,0,0,.07);background:#fff;
               display:flex;gap:.5rem;align-items:center">
            <input type="text" id="chatInput" placeholder="Reply likhein..."
              style="flex:1;padding:.65rem .9rem;border:2px solid rgba(0,0,0,.08);
                     border-radius:10px;font-family:'Inter',sans-serif;font-size:.85rem;
                     outline:none;transition:border-color .2s"
              onfocus="this.style.borderColor='#f59e0b'"
              onblur="this.style.borderColor='rgba(0,0,0,.08)'"
              onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendReply()}" />
            <button onclick="sendReply()" id="sendReplyBtn"
              style="width:40px;height:40px;border-radius:10px;
                     background:linear-gradient(135deg,#1a1a1a,#000);
                     border:none;cursor:pointer;display:flex;align-items:center;
                     justify-content:center;flex-shrink:0;transition:all .2s"
              onmouseover="this.style.opacity='.85'"
              onmouseout="this.style.opacity='1'">
              <i class="fas fa-paper-plane" style="color:#fff;font-size:.85rem"></i>
            </button>
          </div>
        </div>
      </div>

    </div>
  </div>

</main>

<!-- ORDER DETAILS MODAL -->
<div class="modal-overlay" id="orderModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3><i class="fas fa-receipt" style="margin-right:.5rem"></i> Order Details</h3>
      <button class="btn-modal-close" onclick="closeModal('orderModal')">
        <i class="fas fa-xmark"></i>
      </button>
    </div>
    <div class="modal-body" id="orderModalBody"></div>
  </div>
</div>

<!-- CONFIRM ACTION MODAL -->
<div class="confirm-overlay" id="confirmModal">
  <div class="confirm-box">
    <div class="confirm-header">
      <div class="confirm-icon" id="cIcon"><i id="cIconI" class="fas fa-check"></i></div>
      <div>
        <h3 id="cTitle">Confirm Order</h3>
        <p id="cSubtitle">Kya aap sure hain?</p>
      </div>
    </div>
    <div class="confirm-body">
      <label class="prep-label" id="prepLabel">
        Prep Time <span style="font-weight:400;color:#9ca3af">(optional)</span>
      </label>
      <input type="text" class="prep-input" id="prepInput"
             placeholder="e.g. 20 mins, 30 mins..." />
    </div>
    <div class="confirm-footer">
      <button class="btn-dismiss" onclick="closeModal('confirmModal')">Wapas</button>
      <button class="btn-do" id="cDoBtn" onclick="doUpdate()">Confirm</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script>
  // ── State ──────────────────────────────────────────────
  var currentFilter  = 'today';
  var pendingAction  = null; // {id, status}

  // ── Mobile sidebar ─────────────────────────────────────
  document.getElementById('mobileMenuBtn').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('sidebarOverlay').classList.toggle('active');
  });
  document.getElementById('sidebarOverlay').addEventListener('click', function() {
    document.getElementById('sidebar').classList.remove('active');
    document.getElementById('sidebarOverlay').classList.remove('active');
  });

  // ── Toast ──────────────────────────────────────────────
  function showToast(msg, type) {
    var t = document.getElementById('toast');
    var icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-circle-xmark' : 'fa-info-circle';
    t.innerHTML = '<i class="fas ' + icon + '"></i> ' + msg;
    t.className = 'toast show' + (type ? ' t-' + type : '');
    clearTimeout(t._tmr);
    t._tmr = setTimeout(function(){ t.classList.remove('show'); }, 3200);
  }

  // ── Close modal ────────────────────────────────────────
  function closeModal(id) {
    document.getElementById(id).classList.remove('open');
  }

  // ── HTML escape ────────────────────────────────────────
  function esc(s) {
    return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : '';
  }
  function ucf(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
  function fmtDate(d) {
    if (!d) return '';
    var dt = new Date(d);
    return dt.toLocaleDateString('en-PK',{day:'numeric',month:'short',year:'numeric'}) + ' ' +
           dt.toLocaleTimeString('en-PK',{hour:'2-digit',minute:'2-digit'});
  }

  // ── View order details ─────────────────────────────────
  function viewOrder(order) {
    var items = [];
    try { items = JSON.parse(order.items); } catch(e){}
    if (!Array.isArray(items)) items = [];

    var itemsHtml = '';
    if (items.length) {
      itemsHtml = '<div class="modal-items"><h4><i class="fas fa-utensils"></i> Ordered Items</h4>';
      items.forEach(function(it) {
        var name  = it.title || it.name || 'Item';
        var qty   = it.qty || it.quantity || 1;
        var price = it.price || 0;
        itemsHtml += '<div class="modal-item-row">' +
          '<span class="modal-item-name">' + esc(name) + ' &times;' + qty + '</span>' +
          '<span class="modal-item-price">Rs. ' + Number(price * qty).toLocaleString() + '</span>' +
          '</div>';
      });
      itemsHtml += '</div>';
    }

    var prepHtml  = order.prep_time  ? '<div class="info-row"><span class="info-label">Prep Time</span><span class="info-val">' + esc(order.prep_time)  + '</span></div>' : '';
    var notesHtml = order.notes      ? '<div class="info-row"><span class="info-label">Notes</span><span class="info-val" style="color:#f59e0b">' + esc(order.notes) + '</span></div>' : '';

    document.getElementById('orderModalBody').innerHTML =
      '<div class="info-row"><span class="info-label">Order #</span><span class="info-val" style="font-weight:900">#' + order.id + '</span></div>' +
      '<div class="info-row"><span class="info-label">Customer</span><span class="info-val">' + esc(order.name) + '</span></div>' +
      '<div class="info-row"><span class="info-label">Phone</span><span class="info-val">' + esc(order.phone) + '</span></div>' +
      '<div class="info-row"><span class="info-label">Address</span><span class="info-val">' + esc(order.address) + '</span></div>' +
      notesHtml + itemsHtml +
      '<div class="modal-total">Total: <span>Rs. ' + Number(order.total).toLocaleString() + '</span></div>' +
      prepHtml +
      '<div class="info-row" style="margin-top:.5rem"><span class="info-label">Status</span>' +
      '<span class="order-badge order-badge-' + order.status + '">' + ucf(order.status) + '</span></div>' +
      '<div class="info-row"><span class="info-label">Waqt</span><span class="info-val">' + fmtDate(order.created_at) + '</span></div>';

    document.getElementById('orderModal').classList.add('open');
  }

  // ── Open confirm modal ─────────────────────────────────
  function openConfirm(orderId, newStatus, customerName) {
    pendingAction = { id: orderId, status: newStatus };

    var map = {
      confirmed:  { icon:'fa-check',      cls:'c-confirm',  title:'Order Confirm Karen',    sub:'Order #'+orderId+' ('+customerName+') confirm karna chahte hain?', btn:'Confirm Karun' },
      dispatched: { icon:'fa-motorcycle', cls:'c-dispatch', title:'Order Dispatch Karen',   sub:'Order #'+orderId+' dispatch mark karna chahte hain?',              btn:'Dispatch Karun' },
      cancelled:  { icon:'fa-times',      cls:'c-cancel',   title:'Order Cancel Karen',     sub:'Order #'+orderId+' ('+customerName+') cancel karna chahte hain?',  btn:'Cancel Karun' }
    };
    var c = map[newStatus] || map.confirmed;

    document.getElementById('cIcon').className    = 'confirm-icon ' + c.cls;
    document.getElementById('cIconI').className   = 'fas ' + c.icon;
    document.getElementById('cTitle').textContent = c.title;
    document.getElementById('cSubtitle').textContent = c.sub;
    document.getElementById('cDoBtn').className   = 'btn-do ' + c.cls;
    document.getElementById('cDoBtn').textContent = c.btn;
    document.getElementById('prepInput').value    = '';

    // Show prep time only when confirming
    var showPrep = newStatus === 'confirmed';
    document.getElementById('prepLabel').style.display = showPrep ? 'block' : 'none';
    document.getElementById('prepInput').style.display = showPrep ? 'block' : 'none';

    document.getElementById('confirmModal').classList.add('open');
  }

  // ── Execute status update ──────────────────────────────
  function doUpdate() {
    if (!pendingAction) return;
    var btn = document.getElementById('cDoBtn');
    btn.disabled = true;
    btn.textContent = 'Wait...';

    var prepTime = document.getElementById('prepInput').value.trim();

    $.post('action.php', {
      action:    'receptionist_update_order',
      id:        pendingAction.id,
      status:    pendingAction.status,
      prep_time: prepTime
    }, function(res) {
      if (res.success) {
        closeModal('confirmModal');
        applyRowUpdate(pendingAction.id, pendingAction.status, prepTime);
        showToast('Order #' + pendingAction.id + ' — ' + pendingAction.status, 'success');
        refreshStats();
        pendingAction = null;
      } else {
        showToast(res.message || 'Error hua, dobara try karen', 'error');
      }
      btn.disabled = false;
      btn.textContent = document.getElementById('cDoBtn').className.includes('confirm') ? 'Confirm Karun'
                      : document.getElementById('cDoBtn').className.includes('dispatch') ? 'Dispatch Karun' : 'Cancel Karun';
    }, 'json').fail(function() {
      showToast('Network error', 'error');
      btn.disabled = false;
    });
  }

  // ── Update row without page reload ────────────────────
  function applyRowUpdate(orderId, newStatus, prepTime) {
    var row   = document.querySelector('.order-row[data-id="' + orderId + '"]');
    var badge = document.getElementById('badge-' + orderId);
    if (!row || !badge) return;

    row.setAttribute('data-status', newStatus);
    badge.className   = 'order-badge order-badge-' + newStatus;
    badge.textContent = ucf(newStatus);

    if (prepTime) {
      var statusTd = badge.closest('td');
      var pd = statusTd.querySelector('.prep-div');
      if (!pd) { pd = document.createElement('div'); pd.className = 'prep-div'; statusTd.appendChild(pd); }
      pd.style.cssText = 'font-size:.68rem;color:#6b7280;margin-top:3px';
      pd.innerHTML = '<i class="fas fa-clock"></i> ' + esc(prepTime);
    }

    var actionCell = document.getElementById('actions-' + orderId);
    if (actionCell) {
      if (newStatus === 'confirmed') {
        actionCell.innerHTML =
          '<button class="action-btn btn-dispatch" onclick="openConfirm('+orderId+',\'dispatched\',\'\')"><i class="fas fa-motorcycle"></i> Dispatch</button>' +
          '<button class="action-btn btn-cancel"   onclick="openConfirm('+orderId+',\'cancelled\',\'\')"><i class="fas fa-times"></i> Cancel</button>';
      } else if (newStatus === 'dispatched') {
        actionCell.innerHTML = '<span style="font-size:.75rem;color:#059669;font-weight:700"><i class="fas fa-check-double"></i> Dispatched</span>';
      } else if (newStatus === 'cancelled') {
        actionCell.innerHTML = '<span style="font-size:.75rem;color:#6b7280;font-weight:700"><i class="fas fa-ban"></i> Cancelled</span>';
      }
    }

    filterOrders(currentFilter, null);
  }

  // ── Filter rows by tab ─────────────────────────────────
  function filterOrders(filter, btn) {
    currentFilter = filter;
    if (btn) {
      document.querySelectorAll('.order-tab').forEach(function(t){ t.classList.remove('active'); });
      btn.classList.add('active');
    }
    var rows = document.querySelectorAll('.order-row');
    var visible = 0;
    rows.forEach(function(row) {
      var status  = row.getAttribute('data-status');
      var isToday = row.getAttribute('data-today') === '1';
      var show = (filter === 'all') ||
                 (filter === 'today'     && isToday) ||
                 (filter === 'pending'   && status === 'pending') ||
                 (filter === 'confirmed' && status === 'confirmed') ||
                 (filter === 'dispatched'&& status === 'dispatched') ||
                 (filter === 'cancelled' && status === 'cancelled');
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    document.getElementById('emptyState').style.display  = visible ? 'none'  : 'block';
    document.getElementById('ordersTable').style.display = visible ? ''      : 'none';
  }

  // ── Refresh stats & badges from DOM ───────────────────
  function refreshStats() {
    var todayC=0, pendingC=0, confirmedC=0, dispatchedC=0, allC=0;
    document.querySelectorAll('.order-row').forEach(function(r) {
      allC++;
      var s = r.getAttribute('data-status');
      var t = r.getAttribute('data-today') === '1';
      if (t) todayC++;
      if (s === 'pending')   pendingC++;
      if (s === 'confirmed') confirmedC++;
      if (s === 'dispatched')dispatchedC++;
    });
    document.getElementById('statTotal').textContent     = todayC;
    document.getElementById('statPending').textContent   = pendingC;
    document.getElementById('statConfirmed').textContent = confirmedC;
    document.getElementById('tab-today').textContent     = todayC;
    document.getElementById('tab-pending').textContent   = pendingC;
    document.getElementById('tab-confirmed').textContent = confirmedC;
    document.getElementById('tab-dispatched').textContent= dispatchedC;
    document.getElementById('tab-all').textContent       = allC;

    var badge = document.getElementById('sidebarBadge');
    if (pendingC > 0) {
      if (badge) badge.textContent = pendingC;
      else {
        var nb = document.createElement('span');
        nb.className = 'nav-badge'; nb.id = 'sidebarBadge'; nb.textContent = pendingC;
        document.querySelector('.nav-btn.active').appendChild(nb);
      }
    } else if (badge) badge.remove();
  }

  // ── Auto-refresh page every 30s ───────────────────────
  var autoTimer = setInterval(function(){ location.reload(); }, 30000);
  document.addEventListener('click', function() {
    clearInterval(autoTimer);
    autoTimer = setInterval(function(){ location.reload(); }, 30000);
  });

  // ── Init tab counts on load ────────────────────────────
  (function(){
    refreshStats();
    filterOrders('today', null);
    // Poll unread chat count immediately
    pollUnreadCount();
  })();

  // ══════════════════════════════════════════════════════
  // PANEL SWITCHER
  // ══════════════════════════════════════════════════════
  function switchPanel(panel, btn) {
    document.querySelectorAll('.nav-btn').forEach(function(b){ b.classList.remove('active'); });
    if (btn) btn.classList.add('active');

    if (panel === 'orders') {
      document.getElementById('panelChat').style.display   = 'none';
      document.getElementById('panelOrders').style.display = '';
      document.getElementById('pageTitle').textContent     = 'Orders';
      document.getElementById('pageSubtitle').textContent  = 'Customer orders dekhen aur confirm karen';
      // Resume order auto-refresh
      clearInterval(autoTimer);
      autoTimer = setInterval(function(){ location.reload(); }, 30000);
    } else {
      document.getElementById('panelOrders').style.display = 'none';
      document.getElementById('panelChat').style.display   = '';
      document.getElementById('pageTitle').textContent     = 'Live Chat';
      document.getElementById('pageSubtitle').textContent  = 'Customers ke saath live baat karen';
      clearInterval(autoTimer); // don't reload page while chatting
      loadSessions();
    }
  }

  // ══════════════════════════════════════════════════════
  // LIVE CHAT JS
  // ══════════════════════════════════════════════════════
  var activeSessionId  = null;
  var chatLastId       = 0;
  var chatPollTimer    = null;
  var sessionPollTimer = null;

  // ── Load sessions list ─────────────────────────────────
  function loadSessions() {
    $.get('chat.php', { action: 'get_sessions' }, function(res) {
      if (!res.success) return;
      var list = document.getElementById('sessionsList');
      var badge = document.getElementById('chatUnreadBadge');
      var countBadge = document.getElementById('sessionCountBadge');

      if (res.total_unread > 0) {
        badge.style.display = '';
        badge.textContent = res.total_unread;
      } else {
        badge.style.display = 'none';
      }

      if (res.sessions.length === 0) {
        list.innerHTML = '<div class="empty-state" style="padding:2rem"><i class="fas fa-comments"></i><p>Koi active chat nahi</p></div>';
        countBadge.style.display = 'none';
        return;
      }

      countBadge.style.display = '';
      countBadge.textContent   = res.sessions.length;

      var html = '';
      res.sessions.forEach(function(s) {
        var isActive = s.id == activeSessionId;
        var lastMsg  = s.last_message ? s.last_message.substring(0, 45) + (s.last_message.length > 45 ? '…' : '') : 'Chat started';
        var unreadDot = s.unread_count > 0
          ? '<span style="width:8px;height:8px;background:#ef4444;border-radius:50%;flex-shrink:0"></span>'
          : '<span style="width:8px;height:8px;flex-shrink:0"></span>';

        html += '<div onclick="openSession(' + s.id + ',\'' + escJs(s.customer_name) + '\',' + s.order_id + ',' + (s.total||0) + ',\'' + escJs(s.order_status||'') + '\')" ' +
          'style="padding:.8rem;border-radius:10px;cursor:pointer;margin-bottom:4px;transition:all .2s;' +
          'border:2px solid ' + (isActive ? '#f59e0b' : 'rgba(0,0,0,.06)') + ';' +
          'background:' + (isActive ? '#fef9ee' : '#fff') + '"' +
          ' onmouseover="if(' + (!isActive) + ')this.style.background=\'#f9fafb\'"' +
          ' onmouseout="if(' + (!isActive) + ')this.style.background=\'#fff\'">' +

          '<div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.3rem">' +
          unreadDot +
          '<span style="font-size:.82rem;font-weight:800;color:#1a1a1a;flex:1;min-width:0;' +
          'white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + escHtml(s.customer_name) + '</span>' +
          (s.unread_count > 0 ? '<span style="background:#ef4444;color:#fff;font-size:.62rem;font-weight:800;padding:1px 6px;border-radius:5px">' + s.unread_count + '</span>' : '') +
          '</div>' +

          '<div style="font-size:.72rem;color:#6b7280;margin-bottom:.25rem">' +
          '<i class="fas fa-receipt" style="font-size:.65rem"></i> Order #' + s.order_id +
          ' &nbsp;·&nbsp; Rs. ' + Number(s.total||0).toLocaleString() + '</div>' +

          '<div style="font-size:.73rem;color:#9ca3af;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' +
          escHtml(lastMsg) + '</div>' +
          '</div>';
      });
      list.innerHTML = html;
    }, 'json');
  }

  // ── Open a session ─────────────────────────────────────
  function openSession(sessionId, customerName, orderId, total, orderStatus) {
    activeSessionId = sessionId;
    chatLastId      = 0;

    document.getElementById('chatEmpty').style.display  = 'none';
    document.getElementById('chatActive').style.display = 'flex';
    document.getElementById('chatCustomerName').textContent = customerName;
    document.getElementById('chatOrderInfo').textContent    =
      'Order #' + orderId + ' · Rs. ' + Number(total).toLocaleString() + ' · ' + (orderStatus || 'pending');

    document.getElementById('chatMessages').innerHTML = '';
    document.getElementById('chatInput').value = '';

    // Load history
    pollChatMessages(true);

    // Poll every 3s
    if (chatPollTimer) clearInterval(chatPollTimer);
    chatPollTimer = setInterval(function(){ pollChatMessages(false); }, 3000);

    // Refresh sessions sidebar every 8s
    if (sessionPollTimer) clearInterval(sessionPollTimer);
    sessionPollTimer = setInterval(loadSessions, 8000);

    // Scroll to bottom
    setTimeout(function(){
      var msgs = document.getElementById('chatMessages');
      msgs.scrollTop = msgs.scrollHeight;
    }, 200);
  }

  // ── Poll messages for active session ──────────────────
  function pollChatMessages(isInitial) {
    if (!activeSessionId) return;
    var afterId = isInitial ? 0 : chatLastId;

    $.get('chat.php', {
      action:     'get_messages',
      session_id: activeSessionId,
      after_id:   afterId
    }, function(res) {
      if (!res.success || !res.messages) return;
      if (res.messages.length === 0) return;

      var container = document.getElementById('chatMessages');
      var wasAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 60;

      if (isInitial) container.innerHTML = '';

      res.messages.forEach(function(msg) {
        chatLastId = Math.max(chatLastId, msg.id);
        appendChatMessage(msg.sender, msg.message, msg.created_at);
      });

      if (wasAtBottom || isInitial) {
        container.scrollTop = container.scrollHeight;
      }

      // Update unread badge
      loadSessions();
    }, 'json');
  }

  // ── Append a message bubble ────────────────────────────
  function appendChatMessage(sender, message, createdAt) {
    var container = document.getElementById('chatMessages');
    var isUser = sender === 'user';
    var time = '';
    try {
      var d = new Date(createdAt);
      time = d.toLocaleTimeString('en-PK', { hour:'2-digit', minute:'2-digit' });
    } catch(e) {}

    var div = document.createElement('div');
    div.style.cssText = 'display:flex;justify-content:' + (isUser ? 'flex-start' : 'flex-end');

    var bubble = '<div style="max-width:75%;padding:.55rem .85rem;' +
      'border-radius:' + (isUser ? '14px 14px 14px 4px' : '14px 14px 4px 14px') + ';' +
      'background:' + (isUser ? '#fff' : 'linear-gradient(135deg,#1a1a1a,#0a0a0a)') + ';' +
      'color:' + (isUser ? '#1a1a1a' : '#fff') + ';' +
      'font-size:.83rem;line-height:1.5;' +
      'border:' + (isUser ? '1px solid rgba(0,0,0,.08)' : 'none') + ';' +
      'box-shadow:0 2px 6px rgba(0,0,0,.06)">';

    if (isUser) {
      bubble += '<p style="margin:0 0 2px;font-size:.63rem;font-weight:700;color:#f59e0b;text-transform:uppercase;letter-spacing:.05em">Customer</p>';
    } else {
      bubble += '<p style="margin:0 0 2px;font-size:.63rem;font-weight:700;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.05em">You</p>';
    }

    bubble += '<p style="margin:0">' + escHtml(message) + '</p>';
    bubble += '<p style="margin:3px 0 0;font-size:.63rem;opacity:.5;text-align:right">' + time + '</p>';
    bubble += '</div>';

    div.innerHTML = bubble;
    container.appendChild(div);
  }

  // ── Send reply ─────────────────────────────────────────
  function sendReply() {
    if (!activeSessionId) return;
    var input = document.getElementById('chatInput');
    var msg   = input.value.trim();
    if (!msg) return;

    input.value = '';
    var btn = document.getElementById('sendReplyBtn');
    btn.disabled = true;
    btn.textContent = '...';

    // Optimistic
    appendChatMessage('receptionist', msg, new Date().toISOString());
    var container = document.getElementById('chatMessages');
    container.scrollTop = container.scrollHeight;

    $.post('chat.php', {
      action:     'send_message',
      session_id: activeSessionId,
      message:    msg
    }, function(res) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane" style="color:#fff;font-size:.85rem"></i>';
      if (!res.success) {
        showToast(res.message || 'Message nahi gaya', 'error');
      }
    }, 'json').fail(function() {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane" style="color:#fff;font-size:.85rem"></i>';
      showToast('Network error', 'error');
    });
  }

  // ── Close active chat ──────────────────────────────────
  function closeActiveChat() {
    if (!activeSessionId) return;
    if (!confirm('Is chat session ko close karna chahte hain? Customer ko naya message ana pe reopen ho jayega.')) return;

    $.post('chat.php', { action: 'close_session', session_id: activeSessionId }, function(res) {
      if (res.success) {
        showToast('Chat session close ho gaya', 'success');
        activeSessionId = null;
        if (chatPollTimer) clearInterval(chatPollTimer);
        document.getElementById('chatEmpty').style.display  = '';
        document.getElementById('chatActive').style.display = 'none';
        loadSessions();
      }
    }, 'json');
  }

  // ── Poll global unread count (for sidebar badge) ───────
  function pollUnreadCount() {
    $.get('chat.php', { action: 'get_unread_count' }, function(res) {
      if (!res.success) return;
      var badge = document.getElementById('chatUnreadBadge');
      if (res.count > 0) {
        badge.style.display = '';
        badge.textContent   = res.count;
      } else {
        badge.style.display = 'none';
      }
    }, 'json');
  }
  // Poll unread every 10s always (even in orders panel)
  setInterval(pollUnreadCount, 10000);
  pollUnreadCount();

  // ── HTML/JS escape helpers ─────────────────────────────
  function escHtml(s) {
    return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : '';
  }
  function escJs(s) {
    return s ? String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'") : '';
  }
</script>
</body>
</html>
