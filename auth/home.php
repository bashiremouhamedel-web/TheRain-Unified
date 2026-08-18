<?php

require_once __DIR__ . '/../core/config/bootstrap.php';
require_once __DIR__ . '/../core/config/connection.php';
require_once __DIR__ . '/../core/auth/session-service.php';
require_once __DIR__ . '/../core/auth/auth-service.php';
require_once dirname(__DIR__) . '/modules/module-registry.php';

therain_session_start_secure();
$user = therain_require_login('login.php');
therain_session_touch();

$connection = therain_db();
$tenant = null;
$module = null;

if (!empty($user['tenant_id'])) {
    $statement = $connection->prepare('SELECT * FROM tenants WHERE id = ? LIMIT 1');
    $statement->bind_param('i', $user['tenant_id']);
    $statement->execute();
    $tenant = $statement->get_result()->fetch_assoc();
    $statement->close();

    $moduleStatement = $connection->prepare('SELECT module_slug, status FROM tenant_modules WHERE tenant_id = ? LIMIT 1');
    $moduleStatement->bind_param('i', $user['tenant_id']);
    $moduleStatement->execute();
    $moduleRow = $moduleStatement->get_result()->fetch_assoc();
    $moduleStatement->close();

    if ($moduleRow) {
        $registryEntry = therain_find_module($moduleRow['module_slug']);
        $module = array(
            'name' => $registryEntry ? $registryEntry['name'] : $moduleRow['module_slug'],
            'status' => $moduleRow['status'],
        );
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Welcome | TheRain Unified</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">
  <style>
    * { font-family: 'Poppins', sans-serif; }
    body { background: #F4F6F9; }
    .wrap { max-width: 640px; margin: 60px auto; padding: 0 15px; }
    .card { border: none; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
    .brand { color: #17A2B8; font-weight: 700; }
    .badge-pending { background: #FFCAB0; color: #7a3b1e; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="card-body p-4">
      <p class="brand mb-1">TheRain Unified</p>
      <h4 class="mb-3">Welcome, <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></h4>

      <?php if ($tenant) : ?>
        <p class="text-muted mb-1">Business</p>
        <p class="mb-3"><strong><?php echo htmlspecialchars($tenant['business_name'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
      <?php endif; ?>

      <?php if ($module) : ?>
        <p class="text-muted mb-1">Selected management system</p>
        <p class="mb-3">
          <strong><?php echo htmlspecialchars($module['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
          <?php if ($module['status'] === 'enabled') : ?>
            <span class="badge badge-info ml-1">Enabled</span>
          <?php else : ?>
            <span class="badge badge-pending ml-1">Coming soon</span>
          <?php endif; ?>
        </p>
      <?php endif; ?>

      <div class="alert alert-light border">
        Your Unified account and business are set up. Dashboard access for your selected management
        system is connected in a later phase; it is not available from this account yet.
      </div>

      <a href="actions/logout.php" class="btn btn-outline-secondary btn-sm">Sign out</a>
    </div>
  </div>
</div>
</body>
</html>
