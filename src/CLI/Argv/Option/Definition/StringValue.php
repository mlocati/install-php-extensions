<?php

namespace IPE\CLI\Argv\Option\Definition;

use IPE\Exception;

/**
 * @method string getDefaultValue()
 */
class StringValue extends Value
{
    /**
     * @var string
     */
    private $regex;

    /**
     * @param non-empty-string $name
     * @param non-empty-string $description
     * @param string $defaultValue
     * @param string $regex
     */
    public function __construct($name, $description, $defaultValue = '', $regex = '')
    {
        parent::__construct($name, $description, $defaultValue);
        $this->regex = $regex;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\CLI\Argv\Option\Definition\Value::parseString()
     *
     * @return string
     */
    public function parseString($str)
    {
        if ($this->regex !== '' && !preg_match($this->regex, $str)) {
            throw new Exception\InvalidOption("The {$this->getName()} option '{$str}' doesn't satisfy the regular expression {$this->regex}");
        }

        return $str;
    }
}
