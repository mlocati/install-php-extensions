<?php

namespace IPE\Test\Support;

abstract class TestCase6 extends TestCaseBase
{
    public final static function setupBeforeClass()
    {
        static::doSetUpBeforeClass();
    }

    public final function setUp()
    {
        static::doSetUp();
    }

    public final function tearDown()
    {
        static::doTearDown();
    }

    public final static function tearDownAfterClass()
    {
        static::doTearDownAfterClass();
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public final static function assertIsInt($actual, $message = '')
    {
        static::assertInternalType('int', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public final static function assertIsBool($actual, $message = '')
    {
        static::assertInternalType('bool', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public final static function assertIsString($actual, $message = '')
    {
        static::assertInternalType('string', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public final static function assertIsArray($actual, $message = '')
    {
        static::assertInternalType('array', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public final static function assertStringContainsString($needle, $haystack, $message = '')
    {
        static::assertContains($needle, $haystack, $message);
    }

    /**
     * @param string $needle
     * @param string $haystack
     * @param string $message
     *
     * @return void
     */
    public final static function assertStringNotContainsString($needle, $haystack, $message = '')
    {
        static::assertNotContains($needle, $haystack, $message);
    }

    /**
     * @param string $pattern
     * @param string $string
     * @param string $message
     *
     * @return void
     */
    public final static function assertMatchesRegularExpression($pattern, $string, $message = '')
    {
        static::assertRegExp($pattern, $string, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public final static function assertIsCallable($actual, $message = '')
    {
        static::assertInternalType('callable', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public final static function assertEqualsCanonicalizing($expected, $actual, $message = '')
    {
        static::assertEquals($expected, $actual, $message, 0.0, 10, true);
    }
}
