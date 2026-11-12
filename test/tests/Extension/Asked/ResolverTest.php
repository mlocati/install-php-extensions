<?php

namespace IPE\Test\Extension\Asked;

use IPE\ComposerRepository;
use IPE\Exception;
use IPE\Extension;
use IPE\Extension\Asked;
use IPE\Extension\Asked\Resolver;
use IPE\Extension\Asked\Resolver\Options;
use IPE\Extension\Manifest;
use IPE\Extension\Resolved;
use IPE\Extension\Source;
use IPE\Extension\Version;
use IPE\Extensions;
use IPE\Filesystem;
use IPE\Installer\CACertificatesInstaller;
use IPE\Installer\Enabler;
use IPE\Installer\PHPSourceProvider;
use IPE\Installer\State;
use IPE\Installer\VendorArchivePreparer;
use IPE\Option;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Service\Container;
use IPE\Service\JSON;
use IPE\Service\PHPSystemPackage;
use IPE\SystemPackager;
use IPE\Test\Support\DependencyInstallerTest\FakePackager;
use IPE\Test\Support\ResolverTest\FakeBinaryAndPECLExtension;
use IPE\Test\Support\ResolverTest\FakeBinaryExtension;
use IPE\Test\Support\ResolverTest\FakeBinaryInstallerExtension;
use IPE\Test\Support\ResolverTest\FakeConflictsExtension;
use IPE\Test\Support\ResolverTest\FakeDefaultVersionExtension;
use IPE\Test\Support\ResolverTest\FakeDependentExtensionA;
use IPE\Test\Support\ResolverTest\FakeDependentExtensionB;
use IPE\Test\Support\ResolverTest\FakeExtension;
use IPE\Test\Support\ResolverTest\FakeForbiddingBinaryExtension;
use IPE\Test\Support\ResolverTest\FakeForcedVersionExtension;
use IPE\Test\Support\ResolverTest\FakeRestrictedVersionsExtension;
use IPE\Test\Support\ResolverTest\FakeThreadSafetyExtension;
use IPE\Test\Support\ResolverTest\FakeVendorArchiveExtension;
use IPE\Test\Support\ResolverTest\FixtureDownloader;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class ResolverTest extends TestCase
{
    /**
     * @var \IPE\Filesystem\Local
     */
    private static $fs;

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Test\Support\TestCaseBase::doSetUpBeforeClass()
     */
    public static function doSetUpBeforeClass()
    {
        self::$fs = self::createLocalFilesystem();
    }

    /**
     * @return non-empty-string
     */
    private static function getAssetsDir()
    {
        return self::$fs->getRealPath(IPE_ROOT_DIR . '/test/assets/resolver');
    }

    /**
     * @param string $relativePath with forward slashes
     *
     * @return non-empty-string
     */
    private static function getAssetPath($relativePath)
    {
        return self::getAssetsDir() . str_replace('/', DIRECTORY_SEPARATOR, '/' . $relativePath);
    }

    /**
     * A downloader that serves the fixture files instead of hitting the network.
     *
     * @return \IPE\Filesystem\Downloader
     */
    private static function createDownloader()
    {
        return new FixtureDownloader(self::$fs, self::getAssetsDir(), [
            'https://pecl.php.net/rest/r/mcrypt/allreleases.xml' => 'pecl/mcrypt-allreleases.xml',
            'https://pecl.php.net/get/mcrypt/1.0.7' => 'pecl/mcrypt-1.0.7.tgz',
            'https://pecl.php.net/get/mcrypt/1.0.8' => 'pecl/mcrypt-1.0.7.tgz',
            'https://pecl.php.net/get/mcrypt/1.0.6' => 'pecl/mcrypt-1.0.7.tgz',
            'https://repo.packagist.org/p2/apcu/apcu.json' => 'packagist/apcu.json',
            'https://example.com/dist/apcu-5.1.24.zip' => 'packagist/apcu-5.1.24.zip',
            'https://example.com/dist/apcu-5.1.0.zip' => 'packagist/apcu-5.1.24.zip',
            'https://example.com/dist/apcu-4.0.11.zip' => 'packagist/apcu-4.0.11.zip',
            'https://repo.packagist.org/p2/foo/bar.json' => 'packagist/foo-bar.json',
            'https://example.com/dist/foo-bar-1.2.3.zip' => 'packagist/foo-bar-1.2.3.zip',
            'https://repo.packagist.org/p2/foo/prepackaged.json' => 'packagist/foo-prepackaged.json',
            'https://github.com/foo/prepackaged/releases/download/1.0.7/prepackaged-1.0.7.tgz' => 'pecl/mcrypt-1.0.7.tgz',
            'https://repo.packagist.org/p2/foo/nomanifest.json' => 'packagist/foo-nomanifest.json',
            'https://github.com/foo/nomanifest/releases/download/1.0.0/php_apcu-1.0.0-src.tgz' => 'packagist/nomanifest-src.tgz',
            'https://example.com/dist/binary.tar.gz' => 'pecl/mcrypt-1.0.7.tgz',
        ]);
    }

    /**
     * @param string $version
     * @param bool $extensionsInstalled
     * @param list<string> $additionalInstalledNames
     * @param bool $threadSafe
     *
     * @return \IPE\PHPInfo
     */
    private static function createPHP($version, $extensionsInstalled = false, array $additionalInstalledNames = [], $threadSafe = false)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getVersion')->andReturn($version);
        $php->shouldReceive('getBaseVersion')->andReturn($version);
        $php->shouldReceive('isThreadSafe')->andReturn($threadSafe);
        list($major, $minor) = explode('.', $version);
        $php->shouldReceive('getMajorMinorVersion')->andReturn((int) $major * 100 + (int) $minor);
        $php->shouldReceive('isExtensionInstalled')->andReturn($extensionsInstalled);
        $php->shouldReceive('getInstalledExtensions')->andReturn(array_merge(['Core', 'json', 'SPL', 'standard'], $additionalInstalledNames));

        return $php;
    }

    /**
     * @return \IPE\OS
     */
    private static function createOS()
    {
        $os = M::mock(OS::class);
        $os->shouldReceive('getFilesystem')->andReturn(self::$fs);

        return $os;
    }

    /**
     * @return \IPE\OS\Alpine
     */
    private static function createAlpineOS()
    {
        $os = M::mock(OS\Alpine::class);
        $os->shouldReceive('getSystemPackager')->andReturn(new FakePackager($os, [], []));

        return $os;
    }

    /**
     * @param \IPE\Extension\Asked\Resolver\Options|null $options
     * @param list<\IPE\Extension> $additionalExtensions
     * @param \IPE\OS|null $os
     * @param \IPE\Installer\Enabler|null $enabler
     * @param \IPE\Service\PHPSystemPackage|null $phpSystemPackage
     *
     * @return \IPE\Extension\Asked\Resolver
     */
    private static function createResolver(PHPInfo $php, $options = null, array $additionalExtensions = [], $os = null, $enabler = null, $phpSystemPackage = null)
    {
        $downloader = self::createDownloader();
        $archiveExtractor = new Filesystem\ArchiveExtractor(self::$fs, new \IPE\Logger());
        $archiveFetcher = new Filesystem\ArchiveFetcher($downloader, $archiveExtractor, new \IPE\Logger());
        $selector = new Version\Selector(new \IPE\Logger());
        $phpSourceProvider = M::mock(PHPSourceProvider::class);
        $phpSourceProvider->shouldReceive('providePHPSourceDirectory')->andReturn(self::getAssetPath('phpsrc'));
        $os = $os === null ? self::createOS() : $os;
        $app = self::createApp();
        $app->instance(PHPInfo::class, $php);
        $app->instance(OS::class, $os);
        $app->instance(Filesystem\Downloader::class, $downloader);
        $registry = new Extension\Registry($app);
        $registry->registerDefaultExtensions();
        $registry->registerClass(FakeExtension::class);
        $registry->registerClass(FakeDefaultVersionExtension::class);
        $registry->registerClass(FakeRestrictedVersionsExtension::class);
        foreach ($additionalExtensions as $additionalExtension) {
            $registry->registerExtension($additionalExtension);
        }

        return new Resolver\Posix(
            $php,
            $os,
            $downloader,
            $archiveExtractor,
            $registry,
            $options === null ? new Options() : $options,
            new Source\Bundled($phpSourceProvider),
            new Source\Composer(new Version\Lister\Composer($downloader, (new ComposerRepository\Registry())->registerDefaultRepositories(), new \IPE\Logger()), $selector, $archiveFetcher, new \IPE\Logger()),
            new Source\Pecl(new Version\Lister\Pecl($downloader), $selector, $archiveFetcher, new \IPE\Logger()),
            $archiveFetcher,
            new Manifest\Reader(new \IPE\Logger()),
            new Option\Validator(new \IPE\Logger()),
            new Extension\UnsupportedEnvironment(new OS\Factory(new Container(), new \IPE\Logger())),
            $enabler === null ? new Enabler($php, self::$fs, new \IPE\Logger(), new Enabler\Options()) : $enabler,
            $phpSystemPackage === null ? new PHPSystemPackage($php, $os) : $phpSystemPackage,
            // Not a POSIX system, so it never installs anything
            new CACertificatesInstaller(self::createOS(), new \IPE\Logger()),
            new \IPE\Logger()
        );
    }

    /**
     * @return \IPE\Extension\Resolved
     */
    private static function resolveOne(Resolver $resolver, Extension\Asked $asked, State $state)
    {
        $resolved = $resolver->resolveAskedExtensions([$asked], $state);
        self::assertCount(1, $resolved);

        return $resolved[0];
    }

    /**
     * @return list<string> the names and the reasons of the skipped extensions
     */
    private static function describeSkipped(State $state)
    {
        return array_map(static function (Extension\Skipped $skipped) { return "{$skipped->getName()}: {$skipped->getReason()}"; }, $state->getSkippedExtensions());
    }

    /**
     * @return void
     */
    public function testPHP55OnAlpine()
    {
        $php = self::createPHP('5.5.38');
        $state = new State($php, self::$fs);
        self::assertSame([], self::createResolver($php)->resolveAskedExtensions([], $state));
        $this->expectException(Exception\UnsupportedOS::class);
        $this->expectExceptionMessage('PHP 5.5 is not supported on Alpine');
        self::createResolver($php, null, [], self::createAlpineOS())->resolveAskedExtensions([], $state);
    }

    /**
     * @return void
     */
    public function testPHP56OnAlpine()
    {
        $php = self::createPHP('5.6.40');
        self::assertSame([], self::createResolver($php, null, [], self::createAlpineOS())->resolveAskedExtensions([], new State($php, self::$fs)));
    }

    /**
     * @return void
     */
    public function testBundledExtension()
    {
        $php = self::createPHP('7.1.33');
        $state = new State($php, self::$fs);
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromHandle('mcrypt', ''), $state);
        self::assertInstanceOf(Extensions\mcrypt::class, $resolved->getKnownExtension());
        self::assertSame(Resolved::ORIGIN_BUNDLED, $resolved->getOrigin());
        self::assertSame('7.1.33', $resolved->getVersion());
        self::assertSame(self::getAssetPath('phpsrc/ext/mcrypt'), $resolved->getPath());
        self::assertSame('mcrypt 7.1.33 (bundled in PHP)', (string) $resolved);
    }

    /**
     * @return void
     */
    public function testBundledExtensionWithIncompatibleRequirements()
    {
        // mcrypt is bundled in PHP 7.1, but the user asked for version 1.x: fall back to PECL
        $php = self::createPHP('7.1.33');
        $state = new State($php, self::$fs);
        $options = (new Options())->withOnUnsupportedExtensions(Options::ONUNSUPPORTED_FORCE);
        $resolved = self::resolveOne(self::createResolver($php, $options), new Asked\FromHandle('mcrypt', '^1.0'), $state);
        self::assertSame(Resolved::ORIGIN_PECL, $resolved->getOrigin());
        self::assertSame('1.0.7', $resolved->getVersion());
    }

    /**
     * @return void
     */
    public function testBundledExtensionAskedWithComposerPackageName()
    {
        $options = (new Options())->withOnUnsupportedExtensions(Options::ONUNSUPPORTED_FORCE);
        // php/mcrypt is the same as mcrypt, also when it's not bundled in PHP
        foreach ([['7.1.33', '', Resolved::ORIGIN_BUNDLED, '7.1.33'], ['7.1.33', '^1.0', Resolved::ORIGIN_PECL, '1.0.7'], ['7.2.34', '', Resolved::ORIGIN_PECL, '1.0.7']] as $case) {
            list($phpVersion, $versionRequirements, $expectedOrigin, $expectedVersion) = $case;
            $php = self::createPHP($phpVersion);
            foreach ([new Asked\FromHandle('mcrypt', $versionRequirements), new Asked\FromComposer('php/mcrypt', $versionRequirements)] as $asked) {
                $resolved = self::resolveOne(self::createResolver($php, $options), $asked, new State($php, self::$fs));
                self::assertInstanceOf(Extensions\mcrypt::class, $resolved->getKnownExtension());
                self::assertSame($expectedOrigin, $resolved->getOrigin());
                self::assertSame($expectedVersion, $resolved->getVersion());
            }
        }
    }

    /**
     * @return void
     */
    public function testNotBundledExtensionAskedWithComposerPackageName()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        // They may be published on Packagist
        foreach (['php/apcu', 'php/not-known'] as $fullName) {
            try {
                self::createResolver($php)->resolveAskedExtensions([new Asked\FromComposer($fullName, '')], $state);
                self::fail('Expected exception not thrown');
            } catch (Exception\UnsupportedExtension $x) {
                self::assertStringStartsWith("Unable to find a suitable version of {$fullName} in the Composer repositories", $x->getMessage());
            }
        }
    }

    /**
     * @return void
     */
    public function testManifestRequiringAnotherPHPVersion()
    {
        $php = self::createPHP('7.1.33');
        $state = new State($php, self::$fs);
        try {
            self::createResolver($php)->resolveAskedExtensions([new Asked\FromHandle('mcrypt', '^1.0')], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString('mcrypt 1.0.7 requires PHP', $x->getMessage());
            self::assertStringContainsString('7.1.33', $x->getMessage());
        }
        $options = (new Options())->withOnUnsupportedExtensions(Options::ONUNSUPPORTED_SKIP);
        self::assertSame([], self::createResolver($php, $options)->resolveAskedExtensions([new Asked\FromHandle('mcrypt', '^1.0')], $state));
    }

    /**
     * @return void
     */
    public function testExactVersionIgnoresThePHPRequirements()
    {
        $php = self::createPHP('7.1.33');
        $state = new State($php, self::$fs);
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromHandle('mcrypt', '1.0.7'), $state);
        self::assertSame(Resolved::ORIGIN_PECL, $resolved->getOrigin());
        self::assertSame('1.0.7', $resolved->getVersion());
    }

    /**
     * @return void
     */
    public function testPHPVersionNotSupportedByTheExtension()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $asked = [new Asked\FromHandle('propro', '')];
        try {
            self::createResolver($php)->resolveAskedExtensions($asked, $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString('propro is not supported on PHP 8.3', $x->getMessage());
        }
        $options = (new Options())->withOnUnsupportedExtensions(Options::ONUNSUPPORTED_SKIP);
        $resolver = self::createResolver($php, $options);
        self::assertSame([], $resolver->resolveAskedExtensions($asked, $state));
        self::assertSame(['propro: ' . Extension\Skipped::REASON_UNSUPPORTED], self::describeSkipped($state));
    }

    /**
     * @return void
     */
    public function testConflictsDeclaredByTheManifest()
    {
        $asked = [new Asked\FromDirectory(self::getAssetPath('directories/conflicting')), new Asked\FromDirectory(self::getAssetPath('directories/known-pecl'))];
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        try {
            self::createResolver($php)->resolveAskedExtensions($asked, $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString('conflicts with the extension lzf', $x->getMessage());
        }
        $options = (new Options())->withOnUnsupportedExtensions(Options::ONUNSUPPORTED_SKIP);
        $resolved = self::createResolver($php, $options)->resolveAskedExtensions($asked, $state);
        self::assertCount(1, $resolved);
        self::assertTrue($resolved[0]->hasName('lzf'));
        $options = (new Options())->withOnUnsupportedExtensions(Options::ONUNSUPPORTED_FORCE);
        self::assertCount(2, self::createResolver($php, $options)->resolveAskedExtensions($asked, $state));
    }

    /**
     * @return void
     */
    public function testConflictWithInstalledExtension()
    {
        $php = self::createPHP('8.3.0', false, ['LZF']);
        $state = new State($php, self::$fs);
        try {
            self::createResolver($php)->resolveAskedExtensions([new Asked\FromDirectory(self::getAssetPath('directories/conflicting'))], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString('conflicts with the installed extension lzf', $x->getMessage());
        }
        // The version of the installed extensions is not known
        self::assertCount(1, self::createResolver($php)->resolveAskedExtensions([new Asked\FromDirectory(self::getAssetPath('directories/conflicting-old'))], $state));
    }

    /**
     * @return void
     */
    public function testConflictWithAnotherVersion()
    {
        $php = self::createPHP('8.3.0');
        $asked = [new Asked\FromDirectory(self::getAssetPath('directories/conflicting-old')), new Asked\FromDirectory(self::getAssetPath('directories/known-pecl'))];
        self::assertCount(2, self::createResolver($php)->resolveAskedExtensions($asked, new State($php, self::$fs)));
    }

    /**
     * @return void
     */
    public function testConflictsDeclaredByTheExtensionWinOverTheManifest()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $asked = [new Asked\FromDirectory(self::getAssetPath('directories/conflicting')), new Asked\FromDirectory(self::getAssetPath('directories/known-pecl'))];
        self::assertCount(2, self::createResolver($php, null, [new FakeConflictsExtension($php, self::createOS(), [])])->resolveAskedExtensions($asked, $state));
        $asked = [new Asked\FromDirectory(self::getAssetPath('directories/conflicting-old')), new Asked\FromDirectory(self::getAssetPath('directories/known-pecl'))];
        try {
            self::createResolver($php, null, [new FakeConflictsExtension($php, self::createOS(), ['lzf'])])->resolveAskedExtensions($asked, $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString('conflicts with the extension lzf', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testSkippingAConflictingExtensionSkipsItsDependents()
    {
        $php = self::createPHP('8.3.0');
        $options = (new Options())->withOnUnsupportedExtensions(Options::ONUNSUPPORTED_SKIP);
        $state = new State($php, self::$fs);
        $resolved = self::createResolver($php, $options)->resolveAskedExtensions([
            new Asked\FromDirectory(self::getAssetPath('directories/conflicting')),
            new Asked\FromDirectory(self::getAssetPath('directories/known-pecl')),
            new Asked\FromDirectory(self::getAssetPath('directories/needs-conflicting')),
        ], $state);
        self::assertCount(1, $resolved);
        self::assertTrue($resolved[0]->hasName('lzf'));
        self::assertSame([Extension\Skipped::REASON_CONFLICT, Extension\Skipped::REASON_REQUIRES_SKIPPED], array_map(static function (Extension\Skipped $skipped) { return $skipped->getReason(); }, $state->getSkippedExtensions()));
    }

    /**
     * @return void
     */
    public function testThreadSafetyDeclaredByTheExtension()
    {
        $asked = new Asked\FromHandle('fakethreadsafety', '');
        $zts = self::createPHP('7.2.34', false, [], true);
        $extension = new FakeThreadSafetyExtension($zts, self::createOS(), true, false);
        $resolved = self::resolveOne(self::createResolver($zts, null, [$extension]), $asked, new State($zts, self::$fs));
        self::assertSame(Resolved::ORIGIN_PECL, $resolved->getOrigin());
        $nts = self::createPHP('7.2.34');
        $state = new State($nts, self::$fs);
        try {
            self::createResolver($nts, null, [$extension])->resolveAskedExtensions([$asked], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString("fakethreadsafety doesn't support the NTS builds", $x->getMessage());
        }
        $options = (new Options())->withOnUnsupportedExtensions(Options::ONUNSUPPORTED_SKIP);
        self::assertSame([], self::createResolver($nts, $options, [$extension])->resolveAskedExtensions([$asked], $state));
        $options = (new Options())->withOnUnsupportedExtensions(Options::ONUNSUPPORTED_FORCE);
        self::assertCount(1, self::createResolver($nts, $options, [$extension])->resolveAskedExtensions([$asked], $state));
    }

    /**
     * @return void
     */
    public function testThreadSafetyDeclaredByTheManifest()
    {
        $asked = new Asked\FromDirectory(self::getAssetPath('directories/no-zts'));
        $nts = self::createPHP('8.3.0');
        self::assertCount(1, self::createResolver($nts)->resolveAskedExtensions([$asked], new State($nts, self::$fs)));
        $zts = self::createPHP('8.3.0', false, [], true);
        $state = new State($zts, self::$fs);
        try {
            self::createResolver($zts)->resolveAskedExtensions([$asked], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString("fakethreadsafety 1.0.0 doesn't support the ZTS builds", $x->getMessage());
        }
        $options = (new Options())->withOnUnsupportedExtensions(Options::ONUNSUPPORTED_SKIP);
        self::assertSame([], self::createResolver($zts, $options)->resolveAskedExtensions([$asked], $state));
    }

    /**
     * @return void
     */
    public function testThreadSafetyDeclaredByTheExtensionWinsOverTheManifest()
    {
        $php = self::createPHP('8.3.0', false, [], true);
        $resolver = self::createResolver($php, null, [new FakeThreadSafetyExtension($php, self::createOS(), true, true)]);
        $resolved = self::resolveOne($resolver, new Asked\FromDirectory(self::getAssetPath('directories/no-zts')), new State($php, self::$fs));
        self::assertInstanceOf(FakeThreadSafetyExtension::class, $resolved->getKnownExtension());
    }

    /**
     * @return void
     */
    public function testExactVersionDeclaredByTheExtensionIsAlwaysUsed()
    {
        $php = self::createPHP('7.1.33');
        $state = new State($php, self::$fs);
        $resolver = self::createResolver($php, null, [new FakeForcedVersionExtension($php, self::createOS())]);
        $resolved = self::resolveOne($resolver, new Asked\FromHandle('fakeforcedversion', ''), $state);
        self::assertSame(Resolved::ORIGIN_PECL, $resolved->getOrigin());
        self::assertSame('1.0.8', $resolved->getVersion());
    }

    /**
     * @return void
     */
    public function testPeclExtension()
    {
        $php = self::createPHP('7.2.34');
        $state = new State($php, self::$fs);
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromHandle('mcrypt', ''), $state);
        self::assertInstanceOf(Extensions\mcrypt::class, $resolved->getKnownExtension());
        self::assertSame(Resolved::ORIGIN_PECL, $resolved->getOrigin());
        self::assertSame('1.0.7', $resolved->getVersion());
        self::assertInstanceOf(Manifest::class, $resolved->getManifest());
        self::assertSame('mcrypt', $resolved->getManifest()->getPackageName());
        self::assertTrue($resolved->getOptionValues()->isEmpty());
        self::assertTrue(self::$fs->isFile($resolved->getPath() . DIRECTORY_SEPARATOR . 'config.m4'));
        self::assertTrue(self::$fs->isFile($resolved->getPath() . DIRECTORY_SEPARATOR . 'package.xml'), 'package.xml should be moved inside the source directory');
        self::assertSame('mcrypt 1.0.7 (from pecl)', (string) $resolved);
    }

    /**
     * @return void
     */
    public function testPeclExtensionWithStabilityFlag()
    {
        $php = self::createPHP('7.2.34');
        $state = new State($php, self::$fs);
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromHandle('mcrypt', '@beta'), $state);
        self::assertSame(Resolved::ORIGIN_PECL, $resolved->getOrigin());
        self::assertSame('1.0.8', $resolved->getVersion());
    }

    /**
     * @return void
     */
    public function testComposerExtension()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromHandle('apcu', ''), $state);
        self::assertInstanceOf(Extensions\apcu::class, $resolved->getKnownExtension());
        self::assertSame(Resolved::ORIGIN_COMPOSER, $resolved->getOrigin());
        self::assertSame('5.1.24', $resolved->getVersion());
        self::assertTrue(self::$fs->isFile($resolved->getPath() . DIRECTORY_SEPARATOR . 'config.m4'));
    }

    /**
     * @return void
     */
    public function testComposerExtensionFilteredByPHPVersion()
    {
        $php = self::createPHP('5.6.40');
        $state = new State($php, self::$fs);
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromHandle('apcu', ''), $state);
        self::assertSame(Resolved::ORIGIN_COMPOSER, $resolved->getOrigin());
        self::assertSame('4.0.11', $resolved->getVersion());
    }

    /**
     * @return void
     */
    public function testRestrictedVersions()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        try {
            // The newest Composer version can't be used: the previous one is selected
            FakeRestrictedVersionsExtension::$restriction = static function ($origin, $version) {
                return $origin !== Resolved::ORIGIN_COMPOSER || $version !== '5.1.24';
            };
            $resolved = self::resolveOne(self::createResolver($php), new Asked\FromHandle('fakerestrictedversions', ''), $state);
            self::assertSame(Resolved::ORIGIN_COMPOSER, $resolved->getOrigin());
            self::assertSame('5.1.0', $resolved->getVersion());
            // No Composer version can be used, and the newest stable PECL version neither
            FakeRestrictedVersionsExtension::$restriction = static function ($origin, $version) {
                return $origin === Resolved::ORIGIN_PECL && $version !== '1.0.7';
            };
            $resolved = self::resolveOne(self::createResolver($php), new Asked\FromHandle('fakerestrictedversions', ''), $state);
            self::assertSame(Resolved::ORIGIN_PECL, $resolved->getOrigin());
            self::assertSame('1.0.6', $resolved->getVersion());
            // The restrictions apply also to the versions asked by the user
            try {
                self::createResolver($php)->resolveAskedExtensions([new Asked\FromHandle('fakerestrictedversions', '1.0.7')], $state);
                self::fail('Expected exception not thrown');
            } catch (Exception\UnsupportedExtension $x) {
                self::assertStringContainsString('pecl: no suitable version found (mcrypt)', $x->getMessage());
            }
        } finally {
            FakeRestrictedVersionsExtension::$restriction = null;
        }
    }

    /**
     * @return void
     */
    public function testDefaultVersionRequirements()
    {
        // On PHP 8 the extension asks for a major version that does not exist
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        try {
            self::createResolver($php)->resolveAskedExtensions([new Asked\FromHandle('fakedefaultversion', '')], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString('^9', $x->getMessage());
        }
        // The requirements specified by the user win over the default ones
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromHandle('fakedefaultversion', '^5'), $state);
        self::assertSame('5.1.24', $resolved->getVersion());
        // On PHP 7 there are no default requirements
        $php = self::createPHP('7.4.33');
        $state = new State($php, self::$fs);
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromHandle('fakedefaultversion', ''), $state);
        self::assertSame('5.1.24', $resolved->getVersion());
    }

    /**
     * @return void
     */
    public function testKnownExtensionAskedWithComposerPackageName()
    {
        $php = self::createPHP('5.6.40');
        $state = new State($php, self::$fs);
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromComposer('apcu/apcu', '^4'), $state);
        self::assertInstanceOf(Extensions\apcu::class, $resolved->getKnownExtension());
        self::assertSame('4.0.11', $resolved->getVersion());
    }

    /**
     * @return void
     */
    public function testNoSuitableVersion()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        try {
            self::createResolver($php)->resolveAskedExtensions([new Asked\FromHandle('apcu', '^4')], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString('apcu', $x->getMessage());
            self::assertStringContainsString('^4', $x->getMessage());
            self::assertStringContainsString("\n- composer: no suitable version found (apcu/apcu)", $x->getMessage());
            self::assertStringContainsString("\n- pecl: no suitable version found (apcu)", $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testUnknownComposerExtension()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromComposer('foo/bar', ''), $state);
        self::assertNull($resolved->getKnownExtension());
        self::assertSame(Resolved::ORIGIN_COMPOSER, $resolved->getOrigin());
        self::assertSame('1.2.3', $resolved->getVersion());
        self::assertSame('ext', basename($resolved->getPath()), 'The build-path declared in composer.json should be applied');
        self::assertSame('bar 1.2.3 (from composer)', (string) $resolved);
        self::assertTrue(self::$fs->isFile($resolved->getPath() . DIRECTORY_SEPARATOR . 'config.m4'));

        $options = (new Options())->withIgnoreUnknownExtensions(true);
        self::assertSame([], self::createResolver($php, $options)->resolveAskedExtensions([new Asked\FromComposer('foo/bar', '')], $state));

        try {
            self::createResolver($php)->resolveAskedExtensions([new Asked\FromComposer('foo/bar', '^2')], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertSame('Unable to find a suitable version of foo/bar in the Composer repositories (version requirements: ^2): no version satisfies the version requirements (the newest is v1.2.3)', $x->getMessage());
        }
        $oldPHP = self::createPHP('5.4.0');
        try {
            self::createResolver($oldPHP)->resolveAskedExtensions([new Asked\FromComposer('foo/bar', '')], new State($oldPHP, self::$fs));
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertSame('Unable to find a suitable version of foo/bar in the Composer repositories: no version is compatible with PHP 5.4.0 (the newest, v1.2.3, requires PHP >=5.5)', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testPrePackagedSourceOfComposerPackage()
    {
        $php = self::createPHP('7.4.0');
        $state = new State($php, self::$fs);
        // The binaries are not supported, the first two names of the source archive are missing, and the dist archive must not be used
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromComposer('foo/prepackaged', ''), $state);
        self::assertSame(Resolved::ORIGIN_COMPOSER, $resolved->getOrigin());
        self::assertSame('1.0.7', $resolved->getVersion());
        self::assertSame('mcrypt-1.0.7', basename($resolved->getPath()));
        self::assertTrue(self::$fs->isFile($resolved->getPath() . DIRECTORY_SEPARATOR . 'config.m4'));
        self::assertTrue(self::$fs->isFile($resolved->getPath() . DIRECTORY_SEPARATOR . 'package.xml'));
        // The archive contains the source code of mcrypt: the package is handled as the known extension
        $known = $resolved->getKnownExtension();
        self::assertInstanceOf(Extension\AvailableOnPECL::class, $known);
        self::assertSame('mcrypt', $known::getPECLHandle());
    }

    /**
     * @return void
     */
    public function testPrePackagedSourceWithoutManifest()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromComposer('foo/nomanifest', ''), $state);
        // config.m4 is in the root of the archive, next to its only subdirectory
        self::assertTrue(self::$fs->isFile($resolved->getPath() . DIRECTORY_SEPARATOR . 'config.m4'));
        self::assertTrue(self::$fs->isDirectory($resolved->getPath() . DIRECTORY_SEPARATOR . 'shared'));
        // The manifest comes from the metadata of the Composer repository
        $manifest = $resolved->getManifest();
        self::assertNotNull($manifest);
        self::assertSame('foo/nomanifest', $manifest->getPackageName());
        $known = $resolved->getKnownExtension();
        self::assertNotNull($known);
        self::assertSame('apcu', $known::getHandle());
    }

    /**
     * @return void
     */
    public function testAlreadyInstalledExtension()
    {
        $php = self::createPHP('8.3.0', true);
        $state = new State($php, self::$fs);
        $resolver = self::createResolver($php);
        self::assertSame([], $resolver->resolveAskedExtensions([new Asked\FromHandle('apcu', '')], $state));
        self::assertSame(['apcu: ' . Extension\Skipped::REASON_ALREADY_INSTALLED], self::describeSkipped($state));
    }

    /**
     * @return void
     */
    public function testBinaryExtension()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $resolved = self::resolveOne(self::createResolver($php, null, [new FakeBinaryExtension($php, self::createOS(), self::createDownloader(), 'package.xml')]), new Asked\FromHandle('fakebinary', ''), $state);
        self::assertSame(Resolved::ORIGIN_BINARY, $resolved->getOrigin());
        self::assertTrue(self::$fs->isFile($resolved->getPath() . DIRECTORY_SEPARATOR . 'package.xml'));
        self::assertStringEndsWith('(from binary)', (string) $resolved);
    }

    /**
     * @return void
     */
    public function testBinaryExtensionNotAvailableForPHP()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $extension = new FakeBinaryExtension($php, self::createOS(), self::createDownloader(), 'missing.so');
        try {
            self::createResolver($php, null, [$extension])->resolveAskedExtensions([new Asked\FromHandle('fakebinary', '')], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString("\n- binary: the vendor doesn't provide a binary", $x->getMessage());
        }
        $options = (new Options())->withOnUnsupportedExtensions(Options::ONUNSUPPORTED_SKIP);
        self::assertSame([], self::createResolver($php, $options, [$extension])->resolveAskedExtensions([new Asked\FromHandle('fakebinary', '')], $state));
    }

    /**
     * @return void
     */
    public function testNotesOfAllTheSources()
    {
        $php = self::createPHP('8.3.0');
        $extension = new FakeBinaryAndPECLExtension($php, self::createOS(), self::createDownloader(), 'missing.so');
        try {
            self::createResolver($php, null, [$extension])->resolveAskedExtensions([new Asked\FromHandle('fakebinarypecl', '^9')], new State($php, self::$fs));
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringEndsWith(":\n- pecl: no suitable version found (mcrypt): no version satisfies the version requirements (the newest is 1.0.8 (beta))\n- binary: the vendor doesn't provide a binary for this environment", $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testSkippingTriesAllTheSources()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $extension = new FakeBinaryAndPECLExtension($php, self::createOS(), self::createDownloader(), 'package.xml');
        $options = (new Options())->withOnUnsupportedExtensions(Options::ONUNSUPPORTED_SKIP);
        // PECL doesn't have mcrypt 9
        $resolver = self::createResolver($php, $options, [$extension]);
        $resolved = self::resolveOne($resolver, new Asked\FromHandle('fakebinarypecl', '^9'), $state);
        self::assertSame(Resolved::ORIGIN_BINARY, $resolved->getOrigin());
        self::assertSame([], $state->getSkippedExtensions());
    }

    /**
     * Some vendors reply with a 404 error when there's no binary for the PHP version.
     *
     * @return void
     */
    public function testBinaryExtensionArchiveNotFound()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        try {
            self::createResolver($php, null, [new FakeBinaryExtension($php, self::createOS(), self::createDownloader(), 'package.xml', 'https://example.com/dist/not-found.tar.gz')])->resolveAskedExtensions([new Asked\FromHandle('fakebinary', '')], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString("doesn't provide a binary", $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testBinaryExtensionArchiveForbidden()
    {
        $php = self::createPHP('8.3.0');
        $url = 'https://example.com/dist/' . FixtureDownloader::FORBIDDEN_BASENAME;
        try {
            self::createResolver($php, null, [new FakeForbiddingBinaryExtension($php, self::createOS(), self::createDownloader(), 'package.xml', $url)])->resolveAskedExtensions([new Asked\FromHandle('fakebinary', '')], new State($php, self::$fs));
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString("doesn't provide a binary", $x->getMessage());
        }
        $this->expectException(Exception\IO\Forbidden::class);
        self::createResolver($php, null, [new FakeBinaryExtension($php, self::createOS(), self::createDownloader(), 'package.xml', $url)])->resolveAskedExtensions([new Asked\FromHandle('fakebinary', '')], new State($php, self::$fs));
    }

    /**
     * @return void
     */
    public function testBinaryInstallerNotAvailableForTheEnvironment()
    {
        $php = self::createPHP('8.3.0');
        $resolved = self::resolveOne(self::createResolver($php, null, [new FakeBinaryInstallerExtension($php, self::createOS(), true)]), new Asked\FromHandle('fakeinstaller', ''), new State($php, self::$fs));
        self::assertSame(Resolved::ORIGIN_BINARY, $resolved->getOrigin());
        $this->expectException(Exception\UnsupportedExtension::class);
        $this->expectExceptionMessage("doesn't provide a binary");
        self::createResolver($php, null, [new FakeBinaryInstallerExtension($php, self::createOS(), false)])->resolveAskedExtensions([new Asked\FromHandle('fakeinstaller', '')], new State($php, self::$fs));
    }

    /**
     * @return void
     */
    public function testVendorArchiveIsDownloadedButNotExtracted()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $resolved = self::resolveOne(self::createResolver($php, null, [new FakeVendorArchiveExtension($php, self::createOS())]), new Asked\FromHandle('fakevendor', ''), $state);
        self::assertSame(Resolved::ORIGIN_VENDOR_ARCHIVE, $resolved->getOrigin());
        self::assertSame('1.2.3', $resolved->getVersion());
        self::assertSame('default', $resolved->getOptionValues()->getCustomValue('variant'));
        self::assertTrue(self::$fs->isFile($resolved->getPath() . DIRECTORY_SEPARATOR . VendorArchivePreparer::ARCHIVE_FILENAME));
    }

    /**
     * @return void
     */
    public function testVendorArchiveNotFound()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $extension = new FakeVendorArchiveExtension($php, self::createOS(), 'https://example.com/dist/not-found.zip');
        try {
            self::createResolver($php, null, [$extension])->resolveAskedExtensions([new Asked\FromHandle('fakevendor', '')], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString("\n- vendor archive: the vendor doesn't provide the version 1.2.3", $x->getMessage());
        }
        $options = (new Options())->withOnUnsupportedExtensions(Options::ONUNSUPPORTED_SKIP);
        self::assertSame([], self::createResolver($php, $options, [$extension])->resolveAskedExtensions([new Asked\FromHandle('fakevendor', '')], $state));
    }

    /**
     * @return void
     */
    public function testAlreadyInstalledUnknownExtension()
    {
        $php = self::createPHP('8.3.0', false, ['BAR']);
        $state = new State($php, self::$fs);
        $resolver = self::createResolver($php);
        self::assertSame([], $resolver->resolveAskedExtensions([new Asked\FromComposer('foo/bar', '')], $state));
        self::assertSame(['bar: ' . Extension\Skipped::REASON_ALREADY_INSTALLED], self::describeSkipped($state));
    }

    /**
     * @return void
     */
    public function testAlreadyInstalledUnknownHandle()
    {
        $php = self::createPHP('8.3.0', false, ['XMLWriter']);
        $state = new State($php, self::$fs);
        $resolver = self::createResolver($php);
        self::assertSame([], $resolver->resolveAskedExtensions([new Asked\FromHandle('xmlwriter', '')], $state));
        self::assertSame(['xmlwriter: ' . Extension\Skipped::REASON_ALREADY_INSTALLED], self::describeSkipped($state));
        // It's already installed, not unknown
        $resolver = self::createResolver($php, (new Options())->withIgnoreUnknownExtensions(true));
        self::assertSame([], $resolver->resolveAskedExtensions([new Asked\FromHandle('xmlwriter', '')], $state));
        self::assertSame(['xmlwriter: ' . Extension\Skipped::REASON_ALREADY_INSTALLED], self::describeSkipped($state));
    }

    /**
     * @return void
     */
    public function testUnknownHandle()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        try {
            self::createResolver($php)->resolveAskedExtensions([new Asked\FromHandle('nope', '')], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString('nope', $x->getMessage());
        }
        $options = (new Options())->withIgnoreUnknownExtensions(true);
        $resolver = self::createResolver($php, $options);
        self::assertSame([], $resolver->resolveAskedExtensions([new Asked\FromHandle('nope', '')], $state));
        self::assertSame(['nope: ' . Extension\Skipped::REASON_UNKNOWN], self::describeSkipped($state));
        // Every resolution starts from scratch
        self::assertSame([], $resolver->resolveAskedExtensions([], $state));
        self::assertSame([], $state->getSkippedExtensions());
    }

    /**
     * @return void
     */
    public function testOptionsDeclaredByManifest()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $path = self::getAssetPath('directories/known-pecl');
        // Default value from package.xml
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromDirectory($path), $state);
        self::assertSame(['--enable-lzf-better-compression=no'], $resolved->getOptionValues()->getConfigureArguments());
        self::assertSame([], $resolved->getOptionValues()->getCustomValues());
        // User values
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromDirectory($path, ['enable-lzf-better-compression' => 'yes', 'with-lzf-dir' => true]), $state);
        self::assertSame(['--enable-lzf-better-compression=yes', '--with-lzf-dir'], $resolved->getOptionValues()->getConfigureArguments());
        // Undeclared options are rejected when a manifest exists
        try {
            self::createResolver($php)->resolveAskedExtensions([new Asked\FromDirectory($path, ['nope' => true])], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidOptionValues $x) {
            self::assertStringContainsString('lzf', $x->getMessage());
            self::assertStringContainsString('enable-lzf-better-compression', $x->getMessage());
        }
        // Same for unknown extensions fetched from the Composer repositories (their composer.json is the manifest)
        try {
            self::createResolver($php)->resolveAskedExtensions([new Asked\FromComposer('foo/bar', '', ['nope' => true])], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidOptionValues $x) {
            self::assertStringContainsString('no options are accepted', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testOptionsWithoutManifest()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $path = self::getAssetPath('directories/nomanifest');
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromDirectory($path, ['with-foo' => '/usr', 'enable-bar' => true]), $state);
        self::assertNull($resolved->getKnownExtension());
        self::assertNull($resolved->getManifest());
        self::assertSame(['--with-foo=/usr', '--enable-bar'], $resolved->getOptionValues()->getConfigureArguments());
        self::assertSame("unknown extension (from {$path})", (string) $resolved);
    }

    /**
     * @return void
     */
    public function testOptionsDeclaredByExtension()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromHandle('fakeext', '', ['enable-fake-debug' => true]), $state);
        self::assertInstanceOf(FakeExtension::class, $resolved->getKnownExtension());
        self::assertSame(Resolved::ORIGIN_BUNDLED, $resolved->getOrigin());
        self::assertNull($resolved->getManifest());
        self::assertSame(['--enable-fake-debug'], $resolved->getOptionValues()->getConfigureArguments());
        self::assertSame(['variant' => 'std'], $resolved->getOptionValues()->getCustomValues());
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromHandle('fakeext', '', ['variant' => 'alt', 'with-fake-dir' => '/opt']), $state);
        self::assertSame(['--with-fake-dir=/opt'], $resolved->getOptionValues()->getConfigureArguments());
        self::assertSame('alt', $resolved->getOptionValues()->getCustomValue('variant'));
        try {
            self::createResolver($php)->resolveAskedExtensions([new Asked\FromHandle('fakeext', '', ['with-fake-dir' => true])], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidOptionValues $x) {
            self::assertStringContainsString('requires a value', $x->getMessage());
        }
    }

    /**
     * @param list<\IPE\Extension\Resolved> $resolved
     *
     * @return list<string>
     */
    private static function describe(array $resolved)
    {
        return array_map(
            static function (Resolved $extension) {
                $known = $extension->getKnownExtension();
                if ($known !== null) {
                    return $known::getHandle();
                }
                $manifest = $extension->getManifest();

                return $manifest === null ? '' : $manifest->getExtensionName();
            },
            $resolved
        );
    }

    /**
     * @return void
     */
    public function testRequiredExtensionsAreAddedBeforeTheExtension()
    {
        $php = self::createPHP('8.3.0');
        $resolver = self::createResolver($php, null, [
            new FakeDependentExtensionA($php, self::createOS(), ['fake_b', 'json']),
            new FakeDependentExtensionB($php, self::createOS(), ['apcu']),
        ]);
        $resolved = $resolver->resolveAskedExtensions([new Asked\FromHandle('fake_a', '')], new State($php, self::$fs));
        self::assertSame(['apcu', 'fake_b', 'fake_a'], self::describe($resolved));
        self::assertSame([false, false, true], array_map(static function (Resolved $extension) { return $extension->isAsked(); }, $resolved));
        self::assertSame([['fake_b'], ['fake_a'], []], array_map(static function (Resolved $extension) { return $extension->getRequiredBy(); }, $resolved));
    }

    /**
     * @param array<string, string> $disabledIniFiles the keys are the handles of the disabled extensions
     *
     * @return \IPE\Installer\Enabler
     */
    private static function createEnablerWithDisabled(array $disabledIniFiles)
    {
        $enabler = M::mock(Enabler::class);
        $enabler->shouldReceive('getDisabledIniFile')->andReturnUsing(static function (Extension $extension) use ($disabledIniFiles) {
            return isset($disabledIniFiles[$extension::getHandle()]) ? $disabledIniFiles[$extension::getHandle()] : '';
        });

        return $enabler;
    }

    /**
     * @return void
     */
    public function testDisabledExtension()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $resolver = self::createResolver($php, null, [], null, self::createEnablerWithDisabled(['apcu' => '/etc/php/8.3/mods-available/apcu.ini']));
        $resolved = self::resolveOne($resolver, new Asked\FromHandle('apcu', '^5'), $state);
        self::assertSame(Resolved::ORIGIN_DISABLED, $resolved->getOrigin());
        self::assertSame('/etc/php/8.3/mods-available/apcu.ini', $resolved->getPath());
        self::assertSame('', $resolved->getVersion());
        self::assertSame('apcu (installed but disabled)', (string) $resolved);
        self::assertSame([], $state->getSkippedExtensions());
    }

    /**
     * @return void
     */
    public function testDisabledRequiredExtension()
    {
        $php = self::createPHP('8.3.0');
        $resolver = self::createResolver(
            $php,
            null,
            [new FakeDependentExtensionA($php, self::createOS(), ['apcu'])],
            null,
            self::createEnablerWithDisabled(['apcu' => '/etc/php/8.3/mods-available/apcu.ini'])
        );
        $resolved = $resolver->resolveAskedExtensions([new Asked\FromHandle('fake_a', '')], new State($php, self::$fs));
        self::assertSame(['apcu', 'fake_a'], self::describe($resolved));
        self::assertSame(Resolved::ORIGIN_DISABLED, $resolved[0]->getOrigin());
        self::assertSame(['fake_a'], $resolved[0]->getRequiredBy());
    }

    /**
     * @param \IPE\SystemPackager\Package|null $phpPackage NULL if PHP hasn't been installed by the system packager
     * @param array<non-empty-string, list<\IPE\SystemPackager\Package>> $available
     * @param array<non-empty-string, list<non-empty-string>> $expansions
     *
     * @return \IPE\Service\PHPSystemPackage
     */
    private static function createPHPSystemPackage($phpPackage, array $available = [], array $expansions = [])
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->andReturn('/usr/bin/php');
        $php->shouldReceive('getAPIVersion')->andReturn(20240924);
        $os = M::mock(OS\Debian::class);
        $packager = new FakePackager($os, [], $expansions);
        $packager->owningPackage = $phpPackage;
        $packager->available = $available;
        $os->shouldReceive('getSystemPackager')->andReturn($packager);

        return new PHPSystemPackage($php, $os);
    }

    /**
     * @param string $systemPackages
     * @param string $onUnsupported
     *
     * @return \IPE\Extension\Asked\Resolver
     */
    private static function createResolverWithMcryptPackage(PHPInfo $php, $systemPackages, $onUnsupported = Options::ONUNSUPPORTED_ABORT)
    {
        $phpSystemPackage = self::createPHPSystemPackage(new SystemPackager\Package('php7.1-cli', '7.1.33-1', 'php7.1'), [
            'php7.1-mcrypt' => [new SystemPackager\Package('php7.1-mcrypt', '7.1.33-1', 'php7.1')],
        ]);
        $options = (new Options())->withSystemPackages($systemPackages)->withOnUnsupportedExtensions($onUnsupported);

        return self::createResolver($php, $options, [], null, null, $phpSystemPackage);
    }

    /**
     * @return void
     */
    public function testSystemPackage()
    {
        $php = self::createPHP('7.1.33');
        $state = new State($php, self::$fs);
        $resolved = self::resolveOne(self::createResolverWithMcryptPackage($php, Options::SYSTEMPACKAGES_AUTO), new Asked\FromHandle('mcrypt', '^7'), $state);
        self::assertSame(Resolved::ORIGIN_SYSTEM_PACKAGE, $resolved->getOrigin());
        self::assertSame('php7.1-mcrypt=7.1.33-1', $resolved->getPath());
        self::assertSame('7.1.33', $resolved->getVersion());
        self::assertSame('mcrypt 7.1.33 (from the system package php7.1-mcrypt=7.1.33-1)', (string) $resolved);
        $resolved = self::resolveOne(self::createResolverWithMcryptPackage($php, Options::SYSTEMPACKAGES_ONLY), new Asked\FromHandle('mcrypt', ''), $state);
        self::assertSame(Resolved::ORIGIN_SYSTEM_PACKAGE, $resolved->getOrigin());
        $resolved = self::resolveOne(self::createResolverWithMcryptPackage($php, Options::SYSTEMPACKAGES_NEVER), new Asked\FromHandle('mcrypt', ''), $state);
        self::assertSame(Resolved::ORIGIN_BUNDLED, $resolved->getOrigin());
    }

    /**
     * @return void
     */
    public function testSystemPackageNotSatisfyingTheVersionRequirements()
    {
        $php = self::createPHP('7.1.33');
        $state = new State($php, self::$fs);
        $resolved = self::resolveOne(self::createResolverWithMcryptPackage($php, Options::SYSTEMPACKAGES_AUTO), new Asked\FromHandle('mcrypt', '1.0.7'), $state);
        self::assertSame(Resolved::ORIGIN_PECL, $resolved->getOrigin());
        self::assertSame('1.0.7', $resolved->getVersion());
        try {
            self::createResolverWithMcryptPackage($php, Options::SYSTEMPACKAGES_ONLY)->resolveAskedExtensions([new Asked\FromHandle('mcrypt', '1.0.7')], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString("system package: the package php7.1-mcrypt 7.1.33-1 doesn't satisfy the version requirements", $x->getMessage());
        }
        $resolver = self::createResolverWithMcryptPackage($php, Options::SYSTEMPACKAGES_ONLY, Options::ONUNSUPPORTED_SKIP);
        self::assertSame([], $resolver->resolveAskedExtensions([new Asked\FromHandle('mcrypt', '1.0.7')], $state));
        self::assertCount(1, $state->getSkippedExtensions());
    }

    /**
     * @return void
     */
    public function testSystemPackageInstallingAnotherPHP()
    {
        $php = self::createPHP('7.1.33');
        $state = new State($php, self::$fs);
        $phpSystemPackage = self::createPHPSystemPackage(
            new SystemPackager\Package('php7.1-cli', '7.1.33-1', 'php7.1'),
            ['php7.1-mcrypt' => [new SystemPackager\Package('php7.1-mcrypt', '1.0.7-1', 'php-mcrypt')]],
            ['php7.1-mcrypt' => ['php7.1-mcrypt', 'php-common', 'php8.5-common', 'php8.5-cli']]
        );
        $resolved = self::resolveOne(self::createResolver($php, null, [], null, null, $phpSystemPackage), new Asked\FromHandle('mcrypt', ''), $state);
        self::assertSame(Resolved::ORIGIN_BUNDLED, $resolved->getOrigin());
        $resolver = self::createResolver($php, (new Options())->withSystemPackages(Options::SYSTEMPACKAGES_ONLY), [], null, null, $phpSystemPackage);
        try {
            $resolver->resolveAskedExtensions([new Asked\FromHandle('mcrypt', '')], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString('system package: the package php7.1-mcrypt 1.0.7-1 would install packages of another PHP version (php8.5-common, php8.5-cli)', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testSystemPackageWithRestrictedVersion()
    {
        $php = self::createPHP('7.1.33');
        $state = new State($php, self::$fs);
        $options = (new Options())->withSystemPackages(Options::SYSTEMPACKAGES_ONLY);
        $phpPackage = new SystemPackager\Package('php7.1-cli', '7.1.33-1', 'php7.1');
        $phpSystemPackage = self::createPHPSystemPackage($phpPackage, ['php7.1-mongodb' => [new SystemPackager\Package('php7.1-mongodb', '1.11.1+1.9.2+1.7.5-1', 'php-mongodb')]]);
        try {
            self::createResolver($php, $options, [], null, null, $phpSystemPackage)->resolveAskedExtensions([new Asked\FromHandle('mongodb', '')], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString("system package: the package php7.1-mongodb 1.11.1+1.9.2+1.7.5-1 can't be used", $x->getMessage());
        }
        $phpSystemPackage = self::createPHPSystemPackage($phpPackage, ['php7.1-mongodb' => [new SystemPackager\Package('php7.1-mongodb', '1.16.2-1', 'php-mongodb')]]);
        $resolved = self::resolveOne(self::createResolver($php, $options, [], null, null, $phpSystemPackage), new Asked\FromHandle('mongodb', ''), $state);
        self::assertSame(Resolved::ORIGIN_SYSTEM_PACKAGE, $resolved->getOrigin());
        self::assertSame('1.16.2', $resolved->getVersion());
    }

    /**
     * @return void
     */
    public function testSystemPackageWithOptions()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $phpSystemPackage = self::createPHPSystemPackage(new SystemPackager\Package('php8.3-cli', '8.3.0-1', 'php8.3'), [
            'php8.3-fakeext' => [new SystemPackager\Package('php8.3-fakeext', '1.0-1', 'php-fakeext')],
        ]);
        $resolver = self::createResolver($php, null, [], null, null, $phpSystemPackage);
        self::assertSame(Resolved::ORIGIN_SYSTEM_PACKAGE, self::resolveOne($resolver, new Asked\FromHandle('fakeext', ''), $state)->getOrigin());
        // The packages are built without the options
        self::assertSame(Resolved::ORIGIN_BUNDLED, self::resolveOne($resolver, new Asked\FromHandle('fakeext', '', ['enable-fake-debug' => true]), $state)->getOrigin());
    }

    /**
     * @return void
     */
    public function testSystemPackageNotAvailable()
    {
        $php = self::createPHP('7.1.33');
        $state = new State($php, self::$fs);
        $phpSystemPackage = self::createPHPSystemPackage(new SystemPackager\Package('php7.1-cli', '7.1.33-1', 'php7.1'));
        $resolved = self::resolveOne(self::createResolver($php, null, [], null, null, $phpSystemPackage), new Asked\FromHandle('mcrypt', ''), $state);
        self::assertSame(Resolved::ORIGIN_BUNDLED, $resolved->getOrigin());
        try {
            self::createResolver($php, (new Options())->withSystemPackages(Options::SYSTEMPACKAGES_ONLY), [], null, null, $phpSystemPackage)->resolveAskedExtensions([new Asked\FromHandle('mcrypt', '')], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString('system package: no package is available for the installed PHP', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testOnlySystemPackagesWithoutPHPPackage()
    {
        $php = self::createPHP('7.1.33');
        $options = (new Options())->withSystemPackages(Options::SYSTEMPACKAGES_ONLY);
        $this->expectException(Exception\UnsupportedOS::class);
        self::createResolver($php, $options, [], null, null, self::createPHPSystemPackage(null))->resolveAskedExtensions([new Asked\FromHandle('mcrypt', '')], new State($php, self::$fs));
    }

    /**
     * @return void
     */
    public function testOnlySystemPackagesWithoutHandle()
    {
        $php = self::createPHP('7.1.33');
        $state = new State($php, self::$fs);
        $asked = [new Asked\FromDirectory(self::getAssetPath('directories/conflicting-old'))];
        try {
            self::createResolverWithMcryptPackage($php, Options::SYSTEMPACKAGES_ONLY)->resolveAskedExtensions($asked, $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString('would be compiled, but the extensions must be installed only from the system packages', $x->getMessage());
        }
        self::assertSame([], self::createResolverWithMcryptPackage($php, Options::SYSTEMPACKAGES_ONLY, Options::ONUNSUPPORTED_SKIP)->resolveAskedExtensions($asked, $state));
    }

    /**
     * @return void
     */
    public function testSystemPackageRequiresExtensions()
    {
        $php = self::createPHP('8.3.0');
        $phpSystemPackage = self::createPHPSystemPackage(new SystemPackager\Package('php8.3-cli', '8.3.0-1', 'php8.3'), [
            'php8.3-fake-a' => [new SystemPackager\Package('php8.3-fake-a', '1.0-1', 'php-fake-a')],
        ]);
        $resolver = self::createResolver($php, null, [new FakeDependentExtensionA($php, self::createOS(), ['apcu'])], null, null, $phpSystemPackage);
        // Some packages don't depend on the packages of the extensions they need
        $resolved = $resolver->resolveAskedExtensions([new Asked\FromHandle('fake_a', '')], new State($php, self::$fs));
        self::assertSame(['apcu', 'fake_a'], self::describe($resolved));
        self::assertSame(Resolved::ORIGIN_SYSTEM_PACKAGE, $resolved[1]->getOrigin());
    }

    /**
     * @return void
     */
    public function testRequiredExtensionAlreadyInstalled()
    {
        $php = self::createPHP('8.3.0');
        $resolver = self::createResolver($php, null, [new FakeDependentExtensionA($php, self::createOS(), ['STANDARD', 'SPL'])]);
        $resolved = $resolver->resolveAskedExtensions([new Asked\FromHandle('fake_a', '')], new State($php, self::$fs));
        self::assertSame(['fake_a'], self::describe($resolved));
    }

    /**
     * @return void
     */
    public function testRequiredExtensionAskedByTheUserKeepsItsRequirements()
    {
        $php = self::createPHP('5.6.40');
        $resolver = self::createResolver($php, null, [new FakeDependentExtensionA($php, self::createOS(), ['apcu'])]);
        $resolved = $resolver->resolveAskedExtensions([new Asked\FromHandle('fake_a', ''), new Asked\FromHandle('apcu', '^4')], new State($php, self::$fs));
        self::assertSame(['apcu', 'fake_a'], self::describe($resolved));
        self::assertSame('4.0.11', $resolved[0]->getVersion());
    }

    /**
     * Create the source code of an unknown extension that requires another one.
     *
     * @param non-empty-string $name
     * @param string $requiredExtension
     * @param string $constraint
     *
     * @return \IPE\Extension\Asked\FromDirectory
     */
    private static function createDependentDirectory(Filesystem\VolatileDirectory $vol, $name, $requiredExtension, $constraint)
    {
        $path = $vol->createSubdir();
        self::$fs->writeFile($path . DIRECTORY_SEPARATOR . 'composer.json', JSON::encode([
            'name' => "vendor/{$name}",
            'type' => 'php-ext',
            'require' => ["ext-{$requiredExtension}" => $constraint],
        ]));

        return new Asked\FromDirectory($path);
    }

    /**
     * @param list<\IPE\Extension\Resolved> $resolved
     *
     * @return string the version of apcu
     */
    private static function getApcuVersion(array $resolved)
    {
        foreach ($resolved as $extension) {
            if ($extension->hasName('apcu')) {
                return $extension->getVersion();
            }
        }
        self::fail('apcu has not been resolved');
    }

    /**
     * @return void
     */
    public function testAnotherVersionIsLookedForWhenARequirementIsNotSatisfied()
    {
        $vol = self::$fs->createVolatileDirectory();
        $php = self::createPHP('7.4.33');
        $state = new State($php, self::$fs);
        $resolver = self::createResolver($php);
        $resolved = $resolver->resolveAskedExtensions([new Asked\FromHandle('apcu', ''), self::createDependentDirectory($vol, 'a', 'apcu', '<5.1.24')], $state);
        self::assertSame('5.1.0', self::getApcuVersion($resolved));
        $resolved = $resolver->resolveAskedExtensions([self::createDependentDirectory($vol, 'a', 'apcu', '>=5.0'), self::createDependentDirectory($vol, 'b', 'apcu', '<5.1.24')], $state);
        self::assertSame('5.1.0', self::getApcuVersion($resolved));
        self::assertCount(3, $resolved);
        $resolved = $resolver->resolveAskedExtensions([self::createDependentDirectory($vol, 'a', 'apcu', '>=5.0'), self::createDependentDirectory($vol, 'b', 'apcu', '^5.1')], $state);
        self::assertSame('5.1.24', self::getApcuVersion($resolved));
    }

    /**
     * @return array<string, array{list<array{string, string}>, string, list<string>}>
     */
    public static function provideConflictingRequirementsCases()
    {
        return [
            'between extensions' => [[['a', '^5'], ['b', '^4']], '', ['requires ^5', 'requires ^4']],
            'with the version asked by the user' => [[['a', '<5.1.24']], '5.1.24', ['requires <5.1.24', 'you asked for 5.1.24']],
        ];
    }

    /**
     * @dataProvider provideConflictingRequirementsCases
     *
     * @param list<array{string, string}> $dependents
     * @param string $askedVersion
     * @param list<string> $expectedMessages
     *
     * @return void
     */
    public function testConflictingRequirements(array $dependents, $askedVersion, array $expectedMessages)
    {
        $vol = self::$fs->createVolatileDirectory();
        $php = self::createPHP('7.4.33');
        $state = new State($php, self::$fs);
        $asked = $askedVersion === '' ? [] : [new Asked\FromHandle('apcu', $askedVersion)];
        foreach ($dependents as list($name, $constraint)) {
            /** @var non-empty-string $name */
            $asked[] = self::createDependentDirectory($vol, $name, 'apcu', $constraint);
        }
        try {
            self::createResolver($php)->resolveAskedExtensions($asked, $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString('satisfies all the requirements', $x->getMessage());
            foreach ($expectedMessages as $expectedMessage) {
                self::assertStringContainsString($expectedMessage, $x->getMessage());
            }
        }
        $options = (new Options())->withOnUnsupportedExtensions(Options::ONUNSUPPORTED_FORCE);
        self::assertNotSame('', self::getApcuVersion(self::createResolver($php, $options)->resolveAskedExtensions($asked, $state)));
    }

    /**
     * @return void
     */
    public function testExactVersionDeclaredByTheExtensionWinsOverRequirements()
    {
        $vol = self::$fs->createVolatileDirectory();
        $php = self::createPHP('7.1.33');
        $state = new State($php, self::$fs);
        $resolver = self::createResolver($php, null, [new FakeForcedVersionExtension($php, self::createOS())]);
        foreach ([
            [self::createDependentDirectory($vol, 'a', 'fakeforcedversion', '^2')],
            [new Asked\FromHandle('fakeforcedversion', ''), self::createDependentDirectory($vol, 'a', 'fakeforcedversion', '^2')],
        ] as $asked) {
            $versions = [];
            foreach ($resolver->resolveAskedExtensions($asked, $state) as $extension) {
                if ($extension->hasName('fakeforcedversion')) {
                    $versions[] = $extension->getVersion();
                }
            }
            self::assertSame(['1.0.8'], $versions);
        }
    }

    /**
     * @return void
     */
    public function testOptionalExtensionsAreOnlySorted()
    {
        $php = self::createPHP('8.3.0');
        $resolver = self::createResolver($php, null, [new FakeDependentExtensionA($php, self::createOS(), [], ['mcrypt', 'not-known'])]);
        $state = new State($php, self::$fs);
        $resolved = $resolver->resolveAskedExtensions([new Asked\FromHandle('fake_a', '')], $state);
        self::assertSame(['fake_a'], self::describe($resolved));
        $resolved = $resolver->resolveAskedExtensions([new Asked\FromHandle('fake_a', ''), new Asked\FromHandle('mcrypt', '')], $state);
        self::assertSame(['mcrypt', 'fake_a'], self::describe($resolved));
    }

    /**
     * @return void
     */
    public function testCircularDependencies()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $resolver = self::createResolver($php, null, [
            new FakeDependentExtensionA($php, self::createOS(), ['fake_b']),
            new FakeDependentExtensionB($php, self::createOS(), ['fake_a']),
        ]);
        try {
            $resolver->resolveAskedExtensions([new Asked\FromHandle('fake_a', '')], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString('Circular dependency', $x->getMessage());
        }
        $resolver = self::createResolver($php, null, [
            new FakeDependentExtensionA($php, self::createOS(), ['fake_b']),
            new FakeDependentExtensionB($php, self::createOS(), [], ['fake_a']),
        ]);
        $resolved = $resolver->resolveAskedExtensions([new Asked\FromHandle('fake_a', '')], $state);
        self::assertSame(['fake_b', 'fake_a'], self::describe($resolved));
    }

    /**
     * @return void
     */
    public function testUnknownRequiredExtension()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $extension = new FakeDependentExtensionA($php, self::createOS(), ['not-known']);
        try {
            self::createResolver($php, null, [$extension])->resolveAskedExtensions([new Asked\FromHandle('fake_a', '')], $state);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString('not-known', $x->getMessage());
        }
        $options = (new Options())->withIgnoreUnknownExtensions(true);
        $resolver = self::createResolver($php, $options, [$extension]);
        $resolved = $resolver->resolveAskedExtensions([new Asked\FromHandle('fake_a', '')], $state);
        self::assertSame(['fake_a'], self::describe($resolved));
        self::assertSame(['not-known: ' . Extension\Skipped::REASON_UNKNOWN], self::describeSkipped($state));
    }

    /**
     * @return void
     */
    public function testDependenciesDeclaredByTheManifest()
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $asked = new Asked\FromDirectory(self::getAssetPath('directories/requires-extensions'));
        $resolved = self::createResolver($php)->resolveAskedExtensions([$asked], $state);
        self::assertSame(['apcu', 'needs-apcu'], self::describe($resolved));
        self::assertSame('5.1.24', $resolved[0]->getVersion());
        $resolved = self::createResolver($php)->resolveAskedExtensions([$asked, new Asked\FromHandle('mcrypt', '')], $state);
        self::assertSame(['apcu', 'mcrypt', 'needs-apcu'], self::describe($resolved));
    }

    /**
     * @return list<array{string, class-string<\IPE\Extension>|'', string, string}>
     */
    public static function provideDirectoryCases()
    {
        return [
            ['known-pecl', Extensions\lzf::class, '1.7.0', ''],
            ['known-composer', Extensions\apcu::class, '', 'src'],
            ['unknown', '', '9.9.9', ''],
            ['nomanifest', '', '', ''],
            ['notextension', '', '', ''],
        ];
    }

    /**
     * @dataProvider provideDirectoryCases
     *
     * @param string $directory
     * @param class-string<\IPE\Extension>|'' $expectedExtensionClass empty if the extension should be unknown
     * @param string $expectedVersion
     * @param string $expectedBuildPath the subdirectory declared in the manifest (empty if none)
     *
     * @return void
     */
    public function testDirectory($directory, $expectedExtensionClass, $expectedVersion, $expectedBuildPath)
    {
        $php = self::createPHP('8.3.0');
        $state = new State($php, self::$fs);
        $path = self::getAssetPath('directories/' . $directory);
        $resolved = self::resolveOne(self::createResolver($php), new Asked\FromDirectory($path), $state);
        if ($expectedExtensionClass === '') {
            self::assertNull($resolved->getKnownExtension());
        } else {
            self::assertInstanceOf($expectedExtensionClass, $resolved->getKnownExtension());
        }
        self::assertSame(Resolved::ORIGIN_DIRECTORY, $resolved->getOrigin());
        self::assertSame($expectedVersion, $resolved->getVersion());
        self::assertSame($expectedBuildPath === '' ? $path : $path . DIRECTORY_SEPARATOR . $expectedBuildPath, $resolved->getPath());

        $options = (new Options())->withIgnoreUnknownExtensions(true);
        $resolved = self::createResolver($php, $options)->resolveAskedExtensions([new Asked\FromDirectory($path)], $state);
        self::assertCount($expectedExtensionClass === '' ? 0 : 1, $resolved);
    }
}
