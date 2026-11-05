<?php

namespace IPE\Test\Support\Log;

class Console extends \IPE\Logger\Console
{
    /**
     * @var list<array{bool, string, mixed[]}> the logged messages, as [toStdErr, message, context]
     */
    public $loggedStuff = [];

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Logger\Console::write()
     */
    protected function write($toStdErr, $message, array $context)
    {
        $this->loggedStuff[] = [$toStdErr, $this->toString($message), $context];
    }
}
