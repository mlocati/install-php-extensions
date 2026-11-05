<?php

namespace IPE\CLI;

use IPE\CLI\Argv\Option;
use IPE\Exception;
use IPE\Service\JSON;

class Argv
{
    /**
     * @var \IPE\CLI\Argv\Option\Definition[]
     */
    protected $definitions = [];

    /**
     * The values of the options with a Flag or Value definition.
     *
     * @var array<non-empty-string, mixed>
     */
    protected $optionValues = [];

    /**
     * The values of the options with an OptionalValue definition (true for options specified without a value).
     *
     * @var array<non-empty-string, string|true>
     */
    protected $optionalValues = [];

    /**
     * @var list<string>
     */
    protected $arguments = [];

    /**
     * @param \IPE\CLI\Argv\Option\Definition[] $definitions
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return $this
     */
    public function addDefinitions(array $definitions)
    {
        foreach ($definitions as $definition) {
            $this->addDefinition($definition);
        }

        return $this;
    }

    /**
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return $this
     */
    public function addDefinition(Option\Definition $definition)
    {
        $name = $definition->getName();
        if (isset($this->definitions[$name])) {
            throw new Exception\InvalidArgument("Duplicated definition name: {$name}");
        }
        $this->definitions[$name] = $definition;

        return $this;
    }

    /**
     * @return \IPE\CLI\Argv\Option\Definition[]
     */
    public function getDefinitions()
    {
        return $this->definitions;
    }

    /**
     * @param list<string> $argv
     *
     * @throws \IPE\Exception\InvalidOption
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return $this
     */
    public function addArgv(array $argv)
    {
        $arguments = [];
        array_shift($argv);
        while ($argv !== []) {
            $arg = array_shift($argv);
            if ($arg === '--') {
                $arguments = array_merge($arguments, $argv);
                break;
            }
            if ($arg === '' || $arg[0] !== '-') {
                $arguments[] = $arg;
                continue;
            }
            $chunks = explode('=', $arg, 2);
            $arg = $chunks[0];
            $optionValue = isset($chunks[1]) ? $chunks[1] : null;
            $optionName = substr($arg, 1);
            if ($optionName !== '' && $optionName[0] === '-') {
                $optionName = substr($optionName, 1);
            }
            if ($optionName === '') {
                throw new Exception\InvalidOption("Invalid option: {$arg}");
            }
            $definition = $this->getOptionDefinition($optionName);
            if ($definition instanceof Option\Definition\Flag) {
                if ($optionValue !== null) {
                    throw new Exception\InvalidOption("The option {$arg} does not accept a value");
                }
                $this->optionValues[$optionName] = true;
            } elseif ($definition instanceof Option\Definition\Value) {
                if ($optionValue === null) {
                    $optionValue = array_shift($argv);
                    if ($optionValue === null) {
                        throw new Exception\InvalidOption("The option {$arg} requires a value");
                    }
                }
                if ($definition instanceof Option\Definition\StringList) {
                    $this->optionValues[$optionName] = array_merge($this->getStringList($optionName), [$definition->parseString($optionValue)]);
                } else {
                    $this->optionValues[$optionName] = $definition->parseString($optionValue);
                }
            } elseif ($definition instanceof Option\Definition\OptionalValue) {
                $this->optionalValues[$optionName] = $optionValue === null ? true : $optionValue;
            } else {
                throw new Exception\InvalidArgument('Unrecognized option kind');
            }
        }
        $this->arguments = array_merge($this->arguments, $arguments);

        return $this;
    }

    /**
     * @param non-empty-string $name
     *
     * @throws \IPE\Exception\InvalidOption
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return mixed the value of the option
     */
    public function get($name)
    {
        if (array_key_exists($name, $this->optionValues)) {
            return $this->optionValues[$name];
        }
        if (array_key_exists($name, $this->optionalValues)) {
            return $this->optionalValues[$name];
        }
        $definition = $this->getOptionDefinition($name);
        if ($definition instanceof Option\Definition\Flag) {
            return false;
        }
        if ($definition instanceof Option\Definition\Value) {
            return $definition->getDefaultValue();
        }
        if ($definition instanceof Option\Definition\OptionalValue) {
            return null;
        }
        throw new Exception\InvalidArgument('Unrecognized option kind');
    }

