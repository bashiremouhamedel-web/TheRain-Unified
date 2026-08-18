<?php

require_once __DIR__ . '/../core/config/bootstrap.php';
require_once __DIR__ . '/../core/auth/csrf.php';
require_once __DIR__ . '/../core/auth/session-service.php';

therain_session_start_secure();

if (!empty($_SESSION['therain_user_id'])) {
    header('Location: home.php');
    exit();
}

$errorMessage = isset($_SESSION['therain_login_error']) ? $_SESSION['therain_login_error'] : null;
$successMessage = isset($_SESSION['therain_login_success']) ? $_SESSION['therain_login_success'] : null;
unset($_SESSION['therain_login_error'], $_SESSION['therain_login_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in | TheRain Unified</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">

  <style>
    * { font-family: 'Poppins', sans-serif; }
    body { background: linear-gradient(135deg, #17A2B8 0%, #6f42c1 100%); }
    .login-box { width: 100%; max-width: 400px; }
    .login-logo { text-align: center; color: #fff; margin-bottom: 20px; }
    .login-logo a { color: #fff; text-decoration: none; font-weight: 700; font-size: 28px; }
    .login-logo span { display: block; font-size: 12px; font-weight: 400; opacity: 0.9; }
    .card { border: none; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
    .login-card-body { padding: 30px; }
    .form-control:focus { border-color: #17A2B8; box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25); }
    .btn-primary { background: linear-gradient(135deg, #17A2B8 0%, #6f42c1 100%); border: none; border-radius: 6px; font-weight: 600; }
    .btn-primary:hover { opacity: 0.92; }
    .login-box-msg { color: #6C757D; font-weight: 500; }
  </style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="login-logo">
    <a href="#"><b>TheRain Unified</b><span>One Platform. Every Management System.</span></a>
  </div>
  <div class="card">
    <div class="card-body login-card-body">
      <?php if ($successMessage) : ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
      <?php if ($errorMessage) : ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <p class="login-box-msg">Sign in to your TheRain Unified account</p>

      <form action="actions/login.php" method="post">
        <?php echo therain_csrf_field(); ?>
        <div class="input-group mb-3">
          <input type="email" class="form-control" placeholder="Email" name="email" required autofocus>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-envelope"></span></div>
          </div>
        </div>
        <div class="input-group mb-3">
          <input type="password" class="form-control" placeholder="Password" name="password" required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-lock"></span></div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
      </form>

      <p class="mb-1 mt-3">
        <a href="register.php">Create a new account</a>
      </p>
      <p class="mb-0 text-muted" style="font-size: 12px;">
        Looking for the Pharmacy POS login? <a href="../login.php">Use the Pharmacy sign-in page</a>.
      </p>
    </div>
  </div>
</div>

<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../dist/js/adminlte.min.js"></script>
</body>
</html>
