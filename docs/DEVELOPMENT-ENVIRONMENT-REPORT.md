# Development environment report (Phase 8A)

## Goal

Phase 7 documented a real, disclosed environment instability: `php.exe`
processes (both the project's own test suite and a plain, zero-custom-code
`php -S`) sometimes exit with no output and no PHP-level error, and
recommended investigating it first in Phase 8 before trusting any further
HTTP or test-suite results. This report is that investigation, run
independently rather than trusting Phase 7's diagnosis at face value, per
the Phase 8 brief's "do not trust the previous report blindly" instruction.

## Operating environment

- OS: Windows 11 Pro 10.0.26200
- PHP: 8.0.28 (cli, ZTS, Visual C++ 2019 x64), via XAMPP at `C:\xampp\php\php.exe`
  — **not** on the shell `PATH` in either Git Bash or PowerShell; every
  command in this and later reports invokes the full path explicitly.
- MariaDB: 10.4.28, via XAMPP at `C:\xampp\mysql\bin\mysqld.exe`
- Apache: 2.4.56 (Win64), via XAMPP at `C:\xampp\apache\bin\httpd.exe` —
  present and runnable (`httpd -v` succeeds); not used for testing this
  phase (see "Apache" below).
- Antivirus: Windows Defender (Microsoft Defender Antivirus), real-time
  protection **enabled**, tamper protection **enabled**. No third-party AV
  product registered.
- Neither MariaDB nor Apache runs as a Windows service on this machine —
  both are foreground processes started manually per session, confirmed by
  `Get-Service` returning no `mysql`/`apache`-named service and by
  `Get-Process` showing no `mysqld`/`httpd` process before this phase
  started one.

This is confirmed to be the same machine Phase 6/7 ran on (identical PHP
and MariaDB versions, same XAMPP paths, same real `pharmacy` database
already present).

## Tests performed (A–I from the Phase 8 brief)

| # | Test | Result |
|---|---|---|
| A | `php -v` | PASSED — 8.0.28 reported correctly |
| B | `php -m` | PASSED — mysqli, mysqlnd, pdo_mysql, session, openssl, curl, json, mbstring all present |
| C | `php -S localhost:<port>` | PASSED most runs; **intermittently exits with no output** — see below |
| D | independent hello-world page (no project code, plain echo + session_start + mysqli_connect) | PASSED for GET, POST, and 40 rapid sequential GETs in the run that was sampled; the built-in server did **not** crash during that specific sample |
| E | Apache/XAMPP | Not exercised as an HTTP target this phase — the built-in server was sufficient to reach and exceed Phase 7's blocker, and the brief's later steps (8B) target `php -S`-style testing; noted as available and version-verified in case Phase 9 needs it |
| F | plain mysqli connection | PASSED — `mysqli_connect()` from both CLI and from a served page succeeded every time it was attempted |
| G | plain PHP session | PASSED — `session_start()` / `$_SESSION` persistence across requests via cookie worked correctly, including through the full real auth flow (8B) |
| H | simple HTTP GET | PASSED |
| I | simple HTTP POST | PASSED, including `multipart/form-data` (registration file-upload fields) |

## The core finding: the instability is real, confirmed independently, and remains intermittent

This phase did **not** just re-read Phase 7's conclusion — it reproduced
the failure mode directly, on fresh runs, days after Phase 7:

1. First sample: 5 consecutive full runs of `tests/run.php` (109
   assertions, including the large `dbumi.sql` multi-statement import)
   all completed cleanly — **0 crashes in 5 runs**.
2. A later sample, run shortly after completing a full real-HTTP test
   session (8B) against the same PHP binary: 4 out of 5 runs of the same
   `tests/run.php` **crashed** with the exact signature Phase 7 described
   — process exits, zero stdout/stderr output, `$LASTEXITCODE` reporting
   `259` (Windows' `STILL_ACTIVE` code, itself unusual — consistent with
   the process being torn down abruptly rather than exiting normally), no
   PHP fatal error or warning logged anywhere.
3. The crashes did not happen at a fixed point — one run died with zero
   output at all, two others died mid-way through the Pharmacy schema
   test section, one died near the very end of the dbumi-consistency
   section. This rules out a specific line of project code as the trigger
   (a real bug would fail at the same place every time); it is consistent
   with an external process interruption that can land anywhere.
4. `mysqld` was confirmed alive and healthy throughout every crash
   (`SHOW STATUS LIKE 'Threads_connected'` and `SHOW PROCESSLIST` both
   showed a normal, single idle connection immediately after a crashed
   run, with no orphaned connections) — the database server itself is
   never the thing that dies.

**Conclusion: the instability is confirmed to still exist on this
machine, is specific to the `php.exe` process being torn down, is not
caused by MariaDB, and is not deterministic (identical code, identical
database, different outcomes run to run).** This matches, and does not
contradict, Phase 7's own conclusion.

## What was newly investigated this phase: the antivirus hypothesis

Phase 7 named "antivirus/security software interference with rapid child
processes and rapid network connections" as its leading hypothesis but
did not check any AV-side evidence for it. This phase did:

- `Get-MpComputerStatus`: confirmed Windows Defender real-time protection
  is enabled and healthy (`AntivirusEnabled=True`,
  `RealTimeProtectionEnabled=True`, `AMServiceEnabled=True`,
  `IsTamperProtected=True`).
