<?php

namespace IPE\Test\GitHost;

use IPE\Exception;
use IPE\Extension;
use IPE\GitHost\Registry;
use IPE\GitHosts;
use IPE\Test\Support\TestCase;

final class RegistryTest extends TestCase
{
    /**
     * @return void
     */
    public function testHandles()
    {
        $registry = (new Registry())->registerDefaultHosts();
        $handles = [];
        foreach ($registry->getHosts() as $host) {
            $handles[] = $host::getHandle();
            self::assertSame($host, $registry->getByHandle($host::getHandle()));
            self::assertTrue($host->isValidRepository($host->getRepositoryExample()), "The repository example of {$host->getName()} is not valid");
        }
        self::assertSame(['gh', 'gl', 'cb', 'bb'], $handles);
        self::assertNull($registry->getByHandle('xdebug'));
    }

    /**
     * The arguments like xdebug:^3.3 are parsed as handle:version after the shortcuts.
     *
     * @return void
     */
    public function testNoExtensionHasTheHandleOfAHost()
    {
        $extensionHandles = [];
        foreach ((new Extension\Registry(self::createApp()))->registerDefaultExtensions()->getExtensionClasses() as $class) {
            $extensionHandles[] = $class::getHandle();
        }
        foreach ((new Registry())->registerDefaultHosts()->getHosts() as $host) {
            self::assertNotContains($host::getHandle(), $extensionHandles);
        }
    }

    /**
     * @return void
     */
    public function testDuplicatedHandle()
    {
        $registry = (new Registry())->registerDefaultHosts();
        $this->expectException(Exception\InvalidArgument::class);
        $this->expectExceptionMessage("The handle 'gh' of GitHub is already used by GitHub");
        $registry->register(new GitHosts\GitHub());
    }

    /**
     * @return void
     */
    public function testSingletonInApp()
    {
        $app = \IPE\createApp();
        $extended = 0;
        $app->extend(Registry::class, static function (Registry $registry) use (&$extended) {
            $extended++;

            return $registry;
        });
        $registry = $app->make(Registry::class);
        self::assertSame($registry, $app->make(Registry::class));
        self::assertSame(1, $extended);
        self::assertCount(4, $registry->getHosts());
    }
}
