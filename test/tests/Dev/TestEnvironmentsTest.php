<?php

namespace IPE\Test\Dev;

use IPE\Dev\HomebrewFormula;
use IPE\Dev\TestEnvironment;
use IPE\Dev\TestEnvironments;
use IPE\Test\Support\TestCase;

final class TestEnvironmentsTest extends TestCase
{
    /**
     * @return void
     */
    public function testEnvironments()
    {
        $operatingSystems = TestEnvironments::getOperatingSystems();
        self::assertSame(array_values(array_unique($operatingSystems)), $operatingSystems);
        $environments = TestEnvironments::getAll();
        $osIndexes = array_map(static function (TestEnvironment $environment) use ($operatingSystems) { return array_search($environment->getOS(), $operatingSystems, true); }, $environments);
        self::assertSame([], array_keys($osIndexes, false, true), 'Unknown operating system');
        $sortedOSIndexes = $osIndexes;
        sort($sortedOSIndexes);
        self::assertSame($sortedOSIndexes, $osIndexes, 'The environments must be sorted by operating system');
        $runners = [];
        $keys = [];
        foreach ($environments as $environment) {
            $os = $environment->getOS();
            if (isset($runners[$os])) {
                self::assertSame($runners[$os], $environment->getRunsOn(), "All the environments of {$os} must use the same runner");
            } else {
                $runners[$os] = $environment->getRunsOn();
            }
            $keys[] = $os . ' ' . $environment->getName();
        }
        self::assertSame(array_values(array_unique($keys)), $keys);
        self::assertSame('macos-latest', $runners[HomebrewFormula::OS]);
    }

    /**
     * @return void
     */
    public function testFind()
    {
        $names = array_map(
            static function (TestEnvironment $environment) {
                return $environment->getOS() . ' ' . $environment->getName();
            },
            TestEnvironments::find('8.4', false)
        );
        self::assertContains('trixie php:8.4-cli-trixie', $names);
        self::assertContains('macos php@8.4', $names);
        self::assertNotContains('trixie php:8.4-zts-trixie', $names);
    }
}
