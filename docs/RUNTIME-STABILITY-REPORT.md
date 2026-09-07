# Runtime Stability Report

## Status

**IMPLEMENTED + PARTIALLY TESTED.** Test tracing and grouped execution are available through `tests/run.php --group=<name>`. Trace records are written to the system temporary directory with timestamp, process id, memory usage, and the last test function.

## Findings

The Windows XAMPP PHP 8.0.28 environment still intermittently terminates the PHP process with exit code `259` and no PHP-level output. The termination is not deterministic and has occurred at different points, including outside application assertions. MariaDB remains reachable after affected runs. The root cause is therefore **not established as application code**; antivirus/OS I/O contention remains plausible but unproven.

The migration runner now uses statement-by-statement execution, and grouped runs make the failure boundary observable without retries being treated as a fix. The disposable bootstrap rejects names without `test` and rejects exact `pharmacy`, `production`, `prod`, and `live` names.

## Observed validation sample

- Database group: 19 passed, 0 failed.
- Modules group: 14 passed, 0 failed.
- Auth group: 19 passed, 0 failed.
- Pharmacy group: 23 passed, 0 failed.
- Full-suite runs: intermittent Windows process termination remains; no application assertion failure was established in the observed terminated runs.

A 10-run-per-group sample was not completed in this session, so no stronger stability claim is made.
