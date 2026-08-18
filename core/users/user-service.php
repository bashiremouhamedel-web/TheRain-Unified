<?php

require_once __DIR__ . '/../config/connection.php';

if (!function_exists('therain_slugify')) {
    /**
     * Converts a string into a URL-safe, lowercase slug.
     *
     * @param string $value
     * @param string $fallback
     * @return string
     */
    function therain_slugify($value, $fallback = 'x')
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug === '' ? $fallback : $slug;
    }
}

if (!function_exists('therain_generate_uuid')) {
    /**
     * Generates a random UUID v4.
     *
     * @return string
     */
    function therain_generate_uuid()
    {
        $data = random_bytes(16);

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('therain_find_user_by_email')) {
    /**
     * Finds a user by email address.
     *
     * @param string $email
     * @param mysqli|null $connection
     * @return array|null
     */
    function therain_find_user_by_email($email, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $statement->bind_param('s', $email);
        $statement->execute();
        $result = $statement->get_result();
        $user = $result->fetch_assoc();
        $statement->close();

        return $user ?: null;
    }
}

if (!function_exists('therain_generate_unique_username')) {
    /**
     * Generates a unique username from a base value (e.g. an email's local
     * part), appending a numeric suffix on collision.
     *
     * @param string $base
     * @param mysqli|null $connection
     * @return string
     */
    function therain_generate_unique_username($base, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $base = therain_slugify($base);
        $username = $base;
        $suffix = 1;

        $statement = $connection->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');

        do {
            $statement->bind_param('s', $username);
            $statement->execute();
            $exists = $statement->get_result()->fetch_assoc() !== null;

            if ($exists) {
                $suffix++;
                $username = $base . '-' . $suffix;
            }
        } while ($exists);

        $statement->close();

        return $username;
    }
}

if (!function_exists('therain_create_user')) {
    /**
     * Creates a new user with a securely hashed password.
     *
     * @param array $data Expects tenant_id, username, email, password, status.
     * @param mysqli|null $connection
     * @return int Inserted user id.
     */
    function therain_create_user(array $data, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $uuid = therain_generate_uuid();
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $status = isset($data['status']) ? $data['status'] : 'active';
        $tenantId = isset($data['tenant_id']) ? (int) $data['tenant_id'] : null;

        $statement = $connection->prepare(
            'INSERT INTO users (tenant_id, uuid, username, email, password_hash, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $statement->bind_param(
            'isssss',
            $tenantId,
            $uuid,
            $data['username'],
            $data['email'],
            $passwordHash,
            $status
        );
        $statement->execute();
        $userId = $connection->insert_id;
        $statement->close();

        return $userId;
    }
}

if (!function_exists('therain_create_user_profile')) {
    /**
     * Creates the profile record for a user.
     *
     * @param int $userId
     * @param array $data Expects first_name, last_name, phone, profile_image_path.
     * @param mysqli|null $connection
     * @return void
     */
    function therain_create_user_profile($userId, array $data, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $firstName = isset($data['first_name']) ? $data['first_name'] : null;
        $lastName = isset($data['last_name']) ? $data['last_name'] : null;
        $phone = isset($data['phone']) ? $data['phone'] : null;
        $profileImagePath = isset($data['profile_image_path']) ? $data['profile_image_path'] : null;

        $statement = $connection->prepare(
            'INSERT INTO user_profiles (user_id, first_name, last_name, phone, profile_image_path, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $statement->bind_param('issss', $userId, $firstName, $lastName, $phone, $profileImagePath);
        $statement->execute();
        $statement->close();
    }
}

if (!function_exists('therain_update_last_login')) {
    /**
     * Records the last successful login time for a user.
     *
     * @param int $userId
     * @param mysqli|null $connection
     * @return void
     */
    function therain_update_last_login($userId, mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $statement = $connection->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $statement->bind_param('i', $userId);
        $statement->execute();
        $statement->close();
    }
}
