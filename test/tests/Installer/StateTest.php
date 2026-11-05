<?php

namespace IPE\Test\Installer;

use IPE\Filesystem;
use IPE\Installer\State;
use IPE\PHPInfo;
use IPE\SystemDependencies\libicu;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class StateTest extends TestCase
{
    /**
     * @return \IPE\Installer\State
     */
    private static function createState()
    {
        return new State(M::mock(PHPInfo::class), M::mock(Filesystem::class));
    }

    /**
     * @return void
     */
    public function testSystemDependencyUserOptions()
    {
        $state = self::createState()->withSystemDependencyOptions([libicu::HANDLE => [libicu::OPTION_EN_ONLY => true, 'not-validated-here' => 'value']]);
        self::assertSame([libicu::OPTION_EN_ONLY => true, 'not-validated-here' => 'value'], $state->getSystemDependencyUserOptions(libicu::HANDLE));
        self::assertSame([libicu::HANDLE], $state->getHandlesWithSystemDependencyOptions());
    }

    /**
     * @return void
     */
    public function testSystemDependencyUserOptionsNotSpecified()
    {
        $state = self::createState();
        self::assertSame([], $state->getSystemDependencyUserOptions(libicu::HANDLE));
        self::assertSame([], $state->getHandlesWithSystemDependencyOptions());
    }
}
