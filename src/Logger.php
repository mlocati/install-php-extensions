<?php

namespace IPE;

/**
 * The logger used by IPE (a singleton in the container): it forwards the messages to a target that can be changed at any time.
 *
 * It has the same methods as the PSR-3 LoggerInterface, without requiring psr/log at runtime.
 */
final class Logger
{
    /**
     * @var \IPE\Logger\Console|\Psr\Log\LoggerInterface|null
     */
    private $target;

    /**
     * @param \IPE\Logger\Console|\Psr\Log\LoggerInterface|null $target NULL to discard the messages
     */
    public function __construct($target = null)
    {
        $this->target = $target;
    }

    /**
     * @return \IPE\Logger\Console|\Psr\Log\LoggerInterface|null
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * Set the logger that receives the messages.
     *
     * @param \IPE\Logger\Console|\Psr\Log\LoggerInterface|null $target NULL to discard the messages
     *
     * @return $this
     */
    public function setTarget($target)
    {
        $this->target = $target;

        return $this;
    }

    /**
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function emergency($message, array $context = [])
    {
        $this->log('emergency', $message, $context);
    }

    /**
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function alert($message, array $context = [])
    {
        $this->log('alert', $message, $context);
    }

    /**
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function critical($message, array $context = [])
    {
        $this->log('critical', $message, $context);
    }

    /**
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function error($message, array $context = [])
    {
        $this->log('error', $message, $context);
    }

    /**
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function warning($message, array $context = [])
    {
        $this->log('warning', $message, $context);
    }

    /**
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function notice($message, array $context = [])
    {
        $this->log('notice', $message, $context);
    }

    /**
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function info($message, array $context = [])
    {
        $this->log('info', $message, $context);
    }

    /**
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function debug($message, array $context = [])
    {
        $this->log('debug', $message, $context);
    }

    /**
     * @param string $level one of the PSR-3 levels
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @throws \IPE\Exception\InvalidArgument if $level is not valid
     *
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        if ($this->target === null) {
            return;
        }
        try {
            $this->target->log($level, $message, $context);
        } catch (\Psr\Log\InvalidArgumentException $x) {
            throw new Exception\InvalidArgument($x->getMessage());
        }
    }
}
