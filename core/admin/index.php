<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/connection.php';
require_once dirname(__DIR__) . '/auth/session-service.php';
require_once dirname(__DIR__) . '/auth/auth-service.php';
require_once dirname(__DIR__) . '/auth/csrf.php';
require_once dirname(__DIR__) . '/auth/registration-service.php';
require_once dirname(__DIR__) . '/users/user-service.php';
require_once dirname(__DIR__) . '/permissions/permission-service.php';
require_once dirname(__DIR__) . '/dashboard/dashboard-shell.php';
require_once dirname(__DIR__) . '/navigation/navigation-service.php';
require_once dirname(__DIR__, 2) . '/modules/module-registry.php';
require_once dirname(__DIR__, 2) . '/modules/module-context.php';

therain_session_start_secure();
$user = therain_require_login('../../auth/login.php');
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
$actionMessage = null;
$actionError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && therain_csrf_verify($_POST['csrf_token'] ?? null)) {
    $targetId = (int) ($_POST['user_id'] ?? 0);
    $userAction = $_POST['user_action'] ?? '';
    if ($userAction === 'create') {
        $email = trim($_POST['email'] ?? ''); $username = trim($_POST['username'] ?? ''); $password = $_POST['password'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || $username === '') {
            $actionError = 'Valid email, username, and an 8-character password are required.';
        } elseif (therain_find_user_by_email($email, $connection)) {
            $actionError = 'An account with this email already exists.';
        } else {
            $profileImagePath = null;
            if (!empty($_FILES['profile_photo']['name'])) {
                $validation = therain_validate_uploaded_image($_FILES['profile_photo']);
                if (!$validation['valid']) $actionError = $validation['error'];
                else $profileImagePath = therain_store_uploaded_image($_FILES['profile_photo'], $validation['extension'], 'tenants/' . $user['tenant_id'] . '/profiles');
            }
            if (!$actionError) {
                $newUserId = therain_create_user(array('tenant_id' => $user['tenant_id'], 'username' => $username, 'email' => $email, 'password' => $password, 'status' => 'active'), $connection);
                therain_create_user_profile($newUserId, array('first_name' => trim($_POST['first_name'] ?? ''), 'last_name' => trim($_POST['last_name'] ?? ''), 'phone' => trim($_POST['phone'] ?? ''), 'profile_image_path' => $profileImagePath), $connection);
                $actionMessage = 'User created successfully.';
            }
        }
    } elseif ($targetId === $user['id'] && $userAction === 'delete') {
        $actionError = 'You cannot delete your own owner account.';
    } elseif ($targetId > 0 && $userAction === 'delete') {
        $delete = $connection->prepare('DELETE FROM users WHERE id = ? AND tenant_id = ?');
        $delete->bind_param('ii', $targetId, $user['tenant_id']); $delete->execute();
        $actionMessage = $delete->affected_rows ? 'User deleted successfully.' : 'User was not found.';
        $delete->close();
    } elseif ($targetId > 0 && $userAction === 'edit') {
        $firstName = trim($_POST['first_name'] ?? ''); $lastName = trim($_POST['last_name'] ?? ''); $phone = trim($_POST['phone'] ?? '');
        $profileImagePath = null;
        if (!empty($_FILES['profile_photo']['name'])) {
            $validation = therain_validate_uploaded_image($_FILES['profile_photo']);
            if (!$validation['valid']) $actionError = $validation['error'];
            else $profileImagePath = therain_store_uploaded_image($_FILES['profile_photo'], $validation['extension'], 'tenants/' . $user['tenant_id'] . '/profiles');
        }
        if (!$actionError) {
            if ($profileImagePath !== null) {
                $update = $connection->prepare('UPDATE user_profiles SET first_name = ?, last_name = ?, phone = ?, profile_image_path = ?, updated_at = NOW() WHERE user_id = ?');
                $update->bind_param('ssssi', $firstName, $lastName, $phone, $profileImagePath, $targetId);
            } else {
                $update = $connection->prepare('UPDATE user_profiles SET first_name = ?, last_name = ?, phone = ?, updated_at = NOW() WHERE user_id = ?');
                $update->bind_param('sssi', $firstName, $lastName, $phone, $targetId);
            }
            $update->execute(); $actionMessage = 'User profile updated successfully.'; $update->close();
        }
    }
}
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
    $rows .= '<tr><td><strong>' . $escape($name) . '</strong><small>' . $escape($record['email']) . '</small></td><td>' . $escape($record['phone'] ?: '-') . '</td><td>' . $escape($record['roles'] ?: 'Unassigned') . '</td><td><span class="admin-status admin-status-' . $escape($record['status']) . '">' . $escape(ucfirst($record['status'])) . '</span></td><td>' . $escape($record['last_login_at'] ?: 'Never') . '</td><td>' . $escape($record['created_at']) . '</td><td><a class="panel-chip" href="?section=edit-user&user_id=' . (int) $record['id'] . '">Edit</a> <form method="post" style="display:inline" onsubmit="return confirm(\'Delete this user?\');">' . therain_csrf_field() . '<input type="hidden" name="user_action" value="delete"><input type="hidden" name="user_id" value="' . (int) $record['id'] . '"><button class="panel-chip admin-danger-button" type="submit">Delete</button></form></td></tr>';
}
if (!$rows) $rows = '<tr><td colspan="7">No tenant users found.</td></tr>';
$roleRows = '';
foreach ($roles as $role) $roleRows .= '<tr><td>' . $escape($role['name']) . '</td><td>' . $escape($role['slug']) . '</td><td>' . $escape($role['description'] ?: '-') . '</td><td>' . (!empty($role['is_system_role']) ? 'System role' : 'Tenant role') . '</td></tr>';
if (!$roleRows) $roleRows = '<tr><td colspan="4">No roles configured.</td></tr>';

