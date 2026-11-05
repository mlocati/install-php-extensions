<?php

namespace IPE\Test\Installer;

use IPE\Exception;
use IPE\Installer\BuildOptions;
use IPE\Test\Support\TestCase;

final class BuildOptionsTest extends TestCase
{
    /**
     * @return void
     */
    public function testDefaults()
    {
        $options = new BuildOptions();
        self::assertSame(0, $options->getParallelJobs());
        self::assertSame(BuildOptions::TYPE_OPTIMIZED, $options->getType());
    }

    /**
     * @return void
     */
    public function testInstancesAreImmutable()
    {
        $original = new BuildOptions();
        $modified = $original->withParallelJobs(3)->withType(BuildOptions::TYPE_DEBUG);
        self::assertSame(3, $modified->getParallelJobs());
        self::assertSame(BuildOptions::TYPE_DEBUG, $modified->getType());
        self::assertSame(0, $original->getParallelJobs());
        self::assertSame(BuildOptions::TYPE_OPTIMIZED, $original->getType());
    }

    /**
     * @return void
     */
    public function testInvalidValues()
    {
        $options = new BuildOptions();
        try {
            $options->withParallelJobs(-1);
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidArgument $x) {
            self::assertNotSame('', $x->getMessage());
        }
        try {
            $options->withType('nope');
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidArgument $x) {
            self::assertNotSame('', $x->getMessage());
        }
    }
}
