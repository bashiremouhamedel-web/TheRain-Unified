<?php

/**
 * Requirements detection — the one installer step Phase 6 makes real.
 *
 * This page only DETECTS environment state (PHP version, extensions,
 * database connectivity, writable storage, presence of a .env file). It
 * never writes configuration, never creates a database or account, and
 * never selects a module or license. Every other installer/*.php step
 * remains the Phase 1 HTTP 501 foundation notice — this file is the
 * intentional, narrow exception described in docs/PHASE-6-REPORT.md.
 */

$rootPath = dirname(__DIR__);

$phpVersionOk = version_compare(PHP_VERSION, '7.4.0', '>=');

$requiredExtensions = array('mysqli', 'fileinfo', 'mbstring', 'openssl', 'json');
$extensionResults = array();
foreach ($requiredExtensions as $extension) {
    $extensionResults[$extension] = extension_loaded($extension);
}

$writablePaths = array(
    'storage/uploads',
    'storage/logs',
    'storage/cache',
    'storage/backups',
    'storage/invoices',
    'storage/receipts',
    'storage/tmp',
);
$writableResults = array();
foreach ($writablePaths as $relativePath) {
    $fullPath = $rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $writableResults[$relativePath] = is_dir($fullPath) && is_writable($fullPath);
}

$envFileExists = is_file($rootPath . DIRECTORY_SEPARATOR . '.env');

$databaseStatus = 'skipped';
$databaseMessage = 'No .env file found — nothing to test yet. Copy .env.example to .env first.';

if ($envFileExists) {
    require_once $rootPath . '/core/config/bootstrap.php';
    require_once $rootPath . '/core/config/connection.php';

    try {
        $connection = therain_db();
        $databaseStatus = 'ok';
        $databaseMessage = 'Connected successfully.';
    } catch (Exception $exception) {
        $databaseStatus = 'fail';
        // Deliberately generic — never echo the driver's own exception
        // message here, since it can include host/credential details.
        $databaseMessage = 'Could not connect with the configured DB_* values. Check host, port, username, password, and that the server is running.';
    }
}

$allExtensionsOk = !in_array(false, $extensionResults, true);
$allWritableOk = !in_array(false, $writableResults, true);
$overallReady = $phpVersionOk && $allExtensionsOk && $allWritableOk && $databaseStatus === 'ok';

function therain_requirement_row($label, $status, $detail = '')
{
    $icon = $status === true || $status === 'ok' ? '✅' : ($status === 'skipped' ? '⏸️' : '❌');
    $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $safeDetail = htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
    echo '<tr><td>' . $icon . '</td><td>' . $safeLabel . '</td><td>' . $safeDetail . '</td></tr>';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installer requirements check | TheRain Unified</title>
<style>
body{font-family:Arial,sans-serif;max-width:760px;margin:4rem auto;padding:0 1.25rem;line-height:1.55;color:#24313d}
h1{color:#17a2b8}
table{width:100%;border-collapse:collapse;margin:1rem 0}
td{padding:.4rem .6rem;border-bottom:1px solid #e3e6e8}
.notice{background:#fff3cd;border:1px solid #ffeeba;border-radius:6px;padding:1rem}
.summary{background:<?php echo $overallReady ? '#d4edda' : '#f8d7da'; ?>;border:1px solid <?php echo $overallReady ? '#c3e6cb' : '#f5c6cb'; ?>;border-radius:6px;padding:1rem;margin-top:1rem}
</style>
</head>
<body>
<h1>Installer requirements check</h1>
<p class="notice">This step only detects environment state. It does not write configuration, create a database or account, or select a module/license — every other installer step is still not operational.</p>

<table>
<?php therain_requirement_row('PHP ' . PHP_VERSION . ' (7.4.0 or newer required)', $phpVersionOk); ?>
<?php foreach ($extensionResults as $extension => $ok) { therain_requirement_row('PHP extension: ' . $extension, $ok); } ?>
<?php foreach ($writableResults as $path => $ok) { therain_requirement_row('Writable: ' . $path, $ok); } ?>
<?php therain_requirement_row('.env file present', $envFileExists); ?>
<?php therain_requirement_row('Database connectivity', $databaseStatus, $databaseMessage); ?>
</table>

<div class="summary">
<?php if ($overallReady) : ?>
  All checks passed. This does not mean installation is available — the remaining installer steps are still Phase 1 foundations.
<?php else : ?>
  One or more checks did not pass. Resolve them before attempting any future installer step.
<?php endif; ?>
</div>
</body>
</html>
