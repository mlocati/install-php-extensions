<?php

namespace IPE\CLI\Argv\Option\Definition;

use IPE\CLI\Argv\Option\Definition;

abstract class Value extends Definition
{
    /**
     * @var mixed type depending on the option
     */
    private $defaultValue;

    /**
     * @param non-empty-string $name
     * @param non-empty-string $description
     * @param mixed $defaultValue type depending on the option
     */
    public function __construct($name, $description, $defaultValue)
    {
        parent::__construct($name, $description);
        $this->defaultValue = $defaultValue;
    }

    /**
     * @return mixed type depending on the option
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * @param string $str
     *
     * @throws \IPE\Exception\InvalidOption
     *
     * @return mixed type depending on the option
     */
    public abstract function parseString($str);
}
