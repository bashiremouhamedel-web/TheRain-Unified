<?php

// Shared transaction state vocabulary and transition rules.
//
// Design note (see docs/TRANSACTION-ARCHITECTURE.md for the full
// rationale): this is deliberately a superset of states, not a single
// rigid workflow. A module declares which subset of these states it
// actually uses (see therain_transaction_module_states() below) and the
// validator only allows transitions within that subset -- so Pharmacy's
// real, proven workflow (single-step: draft -> completed, or
// draft -> cancelled) and a future Supermarket-style staged workflow
// (draft -> pending -> awaiting_payment -> payment_processing -> paid ->
// completed) can both be expressed correctly without forcing one
// module's shape onto another.

if (!defined('THERAIN_TXN_STATE_DRAFT')) {
    define('THERAIN_TXN_STATE_DRAFT', 'draft');
    define('THERAIN_TXN_STATE_PENDING', 'pending');
    define('THERAIN_TXN_STATE_AWAITING_PAYMENT', 'awaiting_payment');
    define('THERAIN_TXN_STATE_PAYMENT_PROCESSING', 'payment_processing');
    define('THERAIN_TXN_STATE_PARTIALLY_PAID', 'partially_paid');
    define('THERAIN_TXN_STATE_PAID', 'paid');
    define('THERAIN_TXN_STATE_CREDIT', 'credit');
    define('THERAIN_TXN_STATE_COMPLETED', 'completed');
    define('THERAIN_TXN_STATE_CANCELLED', 'cancelled');
    define('THERAIN_TXN_STATE_REFUNDED', 'refunded');
    define('THERAIN_TXN_STATE_RETURNED', 'returned');
    define('THERAIN_TXN_STATE_FAILED', 'failed');
}

if (!function_exists('therain_transaction_all_states')) {
    /**
     * Every state the engine knows about. A module's own allowed-states
     * list (therain_transaction_module_states()) must be a subset of this.
     *
     * @return string[]
     */
    function therain_transaction_all_states()
    {
        return array(
            THERAIN_TXN_STATE_DRAFT,
            THERAIN_TXN_STATE_PENDING,
            THERAIN_TXN_STATE_AWAITING_PAYMENT,
            THERAIN_TXN_STATE_PAYMENT_PROCESSING,
            THERAIN_TXN_STATE_PARTIALLY_PAID,
            THERAIN_TXN_STATE_PAID,
            THERAIN_TXN_STATE_CREDIT,
            THERAIN_TXN_STATE_COMPLETED,
            THERAIN_TXN_STATE_CANCELLED,
            THERAIN_TXN_STATE_REFUNDED,
            THERAIN_TXN_STATE_RETURNED,
            THERAIN_TXN_STATE_FAILED,
        );
    }
}

