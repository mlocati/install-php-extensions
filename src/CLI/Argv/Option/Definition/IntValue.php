<?php

namespace IPE\CLI\Argv\Option\Definition;

use IPE\Exception;

class IntValue extends Value
{
    /**
     * @var int|null
     */
    private $min;

    /**
     * @var int|null
     */
    private $max;

    /**
     * @param non-empty-string $name
     * @param non-empty-string $description
     * @param int|null $defaultValue
     * @param int|null $min
     * @param int|null $max
     */
    public function __construct($name, $description, $defaultValue = null, $min = null, $max = null)
    {
        parent::__construct($name, $description, $defaultValue);
        if ($min !== null && $max !== null && $min > $max) {
            throw new Exception\InvalidArgument('$max must be greater or equal to $min');
        }
        $this->min = $min;
        $this->max = $max;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\CLI\Argv\Option\Definition\Value::parseString()
     *
     * @return int|null
     */
    public function parseString($str)
    {
        if ($str === '' && $this->getDefaultValue() === null) {
            return null;
        }
        if (!preg_match('#^[\-+]?\d{1,9}$#D', $str)) {
            throw new Exception\InvalidOption("The {$this->getName()} option '{$str}' must be an integer");
        }
        $int = (int) $str;
        if ($this->min !== null && $int < $this->min) {
            throw new Exception\InvalidOption("The {$this->getName()} option '{$str}' must not be smaller than {$this->min}");
        }
        if ($this->max !== null && $int > $this->max) {
            throw new Exception\InvalidOption("The {$this->getName()} option '{$str}' must not be greater than {$this->max}");
        }

        return $int;
    }
}
