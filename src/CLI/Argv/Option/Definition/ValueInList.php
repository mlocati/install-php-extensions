<?php

namespace IPE\CLI\Argv\Option\Definition;

use IPE\Exception;

/**
 * @method string|int getDefaultValue()
 */
class ValueInList extends Value
{
    /**
     * @var non-empty-array<int, string|int>
     */
    private $allowedValues;

    /**
     * @param non-empty-string $name
     * @param non-empty-string $description
     * @param non-empty-array<int, string|int> $allowedValues
     * @param string|int $defaultValue
     */
    public function __construct($name, $description, array $allowedValues, $defaultValue = '')
    {
        parent::__construct($name, $description, $defaultValue);
        $this->allowedValues = $allowedValues;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\CLI\Argv\Option\Definition\Value::parseString()
     *
     * @return string|int
     */
    public function parseString($str)
    {
        $stringAllowedValues = array_map('strval', $this->allowedValues);
        $index = array_search($str, $stringAllowedValues, true);
        if ($index === false) {
            throw new Exception\InvalidOption("'{$str}' is not a valid value for the option {$this->getName()}\nValid values are:\n- " . implode("\n- ", $stringAllowedValues));
        }

        return $this->allowedValues[$index];
    }

    /**
     * @return non-empty-array<int, string|int>
     */
    public function getAllowedValues()
    {
        return $this->allowedValues;
    }
}
