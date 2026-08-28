# Currency Integration Report

## Status

**IMPLEMENTED + NOT TESTED:** CORE currency helpers, tenant defaults, employee display preference, exchange-rate lookup, and payment currency storage are available. The transaction model stores original currency separately from display preference.

**DEFERRED:** the legacy Pharmacy schema still contains XAF defaults and hardcoded display text. No historical amount was reinterpreted and no reckless global replacement was made.

Runtime validation of XAF, USD, EUR, NGN, GHS, and KES is blocked until MariaDB is available.
