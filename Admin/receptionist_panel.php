<?php
session_start();
require 'config.php';

// Check if logged in as receptionist
if (!isset($_SESSION['snackzone_receptionist'])) {
    header('Location: receptionist_login.php');
    exit;
}

$receptionist = $_SESSION['snackzone_receptionist'];
$conn = getDB();

// Fetch data
$orders = $conn->query("SELECT * FROM orders ORDER BY created_at DESC");
$menuItems = $conn->query("SELECT * FROM menu_items ORDER BY category ASC, price ASC");
$categoriesRes = $conn->query("SELECT * FROM categories ORDER BY name ASC");
$categoriesList = [];
while ($cat = $categoriesRes->fetch_assoc()) $categoriesList[] = $cat;

$pendingOrders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status='pending'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Receptionist Panel - Snack Zone</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: #f5f5f5;
      color: #1a1a1a;
    }
    
    /* Header */
    .header {
      background: linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%);
      color: #fff;
      padding: 1rem 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .header-left h1 {
      font-size: 1.3rem;
      font-weight: 900;
      margin-bottom: 0.25rem;
    }
    .header-left p {
      font-size: 0.75rem;
      opacity: 0.7;
    }
    .header-right {
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .user-info {
      text-align: right;
    }
    .user-info strong {
      display: block;
      font-size: 0.85rem;
    }
    .user-info small {
      font-size: 0.7rem;
      opacity: 0.7;
    }
    .btn-logout {
      background: rgba(255,255,255,0.1);
      color: #fff;
      border: 1px solid rgba(255,255,255,0.2);
      padding: 0.5rem 1rem;
      border-radius: 8px;
      font-size: 0.75rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-logout:hover {
      background: rgba(255,255,255,0.2);
    }
    
    /* Tabs */
    .tabs {
      background: #fff;
      border-bottom: 2px solid rgba(0,0,0,0.08);
      display: flex;
      padding: 0 1.5rem;
    }
    .tab {
      padding: 1rem 1.5rem;
      font-size: 0.85rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      cursor: pointer;
      border-bottom: 3px solid transparent;
      transition: all 0.2s;
      color: #6b7280;
    }
    .tab.active {
      color: #1a1a1a;
      border-bottom-color: #1a1a1a;
    }
    .tab:hover {
      color: #1a1a1a;
    }
    
    /* Content */
    .content {
      padding: 1.5rem;
      width: 100%;
    }
    .panel {
      display: none;
    }
    .panel.active {
      display: block;
    }
    
    /* Card */
    .card {
      background: #fff;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      margin-bottom: 1.5rem;
    }
    .card-title {
      font-size: 1.1rem;
      font-weight: 800;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    /* Orders Table */
    .orders-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      font-size: 0.85rem;
    }
    .orders-table th {
      background: #f9fafb;
      padding: 0.875rem 1rem;
      text-align: left;
      font-weight: 700;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #6b7280;
      border-bottom: 2px solid rgba(0,0,0,0.08);
      white-space: nowrap;
    }
    .orders-table td {
      padding: 1rem;
      border-bottom: 1px solid rgba(0,0,0,0.05);
      vertical-align: top;
    }
    .orders-table tr:hover {
      background: #f9fafb;
    }
    
    /* Responsive table wrapper */
    .table-wrapper {
      overflow-x: auto;
      margin: 0 -1.5rem;
      padding: 0 1.5rem;
    }
    
    /* Column specific widths */
    .orders-table td:nth-child(1) { /* Order # */
      width: 80px;
      white-space: nowrap;
    }
    .orders-table td:nth-child(2) { /* Customer */
      min-width: 150px;
      max-width: 200px;
    }
    .orders-table td:nth-child(3) { /* Items */
      min-width: 200px;
      max-width: 300px;
    }
    .orders-table td:nth-child(4) { /* Total */
      width: 100px;
      white-space: nowrap;
    }
    .orders-table td:nth-child(5) { /* Status */
      width: 120px;
      white-space: nowrap;
    }
    .orders-table td:nth-child(6) { /* Time */
      width: 120px;
      white-space: nowrap;
    }
    
    .status-badge {
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 0.7rem;
      font-weight: 700;
      text-transform: uppercase;
      display: inline-block;
      white-space: nowrap;
    }
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-confirmed { background: #dbeafe; color: #1e40af; }
    .status-dispatched { background: #d1fae5; color: #065f46; }
    .status-cancelled { background: #fee2e2; color: #991b1b; }
    
    /* Menu Grid */
    .menu-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1rem;
    }
    .menu-card {
      background: #fff;
      border: 1px solid rgba(0,0,0,0.08);
      border-radius: 10px;
      overflow: hidden;
      transition: all 0.2s;
      position: relative;
    }
    .menu-card.out-of-stock {
      opacity: 0.6;
    }
    .menu-card.out-of-stock::after {
      content: 'OUT OF STOCK';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) rotate(-15deg);
      background: #dc2626;
      color: #fff;
      padding: 0.75rem 2rem;
      font-weight: 900;
      font-size: 1.2rem;
      letter-spacing: 0.1em;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(220,38,38,0.4);
      z-index: 10;
    }
    .menu-card:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .menu-card img {
      width: 100%;
      height: 160px;
      object-fit: cover;
    }
    .menu-card-body {
      padding: 1rem;
    }
    .menu-card-title {
      font-size: 0.9rem;
      font-weight: 800;
      margin-bottom: 0.25rem;
    }
    .menu-card-price {
      font-size: 0.85rem;
      font-weight: 900;
      color: #e05c00;
      margin-bottom: 0.75rem;
    }
    .stock-toggle {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.75rem;
      background: #f9fafb;
      border-top: 1px solid rgba(0,0,0,0.06);
    }
    .stock-toggle label {
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #6b7280;
    }
    .toggle-switch {
      position: relative;
      width: 48px;
      height: 24px;
      background: #e5e7eb;
      border-radius: 24px;
      cursor: pointer;
      transition: all 0.3s;
    }
    .toggle-switch.active {
      background: #10b981;
    }
    .toggle-switch::after {
      content: '';
      position: absolute;
      top: 2px;
      left: 2px;
      width: 20px;
      height: 20px;
      background: #fff;
      border-radius: 50%;
      transition: all 0.3s;
    }
    .toggle-switch.active::after {
      left: 26px;
    }
    
    /* Toast */
    .toast {
      position: fixed;
      bottom: 2rem;
      right: 2rem;
      background: #1a1a1a;
      color: #fff;
      padding: 1rem 1.5rem;
      border-radius: 10px;
      font-size: 0.85rem;
      font-weight: 600;
      box-shadow: 0 8px 24px rgba(0,0,0,0.3);
      opacity: 0;
      transform: translateY(20px);
      transition: all 0.3s;
      z-index: 1000;
    }
    .toast.show {
      opacity: 1;
      transform: translateY(0);
    }
    
    @media (max-width: 768px) {
      .menu-grid {
        grid-template-columns: 1fr;
      }
      .tabs {
        overflow-x: auto;
      }
      .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
      }
      .header-right {
        width: 100%;
        justify-content: space-between;
      }
      .orders-table {
        font-size: 0.8rem;
      }
      .orders-table th,
      .orders-table td {
        padding: 0.75rem 0.5rem;
      }
    }
  </style>
</head>
<body>
  <!-- Header -->
  <div class="header">
    <div class="header-left">
      <h1>RECEPTIONIST PANEL</h1>
      <p>Snack Zone Management System</p>
    </div>
    <div class="header-right">
      <div class="user-info">
        <strong><?= htmlspecialchars($receptionist['username']) ?></strong>
        <small>Receptionist</small>
      </div>
      <form action="receptionist_logout.php" method="POST" style="margin:0;">
        <button type="submit" class="btn-logout">
          <i class="fas fa-sign-out-alt"></i> Logout
        </button>
      </form>
    </div>
  </div>
  
  <!-- Tabs -->
  <div class="tabs">
    <div class="tab active" onclick="showTab('orders')">
      <i class="fas fa-shopping-bag"></i> Orders
      <?php if ($pendingOrders > 0): ?>
        <span style="background:#dc2626;color:#fff;padding:2px 8px;border-radius:12px;font-size:0.7rem;margin-left:6px;"><?= $pendingOrders ?></span>
      <?php endif; ?>
    </div>
    <div class="tab" onclick="showTab('menu')">
      <i class="fas fa-utensils"></i> Menu Items
    </div>
  </div>
  
  <!-- Content -->
  <div class="content">
    <!-- Orders Panel -->
    <div class="panel active" id="panel-orders">
      <div class="card">
        <div class="card-title">
          <i class="fas fa-shopping-bag"></i>
          Recent Orders
        </div>
        <div class="table-wrapper">
          <table class="orders-table">
            <thead>
              <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Total</th>
                <th>Status</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody id="ordersTableBody">
              <?php while ($order = $orders->fetch_assoc()): ?>
                <tr>
                  <td><strong>#<?= $order['id'] ?></strong></td>
                  <td>
                    <div><strong><?= htmlspecialchars($order['name']) ?></strong></div>
                    <div style="font-size:0.75rem;color:#6b7280;"><?= htmlspecialchars($order['phone']) ?></div>
                  </td>
                  <td>
                    <div style="font-size:0.8rem;">
                      <?php
                        $items = json_decode($order['items'], true);
                        $itemNames = array_map(function($i) { return $i['title'] . ' x' . $i['qty']; }, $items);
                        echo htmlspecialchars(implode(', ', $itemNames));
                      ?>
                    </div>
                  </td>
                  <td><strong>Rs. <?= number_format($order['total'], 0) ?></strong></td>
                  <td>
                    <span class="status-badge status-<?= $order['status'] ?>">
                      <?= ucfirst($order['status']) ?>
                    </span>
                  </td>
                  <td style="font-size:0.75rem;color:#6b7280;">
                    <?= date('d M, H:i', strtotime($order['created_at'])) ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    
    <!-- Menu Panel -->
    <div class="panel" id="panel-menu">
      <div class="card">
        <div class="card-title">
          <i class="fas fa-utensils"></i>
          Menu Items - Stock Management
        </div>
        <p style="font-size:0.85rem;color:#6b7280;margin-bottom:1.5rem;">
          Toggle items on/off to mark them as in-stock or out-of-stock
        </p>
        <div class="menu-grid">
          <?php
            $menuItems->data_seek(0);
            while ($item = $menuItems->fetch_assoc()):
              $inStock = $item['in_stock'] ?? 1;
              $catName = ucfirst($item['category']);
              foreach ($categoriesList as $c) {
                if ($c['slug'] === $item['category']) { $catName = $c['name']; break; }
              }
          ?>
            <div class="menu-card <?= $inStock ? '' : 'out-of-stock' ?>" id="menu-card-<?= $item['id'] ?>">
              <img src="../<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" />
              <div class="menu-card-body">
                <div class="menu-card-title"><?= htmlspecialchars($item['title']) ?></div>
                <div class="menu-card-price">Rs. <?= number_format($item['price'], 0) ?></div>
              </div>
              <div class="stock-toggle">
                <label><?= $inStock ? 'In Stock' : 'Out of Stock' ?></label>
                <div class="toggle-switch <?= $inStock ? 'active' : '' ?>" 
                     onclick="toggleStock(<?= $item['id'] ?>, <?= $inStock ? 0 : 1 ?>)">
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
      </div>
    </div>
  </div>
  
  <div class="toast" id="toast"></div>
  
  <script>
    function showTab(tab) {
      $('.tab').removeClass('active');
      $('.panel').removeClass('active');
      $(`.tab:contains('${tab === 'orders' ? 'Orders' : 'Menu'}')`).first().addClass('active');
      $(`#panel-${tab}`).addClass('active');
    }
    
    function toggleStock(itemId, inStock) {
      console.log('Toggle Stock called:', itemId, inStock);
      
      $.post('action.php', {
        action: 'toggle_stock',
        item_id: itemId,
        in_stock: inStock
      }, function(res) {
        console.log('Response received:', res);
        
        if (res.success) {
          const $card = $(`#menu-card-${itemId}`);
          const $toggle = $card.find('.toggle-switch');
          const $label = $card.find('.stock-toggle label');
          
          if (inStock) {
            $card.removeClass('out-of-stock');
            $toggle.addClass('active');
            $label.text('In Stock');
          } else {
            $card.addClass('out-of-stock');
            $toggle.removeClass('active');
            $label.text('Out of Stock');
          }
          
          showToast(res.message);
        } else {
          console.error('Error:', res.message);
          showToast(res.message || 'Error updating stock', 'error');
        }
      }, 'json').fail(function(xhr, status, error) {
        console.error('AJAX Error:', status, error);
        console.error('Response Text:', xhr.responseText);
        showToast('Network error. Please try again.', 'error');
      });
    }
    
    function showToast(msg, type = 'success') {
      const $toast = $('#toast');
      $toast.text(msg).addClass('show');
      setTimeout(() => $toast.removeClass('show'), 3000);
    }
  </script>
</body>
</html>
