<?php

namespace IPE\Test\Filesystem;

use IPE\Exception;
use IPE\Filesystem\Command;
use IPE\Test\Support\TestCase;

final class CommandTest extends TestCase
{
    /**
     * @return void
     */
    public function testCreate()
    {
        $command = Command::create('apt-get', ['update', '-qy']);
        self::assertFalse($command->isRaw());
        self::assertSame('apt-get', $command->getCommand());
        self::assertSame(['update', '-qy'], $command->getArguments());
        self::assertSame('', $command->getWorkingDirectory());
        self::assertSame([], $command->getEnvironment());
        self::assertSame(Command::OUTPUT_CAPTURE_ALL, $command->getOutput());
        self::assertSame([], Command::create('ls')->getArguments());
    }

    /**
     * @return void
     */
    public function testRaw()
    {
        $command = Command::raw('a ; b');
        self::assertTrue($command->isRaw());
        self::assertSame('a ; b', $command->getCommand());
        self::assertSame([], $command->getArguments());
    }

    /**
     * @return void
     */
    public function testInstancesAreImmutable()
    {
        $original = Command::create('ls');
        $modified = $original
            ->withWorkingDirectory('/tmp')
            ->withEnvironment(['FOO' => 'bar'])
            ->withOutput(Command::OUTPUT_SHOW)
        ;
        self::assertSame('/tmp', $modified->getWorkingDirectory());
        self::assertSame(['FOO' => 'bar'], $modified->getEnvironment());
        self::assertSame(Command::OUTPUT_SHOW, $modified->getOutput());
        self::assertSame('', $original->getWorkingDirectory());
        self::assertSame([], $original->getEnvironment());
        self::assertSame(Command::OUTPUT_CAPTURE_ALL, $original->getOutput());
    }

    /**
     * @return void
     */
    public function testInvalidOutput()
    {
        try {
            Command::create('ls')->withOutput('nope');
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidArgument $x) {
            self::assertNotSame('', $x->getMessage());
        }
    }
}
