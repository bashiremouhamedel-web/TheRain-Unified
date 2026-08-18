# Phase 2 configuration report

## Created configuration foundation

core/config now contains:

- paths.php: application, core, database, storage, and local .env paths.
- environment.php: a dependency-free KEY=VALUE loader and environment access helpers.
- app.php: non-secret application configuration.
- database.php: non-secret database connection settings read from environment variables.
- constants.php: safe one-time constant definition helper.
- bootstrap.php: opt-in loader returning paths, app, and database configuration.

## Environment behaviour

bootstrap.php loads a local root .env file if one exists. Process environment variables always take precedence, allowing cloud hosts and deployment systems to inject secrets without placing them in source files.

.env.example is the committed safe template. .env remains ignored. The configuration layer contains no production password or API secret.

## Compatibility decision

The legacy Pharmacy POS continues to load config/db.php exactly as before. It does not yet load the new bootstrap because changing its connection path without PHP and database testing would risk breaking the baseline application.

database/migrate.php is the only new Phase 2 consumer of the configuration bootstrap. It is intentionally CLI-only.

## Deployment direction

The same environment variables support local standalone deployments, cloud installations, and future package-specific settings. Tenant selection and module configuration are database concerns planned for later phases, not hardcoded environment values.
