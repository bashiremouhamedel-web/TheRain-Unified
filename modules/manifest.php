<?php

/*
 * TheRain Unified module manifest.
 *
 * Only modules marked enabled are loadable. Planned management systems are
 * intentionally absent until their implementations and dependencies exist.
 */
return array(
    'pharmacy' => array(
        'name' => 'Pharmacy Management',
        'slug' => 'pharmacy',
        'version' => '1.0.0-legacy',
        'type' => 'management-system',
        'enabled' => true,
        'status' => 'legacy-compatible',
        'path' => 'management/pharmacy',
        'dependencies' => array(),
        'permissions' => array(),
        'routes' => array(),
        'notes' => 'The existing Pharmacy POS remains at legacy root routes during the staged migration.',
    ),
);
