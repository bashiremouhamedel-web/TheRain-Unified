<?php

require_once __DIR__ . '/../core/config/bootstrap.php';
require_once __DIR__ . '/../core/config/catalog.php';
require_once __DIR__ . '/../core/auth/csrf.php';
require_once __DIR__ . '/../core/auth/session-service.php';
require_once __DIR__ . '/../core/currency/currency-service.php';
require_once dirname(__DIR__) . '/modules/module-registry.php';

therain_session_start_secure();

if (!empty($_SESSION['therain_user_id'])) {
    header('Location: home.php');
    exit();
}

$errors = isset($_SESSION['therain_register_errors']) ? $_SESSION['therain_register_errors'] : array();
$old = isset($_SESSION['therain_register_old']) ? $_SESSION['therain_register_old'] : array();
unset($_SESSION['therain_register_errors'], $_SESSION['therain_register_old']);

$modules = therain_module_registry();
$languages = therain_language_options();
$timezones = therain_timezone_options();

// Prefer the live, richer database currency catalog (Phase 5); fall back
// to the small static list only if the database is unreachable, so this
// form still renders without a database connection (Phase 3 behaviour).
try {
    $currencies = array();
    foreach (therain_currency_catalog(true) as $currencyRow) {
        $currencies[$currencyRow['code']] = $currencyRow['name'] . ' (' . $currencyRow['symbol'] . ')';
    }
    if (empty($currencies)) {
        $currencies = therain_currency_options();
    }
} catch (Exception $exception) {
    $currencies = therain_currency_options();
}