- Attempted to read Defender's configured exclusion paths/processes
  (`Get-MpPreference`) — **refused**: "Must be an administrator to view
  exclusions." This session does not have administrator rights, and did
  not attempt to elevate or change any system-wide AV configuration
  (out of scope for a project-level change, and elevation was not
  authorized).
- Searched the `Microsoft-Windows-Windows Defender/Operational` event log
  for any entry mentioning `php` around the crash times, and the
  `Application` log for any crash/error-reporting entry for `php.exe`.
  **Found none.** Defender's own log for the relevant window contains
  only routine hourly health-report entries (IDs 1150/1151) and one
  unrelated configuration-change notice — **no detection, quarantine, or
  block event for `php.exe` at any point during this session.**

**This is new information Phase 7 did not have, and it complicates the
antivirus theory rather than confirming it.** If Defender were actively
terminating `php.exe` (a quarantine or "Malicious Activity Detected"
action), that action is normally logged as its own event; none appears.
This does not rule out Defender's real-time scanning causing enough I/O
contention to indirectly destabilize a process without ever logging a
discrete "block" event, but it means the honest current status is
**"antivirus interference is still plausible but not directly evidenced
in this session's logs,"** not "confirmed," and this report avoids
overstating certainty Phase 7 also did not have.

## A second candidate found this phase: background Windows Update activity

One crash-heavy batch of test runs coincided closely in time with a
logged Windows Update installation attempt
(`Microsoft-Windows-WindowsUpdateClient`, event ID 43 "Installation
Started" at 01:08:31, ID 20 "Installation Failure" at 01:08:35, for a
Microsoft Store component update) — within roughly the same one-minute
window as the crash-heavy sample above. This is **circumstantial, not
proven** (one coincidence in one sample is not causation), but it is a
concrete, previously-undocumented candidate: background OS update/install
activity consuming disk or CPU could plausibly destabilize a
rapidly-spawning, rapidly-connecting PHP process the same way antivirus
scanning could. Recorded here so a future phase can check for correlation
again rather than starting from zero.

## What did NOT reproduce a failure this phase

- A completely independent hello-world script (no project code) survived
  40 rapid sequential GET requests plus a session-persistence GET and a
  POST in its sampled run, with the server process still alive
  afterward — the built-in server is not unconditionally unstable; it
  survives real traffic patterns often enough to be usable for
  interactive development and, this phase, for a complete real HTTP
  integration test (see docs/HTTP-INTEGRATION-TEST-REPORT.md).
- The full real-HTTP auth flow (register → validate-fail → register-ok →
  login-fail → unauthorized → login-ok → home → logout →
  post-logout-unauthorized, 13 real HTTP requests against the actual
  project code and a real migrated database) completed with **zero
  crashes**, immediately before the crash-heavy `tests/run.php` batch
  above. The instability is real but did not block the specific work
  Phase 8B needed to do.

## Addendum: reinforced by continued testing through the rest of Phase 8

Testing continued throughout Phases 8B-8O (real HTTP flows, repeated
`tests/run.php` runs before and after each code change). The pattern
held: long clean streaks (5/5, and others) interrupted by sudden
crash-heavy batches (one batch: 5/6 crashed; a single immediate retry
right after: clean). Every crash observed continued to be the same
zero-output, no-catchable-error signature, and every crash this phase
happened during CORE-only code (migration/table checks) that this
phase's own Pharmacy-integration work never touches — confirming, again,
that the instability is environmental, not a regression introduced by
any change in this phase. No new mitigation was found; "retry, don't
debug the project code" remained the correct and sufficient response
every time.

## Recommended environment (per the "do not modify the project to
compensate for an external defect" instruction)

Nothing in the project was changed to work around this. The
recommendation, unchanged in spirit from Phase 7 but sharpened with this
phase's evidence:

1. **For this machine, expect intermittent `php.exe` termination under
   sustained rapid execution** (repeated test-suite runs, large
   multi-statement SQL imports). A single HTTP test session or a single
   test-suite run is very likely to succeed; a tight loop of many
   consecutive runs is not guaranteed to.
2. **Retry-tolerant usage, not a code fix, is the correct mitigation
   today.** Treat a zero-output, `259`-exit-code run as "environment
   crash, retry," not as a test failure to debug.
3. **If administrator access becomes available**, the next concrete,
   low-risk experiment (not performed this phase — requires elevation
   this session does not have) is adding a Windows Defender process
   exclusion for `C:\xampp\php\php.exe` and re-running the same
   5-consecutive-runs sample; a jump to 5/5 clean would confirm the AV
   hypothesis directly instead of by absence of contrary evidence.
4. **Do not rely on this specific Windows machine as the eventual CI/
   production target.** A Linux environment (no Windows Defender
   real-time scanning, no XAMPP foreground-process model) remains the
   better long-term target for automated testing, as Phase 7 already
   recommended.
5. Continue running MariaDB and Apache as manually-started foreground
   processes for this development environment — they are not registered
   as Windows services, and registering them was not requested and would
   be a system-level change outside this phase's scope.

## Files created

docs/DEVELOPMENT-ENVIRONMENT-REPORT.md (this file).

## Files changed

None (investigation only).
