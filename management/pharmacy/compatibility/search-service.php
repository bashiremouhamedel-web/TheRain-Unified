<?php

require_once __DIR__ . '/bridge-service.php';
require_once dirname(__DIR__, 3) . '/core/search/search-service.php';

if (!function_exists('therain_pharmacy_search')) {
    /**
     * Searches Pharmacy entities in separate, tenant-scoped queries. The
     * legacy store id is resolved from the Unified tenant bridge; no query
     * can search another store's rows.
     *
     * @param string $query
     * @param int $tenantId
     * @param int|null $branchId Branches are not represented in legacy Pharmacy yet.
     * @return array
     */
    function therain_pharmacy_search($query, $tenantId, $branchId = null)
    {
        $storeId = therain_pharmacy_store_id_for_tenant((int) $tenantId);
        if ($storeId === null) {
            return array();
        }

        $actorId = therain_pharmacy_current_actor_id();
        if ($actorId === null) {
            return array();
        }

        $connection = therain_pharmacy_connection();
        $term = '%' . $query . '%';
        $results = array();

        $providers = array(
            array('p_medicine', 'id', 'name', 'code', 'product', 'pharmacy.products.view', 'manage-products.php'),
            array('p_customer', 'id', 'name', 'phone', 'customer', 'pharmacy.customers.view', 'manage-customer.php'),
            array('p_supplier', 'id', 'name', 'phone', 'supplier', 'pharmacy.suppliers.view', 'manage-supplier.php'),
            array('p_invoice_summary', 'id', 'invoice', 'client', 'invoice', 'pharmacy.sales.view', 'sales-invoice.php'),
        );

        foreach ($providers as $provider) {
            list($table, $idColumn, $nameColumn, $secondaryColumn, $entityType, $permission, $route) = $provider;
            if ($actorId !== null && !therain_pharmacy_actor_can($storeId, $actorId, $permission)) {
                continue;
            }
            $statement = $connection->prepare(
                "SELECT `$idColumn` AS entity_id, `$nameColumn` AS label, `$secondaryColumn` AS secondary_value
                 FROM `$table`
                 WHERE `store` = ? AND (`$nameColumn` LIKE ? OR `$secondaryColumn` LIKE ?)
                 ORDER BY `$nameColumn` ASC LIMIT 25"
            );
            $statement->bind_param('iss', $storeId, $term, $term);
            $statement->execute();
            $rows = $statement->get_result();

            while ($row = $rows->fetch_assoc()) {
                $results[] = array(
                    'module_slug' => 'pharmacy',
                    'entity_type' => $entityType,
                    'entity_id' => (int) $row['entity_id'],
                    'label' => $row['label'],
                    'secondary' => $row['secondary_value'],
                    'url' => $route,
                    'relevance' => stripos((string) $row['label'], (string) $query) === 0 ? 1.0 : 0.5,
                );
            }
            $statement->close();
        }

        return $results;
    }
}

if (!function_exists('therain_register_pharmacy_search_provider')) {
    function therain_register_pharmacy_search_provider()
    {
        therain_search_register_provider('pharmacy', 'therain_pharmacy_search');
    }
}
