<?php

if (!function_exists('therain_validate_module_manifest')) {
    /**
     * Validates the contract fields needed for unified and standalone
     * packaging without requiring planned module schemas to exist.
     *
     * @param array $manifest
     * @return string[]
     */
    function therain_validate_module_manifest(array $manifest)
    {
        $errors = array();
        $requiredFields = array('name', 'slug', 'type', 'path', 'dependencies', 'permissions', 'routes', 'licensing');
        if (!empty($manifest['enabled'])) {
            $requiredFields = array_merge($requiredFields, array('description', 'required_core_services', 'capabilities', 'dashboard_entry', 'installation_requirements'));
        }

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $manifest)) {
                $errors[] = 'Missing module manifest field: ' . $field;
            }
        }

        foreach (array('enabled', 'standalone_ready', 'unified_ready') as $booleanField) {
            if (array_key_exists($booleanField, $manifest) && !is_bool($manifest[$booleanField])) {
                $errors[] = $booleanField . ' must be boolean.';
            }
        }

        if (isset($manifest['database']) && !is_string($manifest['database'])) {
            $errors[] = 'database must be a relative path string or null.';
        }
        if (!isset($manifest['database']) && (!empty($manifest['standalone_ready']) || !empty($manifest['unified_ready']))) {
            $errors[] = 'database is required for a ready module.';
        }

        return $errors;
    }
}

if (!function_exists('therain_validate_module_registry')) {
    /** @return array<string, string[]> */
    function therain_validate_module_registry(array $registry)
    {
        $errors = array();
        foreach ($registry as $slug => $manifest) {
            foreach (therain_validate_module_manifest($manifest) as $error) {
                $errors[] = $slug . ': ' . $error;
            }
            if (isset($manifest['slug']) && $manifest['slug'] !== $slug) {
                $errors[] = $slug . ': slug does not match registry key.';
            }
        }
        return $errors;
    }
}
