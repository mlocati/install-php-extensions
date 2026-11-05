<?php

namespace IPE\CLI\Argv\Option\Definition;

use IPE\CLI\Argv\Option\Definition;

/**
 * An option that may be specified with or without a value (--name or --name=value).
 */
final class OptionalValue extends Definition
{
    public function __construct($name, $description)
    {
        parent::__construct($name, $description);
    }
}
