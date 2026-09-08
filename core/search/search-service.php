<?php

if (!function_exists('therain_search_provider_registry')) {
    /** @return array<string, callable> */
    function &therain_search_provider_registry()
    {
        static $providers = array();
        return $providers;
    }
}

if (!function_exists('therain_search_register_provider')) {
    /**
     * Registers a module/entity provider. The callable receives
     * ($query, $tenantId, $branchId) and must enforce its own entity access.
     */
    function therain_search_register_provider($name, callable $provider)
    {
        $providers = &therain_search_provider_registry();
        $providers[$name] = $provider;
    }
}

if (!function_exists('therain_search')) {
    /**
     * Aggregates bounded provider results without constructing a cross-module
     * SQL query. Providers own their tables and authorization checks.
     *
     * @return array
     */
    function therain_search($query, $tenantId, $branchId = null, $limit = 25)
    {
        $providers = therain_search_provider_registry();
        $limit = max(1, min(100, (int) $limit));
        $results = array();

        // Identity cards belong to the Unified tenant database rather than a
        // legacy module, so they must remain searchable even when Pharmacy
        // has no matching product/customer records.
        $connection = function_exists('therain_db') ? therain_db() : null;
        if ($connection instanceof mysqli && trim((string) $query) !== '') {
            $term = '%' . trim((string) $query) . '%';
            $statement = $connection->prepare(
                'SELECT id, card_number, holder_name, role_label
                 FROM identity_cards
                 WHERE tenant_id = ? AND (card_number LIKE ? OR holder_name LIKE ? OR role_label LIKE ?)
                 ORDER BY created_at DESC LIMIT 25'
            );
            $statement->bind_param('isss', $tenantId, $term, $term, $term);
            $statement->execute();
            foreach ($statement->get_result()->fetch_all(MYSQLI_ASSOC) as $card) {
                $results[] = array(
                    'module_slug' => 'unified',
                    'entity_type' => 'identity_card',
                    'entity_id' => (int) $card['id'],
                    'label' => $card['holder_name'],
                    'secondary' => $card['card_number'] . ' / ' . ($card['role_label'] ?: 'Identity card'),
                    'url' => 'core/cards/index.php',
                    'relevance' => stripos((string) $card['card_number'], (string) $query) === 0 ? 1.0 : 0.7,
                );
                if (count($results) >= $limit) {
                    $statement->close();
                    return $results;
                }
            }
            $statement->close();
        }

        foreach ($providers as $provider) {
            foreach ((array) call_user_func($provider, (string) $query, (int) $tenantId, $branchId) as $result) {
                $results[] = $result;
                if (count($results) >= $limit) {
                    return $results;
                }
            }
        }

        return $results;
    }
}
