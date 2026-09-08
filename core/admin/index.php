<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/connection.php';
require_once dirname(__DIR__) . '/auth/session-service.php';
require_once dirname(__DIR__) . '/auth/auth-service.php';
require_once dirname(__DIR__) . '/permissions/permission-service.php';
require_once dirname(__DIR__) . '/dashboard/dashboard-shell.php';
require_once dirname(__DIR__) . '/navigation/navigation-service.php';
require_once dirname(__DIR__, 2) . '/modules/module-registry.php';
require_once dirname(__DIR__, 2) . '/modules/module-context.php';

therain_session_start_secure();
$user = therain_require_login('../login.php');
$connection = therain_db();
$isOwner = false;
foreach (therain_user_roles_for_tenant($user['id'], $user['tenant_id'], $connection) as $role) {
    if (!empty($role['is_system_role']) && $role['slug'] === THERAIN_SUPER_ADMIN_ROLE_SLUG) $isOwner = true;
}
if (!$isOwner) {
    http_response_code(403);
    exit('Forbidden');
}

$section = isset($_GET['section']) ? trim($_GET['section']) : 'users';
$statement = $connection->prepare(
    'SELECT users.id, users.email, users.username, users.status, users.last_login_at, users.created_at,
            user_profiles.first_name, user_profiles.last_name, user_profiles.phone,
            GROUP_CONCAT(DISTINCT roles.name ORDER BY roles.name SEPARATOR ", ") AS roles
     FROM users LEFT JOIN user_profiles ON user_profiles.user_id = users.id
     LEFT JOIN user_roles ON user_roles.user_id = users.id AND user_roles.tenant_id = users.tenant_id
     LEFT JOIN roles ON roles.id = user_roles.role_id
     WHERE users.tenant_id = ? GROUP BY users.id ORDER BY users.created_at DESC'
);
$statement->bind_param('i', $user['tenant_id']);
$statement->execute();
$users = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
$statement->close();

$roleStatement = $connection->prepare('SELECT name, slug, description, is_system_role FROM roles WHERE tenant_id = ? OR tenant_id IS NULL ORDER BY name');
$roleStatement->bind_param('i', $user['tenant_id']);
$roleStatement->execute();
$roles = $roleStatement->get_result()->fetch_all(MYSQLI_ASSOC);
$roleStatement->close();

$escape = 'therain_dashboard_escape';
$rows = '';
foreach ($users as $record) {
    $name = trim(($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? '')) ?: ($record['username'] ?: $record['email']);
    $rows .= '<tr><td><strong>' . $escape($name) . '</strong><small>' . $escape($record['email']) . '</small></td><td>' . $escape($record['phone'] ?: '-') . '</td><td>' . $escape($record['roles'] ?: 'Unassigned') . '</td><td><span class="admin-status admin-status-' . $escape($record['status']) . '">' . $escape(ucfirst($record['status'])) . '</span></td><td>' . $escape($record['last_login_at'] ?: 'Never') . '</td><td>' . $escape($record['created_at']) . '</td></tr>';
}
if (!$rows) $rows = '<tr><td colspan="6">No tenant users found.</td></tr>';
$roleRows = '';
foreach ($roles as $role) $roleRows .= '<tr><td>' . $escape($role['name']) . '</td><td>' . $escape($role['slug']) . '</td><td>' . $escape($role['description'] ?: '-') . '</td><td>' . (!empty($role['is_system_role']) ? 'System role' : 'Tenant role') . '</td></tr>';
if (!$roleRows) $roleRows = '<tr><td colspan="4">No roles configured.</td></tr>';

$title = $section === 'roles' ? 'Roles' : ($section === 'permissions' ? 'Permissions' : 'Admin Management');
$content = '<section class="dashboard-hero"><div><p class="dashboard-kicker">Owner controls</p><h1>' . $escape($title) . '</h1><p>Manage users, roles, permissions, access and staff accounts for this tenant.</p></div><a class="auth-button dashboard-module-link" href="?section=add-admin"><i class="fas fa-user-plus"></i> Add New Admin</a></section>';
if ($section === 'roles') {
    $content .= '<section class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-user-tag"></i><div><h2>Roles</h2><small>Tenant-scoped role assignments</small></div></div></div><div class="table-scroll"><table class="dashboard-table"><thead><tr><th>Name</th><th>Slug</th><th>Description</th><th>Scope</th></tr></thead><tbody>' . $roleRows . '</tbody></table></div></section>';
} elseif ($section === 'add-admin') {
    $content .= '<section class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-user-plus"></i><div><h2>Add New Admin</h2><small>Use the existing user service to create a tenant account</small></div></div></div><div class="overview-empty"><i class="fas fa-user-shield"></i><strong>User creation is available through the governed registration service.</strong><p>Choose a role and use the secure registration flow so passwords are hashed and tenant membership is recorded correctly.</p><a class="auth-button dashboard-module-link" href="../../register.php"><i class="fas fa-arrow-right"></i> Open secure registration</a></div></section>';
} elseif ($section === 'permissions') {
    $permissions = $connection->query('SELECT name, slug, description FROM permissions ORDER BY name LIMIT 100')->fetch_all(MYSQLI_ASSOC);
    $permissionRows = '';
    foreach ($permissions as $permission) $permissionRows .= '<tr><td>' . $escape($permission['name']) . '</td><td>' . $escape($permission['slug']) . '</td><td>' . $escape($permission['description'] ?: '-') . '</td></tr>';
    $content .= '<section class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-key"></i><div><h2>Permission catalog</h2><small>Backend-enforced permissions available to roles</small></div></div></div><div class="table-scroll"><table class="dashboard-table"><thead><tr><th>Name</th><th>Slug</th><th>Description</th></tr></thead><tbody>' . ($permissionRows ?: '<tr><td colspan="3">No permissions configured.</td></tr>') . '</tbody></table></div></section>';
} else {
    $content .= '<section class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-users-cog"></i><div><h2>Tenant users</h2><small>Only Young Tech records are shown here</small></div></div><span class="panel-chip">' . count($users) . ' users</span></div><div class="table-scroll"><table class="dashboard-table admin-user-table"><thead><tr><th>Profile</th><th>Phone</th><th>Role</th><th>Status</th><th>Last login</th><th>Created</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section>';
}

$moduleStatement = $connection->prepare('SELECT module_slug FROM tenant_modules WHERE tenant_id = ? AND status = "enabled" LIMIT 1');
$moduleStatement->bind_param('i', $user['tenant_id']); $moduleStatement->execute(); $moduleRow = $moduleStatement->get_result()->fetch_assoc(); $moduleStatement->close();
$moduleSlug = $moduleRow['module_slug'] ?? 'pharmacy';
$context = new TheRainModuleContext($user['tenant_id'], $user['id'], null, $connection);
$navigation = therain_navigation_for_user($moduleSlug, $context, $user['id'], $user['tenant_id'], $connection);
$GLOBALS['therain_dashboard_user'] = $user; $GLOBALS['therain_dashboard_base'] = '../../';
therain_dashboard_render(therain_dashboard_identity($user, $connection), $navigation, therain_notification_unread_count($user['id'], $user['tenant_id'], $connection), $content, 'Pharmacy Management');
