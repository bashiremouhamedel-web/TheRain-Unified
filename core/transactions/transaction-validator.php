<?php

require_once __DIR__ . '/transaction-state.php';

// Validates transaction input before it reaches the database. Kept
// separate from transaction-service.php so a caller (a future module, or
// a UI form handler) can validate and show errors without necessarily
// writing anything yet -- the same separation core/auth/registration-service.php
// already uses (therain_validate_registration_input() vs. the write path).

if (!function_exists('therain_transaction_validate_create')) {
    /**
     * @param array $data Expects tenant_id, module_slug, transaction_type,
     *   currency_id, subtotal_amount, discount_amount, tax_amount,
     *   total_amount. Optional: branch_id, amount_paid, state.
     * @return string[] Human-readable errors; empty when valid.
     */
    function therain_transaction_validate_create(array $data)
    {
        $errors = array();

        foreach (array('tenant_id', 'module_slug', 'transaction_type', 'currency_id') as $required) {
            if (empty($data[$required])) {
                $errors[] = "Field \"$required\" is required.";
            }
        }

        foreach (array('subtotal_amount', 'discount_amount', 'tax_amount', 'total_amount') as $amountField) {
            if (isset($data[$amountField]) && !is_numeric($data[$amountField])) {
                $errors[] = "Field \"$amountField\" must be numeric.";
            }
        }

        $subtotal = isset($data['subtotal_amount']) ? (float) $data['subtotal_amount'] : 0.0;
        $discount = isset($data['discount_amount']) ? (float) $data['discount_amount'] : 0.0;
        $tax = isset($data['tax_amount']) ? (float) $data['tax_amount'] : 0.0;
        $total = isset($data['total_amount']) ? (float) $data['total_amount'] : 0.0;

        if ($discount < 0) {
            $errors[] = 'Discount amount cannot be negative.';
        }

        if ($tax < 0) {
            $errors[] = 'Tax amount cannot be negative.';
        }

        $expectedTotal = round($subtotal - $discount + $tax, 2);
        if (empty($errors) && abs($expectedTotal - round($total, 2)) > 0.01) {
            $errors[] = "Total amount ($total) does not match subtotal - discount + tax ($expectedTotal).";
        }

        if (isset($data['state']) && !in_array($data['state'], therain_transaction_all_states(), true)) {
            $errors[] = 'Unknown initial state: ' . $data['state'];
        }

        return $errors;
    }
}

if (!function_exists('therain_transaction_validate_transition')) {
    /**
     * @param string $currentState
     * @param string $toState
     * @param string $moduleSlug
     * @return string[] Human-readable errors; empty when valid.
     */
    function therain_transaction_validate_transition($currentState, $toState, $moduleSlug)
    {
        $errors = array();

        if (!in_array($toState, therain_transaction_all_states(), true)) {
            $errors[] = 'Unknown target state: ' . $toState;
            return $errors;
        }

        if (!therain_transaction_can_transition($currentState, $toState, $moduleSlug)) {
            $errors[] = "Cannot move a $moduleSlug transaction from \"$currentState\" to \"$toState\".";
        }

        return $errors;
    }
}
