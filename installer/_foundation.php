<?php

/**
 * Renders the intentionally non-operational Phase 1 installer foundation.
 *
 * It must not be mistaken for an installer: it does not write configuration,
 * connect to a database, create an account, select modules, or validate a
 * license.
 *
 * @param string $title
 * @return void
 */
function therain_installer_foundation_page($title)
{
    http_response_code(501);
    header('Content-Type: text/html; charset=UTF-8');

    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . $safeTitle . ' | TheRain Unified</title>';
    echo '<style>body{font-family:Arial,sans-serif;max-width:760px;margin:4rem auto;padding:0 1.25rem;line-height:1.55;color:#24313d}h1{color:#17a2b8}.notice{background:#fff3cd;border:1px solid #ffeeba;border-radius:6px;padding:1rem}</style>';
    echo '</head><body><h1>' . $safeTitle . '</h1>';
    echo '<p class="notice">Installer foundation only. This step is intentionally not operational in Phase 1.</p>';
    echo '<p>No configuration, database, account, module, or license data was changed.</p>';
    echo '</body></html>';
}
