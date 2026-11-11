<?php

namespace IPE\Test\SystemDependency;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Installer\State;
use IPE\Option\Validator;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Service\Container;
use IPE\SystemDependencies\libaspell;
use IPE\SystemDependencies\libicu;
use IPE\SystemDependency;
use IPE\SystemDependency\Registry;
use IPE\Test\Support\SystemDependencyTest\CircularDependencyA;
use IPE\Test\Support\SystemDependencyTest\CircularDependencyB;
use IPE\Test\Support\SystemDependencyTest\FakeLibrary;
use IPE\Test\Support\SystemDependencyTest\FakeThirdPartyLibrary;
use IPE\Test\Support\SystemDependencyTest\FakeTool;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class RegistryTest extends TestCase
{
    /**
     * A container where the dependencies can be built for the specified OS.
     *
     * @return \IPE\Service\Container
     */
    private static function createContainer(OS\Posix $os)
    {
        $app = self::createApp();
        $app->instance(PHPInfo::class, M::mock(PHPInfo::class));
        $app->instance(OS\Posix::class, $os);
        $app->instance(Filesystem::class, M::mock(Filesystem::class));
        $app->instance(Filesystem\Downloader::class, M::mock(Filesystem\Downloader::class));
        $app->instance(Filesystem\ArchiveExtractor::class, M::mock(Filesystem\ArchiveExtractor::class));

        return $app;
    }

    /**
     * @return void
     */
    public function testDefaultDependencies()
    {
        $registry = (new Registry(self::createContainer(M::mock(OS\Debian::class)), new Validator(new \IPE\Logger()), new \IPE\Logger()))->registerDefaultDependencies();
        $handles = [];
        foreach ($registry->getDependencyClasses() as $class) {
            $handle = $class::getHandle();
            self::assertMatchesRegularExpression(SystemDependency::RX_VALID_HANDLE, $handle);
            $dependency = $registry->getDependencyByHandle($handle);
            self::assertInstanceOf($class, $dependency);
            self::assertSame($dependency, $registry->getDependencyByHandle($handle));
            $handles[] = $handle;
        }
        self::assertContains('lib-mcrypt', $handles);
        self::assertNull($registry->getDependencyByHandle('not-a-dependency'));
        // One class per file: the number of dependencies must match the number of files
        $files = scandir(IPE_ROOT_DIR . '/src/SystemDependencies') ?: [];
        $phpFiles = array_filter($files, static function ($file) { return substr($file, -4) === '.php'; });
        self::assertCount(count($phpFiles), $handles);
    }

    /**
     * @return void
     */
    public function testRegisteringClassesDoesNotCreateInstances()
    {
        $app = M::mock(Container::class);
        $app->shouldNotReceive('make');
        $registry = (new Registry($app, new Validator(new \IPE\Logger()), new \IPE\Logger()))->registerDefaultDependencies()->registerClass(FakeThirdPartyLibrary::class);
        self::assertContains(FakeThirdPartyLibrary::class, $registry->getDependencyClasses());
        $registry->setOptions(new State(M::mock(PHPInfo::class), M::mock(Filesystem::class)));
    }

    /**
     * @return void
     */
    public function testBuiltDependenciesReceiveTheOptions()
    {
        $os = M::mock(OS\Alpine::class);
        $os->shouldReceive('getNumericVersion')->andReturn(316);
        $state = new State(M::mock(PHPInfo::class), M::mock(Filesystem::class));
        $registry = (new Registry(self::createContainer($os), new Validator(new \IPE\Logger()), new \IPE\Logger()))->registerClass(libicu::class);
        $registry->setOptions($state->withSystemDependencyOptions([libicu::HANDLE => [libicu::OPTION_EN_ONLY => true]]));
        $packages = $registry->collectSystemPackages($registry->resolveDependencies([libicu::HANDLE]), $state);
        self::assertSame(['icu-libs'], $packages->getPersistent());
        $registry->setOptions($state);
        $packages = $registry->collectSystemPackages($registry->resolveDependencies([libicu::HANDLE]), $state);
        self::assertSame(['icu-libs', 'icu-data-full'], $packages->getPersistent());
    }

    /**
     * @return void
     */
    public function testDefaultsAreUsedWithoutSetOptions()
    {
        $os = M::mock(OS\Debian::class);
        $os->shouldReceive('getNumericVersion')->andReturn(OS\Debian::BOOKWORM);
        $registry = (new Registry(self::createContainer($os), new Validator(new \IPE\Logger()), new \IPE\Logger()))->registerClass(FakeThirdPartyLibrary::class)->registerClass(libaspell::class);
        $dependency = $registry->getDependencyByHandle(FakeThirdPartyLibrary::getHandle());
        self::assertInstanceOf(FakeThirdPartyLibrary::class, $dependency);
        self::assertSame('free', $dependency->getEdition());
        $packages = $registry->collectSystemPackages($registry->resolveDependencies([libaspell::HANDLE]), M::mock(State::class));
        self::assertContains('aspell-en', $packages->getPersistent());
    }

    /**
     * @return void
     */
    public function testOptionsOfThirdPartyDependency()
    {
        $registry = (new Registry(self::createContainer(M::mock(OS\Debian::class)), new Validator(new \IPE\Logger()), new \IPE\Logger()))->registerClass(FakeThirdPartyLibrary::class);
        $state = new State(M::mock(PHPInfo::class), M::mock(Filesystem::class));
        $registry->setOptions($state);
        $dependency = $registry->getDependencyByHandle(FakeThirdPartyLibrary::getHandle());
        self::assertInstanceOf(FakeThirdPartyLibrary::class, $dependency);
        self::assertSame('free', $dependency->getEdition());
        $registry->setOptions($state->withSystemDependencyOptions([FakeThirdPartyLibrary::getHandle() => ['edition' => 'pro']]));
        $dependency = $registry->getDependencyByHandle(FakeThirdPartyLibrary::getHandle());
        self::assertInstanceOf(FakeThirdPartyLibrary::class, $dependency);
        self::assertSame('pro', $dependency->getEdition());
    }

    /**
     * @return void
     */
    public function testInvalidOption()
    {
        $registry = (new Registry(self::createContainer(M::mock(OS\Debian::class)), new Validator(new \IPE\Logger()), new \IPE\Logger()))->registerClass(libicu::class);
        $state = new State(M::mock(PHPInfo::class), M::mock(Filesystem::class));
        $this->expectException(Exception\InvalidOptionValues::class);
        $this->expectExceptionMessage("does not accept the option 'foo'");
        $registry->setOptions($state->withSystemDependencyOptions([libicu::HANDLE => ['foo' => true]]));
    }

    /**
     * @return void
     */
    public function testSettingOptionsKeepsTheRegisteredInstances()
    {
        $app = self::createContainer(M::mock(OS\Debian::class));
        $tool = $app->build(FakeTool::class);
        $registry = new Registry($app, new Validator(new \IPE\Logger()), new \IPE\Logger());
        $registry->registerDependency($tool);
        $registry->setOptions(new State(M::mock(PHPInfo::class), M::mock(Filesystem::class)));
        self::assertSame($tool, $registry->getDependencyByHandle(FakeTool::getHandle()));
    }

    /**
     * @return void
     */
    public function testRegisteredClassIsCreatedOnce()
    {
        $registry = (new Registry(self::createContainer(M::mock(OS\Debian::class)), new Validator(new \IPE\Logger()), new \IPE\Logger()))->registerClass(FakeThirdPartyLibrary::class);
        $dependency = $registry->getDependencyByHandle(FakeThirdPartyLibrary::getHandle());
        self::assertInstanceOf(FakeThirdPartyLibrary::class, $dependency);
        self::assertSame($dependency, $registry->getDependencyByHandle(FakeThirdPartyLibrary::getHandle()));
    }

    /**
     * @return void
     */
    public function testPackagesDependOnTheOS()
    {
        $registry = (new Registry(self::createContainer(M::mock(OS\Debian::class)), new Validator(new \IPE\Logger()), new \IPE\Logger()))->registerDefaultDependencies();
        $packages = $registry->collectSystemPackages($registry->resolveDependencies(['lib-mcrypt', 'unknown-dependency', 'lib-mcrypt']), M::mock(State::class));
        self::assertSame(['libmcrypt4'], $packages->getPersistent());
        self::assertSame(['libmcrypt-dev'], $packages->getVolatile());

        $registry = (new Registry(self::createContainer(M::mock(OS\Alpine::class)), new Validator(new \IPE\Logger()), new \IPE\Logger()))->registerDefaultDependencies();
        $packages = $registry->collectSystemPackages($registry->resolveDependencies(['lib-mcrypt']), M::mock(State::class));
        self::assertSame(['libmcrypt'], $packages->getPersistent());
        self::assertSame(['libmcrypt-dev'], $packages->getVolatile());
        self::assertTrue($registry->collectSystemPackages($registry->resolveDependencies([]), M::mock(State::class))->isEmpty());
    }

    /**
     * @return void
     */
    public function testUnsupportedOS()
    {
        $os = M::mock(OS\Posix::class);
        $os->shouldReceive('getDisplayName')->andReturn('FreeBSD 14');
        $registry = (new Registry(self::createContainer($os), new Validator(new \IPE\Logger()), new \IPE\Logger()))->registerDefaultDependencies();
        try {
            $registry->collectSystemPackages($registry->resolveDependencies(['lib-mcrypt']), M::mock(State::class));
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedOS $x) {
            self::assertStringContainsString('FreeBSD 14', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testRequiredDependenciesComeFirstAndInstancesAreShared()
    {
        $app = self::createContainer(M::mock(OS\Debian::class));
        $registry = new Registry($app, new Validator(new \IPE\Logger()), new \IPE\Logger());
        $tool = $app->build(FakeTool::class);
        $registry->registerDependency($tool);
        $registry->registerDependency($app->build(FakeLibrary::class));
        $resolved = $registry->resolveDependencies(['lib-fake', 'fake-tool']);
        self::assertSame(['fake-tool', 'lib-fake'], array_map(static function (SystemDependency $d) { return $d::getHandle(); }, $resolved));
        self::assertSame($tool, $resolved[0]);
        $packages = $registry->collectSystemPackages($resolved, M::mock(State::class));
        self::assertSame(['libfake'], $packages->getPersistent());
        self::assertSame(['fake-tool-pkg', 'libfake-dev'], $packages->getVolatile());
        $state = M::mock(State::class);
        foreach ($resolved as $dependency) {
            $dependency->install($state);
        }
        foreach ($resolved as $dependency) {
            $dependency->finalize($state);
        }
        self::assertSame(['install', 'finalize'], $tool->log);
    }

    /**
     * @return void
     */
    public function testCircularDependencies()
    {
        $app = self::createContainer(M::mock(OS\Debian::class));
        $registry = new Registry($app, new Validator(new \IPE\Logger()), new \IPE\Logger());
        $registry->registerDependency($app->build(CircularDependencyA::class));
        $registry->registerDependency($app->build(CircularDependencyB::class));
        try {
            $registry->resolveDependencies(['fake-a']);
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidArgument $x) {
            self::assertStringContainsString('fake-a -> fake-b -> fake-a', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testDuplicatedHandle()
    {
        $app = self::createContainer(M::mock(OS\Debian::class));
        $registry = new Registry($app, new Validator(new \IPE\Logger()), new \IPE\Logger());
        $registry->registerDependency($app->build(FakeTool::class));
        try {
            $registry->registerDependency($app->build(FakeTool::class));
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidArgument $x) {
            self::assertStringContainsString('fake-tool', $x->getMessage());
        }
    }
}
