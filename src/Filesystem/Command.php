<?php

namespace IPE\Filesystem;

use IPE\Exception;

/**
 * A command to be executed by \IPE\Filesystem::run().
 *
 * Instances are immutable: the with... methods return a modified copy.
 */
final class Command
{
    /**
     * Capture standard output and standard error together (the default).
     *
     * @public
     * @var string
     */
    const OUTPUT_CAPTURE_ALL = 'capture-all';

    /**
     * Capture standard output, and discard standard error.
     *
     * @public
     * @var string
     */
    const OUTPUT_CAPTURE_STDOUT = 'capture-stdout';

    /**
     * Capture standard error, and discard standard output.
     *
     * @public
     * @var string
     */
    const OUTPUT_CAPTURE_STDERR = 'capture-stderr';

    /**
     * Let the command write directly to the standard output and error of the current process.
     * To be used for commands that may generate a lot of output.
     *
     * @public
     * @var string
     */
    const OUTPUT_SHOW = 'show';

    /**
     * Discard standard output and standard error.
     *
     * @public
     * @var string
     */
    const OUTPUT_DISCARD = 'discard';

    /**
     * @var non-empty-string
     */
    private $command;

    /**
     * @var list<string>
     */
    private $arguments;

    /**
     * @var bool
     */
    private $raw;

    /**
     * @var string
     */
    private $workingDirectory = '';

    /**
     * @var array<non-empty-string, string>
     */
    private $environment = [];

    /**
     * @var string
     */
    private $output = self::OUTPUT_CAPTURE_ALL;

    /**
     * @param non-empty-string $command
     * @param list<string> $arguments
     * @param bool $raw
     */
    private function __construct($command, array $arguments, $raw)
    {
        $this->command = $command;
        $this->arguments = $arguments;
        $this->raw = $raw;
    }

    /**
     * Create a command whose executable and arguments will be escaped.
     *
     * @param non-empty-string $executable
     * @param list<string> $arguments
     *
     * @return self
     */
    public static function create($executable, array $arguments = [])
    {
        return new self($executable, $arguments, false);
    }

    /**
     * Create a command from a command line that will be passed to the shell as is (nothing will be escaped).
     *
     * @param non-empty-string $commandLine
     *
     * @return self
     */
    public static function raw($commandLine)
    {
        return new self($commandLine, [], true);
    }

    /**
     * Is this a command line that must be passed to the shell as is?
     *
     * @return bool
     */
    public function isRaw()
    {
        return $this->raw;
    }

    /**
     * Get the executable, or the whole command line for raw commands.
     *
     * @return non-empty-string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @return list<string>
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @return string empty string for the current directory
     */
    public function getWorkingDirectory()
    {
        return $this->workingDirectory;
    }

    /**
     * @param string $value empty string for the current directory
     *
     * @return self
     */
    public function withWorkingDirectory($value)
    {
        $result = clone $this;
        $result->workingDirectory = $value;

        return $result;
    }

    /**
     * Get the environment variables to be added to the ones of the current process.
     *
     * @return array<non-empty-string, string>
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * @param array<non-empty-string, string> $value the environment variables to be added to the ones of the current process
     *
     * @return self
     */
    public function withEnvironment(array $value)
    {
        $result = clone $this;
        $result->environment = $value;

        return $result;
    }

    /**
     * @return string one of the OUTPUT_... constants
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @param string $value one of the OUTPUT_... constants
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return self
     */
    public function withOutput($value)
    {
        if (!in_array($value, [self::OUTPUT_CAPTURE_ALL, self::OUTPUT_CAPTURE_STDOUT, self::OUTPUT_CAPTURE_STDERR, self::OUTPUT_SHOW, self::OUTPUT_DISCARD], true)) {
            throw new Exception\InvalidArgument('Invalid argument $value');
        }
        $result = clone $this;
        $result->output = $value;

        return $result;
    }
}
