<?php

namespace IPE\Test\Support\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

abstract class PSRLoggerBase implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @var list<array{level: non-empty-string, message: string|\Stringable, context: mixed[]}>
     */
    public $loggedStuff = [];

    /**
     * @param non-empty-string $level
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     */
    protected final function doLog($level, $message, array $context)
    {
        $this->loggedStuff[] = compact('level', 'message', 'context');
    }
}
