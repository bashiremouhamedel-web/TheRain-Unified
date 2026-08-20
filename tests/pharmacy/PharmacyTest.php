<?php

/**
 * Imports management/pharmacy/database/db.sql into its own disposable,
 * uniquely-named database (never the real `pharmacy` database a
 * developer's machine might already have) and verifies the schema is
 * sound, and reproduces (not just statically claims) the known
 * medicine/p_medicine defect documented in
 * docs/PHARMACY-DATABASE-MIGRATION-PLAN.md.
 */
function therain_test_run_pharmacy_schema()
{
    therain_test_section('Pharmacy: standalone schema and the medicine/p_medicine defect');

    $dbConfig = therain_config('database');
    $pharmacyDatabaseName = $GLOBALS['therain_test_database_name'] . '_pharmacy';

    $adminConnection = new mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], '', (int) $dbConfig['port']);
    $adminConnection->query('DROP DATABASE IF EXISTS `' . $adminConnection->real_escape_string($pharmacyDatabaseName) . '`');
    $adminConnection->query('CREATE DATABASE `' . $adminConnection->real_escape_string($pharmacyDatabaseName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $adminConnection->close();

    $schemaPath = THERAIN_APP_ROOT . '/management/pharmacy/database/db.sql';
    $schemaSql = file_get_contents($schemaPath);
    // Redirect the file's own CREATE DATABASE/USE statements — it
    // hardcodes the real `pharmacy` database name, which this test must
    // never touch.
    $schemaSql = preg_replace('/`pharmacy`/', '`' . $pharmacyDatabaseName . '`', $schemaSql);

    $connection = new mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $pharmacyDatabaseName, (int) $dbConfig['port']);
    $connection->set_charset($dbConfig['charset']);

    $importError = therain_test_multi_query($connection, $schemaSql);
    therain_test_assert('Pharmacy standalone schema imports without error', $importError === null, (string) $importError);

    $tableCount = $connection->query(
        "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = '" . $connection->real_escape_string($pharmacyDatabaseName) . "'"
    )->fetch_assoc()['c'];
    therain_test_assert('Pharmacy schema has 23 tables', (int) $tableCount === 23, "actual=$tableCount");

    $connection->query("INSERT INTO store (name, user_name, pass, email) VALUES ('Test Pharmacy', 'testuser', 'hash', 'test@example.com')");
    $storeId = $connection->insert_id;
    therain_test_assert('a store row can be inserted', $storeId > 0);

    // Reproduce, not just assert, the known defect: add-damage.php and
    // actions/cart.php query an undefined `medicine` table. This
    // connection runs in classic (non-throwing) mysqli mode — see
    // tests/bootstrap.php — so failure is checked via query()'s return
    // value, not a caught exception.
    $medicineQueryResult = $connection->query("SELECT * FROM `medicine` WHERE `store`='$storeId'");
    $medicineError = $medicineQueryResult === false ? $connection->error : null;
    therain_test_assert(
        'querying the undefined `medicine` table fails as documented (ERROR 1146)',
        $medicineError !== null && strpos($medicineError, "doesn't exist") !== false,
        (string) $medicineError
    );

    $pMedicineQueryResult = $connection->query("SELECT * FROM `p_medicine` WHERE `store`='$storeId'");
    therain_test_assert('the equivalent p_medicine query succeeds (control case)', $pMedicineQueryResult !== false, (string) $connection->error);

    // The deeper finding: p_medicine has no manufacturerprice column
    // either, so a mechanical rename would not fully fix the source files.
    $columns = $connection->query("SHOW COLUMNS FROM p_medicine")->fetch_all(MYSQLI_ASSOC);
    $columnNames = array_column($columns, 'Field');
    therain_test_assert(
        'p_medicine has no manufacturerprice column (confirms a mechanical rename is not a full fix)',
        !in_array('manufacturerprice', $columnNames, true),
        json_encode($columnNames)
    );
    therain_test_assert('p_medicine does have a cost column (the likely intended source)', in_array('cost', $columnNames, true));

    $connection->close();
}