if (!function_exists('therain_transaction_default_transitions')) {
    /**
     * The full, permissive transition graph the engine understands.
     * array('from_state' => array('allowed', 'next', 'states')).
     * A module further restricts this to only the states it declared via
     * therain_transaction_module_states() -- see
     * therain_transaction_allowed_next_states().
     *
     * @return array<string, string[]>
     */
    function therain_transaction_default_transitions()
    {
        return array(
            THERAIN_TXN_STATE_DRAFT => array(
                THERAIN_TXN_STATE_PENDING,
                THERAIN_TXN_STATE_AWAITING_PAYMENT,
                THERAIN_TXN_STATE_COMPLETED, // Pharmacy's real, proven single-step path.
                THERAIN_TXN_STATE_CANCELLED,
            ),
            THERAIN_TXN_STATE_PENDING => array(
                THERAIN_TXN_STATE_AWAITING_PAYMENT,
                THERAIN_TXN_STATE_CANCELLED,
                THERAIN_TXN_STATE_FAILED,
            ),
            THERAIN_TXN_STATE_AWAITING_PAYMENT => array(
                THERAIN_TXN_STATE_PAYMENT_PROCESSING,
                THERAIN_TXN_STATE_PARTIALLY_PAID,
                THERAIN_TXN_STATE_PAID,
                THERAIN_TXN_STATE_CREDIT,
                THERAIN_TXN_STATE_CANCELLED,
                THERAIN_TXN_STATE_FAILED,
            ),
            THERAIN_TXN_STATE_PAYMENT_PROCESSING => array(
                THERAIN_TXN_STATE_PARTIALLY_PAID,
                THERAIN_TXN_STATE_PAID,
                THERAIN_TXN_STATE_FAILED,
            ),
            THERAIN_TXN_STATE_PARTIALLY_PAID => array(
                THERAIN_TXN_STATE_PAYMENT_PROCESSING,
                THERAIN_TXN_STATE_PAID,
                THERAIN_TXN_STATE_CREDIT,
                THERAIN_TXN_STATE_CANCELLED,
            ),
            THERAIN_TXN_STATE_PAID => array(
                THERAIN_TXN_STATE_COMPLETED,
                THERAIN_TXN_STATE_REFUNDED,
            ),
            THERAIN_TXN_STATE_CREDIT => array(
                THERAIN_TXN_STATE_PARTIALLY_PAID,
                THERAIN_TXN_STATE_PAID,
                THERAIN_TXN_STATE_COMPLETED,
                THERAIN_TXN_STATE_CANCELLED,
            ),
            THERAIN_TXN_STATE_COMPLETED => array(
                THERAIN_TXN_STATE_REFUNDED,
                THERAIN_TXN_STATE_RETURNED,
            ),
            THERAIN_TXN_STATE_CANCELLED => array(),
            THERAIN_TXN_STATE_REFUNDED => array(),
            THERAIN_TXN_STATE_RETURNED => array(),
            THERAIN_TXN_STATE_FAILED => array(
                THERAIN_TXN_STATE_DRAFT, // allow retrying a failed attempt
            ),
        );
    }
}

if (!function_exists('therain_transaction_module_states')) {
    /**
     * The states a specific module actually uses, e.g.:
     *   Pharmacy (proven single-step, see docs/PHARMACY-INTEGRATION-REPORT.md's
     *   8M finding): draft, completed, cancelled, refunded.
     *   A future staged-workflow module (e.g. Supermarket, per the Phase 9
     *   brief): draft, pending, awaiting_payment, payment_processing,
     *   partially_paid, paid, completed, cancelled, refunded, returned, failed.
     *
     * Falls back to every known state if a module has not declared a
     * subset -- permissive by default, never silently blocking a module
     * that has not opted into a restricted list.
     *
     * @param string $moduleSlug
     * @return string[]
     */
    function therain_transaction_module_states($moduleSlug)
    {
        $declared = array(
            'pharmacy' => array(
                THERAIN_TXN_STATE_DRAFT,
                THERAIN_TXN_STATE_COMPLETED,
                THERAIN_TXN_STATE_CANCELLED,
                THERAIN_TXN_STATE_REFUNDED,
            ),
        );

        return isset($declared[$moduleSlug]) ? $declared[$moduleSlug] : therain_transaction_all_states();
    }
}

if (!function_exists('therain_transaction_allowed_next_states')) {
    /**
     * The states a transaction may legally move to next, intersected with
     * what its owning module actually uses.
     *
     * @param string $currentState
     * @param string $moduleSlug
     * @return string[]
     */
    function therain_transaction_allowed_next_states($currentState, $moduleSlug)
    {
        $graph = therain_transaction_default_transitions();
        $moduleStates = therain_transaction_module_states($moduleSlug);

        if (!isset($graph[$currentState])) {
            return array();
        }

        return array_values(array_intersect($graph[$currentState], $moduleStates));
    }
}

if (!function_exists('therain_transaction_can_transition')) {
    /**
     * @param string $fromState
     * @param string $toState
     * @param string $moduleSlug
     * @return bool
     */
    function therain_transaction_can_transition($fromState, $toState, $moduleSlug)
    {
        return in_array($toState, therain_transaction_allowed_next_states($fromState, $moduleSlug), true);
    }
}
