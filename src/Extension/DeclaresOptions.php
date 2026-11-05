<?php

namespace IPE\Extension;

/**
 * Interface for extensions for which IPE declares the accepted options.
 */
interface DeclaresOptions
{
    /**
     * Get the options accepted by this extension (they take precedence over the ones declared in the extension manifest).
     * It's static because we need the options before detecting PHP and the operating system (for example to show them in the CLI help).
     *
     * @return \IPE\Option[]
     */
    public static function getOptions();
}
