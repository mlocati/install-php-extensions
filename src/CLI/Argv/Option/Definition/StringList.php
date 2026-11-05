<?php

namespace IPE\CLI\Argv\Option\Definition;

use IPE\Exception;

/**
 * An option that can be specified multiple times: its value is the list of the values received.
 *
 * @method list<non-empty-string> getDefaultValue()
 */
final class StringList extends Value
{
    /**
     * @param non-empty-string $name
     * @param non-empty-string $description
     */
    public function __construct($name, $description)
    {
        parent::__construct($name, $description, []);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\CLI\Argv\Option\Definition\Value::parseString()
     *
     * @return non-empty-string
     */
    public function parseString($str)
    {
        if ($str === '') {
            throw new Exception\InvalidOption("The {$this->getName()} option requires a non empty value");
        }

        return $str;
    }
}
