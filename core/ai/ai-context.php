<?php

if (!function_exists('therain_ai_context')) {
    /**
     * Creates an explicit, tenant-scoped context for a future AI provider.
     * Providers must treat this context as an authorization boundary.
     *
     * @param int $tenantId
     * @param string|null $moduleSlug
     * @param int|null $branchId
     * @param array $options
     * @return array
     */
    function therain_ai_context($tenantId, $moduleSlug = null, $branchId = null, array $options = array())
    {
        return array(
            'tenant_id' => (int) $tenantId,
            'module_slug' => $moduleSlug,
            'branch_id' => $branchId === null ? null : (int) $branchId,
            'options' => $options,
        );
    }
}