$title = $section === 'roles' ? 'Roles' : ($section === 'permissions' ? 'Permissions' : 'Admin Management');
$content = '<section class="dashboard-hero"><div><p class="dashboard-kicker">Owner controls</p><h1>' . $escape($title) . '</h1><p>Manage users, roles, permissions, access and staff accounts for this tenant.</p></div><a class="auth-button dashboard-module-link" href="?section=add-admin"><i class="fas fa-user-plus"></i> Add New Admin</a></section>' . ($actionMessage ? '<div class="card-feedback card-feedback-success">' . $escape($actionMessage) . '</div>' : '') . ($actionError ? '<div class="card-feedback card-feedback-error">' . $escape($actionError) . '</div>' : '');
if ($section === 'roles') {
    $content .= '<section class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-user-tag"></i><div><h2>Roles</h2><small>Tenant-scoped role assignments</small></div></div></div><div class="table-scroll"><table class="dashboard-table"><thead><tr><th>Name</th><th>Slug</th><th>Description</th><th>Scope</th></tr></thead><tbody>' . $roleRows . '</tbody></table></div></section>';
} elseif ($section === 'add-admin') {
    $content .= '<section class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-user-plus"></i><div><h2>Add New Admin</h2><small>Secure tenant user creation</small></div></div></div><form method="post" enctype="multipart/form-data" class="identity-card-form">' . therain_csrf_field() . '<input type="hidden" name="user_action" value="create"><label>Profile photo<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"></label><label>First name<input name="first_name" required></label><label>Last name<input name="last_name"></label><label>Phone<input name="phone"></label><label>Email<input name="email" type="email" required></label><label>Username<input name="username" required></label><label>Temporary password<input name="password" type="password" required minlength="8"></label><button class="auth-button" type="submit">Create user</button></form></section>';
} elseif ($section === 'permissions') {
    $permissions = $connection->query('SELECT name, slug, description FROM permissions ORDER BY name LIMIT 100')->fetch_all(MYSQLI_ASSOC);
    $permissionRows = '';
    foreach ($permissions as $permission) $permissionRows .= '<tr><td>' . $escape($permission['name']) . '</td><td>' . $escape($permission['slug']) . '</td><td>' . $escape($permission['description'] ?: '-') . '</td></tr>';
    $content .= '<section class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-key"></i><div><h2>Permission catalog</h2><small>Backend-enforced permissions available to roles</small></div></div></div><div class="table-scroll"><table class="dashboard-table"><thead><tr><th>Name</th><th>Slug</th><th>Description</th></tr></thead><tbody>' . ($permissionRows ?: '<tr><td colspan="3">No permissions configured.</td></tr>') . '</tbody></table></div></section>';
} else {
    $editUser = (int) ($_GET['user_id'] ?? 0); $editRecord = null; foreach ($users as $record) if ((int) $record['id'] === $editUser) $editRecord = $record;
    if ($section === 'edit-user' && $editRecord) $content .= '<section class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-user-edit"></i><div><h2>Edit user profile</h2><small>' . $escape($editRecord['email']) . '</small></div></div></div><form method="post" enctype="multipart/form-data" class="identity-card-form"><input type="hidden" name="user_action" value="edit"><input type="hidden" name="user_id" value="' . (int) $editRecord['id'] . '">' . therain_csrf_field() . '<label>Profile photo<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"></label><label>First name<input name="first_name" value="' . $escape($editRecord['first_name']) . '" required></label><label>Last name<input name="last_name" value="' . $escape($editRecord['last_name']) . '"></label><label>Phone<input name="phone" value="' . $escape($editRecord['phone']) . '"></label><button class="auth-button" type="submit">Save profile</button></form></section>';
    $content .= '<section class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-users-cog"></i><div><h2>Tenant users</h2><small>Only Young Tech records are shown here</small></div></div><span class="panel-chip">' . count($users) . ' users</span></div><div class="table-scroll"><table class="dashboard-table admin-user-table"><thead><tr><th>Profile</th><th>Phone</th><th>Role</th><th>Status</th><th>Last login</th><th>Created</th><th>Actions</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section>';
}

$moduleStatement = $connection->prepare('SELECT module_slug FROM tenant_modules WHERE tenant_id = ? AND status = "enabled" LIMIT 1');
$moduleStatement->bind_param('i', $user['tenant_id']); $moduleStatement->execute(); $moduleRow = $moduleStatement->get_result()->fetch_assoc(); $moduleStatement->close();
$moduleSlug = $moduleRow['module_slug'] ?? 'pharmacy';
$context = new TheRainModuleContext($user['tenant_id'], $user['id'], null, $connection);
$navigation = therain_navigation_for_user($moduleSlug, $context, $user['id'], $user['tenant_id'], $connection);
$GLOBALS['therain_dashboard_user'] = $user; $GLOBALS['therain_dashboard_base'] = '../../';
therain_dashboard_render(therain_dashboard_identity($user, $connection), $navigation, therain_notification_unread_count($user['id'], $user['tenant_id'], $connection), $content, 'Pharmacy Management');
