<?php

namespace IPE\CLI\Argv\Option\Definition;

use IPE\Exception;

/**
 * @method bool|null getDefaultValue()
 */
class BoolValue extends Value
{
    /**
     * @param non-empty-string $name
     * @param non-empty-string $description
     * @param bool|null $defaultValue
     */
    public function __construct($name, $description, $defaultValue = null)
    {
        parent::__construct($name, $description, $defaultValue);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\CLI\Argv\Option\Definition\Value::parseString()
     *
     * @return bool
     */
    public function parseString($str)
    {
        // filter_var considers empty strings as false
        $value = trim($str) === '' ? null : filter_var($str, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($value === null) {
            throw new Exception\InvalidOption("The {$this->getName()} option '{$str}' must be yes or no");
        }

        return $value;
    }
}
