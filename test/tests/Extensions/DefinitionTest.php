<?php

namespace IPE\Test\Extensions;

use Composer\Semver\VersionParser;
use IPE\Extension;
use IPE\Filesystem\Downloader;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Service\Container;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class DefinitionTest extends TestCase
{
    /**
     * @dataProvider provideExtensionDefinitions
     *
     * @param class-string<\IPE\Extension> $class
     *
     * @return void
     */
    public function testExtensionDefinition($class)
    {
        $handle = $class::getHandle();
        self::assertMatchesRegularExpression(Extension::RX_VALID_HANDLE, $handle);
        foreach ($class::getSupportedPHPVersions() as $phpVersion) {
            self::assertMatchesRegularExpression('/^[5-9]\.\d+$/D', $phpVersion);
        }
        if (is_subclass_of($class, Extension\DeclaresUnsupportedEnvironments::class)) {
            $unsupportedEnvironment = new Extension\UnsupportedEnvironment(new OS\Factory(new Container(), new \IPE\Logger()));
            foreach ($class::getUnsupportedEnvironments() as $environment => $reason) {
                self::assertTrue($unsupportedEnvironment->isValid($environment), $environment);
                self::assertMatchesRegularExpression('/\S/', $reason);
            }
        }
        foreach ($class::getInstalledNames() as $installedName) {
            self::assertMatchesRegularExpression('#^\w+( \w+)*$#D', $installedName);
        }
        $someSourceFound = false;
        if (is_subclass_of($class, Extension\AvailableAsComposerPackage::class)) {
            $someSourceFound = true;
            $composerPackageName = $class::getComposerPackageName();
            self::assertMatchesRegularExpression(Extension\Version\Lister\Composer::RX_VALID_FULLNAME, $composerPackageName);
        }
        if (is_subclass_of($class, Extension\AvailableOnPECL::class)) {
            $someSourceFound = true;
            $peclHandle = $class::getPECLHandle();
            self::assertMatchesRegularExpression(Extension\Version\Lister\Pecl::RX_VALID_NAME, $peclHandle);
            self::assertMatchesRegularExpression('/^[A-Z][A-Za-z0-9 ]*$/D', $class::getPECLCategory());
        }
        if (is_subclass_of($class, Extension\BundledInPHPSource::class)) {
            $someSourceFound = true;
            $extDirectoryName = $class::getPHPSourceExtDirectoryName();
            self::assertMatchesRegularExpression('#^[A-Za-z][A-Za-z0-9_\\-]++$#D', $extDirectoryName);
            (new VersionParser())->parseConstraints($class::getBundledPHPVersions());
        }
        if (is_subclass_of($class, Extension\AvailableAsBinary::class)) {
            $someSourceFound = true;
            self::assertFalse(is_subclass_of($class, Extension\AvailableAsComposerPackage::class));
            self::assertFalse(is_subclass_of($class, Extension\AvailableOnPECL::class));
            self::assertFalse(is_subclass_of($class, Extension\BundledInPHPSource::class));
        }
        if (is_subclass_of($class, Extension\CompiledFromVendorArchive::class)) {
            $someSourceFound = true;
            self::assertFalse(is_subclass_of($class, Extension\AvailableAsComposerPackage::class));
            self::assertFalse(is_subclass_of($class, Extension\AvailableOnPECL::class));
            self::assertFalse(is_subclass_of($class, Extension\BundledInPHPSource::class));
            self::assertFalse(is_subclass_of($class, Extension\AvailableAsBinary::class));
        }
        self::assertTrue($someSourceFound, 'No defined way to install the extension');
    }

    /**
     * @dataProvider provideExtensionDefinitions
     *
     * @param class-string<\IPE\Extension> $class
     *
     * @return void
     */
    public function testFileNameIsHandle($class)
    {
        $fileName = (new \ReflectionClass($class))->getFileName();
        self::assertSame($class::getHandle() . '.php', basename($fileName ?: ''), 'The name of the file must be the handle of the extension');
    }

    /**
     * @dataProvider provideExtensionDefinitions
     *
     * @param class-string<\IPE\Extension> $class
     *
     * @return void
     */
    public function testInterfacesThatRequireOtherInterfaces($class)
    {
        $compiled = self::implementsAny($class, [Extension\BundledInPHPSource::class, Extension\AvailableOnPECL::class, Extension\AvailableAsComposerPackage::class, Extension\CompiledFromVendorArchive::class]);
        foreach ([Extension\PatchesSourceCode::class, Extension\PatchesConfigureScript::class, Extension\ProvidesConfigureArguments::class] as $interface) {
            self::assertTrue($compiled || !is_subclass_of($class, $interface), "{$interface} is useless if the extension is not compiled");
        }
        if (is_subclass_of($class, Extension\DeclaresDefaultVersion::class)) {
            self::assertTrue(self::implementsAny($class, [Extension\AvailableOnPECL::class, Extension\AvailableAsComposerPackage::class, Extension\AvailableAsBinary::class, Extension\CompiledFromVendorArchive::class]), 'The extension has no source that provides versions');
        }
        if (is_subclass_of($class, Extension\DeclaresOptions::class)) {
            self::assertTrue(self::implementsAny($class, [Extension\ProvidesConfigureArguments::class, Extension\RequiresSystemDependencies::class, Extension\CompiledFromVendorArchive::class, Extension\ProvidesBinaryInstaller::class]), 'Nothing reads the options of the extension');
        }
        if (is_subclass_of($class, Extension\AvailableAsBinary::class)) {
            self::assertTrue(self::implementsAny($class, [Extension\BuildsModule::class, Extension\ProvidesBinaryInstaller::class, Extension\ProvidesBinaryModule::class]), 'BinaryInstaller does not know how to install the extension');
        }
    }

    /**
     * @param class-string $class
     * @param list<class-string> $interfaces
     *
     * @return bool
     */
    private static function implementsAny($class, array $interfaces)
    {
        foreach ($interfaces as $interface) {
            if (is_subclass_of($class, $interface)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @dataProvider provideExtensionDefinitions
     *
     * @param class-string<\IPE\Extension> $class
     *
     * @return void
     */
    public function testExtensionCanBeBuilt($class)
    {
        $app = self::createApp();
        $app->instance(PHPInfo::class, M::mock(PHPInfo::class));
        $app->instance(OS::class, M::mock(OS::class));
        $app->instance(Downloader::class, M::mock(Downloader::class));
        $registry = (new Extension\Registry($app))->registerClass($class);
        self::assertInstanceOf($class, $registry->getExtensionByHandle($class::getHandle()));
    }

    /**
     * @return array{class-string<\IPE\Extension>}[]
     */
    public static function provideExtensionDefinitions()
    {
        /** @var array{class-string<\IPE\Extension>}[]|null $result */
        static $result;

        if ($result === null) {
            $registry = new Extension\Registry(new Container());
            $registry->registerDefaultExtensions();
            $result = array_map(
                static function ($class) {
                    return [$class];
                },
                $registry->getExtensionClasses()
            );
        }

        return $result;
    }

    /**
     * @return void
     */
    public function testAllExtensionsAreDetected()
    {
        $fileNamesFromDetectedExtensions = array_map(
            static function (array $case) {
                $chunks = explode('\\', $case[0]);

                return array_pop($chunks) . '.php';
            },
            self::provideExtensionDefinitions()
        );
        $fileNamesInDirectory = array_filter(
            scandir(IPE_ROOT_DIR . '/src/Extensions') ?: [],
            static function ($filename) {
                return preg_match('#.\.php$#i', $filename) === 1;
            }
        );
        natcasesort($fileNamesFromDetectedExtensions);
        natcasesort($fileNamesInDirectory);
        self::assertSame(array_values($fileNamesInDirectory), array_values($fileNamesFromDetectedExtensions));
    }

    /**
     * @return void
     */
    public function testExtensionsAndExtensionTestsMatch()
    {
        $expectedFileNames = array_map(
            static function (array $case) {
                return $case[0]::getHandle() . '.php';
            },
            self::provideExtensionDefinitions()
        );
        $fileNamesInDirectory = array_filter(
            scandir(IPE_ROOT_DIR . '/test/extensions') ?: [],
            static function ($filename) {
                return preg_match('#^[^_].*\.php$#i', $filename) === 1;
            }
        );
        natcasesort($expectedFileNames);
        natcasesort($fileNamesInDirectory);
        self::assertSame(array_values($expectedFileNames), array_values($fileNamesInDirectory));
    }
}
