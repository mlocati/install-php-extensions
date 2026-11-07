<?php

namespace IPE\Test\Support;

use IPE\Extension\BuildContext;
use IPE\Filesystem;
use IPE\Option;
use IPE\Service\Container;
use Mockery\Adapter\Phpunit\MockeryTestCase;

// Fix "Class 'PHPUnit_Framework_TestCase' not found" with Mockery 0.9 and PHPUnit 5+
if (!class_exists('PHPUnit_Framework_TestCase')) {
    class_alias(\PHPUnit\Framework\TestCase::class, 'PHPUnit_Framework_TestCase');
}

abstract class TestCaseBase extends MockeryTestCase
{
    /**
     * This method is called before the first test of this test class is run.
     * Override it instead of setUpBeforeClass().
     *
     * @return void
     */
    protected static function doSetUpBeforeClass()
    {
    }

    /**
     * This method is called before each test.
     * Override it instead of setUp().
     *
     * @return void
     */
    protected function doSetUp()
    {
    }

    /**
     * This method is called after each test.
     * Override it instead of tearDown().
     *
     * @return void
     */
    protected function doTearDown()
    {
    }

    /**
     * This method is called after the last test of this test class is run.
     * Override it instead of tearDownAfterClass().
     *
     * @return void
     */
    protected static function doTearDownAfterClass()
    {
    }

    /**
     * Mockery compares objects by identity: this matcher compares the commands by value.
     *
     * @return \Mockery\Matcher\Closure
     */
    protected static function isCommand(Filesystem\Command $expected)
    {
        return \Mockery::on(static function ($actual) use ($expected) {
            // @phpstan-ignore equal.notAllowed (Mockery compares the objects by identity, we need to compare their properties)
            return $actual == $expected;
        });
    }

    /**
     * @return \IPE\Filesystem\Local
     */
    protected static function createLocalFilesystem()
    {
        return new Filesystem\Local(new \IPE\Logger(), IPE_TEST_TMPDIR);
    }

    /**
     * @param string $version
     * @param \IPE\Option\Values|null $options
     * @param non-empty-string $sourceDirectory
     *
     * @return \IPE\Extension\BuildContext
     */
    protected static function createBuildContext($version = '', $options = null, $sourceDirectory = '/tmp/ipe/ext')
    {
        return new BuildContext($sourceDirectory, $version, $options === null ? new Option\Values() : $options);
    }

    /**
     * @return \IPE\Service\Container
     */
    protected static function createApp()
    {
        $result = new Container();
        $result->instance(Container::class, $result);

        return $result;
    }
}
