<?php

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../tenants/tenant-service.php';

if (!function_exists('THERAIN_SUPER_ADMIN_ROLE_SLUG')) {
    define('THERAIN_SUPER_ADMIN_ROLE_SLUG', 'super-admin');
}

if (!function_exists('therain_create_role')) {
    /**
     * Creates a role scoped to a tenant (or a global template when
     * $tenantId is null).
     *
     * @param int|null $tenantId
     * @param string $name
     * @param string $slug
     * @param bool $isSystemRole
     * @param mysqli|null $connection
     * @return int Inserted role id.
     */
    function therain_create_role($tenantId, $name, $slug, $isSystemRole = false, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();
        $isSystemRoleValue = $isSystemRole ? 1 : 0;

        $statement = $connection->prepare(
            'INSERT INTO roles (tenant_id, name, slug, is_system_role, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $statement->bind_param('issi', $tenantId, $name, $slug, $isSystemRoleValue);
        $statement->execute();
        $roleId = $connection->insert_id;
        $statement->close();

        return $roleId;
    }
}

if (!function_exists('therain_create_super_admin_role')) {
    /**
     * Creates the tenant-scoped Super Admin role. This role is granted
     * full access by therain_user_has_permission() without requiring
     * every permission to be enumerated in role_permissions.
     *
     * @param int $tenantId
     * @param mysqli|null $connection
     * @return int Inserted role id.
     */
    function therain_create_super_admin_role($tenantId, mysqli $connection = null)
    {
        return therain_create_role($tenantId, 'Super Admin', THERAIN_SUPER_ADMIN_ROLE_SLUG, true, $connection);
    }
}

if (!function_exists('therain_assign_role')) {
    /**
     * Assigns a role to a user within a tenant.
     *
     * @param int $userId
     * @param int $roleId
     * @param int $tenantId
     * @param int|null $assignedBy
     * @param mysqli|null $connection
     * @return void
     */
    function therain_assign_role($userId, $roleId, $tenantId, $assignedBy = null, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare(
            'INSERT INTO user_roles (user_id, role_id, tenant_id, assigned_by, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $statement->bind_param('iiii', $userId, $roleId, $tenantId, $assignedBy);
        $statement->execute();
        $statement->close();
    }
}

if (!function_exists('therain_user_roles_for_tenant')) {
    /**
     * Returns the roles a user holds within a tenant.
     *
     * @param int $userId
     * @param int $tenantId
     * @param mysqli|null $connection
     * @return array
     */
    function therain_user_roles_for_tenant($userId, $tenantId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare(
            'SELECT roles.* FROM roles
             INNER JOIN user_roles ON user_roles.role_id = roles.id
             WHERE user_roles.user_id = ? AND user_roles.tenant_id = ?'
        );
        $statement->bind_param('ii', $userId, $tenantId);
        $statement->execute();
        $result = $statement->get_result();
        $roles = array();

        while ($row = $result->fetch_assoc()) {
            $roles[] = $row;
        }

        $statement->close();

        return $roles;
    }
}

if (!function_exists('therain_user_has_permission')) {
    /**
     * Checks whether a user holds a given permission within a tenant.
     * A Super Admin role grants unconditional full access.
     *
     * @param int $userId
     * @param int $tenantId
     * @param string $permissionSlug
     * @param mysqli|null $connection
     * @return bool
     */
    function therain_user_has_permission($userId, $tenantId, $permissionSlug, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $roles = therain_user_roles_for_tenant($userId, $tenantId, $connection);

        foreach ($roles as $role) {
            if (!empty($role['is_system_role']) && $role['slug'] === THERAIN_SUPER_ADMIN_ROLE_SLUG) {
                return true;
            }
        }

        if (empty($roles)) {
            return false;
        }

        $roleIds = array_column($roles, 'id');
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));

        $statement = $connection->prepare(
            "SELECT COUNT(*) AS total FROM role_permissions
             INNER JOIN permissions ON permissions.id = role_permissions.permission_id
             WHERE permissions.slug = ? AND role_permissions.role_id IN ($placeholders)"
        );

        $types = 's' . str_repeat('i', count($roleIds));
        $parameters = array_merge(array($permissionSlug), $roleIds);
        $statement->bind_param($types, ...$parameters);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();

        return !empty($row['total']);
    }
}
