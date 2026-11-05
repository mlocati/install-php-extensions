<?php

namespace IPE\Test\Logger;

use IPE\Exception;
use IPE\Logger\Console as ActualConsole;
use IPE\Test\Support\Log\Console as FakeConsole;
use IPE\Test\Support\TestCase;

final class ConsoleTest extends TestCase
{
    /**
     * @return void
     */
    public function testConsole()
    {
        $console = new FakeConsole();
        self::assertFalse($console->isVerbose());
        $console->log('debug', 'Debug');
        $console->log('info', 'Info', ['foo' => 'bar']);
        $console->log('error', 'Error', ['foo' => 'baz']);
        self::assertSame([
            [false, 'Info', ['foo' => 'bar']],
            [true, 'Error', ['foo' => 'baz']],
        ], $console->loggedStuff);

        $console = new FakeConsole(false);
        self::assertFalse($console->isVerbose());
        $console->log('debug', 'Debug', ['foo' => 'bar']);
        $console->log('info', 'Info', ['foo' => 'baz']);
        $console->log('error', 'Error', ['foo' => 'quux']);
        self::assertSame([
            [false, 'Info', ['foo' => 'baz']],
            [true, 'Error', ['foo' => 'quux']],
        ], $console->loggedStuff);

        $console = new FakeConsole(true);
        self::assertTrue($console->isVerbose());
        $console->log('debug', 'Debug', ['foo' => 'bar']);
        $console->log('info', 'Info', ['foo' => 'baz']);
        $console->log('error', 'Error', ['foo' => 'quux']);
        self::assertSame([
            [false, 'Debug', ['foo' => 'bar']],
            [false, 'Info', ['foo' => 'baz']],
            [true, 'Error', ['foo' => 'quux']],
        ], $console->loggedStuff);
    }

    /**
     * @return void
     */
    public function testInvalidLevel()
    {
        $console = new FakeConsole();
        $this->expectException(Exception\InvalidArgument::class);
        $console->log('verbose', 'Message');
    }

    /**
     * @return void
     */
    public function testActualConsole()
    {
        $started = function_exists('ob_start') ? ob_start() : false;
        if ($started === false) {
            self::markTestSkipped('Failed to start output buffering');
        }
        try {
            $console = new ActualConsole();
            self::assertFalse($console->isVerbose());
            $console->log('debug', 'Debug');
            $console->log('info', 'Info', ['foo' => 'bar']);
            self::assertSame("Info\n", ob_get_contents());
            ob_clean();

            $console = new ActualConsole(false);
            self::assertFalse($console->isVerbose());
            $console->log('debug', 'Debug');
            $console->log('info', 'Info', ['foo' => 'bar']);
            self::assertSame("Info\n", ob_get_contents());
            ob_clean();

            $console = new ActualConsole(true);
            self::assertTrue($console->isVerbose());
            $console->log('debug', 'Debug');
            $console->log('info', 'Info', ['foo' => 'bar']);
            self::assertSame(ob_get_contents(), "Debug\nInfo\n{\n    \"foo\": \"bar\"\n}\n");
            ob_clean();
        } finally {
            ob_end_clean();
        }
    }
}
