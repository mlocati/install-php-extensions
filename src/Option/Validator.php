<?php

namespace IPE\Option;

use IPE\Exception;
use IPE\Option;

/**
 * Checks the options specified by the user against the ones declared for an extension or a system dependency.
 */
final class Validator
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    public function __construct(\IPE\Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param array<string, string|true> $userOptions the options specified by the user (true for options without a value)
     * @param \IPE\Option[] $declaredOptions in order of precedence (for duplicated names, the first one wins)
     * @param bool $allowUndeclared if true, undeclared options are passed to ./configure as they are, otherwise they are rejected
     * @param string $ownerDescription used in error messages
     *
     * @throws \IPE\Exception\InvalidArgument if $declaredOptions contains something that's not an Option
     * @throws \IPE\Exception\InvalidOptionValues
     *
     * @return \IPE\Option\Values
     */
    public function validate(array $userOptions, array $declaredOptions, $allowUndeclared, $ownerDescription)
    {
        $declared = [];
        foreach ($declaredOptions as $option) {
            if (!isset($declared[$option->getName()])) {
                $declared[$option->getName()] = $option;
            }
        }
        $configureArguments = [];
        $customValues = [];
        $seen = [];
        foreach ($userOptions as $name => $value) {
            $name = ltrim($name, '-');
            if (!preg_match(Option::RX_VALID_NAME, $name)) {
                throw new Exception\InvalidOptionValues("Invalid option name for {$ownerDescription}: '{$name}'");
            }
            if (!isset($declared[$name])) {
                if (!$allowUndeclared) {
                    throw new Exception\InvalidOptionValues(
                        "{$ownerDescription} does not accept the option '{$name}'"
                        . ($declared === [] ? ' (no options are accepted)' : ":\naccepted options are\n- " . implode("\n- ", array_keys($declared)))
                    );
                }
                $this->logger->warning("Passing the undeclared option '{$name}' to the configure script of {$ownerDescription}");
                $configureArguments[] = self::buildConfigureArgument($name, $value);
                continue;
            }
            $option = $declared[$name];
            if ($option->needsValue() === true && $value === true) {
                throw new Exception\InvalidOptionValues("The option '{$name}' of {$ownerDescription} requires a value");
            }
            $seen[$name] = true;
            if ($option instanceof CustomOption) {
                $customValues[$name] = $value;
            } else {
                $configureArguments[] = self::buildConfigureArgument($name, $value);
            }
        }
        $defaultConfigureArguments = [];
        foreach ($declared as $name => $option) {
            $default = $option->getDefault();
            if ($default === null || isset($seen[$name])) {
                continue;
            }
            if ($option instanceof CustomOption) {
                $customValues[$name] = $default;
            } elseif ($option instanceof ConfigureOption) {
                $defaultConfigureArguments[] = self::buildConfigureArgument($name, $default);
            }
        }

        return new Values($configureArguments, $customValues, $defaultConfigureArguments);
    }

    /**
     * @param non-empty-string $name
     * @param string|true $value
     *
     * @return non-empty-string
     */
    private static function buildConfigureArgument($name, $value)
    {
        // Like PECL: configure would take "autodetect" as a path
        if ($value === true || ($value === 'autodetect' && strpos($name, 'with-') === 0)) {
            return "--{$name}";
        }

        return "--{$name}={$value}";
    }
}
