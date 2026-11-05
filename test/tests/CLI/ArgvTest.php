<?php

namespace IPE\Test\CLI;

use IPE\CLI\Argv;
use IPE\Test\Support\TestCase;

final class ArgvTest extends TestCase
{
    /**
     * @return \IPE\CLI\Argv
     */
    private static function createArgv()
    {
        return (new Argv())->addDefinitions([
            new Argv\Option\Definition\Flag('flag', 'A flag'),
            new Argv\Option\Definition\StringValue('string', 'A string', 'default'),
            new Argv\Option\Definition\IntValue('int', 'An integer'),
            new Argv\Option\Definition\IntValue('int-with-default', 'An integer with a default value', 3),
            new Argv\Option\Definition\StringList('list', 'A list'),
        ]);
    }

    /**
     * @return void
     */
    public function testDefaultValues()
    {
        $argv = self::createArgv()->addArgv(['script']);
        self::assertFalse($argv->getFlag('flag'));
        self::assertSame('default', $argv->getString('string'));
        self::assertNull($argv->getInt('int'));
        self::assertSame(3, $argv->getInt('int-with-default'));
        self::assertSame([], $argv->getStringList('list'));
    }

    /**
     * @return void
     */
    public function testSpecifiedValues()
    {
        $argv = self::createArgv()->addArgv(['script', '--flag', '--list=a', '--string=foo', '--int', '5', '--list', 'b', 'argument']);
        self::assertSame(['a', 'b'], $argv->getStringList('list'));
        self::assertTrue($argv->getFlag('flag'));
        self::assertSame('foo', $argv->getString('string'));
        self::assertSame(5, $argv->getInt('int'));
        self::assertSame(['argument'], $argv->getArguments());
    }

    /**
     * @return void
     */
    public function testWrongType()
    {
        $this->expectException(\IPE\Exception\InvalidArgument::class);
        self::createArgv()->getString('flag');
    }

    /**
     * @return void
     */
    public function testEmptyListValue()
    {
        $this->expectException(\IPE\Exception\InvalidOption::class);
        self::createArgv()->addArgv(['script', '--list=']);
    }

    /**
     * @return void
     */
    public function testScriptArgv()
    {
        $hadArgv = array_key_exists('argv', $_SERVER);
        $originalArgv = $hadArgv ? $_SERVER['argv'] : null;
        try {
            $_SERVER['argv'] = [2 => 'script', 3 => 1, 4 => 'argument'];
            self::assertSame(['script', 'argument'], Argv::getScriptArgv());
            unset($_SERVER['argv']);
            self::assertSame([''], Argv::getScriptArgv());
        } finally {
            if ($hadArgv) {
                $_SERVER['argv'] = $originalArgv;
            }
        }
    }
}
