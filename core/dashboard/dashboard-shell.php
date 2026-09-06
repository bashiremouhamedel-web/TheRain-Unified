<?php

require_once __DIR__ . '/../notifications/notification-service.php';
require_once __DIR__ . '/../currency/currency-service.php';
require_once __DIR__ . '/../i18n/auth.php';

if (!function_exists('therain_dashboard_identity')) {
    function therain_dashboard_identity(array $user, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $tenant = null;
        $profile = array();
        $roles = array();
        $settings = array();

        $statement = $connection->prepare('SELECT * FROM tenants WHERE id = ? LIMIT 1');
        $statement->bind_param('i', $user['tenant_id']);
        $statement->execute();
        $tenant = $statement->get_result()->fetch_assoc();
        $statement->close();

        $statement = $connection->prepare('SELECT * FROM user_profiles WHERE user_id = ? LIMIT 1');
        $statement->bind_param('i', $user['id']);
        $statement->execute();
        $profile = $statement->get_result()->fetch_assoc() ?: array();
        $statement->close();

        $statement = $connection->prepare(
            'SELECT roles.name FROM roles INNER JOIN user_roles ON user_roles.role_id = roles.id
             WHERE user_roles.user_id = ? AND user_roles.tenant_id = ? ORDER BY roles.name'
        );
        $statement->bind_param('ii', $user['id'], $user['tenant_id']);
        $statement->execute();
        $roles = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        $statement->close();

        $statement = $connection->prepare('SELECT setting_key, setting_value FROM tenant_settings WHERE tenant_id = ?');
        $statement->bind_param('i', $user['tenant_id']);
        $statement->execute();
        foreach ($statement->get_result()->fetch_all(MYSQLI_ASSOC) as $setting) {
            $settings[$setting['setting_key']] = $setting['setting_value'];
        }
        $statement->close();

        return array(
            'tenant' => $tenant ?: array(),
            'profile' => $profile,
            'roles' => $roles,
            'settings' => $settings,
        );
    }
}

