<?php

// A minimal, in-process event dispatcher for the transaction engine.
//
// Deliberately not module-hardcoded: transaction-service.php fires plain
// events like "transaction.completed" with the transaction row (including
// its module_slug) as the payload -- a module registers a listener and
// decides for itself, from the payload's module_slug, whether to react
// (e.g. a future Pharmacy stock-deduction listener would check
// $payload['transaction']['module_slug'] === 'pharmacy' before doing
// anything). This is what keeps "Pharmacy sale completed triggers
// inventory update" possible without teaching core/transactions/ anything
// about Pharmacy -- see the Phase 9 brief's own requirement and
// docs/TRANSACTION-ARCHITECTURE.md.
//
// In-process only (registered listeners exist for the current request's
// lifetime, same as any plain PHP callable array). transaction-service.php
// also writes a durable transaction_state_history row for every state
// change independent of whether any listener is registered -- so no event
// is ever the only record of what happened, matching the audit
// requirements in docs/AUDIT-ARCHITECTURE.md.

if (!function_exists('therain_transaction_event_listeners')) {
    /**
     * @return array<string, callable[]>
     */
    function &therain_transaction_event_listeners()
    {
        static $listeners = array();

        return $listeners;
    }
}

if (!function_exists('therain_transaction_on')) {
    /**
     * Registers a listener for a transaction event.
     *
     * @param string $eventName e.g. 'transaction.completed'
     * @param callable $listener Receives one argument: the event payload array.
     * @return void
     */
    function therain_transaction_on($eventName, callable $listener)
    {
        $listeners = &therain_transaction_event_listeners();

        if (!isset($listeners[$eventName])) {
            $listeners[$eventName] = array();
        }

        $listeners[$eventName][] = $listener;
    }
}

if (!function_exists('therain_transaction_fire')) {
    /**
     * Fires a transaction event to every registered listener, in
     * registration order. A listener throwing does not stop the others
     * (each is wrapped) -- a broken notification listener must never be
     * able to break the transaction write path that fired the event.
     *
     * @param string $eventName
     * @param array $payload
     * @return void
     */
    function therain_transaction_fire($eventName, array $payload)
    {
        $listeners = therain_transaction_event_listeners();

        if (empty($listeners[$eventName])) {
            return;
        }

        foreach ($listeners[$eventName] as $listener) {
            try {
                call_user_func($listener, $payload);
            } catch (Throwable $exception) {
                // Deliberately swallowed -- see the doc comment above.
                // transaction_state_history already durably recorded the
                // state change regardless of listener outcome.
            }
        }
    }
}
