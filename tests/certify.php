<?php

if (PHP_SAPI !== 'cli') {
    exit("Certification is available only from the command line.\n");
}

$root = dirname(__DIR__);
$groups = array('database', 'modules', 'auth', 'sessions', 'tenants', 'permissions', 'currency', 'payments', 'transactions', 'pharmacy');
$report = array();

foreach ($groups as $group) {
    $startedAt = date('c');
    $outputFile = tempnam(sys_get_temp_dir(), 'therain-certify-');
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'run.php') . ' --group=' . escapeshellarg($group);
    $output = array();
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);
    $outputText = implode(PHP_EOL, $output);
    file_put_contents($outputFile, $outputText);
    preg_match('/TOTAL:\s+(\d+) passed,\s+(\d+) failed/', $outputText, $matches);
    $report[] = array(
        'group' => $group,
        'started_at' => $startedAt,
        'finished_at' => date('c'),
        'exit_code' => $exitCode,
        'passes' => isset($matches[1]) ? (int) $matches[1] : 0,
        'failures' => isset($matches[2]) ? (int) $matches[2] : 0,
        'classification' => $exitCode === 259 ? 'environment termination' : ($exitCode === 0 ? 'passed' : 'application/test failure'),
        'output_file' => $outputFile,
    );
}

echo json_encode($report, JSON_PRETTY_PRINT) . PHP_EOL;
foreach ($report as $result) {
    if ($result['exit_code'] !== 0) {
        exit(1);
    }
}
