<?php

namespace IPE\Test\Extension;

use IPE\Exception;
use IPE\Extension\Registry;
use IPE\Extensions\apcu;
use IPE\Extensions\bcmath;
use IPE\Extensions\ev;
use IPE\Extensions\zip;
use IPE\Filesystem\Downloader;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Service\Container;
use IPE\Test\Support\ResolverTest\FakeExtension;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class RegistryTest extends TestCase
{
    /**
     * @return void
     */
    public function testRegisteringClassesDoesNotCreateInstances()
    {
        $registry = (new Registry(new Container()))->registerDefaultExtensions()->registerClass(FakeExtension::class);
        self::assertContains(apcu::class, $registry->getExtensionClasses());
        self::assertContains(FakeExtension::class, $registry->getExtensionClasses());
        self::assertNull($registry->getExtensionByHandle('not-an-extension'));
        self::assertNull($registry->getExtensionByPECLHandle('not-an-extension'));
        self::assertNull($registry->getExtensionByInstalledName('not-an-extension'));
    }

    /**
     * @return void
     */
    public function testInstancesAreCreatedOnceWhenNeeded()
    {
        $app = self::createApp();
        $app->instance(PHPInfo::class, M::mock(PHPInfo::class));
        $app->instance(OS::class, M::mock(OS::class));
        $app->instance(Downloader::class, M::mock(Downloader::class));
        $registry = (new Registry($app))->registerDefaultExtensions();
        $extension = $registry->getExtensionByHandle(apcu::getHandle());
        self::assertInstanceOf(apcu::class, $extension);
        self::assertSame($extension, $registry->getExtensionByHandle(apcu::getHandle()));
        self::assertSame($extension, $registry->getExtensionByPECLHandle('apcu'));
    }

    /**
     * @return void
     */
    public function testComposerPackageNames()
    {
        $app = self::createApp();
        $app->instance(PHPInfo::class, M::mock(PHPInfo::class));
        $app->instance(OS::class, M::mock(OS::class));
        $app->instance(Downloader::class, M::mock(Downloader::class));
        $registry = (new Registry($app))->registerDefaultExtensions();
        self::assertInstanceOf(apcu::class, $registry->getExtensionByComposerPackageName('APCu/apcu'));
        self::assertInstanceOf(bcmath::class, $registry->getExtensionByComposerPackageName('php/bcmath'));
        self::assertInstanceOf(bcmath::class, $registry->getExtensionByComposerPackageName('PHP/BCMath'));
        // Both published on Packagist and bundled in PHP
        self::assertInstanceOf(zip::class, $registry->getExtensionByComposerPackageName('pecl/zip'));
        self::assertInstanceOf(zip::class, $registry->getExtensionByComposerPackageName('php/zip'));
        // Not bundled in PHP
        self::assertNull($registry->getExtensionByComposerPackageName('php/apcu'));
        self::assertNull($registry->getExtensionByComposerPackageName('php/not-an-extension'));
        self::assertNull($registry->getExtensionByComposerPackageName('other/bcmath'));
        self::assertSame(['apcu/apcu'], Registry::getComposerPackageNames(apcu::class));
        self::assertSame(['php/bcmath'], Registry::getComposerPackageNames(bcmath::class));
        self::assertSame(['pecl/zip', 'php/zip'], Registry::getComposerPackageNames(zip::class));
        self::assertSame([], Registry::getComposerPackageNames(ev::class));
    }

    /**
     * @return void
     */
    public function testRegisteredInstancesAreKept()
    {
        $extension = new FakeExtension(M::mock(PHPInfo::class), M::mock(OS::class));
        $registry = (new Registry(new Container()))->registerExtension($extension);
        self::assertSame([FakeExtension::class], $registry->getExtensionClasses());
        self::assertSame($extension, $registry->getExtensionByHandle(FakeExtension::getHandle()));
    }

    /**
     * @return void
     */
    public function testDuplicatedHandle()
    {
        $registry = (new Registry(new Container()))->registerClass(FakeExtension::class);
        $this->expectException(Exception\InvalidArgument::class);
        $registry->registerClass(FakeExtension::class);
    }
}
