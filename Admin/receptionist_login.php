<?php
session_start();
require 'config.php';

// If already logged in as receptionist, redirect to panel
if (isset($_SESSION['snackzone_receptionist'])) {
    header('Location: receptionist_panel.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (!$username || !$password) {
        $error = 'Username and password are required';
    } else {
        $conn = getDB();
        
        $stmt = $conn->prepare("SELECT id, username, password FROM admin_users WHERE username = ? AND role = 'receptionist'");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['snackzone_receptionist'] = [
                    'id' => $user['id'],
                    'username' => $user['username']
                ];
                header('Location: receptionist_panel.php');
                exit;
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Receptionist Login - Snack Zone</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: #0a0a0a;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .login-container {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.5);
      width: 100%;
      max-width: 420px;
      overflow: hidden;
    }
    .login-header {
      background: linear-gradient(135deg, #1a1a1a 0%, #000 100%);
      color: #fff;
      padding: 2.5rem 2rem;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .login-header::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, transparent 70%);
    }
    .login-header i {
      font-size: 3.5rem;
      margin-bottom: 1rem;
      opacity: 0.95;
      position: relative;
      z-index: 1;
    }
    .login-header h1 {
      font-size: 1.6rem;
      font-weight: 900;
      margin-bottom: 0.5rem;
      letter-spacing: -0.02em;
      position: relative;
      z-index: 1;
    }
    .login-header p {
      font-size: 0.85rem;
      opacity: 0.7;
      font-weight: 500;
      position: relative;
      z-index: 1;
    }
    .login-body {
      padding: 2rem;
    }
    .form-group {
      margin-bottom: 1.5rem;
    }
    .form-group label {
      display: block;
      font-size: 0.7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #374151;
      margin-bottom: 0.5rem;
    }
    .input-wrapper {
      position: relative;
    }
    .input-wrapper i {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
      font-size: 0.9rem;
    }
    .form-group input {
      width: 100%;
      padding: 0.875rem 1rem 0.875rem 2.75rem;
      border: 2px solid rgba(0,0,0,0.1);
      border-radius: 8px;
      font-family: 'Inter', sans-serif;
      font-size: 0.95rem;
      transition: all 0.2s;
      outline: none;
    }
    .form-group input:focus {
      border-color: #000;
      box-shadow: 0 0 0 3px rgba(0,0,0,0.05);
    }
    .error-msg {
      background: #fee2e2;
      color: #dc2626;
      padding: 0.875rem 1rem;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .btn-login {
      width: 100%;
      background: linear-gradient(135deg, #1a1a1a 0%, #000 100%);
      color: #fff;
      border: none;
      padding: 1rem;
      font-family: 'Inter', sans-serif;
      font-size: 0.85rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }
    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.4);
    }
    .btn-login:active {
      transform: translateY(0);
    }
    .login-footer {
      padding: 1.5rem 2rem;
      background: #f9fafb;
      text-align: center;
      font-size: 0.8rem;
      color: #6b7280;
    }
    .login-footer a {
      color: #000;
      text-decoration: none;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-header">
      <i class="fas fa-user-circle"></i>
      <h1>RECEPTIONIST LOGIN</h1>
      <p>Snack Zone Management System</p>
    </div>
    <div class="login-body">
      <?php if ($error): ?>
        <div class="error-msg">
          <i class="fas fa-exclamation-circle"></i>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>
      
      <form method="POST" action="">
        <div class="form-group">
          <label>Username</label>
          <div class="input-wrapper">
            <i class="fas fa-user"></i>
            <input type="text" name="username" placeholder="Enter your username" required autofocus />
          </div>
        </div>
        
        <div class="form-group">
          <label>Password</label>
          <div class="input-wrapper">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" placeholder="Enter your password" required />
          </div>
        </div>
        
        <button type="submit" class="btn-login">
          <i class="fas fa-sign-in-alt"></i> Login
        </button>
      </form>
    </div>
    <div class="login-footer">
      Contact admin if you forgot your credentials
    </div>
  </div>
</body>
</html>
