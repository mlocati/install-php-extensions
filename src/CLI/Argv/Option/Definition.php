<?php

namespace IPE\CLI\Argv\Option;

abstract class Definition
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string
     */
    private $defaultDescription = '';

    /**
     * @param string $name
     * @param string $description
     */
    protected function __construct($name, $description)
    {
        $this->name = $name;
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Describe the default, for options whose default isn't a fixed value.
     *
     * @param string $value
     *
     * @return $this
     *
     * @example 'yes in the official Docker PHP images, no otherwise'
     */
    public function setDefaultDescription($value)
    {
        $this->defaultDescription = $value;

        return $this;
    }

    /**
     * @return string empty string if the default isn't described
     */
    public function getDefaultDescription()
    {
        return $this->defaultDescription;
    }
}
