# Core search

This directory contains the module-aware search foundation. Providers are registered by module/entity and receive tenant and optional branch context. Each provider owns its SQL and authorization checks; CORE only aggregates bounded result records. No global cross-table query or search UI is implemented yet.
