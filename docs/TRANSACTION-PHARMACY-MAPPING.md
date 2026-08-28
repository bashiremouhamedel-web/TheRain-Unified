# Transaction to Pharmacy Mapping

## Status

**DESIGNED ONLY:** the shared transaction engine supports Pharmacy's proven immediate workflow and future staged workflows, but the legacy Pharmacy sale path has not been migrated into it.

The intended compatibility mapping is `draft -> completed` for an atomic POS sale, with `paid`, `partially_paid`, or `credit` available when a future adapter can derive those states from real legacy payment data. Historical amounts and invoices must remain untouched until an explicit migration and reconciliation plan exists.