if (!function_exists('therain_dashboard_escape')) {
    function therain_dashboard_escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('therain_dashboard_render')) {
  function therain_dashboard_render(array $identity, array $navigation, $notificationCount, $content, $moduleName = 'Unified workspace')
    {
        $tenant = $identity['tenant'];
        $profile = $identity['profile'];
        $roleNames = array_column($identity['roles'], 'name');
        $displayName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
        $displayName = $displayName !== '' ? $displayName : ($GLOBALS['therain_dashboard_user']['username'] ?? 'Account');
        $businessName = $tenant['business_name'] ?? $tenant['name'] ?? 'TheRain Unified';
        $logo = $identity['settings']['business_logo'] ?? '';
        $photo = $profile['profile_image_path'] ?? '';
        $base = $GLOBALS['therain_dashboard_base'] ?? '../';
        $currency = therain_user_currency_preference($GLOBALS['therain_dashboard_user']['id'], $GLOBALS['therain_dashboard_user']['tenant_id']);
        $locale = therain_auth_locale();
        $languageOptions = therain_language_options();
        $languagePath = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        ?>
<!DOCTYPE html>
<html lang="<?php echo therain_dashboard_escape($locale); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo therain_dashboard_escape($businessName); ?> | TheRain Unified</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo $base; ?>plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="<?php echo $base; ?>dist/css/adminlte.min.css">
  <link rel="stylesheet" href="<?php echo $base; ?>core/dashboard/dashboard.css">
</head>
<body class="dashboard-shell">
<header class="shell-topbar">
  <button class="shell-icon-button" type="button" data-shell-toggle aria-label="Toggle navigation"><i class="fas fa-bars"></i></button>
  <div class="shell-context"><strong>TheRain Unified</strong><small><?php echo therain_dashboard_escape($moduleName); ?></small></div>
  <form class="shell-search" action="<?php echo $base; ?>core/search/index.php" method="get">
    <label class="sr-only" for="shell-search-input">Search</label>
    <input id="shell-search-input" name="q" type="search" placeholder="Search this workspace" autocomplete="off">
    <button type="submit" aria-label="Search"><i class="fas fa-search"></i></button>
  </form>
  <div class="shell-topbar-actions">
    <span class="shell-currency" title="Display currency"><i class="fas fa-coins" aria-hidden="true"></i> <?php echo therain_dashboard_escape($currency['code'] ?? ''); ?></span>
    <label class="shell-language"><i class="fas fa-globe" aria-hidden="true"></i><span class="sr-only">Language</span><select onchange="window.location.href='<?php echo therain_dashboard_escape($languagePath); ?>?lang=' + encodeURIComponent(this.value)">
      <?php foreach ($languageOptions as $code => $language) : ?><option value="<?php echo therain_dashboard_escape($code); ?>" <?php echo $locale === $code ? 'selected' : ''; ?>><?php echo therain_dashboard_escape($language['name']); ?></option><?php endforeach; ?>
    </select></label>
    <a class="shell-notification" href="<?php echo $base; ?>core/notifications/index.php" aria-label="Notifications">
      <i class="far fa-bell"></i><?php if ($notificationCount > 0) : ?><span><?php echo (int) $notificationCount; ?></span><?php endif; ?>
    </a>
    <details class="shell-profile">
      <summary><span class="shell-avatar"><?php if ($photo) : ?><img src="<?php echo therain_dashboard_escape($base . ltrim($photo, '/')); ?>" alt="<?php echo therain_dashboard_escape($displayName); ?> profile photo"><?php else : ?><i class="fas fa-user"></i><?php endif; ?></span><span><strong><?php echo therain_dashboard_escape($displayName); ?></strong><small><?php echo therain_dashboard_escape(implode(', ', $roleNames) ?: 'Member'); ?></small></span><i class="fas fa-chevron-down"></i></summary>
      <div class="shell-profile-menu"><a href="<?php echo $base; ?>auth/actions/logout.php">Logout</a></div>
    </details>
  </div>
</header>
<div class="shell-body">
  <aside class="shell-sidebar" data-shell-sidebar>
    <a class="shell-brand" href="<?php echo $base; ?>auth/home.php">
      <?php if ($logo) : ?><img src="<?php echo therain_dashboard_escape($base . ltrim($logo, '/')); ?>" alt="<?php echo therain_dashboard_escape($businessName); ?> logo"><?php else : ?><span class="shell-brand-mark">TR</span><?php endif; ?>
      <span><strong><?php echo therain_dashboard_escape($businessName); ?></strong><small><?php echo therain_dashboard_escape($moduleName); ?></small></span>
    </a>
    <nav aria-label="Main navigation"><ul>
      <?php foreach ($navigation as $item) : ?><li><a href="<?php echo therain_dashboard_escape($base . ltrim($item['route'], '/')); ?>" title="<?php echo therain_dashboard_escape($item['label']); ?>"><i class="<?php echo therain_dashboard_escape($item['icon'] ?? 'fas fa-circle'); ?>"></i><span><?php echo therain_dashboard_escape($item['label']); ?></span></a></li><?php endforeach; ?>
    </ul></nav>
    <a class="shell-logout" href="<?php echo $base; ?>auth/actions/logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
  </aside>
  <main class="shell-main"><div class="shell-content"><?php echo $content; ?></div></main>
</div>
<footer class="shell-footer"><span><i class="fas fa-shield-alt" aria-hidden="true"></i> Protected workspace</span><span>&copy; <?php echo date('Y'); ?> TheRain Unified. <?php echo therain_auth_t('copyright'); ?></span><nav><a href="#">Privacy</a><a href="#">Terms</a><a href="#">Help</a></nav></footer>
<script>
(function () {
  var shell = document.body;
  var sidebar = document.querySelector('[data-shell-sidebar]');
  var toggle = document.querySelector('[data-shell-toggle]');
  var key = 'therain.dashboard.sidebar.collapsed';
  if (localStorage.getItem(key) === '1') shell.classList.add('shell-collapsed');
  toggle.addEventListener('click', function () {
    if (window.innerWidth <= 900) {
      shell.classList.toggle('shell-mobile-open');
      return;
    }
    shell.classList.toggle('shell-collapsed');
    localStorage.setItem(key, shell.classList.contains('shell-collapsed') ? '1' : '0');
  });
  sidebar.addEventListener('click', function (event) {
    if (window.innerWidth <= 900 && event.target.closest('a')) shell.classList.remove('shell-mobile-open');
  });
  document.addEventListener('click', function (event) {
    if (window.innerWidth <= 900 && shell.classList.contains('shell-mobile-open') && !sidebar.contains(event.target) && !toggle.contains(event.target)) shell.classList.remove('shell-mobile-open');
  });
}());
</script>
</body>
</html>
        <?php
    }
}