    /**
     * Get the value of a boolean option.
     *
     * @param non-empty-string $name
     * @param bool $default what to return if the option has not been specified
     *
     * @throws \IPE\Exception\InvalidOption
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return bool
     */
    public function getBool($name, $default)
    {
        $value = $this->get($name);

        return $value === null ? $default : (bool) $value;
    }

    /**
     * Get the value of an option without value (a flag).
     *
     * @param non-empty-string $name
     *
     * @throws \IPE\Exception\InvalidOption
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return bool
     */
    public function getFlag($name)
    {
        return $this->get($name) === true;
    }

    /**
     * Get the value of an option with a string value.
     *
     * @param non-empty-string $name
     *
     * @throws \IPE\Exception\InvalidOption
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return string
     */
    public function getString($name)
    {
        $value = $this->get($name);
        if (!is_string($value)) {
            throw new Exception\InvalidArgument("The option {$name} doesn't have a string value");
        }

        return $value;
    }

    /**
     * Get the values of an option that can be specified multiple times.
     *
     * @param non-empty-string $name
     *
     * @throws \IPE\Exception\InvalidOption
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return list<non-empty-string>
     */
    public function getStringList($name)
    {
        $value = $this->get($name);
        if (!is_array($value)) {
            throw new Exception\InvalidArgument("The option {$name} doesn't have a list of values");
        }

        /** @var list<non-empty-string> $value */
        return $value;
    }

    /**
     * Get the value of an option with an integer value.
     *
     * @param non-empty-string $name
     *
     * @throws \IPE\Exception\InvalidOption
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return int|null null if the option has not been specified and it doesn't have a default value
     */
    public function getInt($name)
    {
        $value = $this->get($name);
        if ($value !== null && !is_int($value)) {
            throw new Exception\InvalidArgument("The option {$name} doesn't have an integer value");
        }

        return $value;
    }

    /**
     * Get the command line arguments of the running script (the first one is the script name).
     *
     * @return non-empty-list<string>
     */
    public static function getScriptArgv()
    {
        $argv = isset($_SERVER['argv']) && is_array($_SERVER['argv']) ? array_values(array_filter($_SERVER['argv'], 'is_string')) : [];

        return $argv === [] ? [''] : $argv;
    }

    /**
     * @return list<string>
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @throws \IPE\Exception\JSON
     *
     * @return string
     */
    public function buildCLIHelpString()
    {
        return $this->formatDefinitions($this->getDefinitions());
    }

    /**
     * @param \IPE\CLI\Argv\Option\Definition[] $definitions
     *
     * @throws \IPE\Exception\JSON
     *
     * @return string
     */
    protected function formatDefinitions(array $definitions)
    {
        $lines = [];
        foreach ($definitions as $definition) {
            if ($definition instanceof Option\Definition\Hidden) {
                continue;
            }
            $headLine = '--' . $definition->getName();
            if ($definition instanceof Option\Definition\ValueInList) {
                $headLine .= '[=]<' . implode('|', $definition->getAllowedValues()) . '>';
            } elseif ($definition instanceof Option\Definition\BoolValue) {
                $headLine .= '[=]<yes|no>';
            } elseif ($definition instanceof Option\Definition\Value) {
                $headLine .= '[=]value';
            } elseif ($definition instanceof Option\Definition\OptionalValue) {
                $headLine .= '[=value]';
            }
            $default = $definition->getDefaultDescription();
            if ($default === '' && $definition instanceof Option\Definition\Value) {
                $defaultValue = $definition->getDefaultValue();
                if ($defaultValue !== '' && $defaultValue !== null && $defaultValue !== []) {
                    $default = is_string($defaultValue) ? $defaultValue : JSON::encode($defaultValue);
                }
            }
            $lines[] = $headLine;
            $lines[] = '  ' . $definition->getDescription();
            if ($default !== '') {
                $lines[] = "  Default: {$default}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param non-empty-string $optionName
     *
     * @throws \IPE\Exception\InvalidOption
     *
     * @return \IPE\CLI\Argv\Option\Definition
     */
    protected function getOptionDefinition($optionName)
    {
        if (!isset($this->definitions[$optionName])) {
            throw new Exception\InvalidOption("Unrecognized option: --{$optionName}");
        }

        return $this->definitions[$optionName];
    }
}
