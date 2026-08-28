<?php

if (!interface_exists('TheRainAiProvider')) {
    interface TheRainAiProvider
    {
        /**
         * Returns a structured analysis or null when the provider has no
         * supported, authorized, real data for the requested analysis.
         *
         * @param string $analysis
         * @param array $context
         * @param array $input
         * @return array|null
         */
        public function analyze($analysis, array $context, array $input = array());
    }
}
