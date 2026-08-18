<?php

if (!function_exists('therain_define')) {
    /**
     * Defines a constant once without overwriting a host-provided value.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    function therain_define($name, $value)
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}
