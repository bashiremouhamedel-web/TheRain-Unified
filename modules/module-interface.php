<?php

/**
 * Contract implemented by a management module adapter.
 * The adapter exposes module metadata and registers behavior against an
 * explicit context; it does not own CORE authentication or database code.
 */
interface TheRainModuleInterface
{
    /** @return array */
    public function manifest();

    /** @param TheRainModuleContext $context @return void */
    public function register(TheRainModuleContext $context);
}
