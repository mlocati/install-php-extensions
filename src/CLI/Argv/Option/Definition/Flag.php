<?php

namespace IPE\CLI\Argv\Option\Definition;

use IPE\CLI\Argv\Option\Definition;

class Flag extends Definition
{
    /**
     * @param non-empty-string $name
     * @param non-empty-string $description
     */
    public function __construct($name, $description)
    {
        parent::__construct($name, $description);
    }
}
