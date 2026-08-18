# Core configuration

Phase 2 provides a dependency-free, environment-aware configuration foundation:

- environment.php safely loads a local .env file without overriding real process environment variables.
- paths.php defines application paths.
- app.php and database.php return non-secret application and database configuration arrays.
- constants.php and bootstrap.php expose a small opt-in configuration bootstrap.

The legacy Pharmacy POS still uses config/db.php. It has not been switched to this layer because that change needs database-backed compatibility testing. New unified tooling, including database/migrate.php, uses this configuration foundation.