function therain_old($old, $key, $default = '')
{
    return isset($old[$key]) ? htmlspecialchars($old[$key], ENT_QUOTES, 'UTF-8') : $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create your account | TheRain Unified</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">

  <style>
    * { font-family: 'Poppins', sans-serif; }
    body { background: linear-gradient(135deg, #17A2B8 0%, #6f42c1 100%); padding: 40px 0; }
    .register-box { width: 100%; max-width: 760px; margin: 0 auto; }
    .register-logo { text-align: center; color: #fff; margin-bottom: 20px; }
    .register-logo a { color: #fff; text-decoration: none; font-weight: 700; font-size: 26px; }
    .register-logo span { display: block; font-size: 13px; font-weight: 400; opacity: 0.9; }
    .card { border: none; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
    .card-body { padding: 30px; }
    .section-title { color: #6f42c1; font-weight: 600; font-size: 15px; text-transform: uppercase; letter-spacing: 0.03em; margin: 22px 0 12px; }
    .section-title:first-child { margin-top: 0; }
    .form-control:focus { border-color: #17A2B8; box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25); }
    .btn-primary { background: linear-gradient(135deg, #17A2B8 0%, #6f42c1 100%); border: none; border-radius: 6px; font-weight: 600; }
    .btn-primary:hover { opacity: 0.92; }
    .module-option.disabled { color: #adb5bd; }
    .form-text.brand-hint { color: #ff7844; }
  </style>
</head>
<body class="hold-transition">
<div class="register-box">
  <div class="register-logo">
    <a href="../login.php"><b>TheRain Unified</b><span>One Platform. Every Management System.</span></a>
  </div>

  <div class="card">
    <div class="card-body">
      <?php if (!empty($errors)) : ?>
        <div class="alert alert-danger">
          <ul class="mb-0 pl-3">
            <?php foreach ($errors as $error) : ?>
              <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <p class="text-muted mb-4">Register your business and become its Super Admin. This creates a new TheRain Unified tenant.</p>

      <form action="actions/register.php" method="post" enctype="multipart/form-data">
        <?php echo therain_csrf_field(); ?>

        <h6 class="section-title">Account information</h6>
        <div class="form-group">
          <label>Full name</label>
          <input type="text" class="form-control" name="full_name" value="<?php echo therain_old($old, 'full_name'); ?>" required>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Email</label>
            <input type="email" class="form-control" name="email" value="<?php echo therain_old($old, 'email'); ?>" required>
          </div>
          <div class="form-group col-md-6">
            <label>Phone number</label>
            <input type="text" class="form-control" name="phone" value="<?php echo therain_old($old, 'phone'); ?>" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Password</label>
            <input type="password" class="form-control" name="password" required>
            <small class="form-text text-muted">At least 8 characters, with letters and numbers.</small>
          </div>
          <div class="form-group col-md-6">
            <label>Confirm password</label>
            <input type="password" class="form-control" name="confirm_password" required>
          </div>
        </div>

        <h6 class="section-title">Business information</h6>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Business/management name</label>
            <input type="text" class="form-control" name="business_name" value="<?php echo therain_old($old, 'business_name'); ?>" required>
          </div>
          <div class="form-group col-md-6">
            <label>Business type</label>
            <input type="text" class="form-control" name="business_type" value="<?php echo therain_old($old, 'business_type'); ?>" placeholder="e.g. Retail pharmacy">
          </div>
        </div>
        <div class="form-group">
          <label>Business description</label>
          <textarea class="form-control" name="business_description" rows="2"><?php echo therain_old($old, 'business_description'); ?></textarea>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Business email</label>
            <input type="email" class="form-control" name="business_email" value="<?php echo therain_old($old, 'business_email'); ?>">
          </div>
          <div class="form-group col-md-6">
            <label>Business phone</label>
            <input type="text" class="form-control" name="business_phone" value="<?php echo therain_old($old, 'business_phone'); ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label>Address</label>
          <input type="text" class="form-control" name="address" value="<?php echo therain_old($old, 'address'); ?>">
        </div>
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Country</label>
            <input type="text" class="form-control" name="country" value="<?php echo therain_old($old, 'country'); ?>">
          </div>
          <div class="form-group col-md-4">
            <label>City</label>
            <input type="text" class="form-control" name="city" value="<?php echo therain_old($old, 'city'); ?>">
          </div>
          <div class="form-group col-md-4">
            <label>Currency</label>
            <select class="form-control" name="currency">
              <?php foreach ($currencies as $code => $label) : ?>
                <option value="<?php echo $code; ?>" <?php echo (isset($old['currency']) && $old['currency'] === $code) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Timezone</label>
            <select class="form-control" name="timezone">
              <?php foreach ($timezones as $timezone) : ?>
                <option value="<?php echo $timezone; ?>" <?php echo (isset($old['timezone']) && $old['timezone'] === $timezone) ? 'selected' : ''; ?>><?php echo $timezone; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-6">
            <label>Preferred language</label>
            <select class="form-control" name="locale">
              <?php foreach ($languages as $code => $language) : ?>
                <option value="<?php echo $code; ?>" <?php echo empty($language['active']) ? 'disabled' : ''; ?> class="module-option <?php echo empty($language['active']) ? 'disabled' : ''; ?>">
                  <?php echo htmlspecialchars($language['name'], ENT_QUOTES, 'UTF-8'); ?><?php echo empty($language['active']) ? ' (coming soon)' : ''; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <h6 class="section-title">Branding</h6>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Your profile picture</label>
            <input type="file" class="form-control-file" name="profile_picture" accept="image/png,image/jpeg,image/webp">
          </div>
          <div class="form-group col-md-6">
            <label>Business logo</label>
            <input type="file" class="form-control-file" name="business_logo" accept="image/png,image/jpeg,image/webp">
          </div>
        </div>
        <small class="form-text brand-hint mb-3 d-block">JPG, PNG, or WEBP, up to 2 MB each.</small>

        <h6 class="section-title">Management system</h6>
        <div class="form-group">
          <label>Choose the management system for your business</label>
          <select class="form-control" name="management_system" required>
            <option value="">Select a management system&hellip;</option>
            <?php foreach ($modules as $slug => $module) : ?>
              <option value="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (isset($old['management_system']) && $old['management_system'] === $slug) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($module['name'], ENT_QUOTES, 'UTF-8'); ?><?php echo empty($module['enabled']) ? ' (coming soon)' : ''; ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="form-text text-muted">Modules marked "coming soon" record your interest; they are not yet available to use.</small>
        </div>

        <button type="submit" class="btn btn-primary btn-block mt-3">Create my account</button>
      </form>

      <p class="mt-3 mb-0 text-center">
        Already have an account? <a href="login.php">Sign in</a>
      </p>
    </div>
  </div>
</div>

<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../dist/js/adminlte.min.js"></script>
</body>
</html>
