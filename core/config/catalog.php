<?php

/**
 * Static UI catalogs mirroring the seeded rows in
 * database/migrations/0002_identity_foundation.sql. Kept static (rather
 * than querying the database on every form render) so the registration
 * page can render even before a database connection is configured.
 */

if (!function_exists('therain_currency_options')) {
    /**
     * @return array code => "Name (Symbol)"
     */
    function therain_currency_options()
    {
        return array(
            'XAF' => 'Central African CFA Franc (FCFA)',
            'XOF' => 'West African CFA Franc (CFA)',
            'NGN' => 'Nigerian Naira (NGN)',
            'GHS' => 'Ghanaian Cedi (GHS)',
            'KES' => 'Kenyan Shilling (KSh)',
            'ZAR' => 'South African Rand (R)',
            'EGP' => 'Egyptian Pound (E£)',
            'USD' => 'US Dollar ($)',
            'EUR' => 'Euro (€)',
        );
    }
}

if (!function_exists('therain_language_options')) {
    /**
     * @return array code => array('name' => string, 'active' => bool)
     */
    function therain_language_options()
    {
        return array(
            'en' => array('name' => 'English', 'active' => true),
            'fr' => array('name' => 'Français', 'active' => true),
            'ar' => array('name' => 'Arabic', 'active' => false),
            'pt' => array('name' => 'Portuguese', 'active' => false),
            'sw' => array('name' => 'Swahili', 'active' => false),
            'ha' => array('name' => 'Hausa', 'active' => false),
            'es' => array('name' => 'Spanish', 'active' => false),
            'zh' => array('name' => 'Chinese (Simplified)', 'active' => false),
        );
    }
}

if (!function_exists('therain_timezone_options')) {
    /**
     * @return array
     */
    function therain_timezone_options()
    {
        return array(
            'Africa/Douala',
            'Africa/Lagos',
            'Africa/Accra',
            'Africa/Abidjan',
            'Africa/Nairobi',
            'Africa/Johannesburg',
            'Africa/Cairo',
            'Africa/Casablanca',
            'UTC',
        );
    }
}
