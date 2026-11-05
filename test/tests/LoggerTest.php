<?php

namespace IPE\Test;

use IPE\Exception;
use IPE\Logger;
use IPE\Test\Support\Log\Console;
use IPE\Test\Support\PSRLogger;
use IPE\Test\Support\TestCase;

final class LoggerTest extends TestCase
{
    /**
     * @return void
     */
    public function testContainerProvidesAlwaysTheSameLogger()
    {
        $app = \IPE\createApp();
        $logger = $app->make(Logger::class);
        self::assertNull($logger->getTarget());
        // Without a target, the messages are discarded
        $logger->info('Hello');
        $target = new PSRLogger();
        self::assertSame($logger, $logger->setTarget($target));
        self::assertSame($logger, $app->make(Logger::class));
        self::assertSame($target, $app->make(Logger::class)->getTarget());
    }

    /**
     * @return void
     */
    public function testForwardsToPSRLogger()
    {
        $target = new PSRLogger();
        $logger = new Logger($target);
        $logger->info('Hello', ['key' => 'value']);
        $logger->setTarget(null);
        $logger->info('Discarded');
        $logger->setTarget($target);
        $logger->log('debug', 'World');
        self::assertSame(
            [
                ['level' => 'info', 'message' => 'Hello', 'context' => ['key' => 'value']],
                ['level' => 'debug', 'message' => 'World', 'context' => []],
            ],
            $target->loggedStuff
        );
    }

    /**
     * @return void
     */
    public function testForwardsToConsole()
    {
        $target = new Console(false);
        $logger = new Logger($target);
        $logger->warning('Warning');
        $logger->debug('Not verbose');
        self::assertSame([[true, 'Warning', []]], $target->loggedStuff);
    }

    /**
     * @return void
     */
    public function testInvalidLevel()
    {
        $logger = new Logger(new Console());
        $this->expectException(Exception\InvalidArgument::class);
        $logger->log('invalid', 'Hello');
    }
}
