<?php

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../users/user-service.php';
require_once __DIR__ . '/../tenants/tenant-service.php';
require_once __DIR__ . '/../permissions/permission-service.php';
require_once __DIR__ . '/../audit/activity-log-service.php';
require_once __DIR__ . '/../payments/payment-method-service.php';
require_once dirname(__DIR__, 2) . '/modules/module-registry.php';

if (!function_exists('therain_validate_registration_input')) {
    /**
     * Validates registration form input.
     *
     * @param array $input
     * @param mysqli $connection
     * @return array List of human-readable error messages. Empty when valid.
     */
    function therain_validate_registration_input(array $input, mysqli $connection)
    {
        $errors = array();
        $passwordMinLength = (int) therain_config('app', 'password_min_length', 8);

        $fullName = isset($input['full_name']) ? trim($input['full_name']) : '';
        $email = isset($input['email']) ? trim($input['email']) : '';
        $phone = isset($input['phone']) ? trim($input['phone']) : '';
        $password = isset($input['password']) ? (string) $input['password'] : '';
        $confirmPassword = isset($input['confirm_password']) ? (string) $input['confirm_password'] : '';
        $businessName = isset($input['business_name']) ? trim($input['business_name']) : '';
        $businessEmail = isset($input['business_email']) ? trim($input['business_email']) : '';
        $businessPhone = isset($input['business_phone']) ? trim($input['business_phone']) : '';
        $managementSystem = isset($input['management_system']) ? trim($input['management_system']) : '';

        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }

        if ($phone === '') {
            $errors[] = 'Phone number is required.';
        }

        if (strlen($password) < $passwordMinLength) {
            $errors[] = 'Password must be at least ' . $passwordMinLength . ' characters long.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain both letters and numbers.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Password and confirmation do not match.';
        }

        if ($businessName === '') {
            $errors[] = 'Business/management name is required.';
        }

        if ($businessEmail !== '' && !filter_var($businessEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Business email address is invalid.';
        }

        if ($businessPhone === '') {
            $errors[] = 'Business phone number is required.';
        }

        if ($managementSystem === '') {
            $errors[] = 'Please select a management system.';
        } elseif (!isset(therain_module_registry()[$managementSystem])) {
            $errors[] = 'The selected management system is not recognized.';
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && therain_find_user_by_email($email, $connection)) {
            $errors[] = 'An account with this email already exists. Please sign in instead.';
        }

        return $errors;
    }
}

if (!function_exists('therain_validate_uploaded_image')) {
    /**
     * Validates an uploaded image using its real content, not its extension
     * or client-supplied MIME type.
     *
     * @param array $file A single entry from $_FILES.
     * @param int $maxBytes
     * @return array array('valid' => bool, 'error' => string|null, 'extension' => string|null)
     */
    function therain_validate_uploaded_image(array $file, $maxBytes = 2097152)
    {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return array('valid' => true, 'error' => null, 'extension' => null, 'skipped' => true);
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array('valid' => false, 'error' => 'Upload failed. Please try again.', 'extension' => null);
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            return array('valid' => false, 'error' => 'Invalid upload.', 'extension' => null);
        }

        if ($file['size'] > $maxBytes) {
            return array('valid' => false, 'error' => 'Image must be smaller than ' . round($maxBytes / 1048576, 1) . ' MB.', 'extension' => null);
        }

        $imageInfo = @getimagesize($file['tmp_name']);

        if ($imageInfo === false) {
            return array('valid' => false, 'error' => 'The uploaded file is not a valid image.', 'extension' => null);
        }

        $allowedTypes = array(
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
        );

        if (!isset($allowedTypes[$imageInfo[2]])) {
            return array('valid' => false, 'error' => 'Only JPG, PNG, or WEBP images are allowed.', 'extension' => null);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimeTypes = array('image/jpeg', 'image/png', 'image/webp');

        if (!in_array($realMimeType, $allowedMimeTypes, true)) {
            return array('valid' => false, 'error' => 'Only JPG, PNG, or WEBP images are allowed.', 'extension' => null);
        }

        return array('valid' => true, 'error' => null, 'extension' => $allowedTypes[$imageInfo[2]]);
    }
}

if (!function_exists('therain_store_uploaded_image')) {
    /**
     * Moves a validated upload into private storage under a random,
     * non-guessable filename.
     *
     * @param array $file
     * @param string $extension
     * @param string $subDirectory Relative to storage/uploads.
     * @return string Relative path from the application root.
     */
    function therain_store_uploaded_image(array $file, $extension, $subDirectory)
    {
        $targetDirectory = therain_path('storage/uploads/' . trim($subDirectory, '/'));

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('Unable to store the uploaded image.');
        }

        return 'storage/uploads/' . trim($subDirectory, '/') . '/' . $filename;
    }
}

