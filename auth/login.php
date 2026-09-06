<?php

require_once __DIR__ . '/../core/config/bootstrap.php';
require_once __DIR__ . '/../core/config/catalog.php';
require_once __DIR__ . '/../core/auth/csrf.php';
require_once __DIR__ . '/../core/auth/session-service.php';
require_once __DIR__ . '/../core/i18n/auth.php';

therain_session_start_secure();

if (!empty($_SESSION['therain_user_id'])) {
    header('Location: home.php');
    exit();
}

$errorMessage = isset($_SESSION['therain_login_error']) ? $_SESSION['therain_login_error'] : null;
$successMessage = isset($_SESSION['therain_login_success']) ? $_SESSION['therain_login_success'] : null;
unset($_SESSION['therain_login_error'], $_SESSION['therain_login_success']);
$languages = therain_language_options();
?><!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(therain_auth_locale(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TheRain Unified | Sign In</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body class="auth-page">
<div class="auth-layout">
  <section class="auth-visual" aria-label="TheRain Unified">
    <div class="auth-visual-content">
      <img class="auth-visual-logo auth-visual-logo-light" src="../dist/image/logo-light.png" alt="TheRain Unified logo">
      <h1><?php echo therain_auth_t('tagline'); ?></h1>
      <p><?php echo therain_auth_t('platform'); ?> <?php echo therain_auth_t('secure'); ?></p>
      <div class="auth-features" aria-label="Platform highlights">
        <div><i class="fas fa-th-large" aria-hidden="true"></i><span>Unified<br>Platform</span></div>
        <div><i class="fas fa-shield-alt" aria-hidden="true"></i><span>Enterprise<br>Security</span></div>
        <div><i class="fas fa-chart-bar" aria-hidden="true"></i><span>Real-time<br>Analytics</span></div>
        <div><i class="fas fa-users" aria-hidden="true"></i><span>Multi-tenant<br>Ready</span></div>
      </div>
    </div>
  </section>
  <main class="auth-panel">
    <section class="auth-card" aria-labelledby="auth-title">
      <div class="auth-card-header">
        <div class="auth-language">
          <label class="sr-only" for="auth-language">Language</label>
          <select id="auth-language" onchange="window.location.href='login.php?lang=' + encodeURIComponent(this.value)" aria-label="<?php echo therain_auth_t('language_selector'); ?>">
            <?php foreach ($languages as $code => $language) : ?>
              <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo therain_auth_locale() === $code ? 'selected' : ''; ?>><?php echo htmlspecialchars($language['name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <img class="auth-logo" src="../dist/image/logo.png" alt="TheRain Unified">
        <h2 id="auth-title"><?php echo therain_auth_t('welcome_back'); ?></h2>
        <p class="auth-description"><?php echo therain_auth_t('sign_in_description'); ?></p>
      </div>
      <?php if ($successMessage) : ?><div class="auth-alert auth-alert-success" role="status"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
      <?php if ($errorMessage) : ?><div class="auth-alert auth-alert-error" role="alert"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
      <form action="actions/login.php" method="post">
        <?php echo therain_csrf_field(); ?>
        <div class="auth-field"><label for="email"><?php echo therain_auth_t('email_or_username'); ?></label><div class="auth-input"><i class="far fa-envelope" aria-hidden="true"></i><input id="email" type="text" name="email" placeholder="<?php echo therain_auth_t('email_or_username'); ?>" required autofocus autocomplete="username"></div></div>
        <div class="auth-field"><label for="password"><?php echo therain_auth_t('password'); ?></label><div class="auth-input"><i class="fas fa-lock" aria-hidden="true"></i><input id="password" class="has-toggle" type="password" name="password" placeholder="<?php echo therain_auth_t('password'); ?>" required autocomplete="current-password"><button class="auth-toggle" type="button" data-password-toggle="password" aria-label="<?php echo therain_auth_t('show_password'); ?>"><i class="far fa-eye" aria-hidden="true"></i></button></div></div>
        <div class="auth-row"><label class="auth-check"><input type="checkbox" name="remember" value="1"> <?php echo therain_auth_t('remember_me'); ?></label><span><?php echo therain_auth_t('forgot_password'); ?></span></div>
        <button class="auth-button" type="submit"><?php echo therain_auth_t('sign_in'); ?> <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
      </form>
      <div class="auth-divider">OR</div>
      <p class="auth-account-link"><?php echo therain_auth_t('new_account'); ?> <a href="register.php"><?php echo therain_auth_t('create_one'); ?></a></p>
    </section>
  </main>
</div>
<footer class="auth-footer">
  <div class="auth-footer-security"><i class="fas fa-shield-alt" aria-hidden="true"></i> Your data is protected with enterprise-grade security</div>
  <div class="auth-footer-copy">&copy; <?php echo date('Y'); ?> TheRain Unified. <?php echo therain_auth_t('copyright'); ?></div>
  <nav class="auth-footer-links" aria-label="Footer links"><a href="#">Privacy Policy</a><a href="#">Terms &amp; Conditions</a><a href="#">Help Center</a></nav>
</footer>
<script>
document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
  button.addEventListener('click', function () {
    var input = document.getElementById(button.getAttribute('data-password-toggle'));
    var visible = input.type === 'text';
    input.type = visible ? 'password' : 'text';
    button.setAttribute('aria-label', visible ? <?php echo json_encode(therain_auth_t('show_password')); ?> : <?php echo json_encode(therain_auth_t('hide_password')); ?>);
    button.querySelector('i').className = visible ? 'far fa-eye' : 'far fa-eye-slash';
  });
});
</script>
</body>
</html>
