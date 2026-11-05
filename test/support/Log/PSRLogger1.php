<?php

namespace IPE\Test\Support\Log;

use Psr\Log\LoggerTrait;

class PSRLogger1 extends PSRLoggerBase
{
    use LoggerTrait;

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::log()
     *
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        $this->doLog($level, $message, $context);
    }
}
