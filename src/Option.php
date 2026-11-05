<?php

namespace IPE;

/**
 * An option that users can specify for an extension.
 */
abstract class Option
{
    /**
     * @public
     * @var string
     *
     * @see https://github.com/php/pie/blob/1.6.x/resources/composer-json-php-ext-schema.json
     */
    const RX_VALID_NAME = '/^[A-Za-z0-9][A-Za-z0-9_\-]*$/D';

    /**
     * @var non-empty-string
     */
    private $name;

    /**
     * @var string
     */
    private $description;

    /**
     * @var bool|null
     */
    private $needsValue;

    /**
     * @var string|null
     */
    private $default;

    /**
     * @param non-empty-string $name the option name, without the leading dashes (e.g. 'with-zlib-dir')
     * @param string $description
     * @param bool|null $needsValue true if the option requires a value, false if it's a flag, null if unknown
     * @param string|null $default the default value, null if not defined
     *
     * @throws \IPE\Exception\InvalidArgument
     */
    public function __construct($name, $description = '', $needsValue = null, $default = null)
    {
        $name = ltrim($name, '-');
        if (!preg_match(self::RX_VALID_NAME, $name)) {
            throw new Exception\InvalidArgument('Invalid option name' . ($name === '' ? '' : ": {$name}"));
        }
        $this->name = $name;
        $this->description = trim($description);
        $this->needsValue = $needsValue;
        $this->default = $default;
    }

    /**
     * @return non-empty-string
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
     * @return bool|null true if the option requires a value, false if it's a flag, null if unknown
     */
    public function needsValue()
    {
        return $this->needsValue;
    }

    /**
     * @return bool
     */
    public function hasDefault()
    {
        return $this->default !== null;
    }

    /**
     * @return string|null
     */
    public function getDefault()
    {
        return $this->default;
    }
}
