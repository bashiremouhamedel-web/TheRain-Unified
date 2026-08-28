# AI Architecture

## Status

**DESIGNED ONLY:** AI remains provider-neutral and no fake insight generator exists. Deterministic analytics may be added only when derived from real tenant-scoped data and accompanied by tests.

## Contract

Future services should receive an explicit tenant, optional branch, module, date range, and permitted data context. Providers must return structured results with source metrics, confidence/quality information, and a clear explanation of unavailable data. A provider must never bypass authorization or expose another tenant's data.

Potential analyses include sales, profit, inventory risk, cash flow, payment recovery, customer behavior, branch performance, and anomaly detection. Model selection, external API credentials, prompt storage, and asynchronous jobs are deferred. No provider or external network call is introduced in Phase 9.
