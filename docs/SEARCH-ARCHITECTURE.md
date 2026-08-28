# Search Architecture

## Status

**DESIGNED ONLY:** no global search query or UI is introduced in Phase 9.

## Contract

Search must be module-aware and tenant-scoped. A registry of module search providers should receive a normalized query plus tenant and branch context, then return typed entity references and safe display fields. Each provider owns its indexed tables and authorization checks. CORE should aggregate provider results, apply a result limit, and never join every module table into one query.

A later implementation may use provider-specific SQL indexes or a maintained search index. It must support Pharmacy, POS, and future modules without requiring a new cross-module query for each module. Ranking, pagination, UI, and indexing jobs are deferred.