if (!function_exists('therain_register_tenant')) {
    /**
     * Registers a new tenant and its owning Super Admin user.
     *
     * Creates, in one transaction: tenant, tenant_settings, tenant_modules,
     * user, user_profile, the tenant-scoped Super Admin role, and the
     * user_roles assignment. This does not provision a legacy Pharmacy
     * `store` row; that bridge is a separate, later, audited migration.
     *
     * @param array $input Raw $_POST-shaped registration fields.
     * @param array $files Raw $_FILES-shaped upload fields (business_logo, profile_picture).
     * @param mysqli|null $connection
     * @return array array('success' => bool, 'errors' => array, 'tenant_id' => int|null, 'user_id' => int|null)
     */
    function therain_register_tenant(array $input, array $files = array(), mysqli $connection = null)
    {
        $connection = $connection ?: therain_db();

        $errors = therain_validate_registration_input($input, $connection);

        $logoValidation = isset($files['business_logo'])
            ? therain_validate_uploaded_image($files['business_logo'])
            : array('valid' => true, 'skipped' => true);

        $profileValidation = isset($files['profile_picture'])
            ? therain_validate_uploaded_image($files['profile_picture'])
            : array('valid' => true, 'skipped' => true);

        if (!$logoValidation['valid']) {
            $errors[] = 'Business logo: ' . $logoValidation['error'];
        }

        if (!$profileValidation['valid']) {
            $errors[] = 'Profile picture: ' . $profileValidation['error'];
        }

        if (!empty($errors)) {
            return array('success' => false, 'errors' => $errors, 'tenant_id' => null, 'user_id' => null);
        }

        $storedFiles = array();

        $connection->begin_transaction();

        try {
            $tenantId = therain_create_tenant(array(
                'business_name' => trim($input['business_name']),
                'email' => trim($input['business_email']) !== '' ? trim($input['business_email']) : trim($input['email']),
                'phone' => trim($input['business_phone']),
                'timezone' => isset($input['timezone']) ? trim($input['timezone']) : null,
                'currency_code' => isset($input['currency']) ? trim($input['currency']) : null,
                'locale' => isset($input['locale']) ? trim($input['locale']) : 'en',
            ), $connection);

            $tenantUuidStatement = $connection->prepare('SELECT uuid FROM tenants WHERE id = ?');
            $tenantUuidStatement->bind_param('i', $tenantId);
            $tenantUuidStatement->execute();
            $tenantUuid = $tenantUuidStatement->get_result()->fetch_assoc()['uuid'];
            $tenantUuidStatement->close();

            $logoPath = null;
            $profileImagePath = null;

            if (empty($logoValidation['skipped'])) {
                $logoPath = therain_store_uploaded_image($files['business_logo'], $logoValidation['extension'], 'tenants/' . $tenantUuid);
                $storedFiles[] = $logoPath;
            }

            if (empty($profileValidation['skipped'])) {
                $profileImagePath = therain_store_uploaded_image($files['profile_picture'], $profileValidation['extension'], 'tenants/' . $tenantUuid);
                $storedFiles[] = $profileImagePath;
            }

            $emailLocalPart = strstr(trim($input['email']), '@', true) ?: trim($input['email']);
            $username = therain_generate_unique_username($emailLocalPart, $connection);

            $userId = therain_create_user(array(
                'tenant_id' => $tenantId,
                'username' => $username,
                'email' => trim($input['email']),
                'password' => $input['password'],
                'status' => 'active',
            ), $connection);

            $nameParts = preg_split('/\s+/', trim($input['full_name']), 2);

            therain_create_user_profile($userId, array(
                'first_name' => $nameParts[0],
                'last_name' => isset($nameParts[1]) ? $nameParts[1] : null,
                'phone' => trim($input['phone']),
                'profile_image_path' => $profileImagePath,
            ), $connection);

            $roleId = therain_create_super_admin_role($tenantId, $connection);
            therain_assign_role($userId, $roleId, $tenantId, $userId, $connection);
            therain_set_tenant_owner($tenantId, $userId, $connection);

            $currencyCode = !empty($input['currency']) ? trim($input['currency']) : 'XAF';
            therain_apply_tenant_financial_defaults($tenantId, $currencyCode, $connection);

            $settings = array();

            if (!empty($input['business_type'])) {
                $settings['business_type'] = trim($input['business_type']);
            }
            if (!empty($input['business_description'])) {
                $settings['business_description'] = trim($input['business_description']);
            }
            if (!empty($input['address'])) {
                $settings['address'] = trim($input['address']);
            }
            if (!empty($input['country'])) {
                $settings['country'] = trim($input['country']);
            }
            if (!empty($input['city'])) {
                $settings['city'] = trim($input['city']);
            }
            if ($logoPath !== null) {
                $settings['business_logo_path'] = $logoPath;
            }

            if (!empty($settings)) {
                therain_create_tenant_settings($tenantId, $settings, $connection);
            }

            therain_select_tenant_modules($tenantId, array($input['management_system']), $connection);

            therain_log_activity($tenantId, $userId, 'tenant.registered', array(
                'business_name' => trim($input['business_name']),
                'management_system' => $input['management_system'],
            ), $connection);

            $connection->commit();

            return array('success' => true, 'errors' => array(), 'tenant_id' => $tenantId, 'user_id' => $userId);
        } catch (Exception $exception) {
            $connection->rollback();

            foreach ($storedFiles as $storedFile) {
                $absolutePath = therain_path($storedFile);
                if (is_file($absolutePath)) {
                    unlink($absolutePath);
                }
            }

            return array(
                'success' => false,
                'errors' => array('Registration failed. Please try again.'),
                'tenant_id' => null,
                'user_id' => null,
            );
        }
    }
}
