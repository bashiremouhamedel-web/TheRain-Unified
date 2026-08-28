# Pharmacy Employee Identity

## Status

**IMPLEMENTED + NOT TESTED:** Unified users are associated to a Pharmacy store through the existing tenant chain and additive `p_tenant_bridge`. The legacy session receives `therain_acting_user_id` when entered from Unified auth. `therain_pharmacy_actor_can()` resolves the store tenant and reuses the CORE permission service.

The chain is: Unified user -> tenant -> `p_tenant_bridge` -> legacy store -> acting user permissions.

## Compatibility

Standalone legacy stores remain valid and unchanged. A store without a bridge is denied by the Unified permission bridge rather than being granted implicit access. No legacy tables were renamed and no legacy login behavior was replaced.

## Remaining work

The bridge is a capability, not broad enforcement. Legacy pages still primarily check `$_SESSION['store_id']`; they do not yet call the actor permission helper. Phase 10 should select and test a small set of high-risk server-side routes first, then expand incrementally. Legacy plaintext password handling remains deferred.
