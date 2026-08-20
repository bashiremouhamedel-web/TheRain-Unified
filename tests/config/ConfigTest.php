<?php

function therain_test_run_config()
{
    therain_test_section('Config: bootstrap and connection');

    $db = therain_test_db();
    therain_test_assert('test database connection is open', $db instanceof mysqli && $db->ping());
    therain_test_assert(
        'connected to the safety-checked test database, not a real one',
        strpos($GLOBALS['therain_test_database_name'], 'test') !== false
    );
    therain_test_assert('APP config section loads', is_array(therain_config('app')));
    therain_test_assert('password_min_length has a sane default', (int) therain_config('app', 'password_min_length', 0) >= 8);
}
