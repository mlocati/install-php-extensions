<?php

namespace IPE\Test\Support\Log;

use Psr\Log\LoggerTrait;

class PSRLogger23 extends PSRLoggerBase
{
    use LoggerTrait;

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::log($level, $context)
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->doLog($level, $message, $context);
    }
}
