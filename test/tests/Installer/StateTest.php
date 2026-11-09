<?php

namespace IPE\Test\Installer;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Installer\State;
use IPE\PHPInfo;
use IPE\SystemDependencies\libicu;
use IPE\Test\Support\PSRLogger;
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

    /**
     * @return void
     */
    public function testShutdownCallbacks()
    {
        $target = new PSRLogger();
        $logger = new \IPE\Logger($target);
        $state = self::createState();
        $state->registerShutdownCallback(static function () use ($logger) {
            $logger->info('first');
        });
        $clone = $state->withSystemDependencyOptions([]);
        $clone->registerShutdownCallback(static function () use ($logger) {
            $logger->info('second');
            throw new Exception\IO('second failed');
        });
        $state->registerShutdownCallback(static function () use ($logger) {
            $logger->info('third');
        });
        $unregistered = static function () use ($logger) {
            $logger->info('unregistered');
        };
        $state->registerShutdownCallback($unregistered);
        self::assertTrue($clone->unregisterShutdownCallback($unregistered));
        self::assertFalse($state->unregisterShutdownCallback($unregistered));
        $state->shutdown($logger);
        $clone->shutdown($logger);
        self::assertSame(
            [
                'third',
                'second',
                'A cleanup operation failed: second failed',
                'first',
            ],
            array_column($target->loggedStuff, 'message')
        );
        self::assertSame('error', $target->loggedStuff[2]['level']);
    }

    /**
     * @return void
     */
    public function testUnregisterShutdownCallbackRegisteredTwice()
    {
        $target = new PSRLogger();
        $logger = new \IPE\Logger($target);
        $state = self::createState();
        $twice = static function () use ($logger) {
            $logger->info('twice');
        };
        $state->registerShutdownCallback($twice);
        $state->registerShutdownCallback(static function () use ($logger) {
            $logger->info('once');
        });
        $state->registerShutdownCallback($twice);
        self::assertTrue($state->unregisterShutdownCallback($twice));
        $state->shutdown($logger);
        self::assertSame(['once', 'twice'], array_column($target->loggedStuff, 'message'));
    }
}
