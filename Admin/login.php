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

// Already logged in
if (isset($_SESSION['snackzone_admin'])) {
    header('Location: index.php');
    exit;
}
if (isset($_SESSION['snackzone_receptionist'])) {
    header('Location: receptionist.php');
    exit;
}

// DB connection + schema bootstrap (tables created in db.php, reuses getDB())
require_once __DIR__ . '/db.php';   // db.php already calls getDB() and creates all tables
// $conn is available from db.php scope — re-get it cleanly
$conn = getDB();
// Ensure role column exists (receptionist feature)
$conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS role ENUM('admin','receptionist') NOT NULL DEFAULT 'admin'");


// Seed default admin user on first run
$check = $conn->query("SELECT id FROM admin_users LIMIT 1");
if ($check && $check->num_rows === 0) {
    // IMPORTANT: Change this password immediately after first login!
    $defaultPassword = getenv('ADMIN_DEFAULT_PASSWORD') ?: 'SZ@dm!n#2024$Secure';
    $hash = password_hash($defaultPassword, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO admin_users (username, password) VALUES (?, ?)");
    $defaultUser = 'admin';
    $stmt->bind_param('ss', $defaultUser, $hash);
    $stmt->execute();
    $stmt->close();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$username || !$password) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, password FROM admin_users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin  = $result->fetch_assoc();

        if ($admin && password_verify($password, $admin['password'])) {
            // Check role — redirect receptionist to their own dashboard
            $roleStmt = $conn->prepare("SELECT role FROM admin_users WHERE id = ?");
            $roleStmt->bind_param('i', $admin['id']);
            $roleStmt->execute();
            $roleRow = $roleStmt->get_result()->fetch_assoc();
            $roleStmt->close();

            $role = $roleRow['role'] ?? 'admin';
            if ($role === 'receptionist') {
                $_SESSION['snackzone_receptionist'] = $admin['id'];
                header('Location: receptionist.php');
            } else {
                $_SESSION['snackzone_admin'] = $admin['id'];
                header('Location: index.php');
            }
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login — Snack Zone</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      position: relative;
      overflow: hidden;
    }

    /* Animated background elements */
    body::before, body::after {
      content: '';
      position: absolute;
      border-radius: 50%;
      background: rgba(255,255,255,0.1);
      animation: float 20s infinite ease-in-out;
    }
    body::before {
      width: 400px; height: 400px;
      top: -200px; left: -200px;
      animation-delay: -5s;
    }
    body::after {
      width: 300px; height: 300px;
      bottom: -150px; right: -150px;
      animation-delay: -10s;
    }
    @keyframes float {
      0%, 100% { transform: translate(0, 0) scale(1); }
      33%      { transform: translate(30px, -50px) scale(1.1); }
      66%      { transform: translate(-20px, 20px) scale(0.9); }
    }

    .login-wrap {
      width: 100%;
      max-width: 440px;
      background: #ffffff;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      padding: 3rem 2.5rem 2.5rem;
      position: relative;
      z-index: 1;
      animation: slideUp 0.5s ease;
    }
    @keyframes slideUp {
      from { opacity: 0; transform: translateY(30px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* Logo */
    .login-logo {
      text-align: center;
      margin-bottom: 2rem;
    }
    .login-logo .icon {
      width: 70px; height: 70px;
      background: linear-gradient(135deg, #4169e1 0%, #3557c7 100%);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.8rem; color: #fff;
      margin: 0 auto 1.2rem;
      border-radius: 50%;
      box-shadow: 0 8px 20px rgba(65,105,225,0.4);
      animation: pulse 2s infinite;
    }
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50%      { transform: scale(1.05); }
    }
    .login-logo h1 {
      font-size: 1.8rem;
      font-weight: 900;
      background: linear-gradient(135deg, #4169e1 0%, #764ba2 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      text-transform: uppercase;
      letter-spacing: -0.02em;
      line-height: 1;
      margin-bottom: 0.4rem;
    }
    .login-logo p {
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: #6b7280;
    }

    /* Error box */
    .error-box {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: #fff;
      padding: 0.9rem 1.1rem;
      border-radius: 10px;
      display: flex;
      align-items: center;
      gap: 0.7rem;
      font-size: 0.8rem;
      font-weight: 600;
      letter-spacing: 0.02em;
      margin-bottom: 1.5rem;
      animation: shake 0.4s ease;
      box-shadow: 0 4px 12px rgba(239,68,68,0.3);
    }
    @keyframes shake {
      0%,100% { transform: translateX(0); }
      25%      { transform: translateX(-8px); }
      75%      { transform: translateX(8px); }
    }

    /* Form */
    .form-group {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      margin-bottom: 1.3rem;
    }
    .form-group label {
      font-size: 0.7rem;
      font-weight: 800;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: #374151;
    }
    .input-wrap { position: relative; }
    .input-wrap i.fa-user,
    .input-wrap i.fa-lock {
      position: absolute; left: 1.1rem; top: 50%;
      transform: translateY(-50%);
      color: #9ca3af; font-size: 0.9rem;
      pointer-events: none;
      transition: color 0.2s;
    }
    .input-wrap input {
      width: 100%;
      padding: 0.95rem 3rem 0.95rem 3rem;
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      font-family: 'Inter', sans-serif;
      font-size: 0.9rem;
      color: #1f2937;
      background: #f9fafb;
      outline: none;
      transition: all 0.2s;
    }
    .input-wrap input:focus {
      border-color: #4169e1;
      background: #fff;
      box-shadow: 0 0 0 4px rgba(65,105,225,0.1);
    }
    .input-wrap input:focus + i,
    .input-wrap:focus-within i {
      color: #4169e1;
    }
    .input-wrap input::placeholder { color: #9ca3af; }

    /* Toggle password */
    .toggle-pw {
      position: absolute; right: 1.1rem; top: 50%;
      transform: translateY(-50%);
      color: #9ca3af; cursor: pointer; font-size: 0.9rem;
      transition: color 0.15s;
    }
    .toggle-pw:hover { color: #4169e1; }

    /* Submit */
    .btn-login {
      width: 100%;
      padding: 1rem;
      background: linear-gradient(135deg, #4169e1 0%, #3557c7 100%);
      color: #fff;
      border: none;
      border-radius: 12px;
      font-family: 'Inter', sans-serif;
      font-size: 0.82rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      cursor: pointer;
      transition: all 0.3s;
      margin-top: 0.8rem;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.7rem;
      box-shadow: 0 4px 14px rgba(65,105,225,0.4);
    }
    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(65,105,225,0.5);
    }
    .btn-login:active { 
      transform: translateY(0);
      box-shadow: 0 2px 8px rgba(65,105,225,0.3);
    }

    /* Back link */
    .back-link {
      text-align: center;
      margin-top: 1.8rem;
      padding-top: 1.8rem;
      border-top: 1px solid #e5e7eb;
      font-size: 0.75rem;
      font-weight: 600;
      color: #6b7280;
    }
    .back-link a {
      color: #4169e1;
      text-decoration: none;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      transition: all 0.2s;
    }
    .back-link a:hover { 
      color: #3557c7;
      gap: 0.6rem;
    }

    /* Mobile responsive */
    @media (max-width: 480px) {
      .login-wrap { padding: 2.5rem 1.8rem; }
      .login-logo h1 { font-size: 1.5rem; }
      .login-logo .icon { width: 60px; height: 60px; font-size: 1.5rem; }
    }
  </style>
</head>
<body>

  <div class="login-wrap">

    <!-- Logo -->
    <div class="login-logo">
      <div class="icon"><i class="fas fa-burger"></i></div>
      <h1>SnackZone</h1>
      <p>Admin Panel</p>
    </div>

    <!-- Error -->
    <?php if ($error): ?>
    <div class="error-box">
      <i class="fas fa-circle-xmark"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" action="">
      <div class="form-group">
        <label>Username</label>
        <div class="input-wrap">
          <i class="fas fa-user"></i>
          <input
            type="text"
            name="username"
            placeholder="Enter username"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            autocomplete="username"
            required
          />
        </div>
      </div>

      <div class="form-group">
        <label>Password</label>
        <div class="input-wrap">
          <i class="fas fa-lock"></i>
          <input
            type="password"
            name="password"
            id="passwordInput"
            placeholder="Enter password"
            autocomplete="current-password"
            required
          />
          <i class="fas fa-eye toggle-pw" id="togglePw"></i>
        </div>
      </div>

      <button type="submit" class="btn-login">
        <i class="fas fa-right-to-bracket"></i> Login to Admin Panel
      </button>
    </form>

    <div class="back-link">
      <a href="../"><i class="fas fa-arrow-left"></i> Back to Website</a>
    </div>

  </div>

  <script>
    document.getElementById('togglePw').addEventListener('click', function() {
      const input = document.getElementById('passwordInput');
      if (input.type === 'password') {
        input.type = 'text';
        this.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        input.type = 'password';
        this.classList.replace('fa-eye-slash', 'fa-eye');
      }
    });
  </script>

</body>
</html>
