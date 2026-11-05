<?php

namespace IPE\Logger;

use IPE\Exception;
use IPE\Service\JSON;

class Console
{
    /**
     * @var bool
     */
    protected $verbose;

    /**
     * @param bool $verbose
     */
    public function __construct($verbose = false)
    {
        $this->verbose = $verbose;
    }

    /**
     * @return bool
     */
    public function isVerbose()
    {
        return $this->verbose;
    }

    /**
     * @param string $level
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @throws \IPE\Exception\InvalidArgument if $level is not valid
     *
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        if (in_array($level, [
            'emergency',
            'alert',
            'critical',
            'error',
            'warning',
        ], true)) {
            $this->write(true, $message, $context);
        } elseif (in_array($level, [
            'notice',
            'info',
        ], true)) {
            $this->write(false, $message, $context);
        } elseif (in_array($level, [
            'debug',
        ], true)) {
            if ($this->verbose) {
                $this->write(false, $message, $context);
            }
        } else {
            throw new Exception\InvalidArgument('Invalid $level');
        }
    }

    /**
     * @param bool $toStdErr
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return void
     */
    protected function write($toStdErr, $message, array $context)
    {
        $serialized = $this->toString($message) . "\n";
        if ($this->verbose && $context !== []) {
            try {
                $serialized .= JSON::encode($context, true) . "\n";
            } catch (Exception\JSON $x) {
                // The context may contain command output that is not valid UTF-8
                $serialized .= print_r($context, true) . "\n";
            }
        }
        if ($toStdErr) {
            flush();
            fwrite(STDERR, $serialized);
            fflush(STDERR);
        } else {
            echo $serialized;
        }
    }

    /**
     * @param string|\Stringable $message
     *
     * @return string
     */
    protected function toString($message)
    {
        if ($message instanceof Exception\UserMessage) {
            return $message->getMessage();
        }
        if ($message instanceof Exception) {
            return $this->isVerbose() ? (string) $message : $message->getMessage();
        }

        return (string) $message;
    }
}
