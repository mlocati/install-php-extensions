<?php

namespace IPE\Test\Extensions;

use IPE\Exception;
use IPE\Extensions;
use IPE\Filesystem;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemPackager\Homebrew;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class SaxonTest extends TestCase
{
    /**
     * @return \IPE\OS\Posix
     */
    private static function createOS()
    {
        return M::mock(OS\Debian::class);
    }

    /**
     * @param string $page
     *
     * @return \IPE\Filesystem\Downloader
     */
    private static function createDownloader($page = '')
    {
        $downloader = M::mock(Filesystem\Downloader::class);
        $downloader->shouldReceive('getContent')->with(Extensions\saxon::DOWNLOAD_PAGE)->andReturn($page);

        return $downloader;
    }

    /**
     * @param string $edition
     *
     * @return \IPE\Option\Values
     */
    private static function createOptions($edition)
    {
        return new Values([], [Extensions\saxon::OPTION_EDITION => $edition]);
    }

    /**
     * @param int $bits
     * @param non-empty-string $rawMachineArchitecture
     *
     * @return \IPE\PHPInfo
     */
    private static function createPHP($bits = 64, $rawMachineArchitecture = 'x86_64')
    {
        return self::mockPHPInfoOnMachine($rawMachineArchitecture, $bits);
    }

    /**
     * @param non-empty-string $rawArchitecture
     * @param string $page
     *
     * @return \IPE\Extensions\saxon
     */
    private static function createExtension($rawArchitecture = 'x86_64', $page = '')
    {
        return new Extensions\saxon(self::createPHP(64, $rawArchitecture), self::createOS(), self::createDownloader($page));
    }

    /**
     * @return void
     */
    public function testDefaultVersion()
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn(704, 800);
        $extension = new Extensions\saxon($php, self::createOS(), self::createDownloader());
        self::assertSame('12.3', $extension->getDefaultVersionRequirements());
        self::assertSame('', $extension->getDefaultVersionRequirements());
    }

    /**
     * @return void
     */
    public function testLatestVersion()
    {
        $page = <<<'EOT'
<a href="https://downloads.saxonica.com/SaxonC/HE/12/SaxonCHE-linux-x86_64-12-10-1.zip" />
<a href="https://downloads.saxonica.com/SaxonC/HE/13/SaxonCHE-linux-x86_64-13-1-1.zip" />
<a href="https://downloads.saxonica.com/SaxonC/HE/13/SaxonCHE-linux-arm64-13-0-0.zip" />
<a href="https://downloads.saxonica.com/SaxonC/EE/14/SaxonCEE-linux-x86_64-14-0-0.zip" />
EOT;
        self::assertSame('13.1.1', self::createExtension('x86_64', $page)->getVendorArchiveVersion('', self::createOptions('HE')));
        self::assertSame('13.0.0', self::createExtension('aarch64', $page)->getVendorArchiveVersion('', self::createOptions('HE')));
        self::assertSame('12.3', self::createExtension('x86_64', $page)->getVendorArchiveVersion('12.3', self::createOptions('HE')));
        $this->expectException(Exception\IO::class);
        self::createExtension('x86_64', $page)->getVendorArchiveVersion('', self::createOptions('PE'));
    }

    /**
     * @return void
     */
    public function testOnlyExactVersions()
    {
        $this->expectException(Exception\UnsupportedExtension::class);
        self::createExtension()->getVendorArchiveVersion('^12', self::createOptions('HE'));
    }

    /**
     * @return void
     */
    public function testInvalidEdition()
    {
        $this->expectException(Exception\InvalidOptionValues::class);
        self::createExtension()->getVendorArchiveURL('13.1.1', self::createOptions('XX'));
    }

    /**
     * @return void
     */
    public function testArchiveURLs()
    {
        $base = 'https://downloads.saxonica.com/SaxonC/';
        $options = self::createOptions('HE');
        self::assertSame("{$base}HE/13/SaxonCHE-linux-x86_64-13-1-1.zip", self::createExtension()->getVendorArchiveURL('13.1.1', $options));
        self::assertSame("{$base}EE/12/SaxonCEE-linux-x86_64-12-6-0.zip", self::createExtension()->getVendorArchiveURL('12.6.0', self::createOptions('EE')));
        self::assertSame("{$base}HE/12/libsaxon-HEC-linux-x86_64-v12.3.zip", self::createExtension()->getVendorArchiveURL('12.3', $options));
        self::assertSame("{$base}HE/11/libsaxon-HEC-setup64-v11.6.zip", self::createExtension()->getVendorArchiveURL('11.6', $options));
        self::assertSame("{$base}HE/12/SaxonCHE-linux-aarch64-12-8-0.zip", self::createExtension('aarch64')->getVendorArchiveURL('12.8.0', $options));
        self::assertSame("{$base}HE/12/SaxonCHE-linux-arm64-12-10-1.zip", self::createExtension('aarch64')->getVendorArchiveURL('12.10.1', $options));
        self::assertSame("{$base}HE/12/libsaxon-HEC-linux-arm64-v12.3.zip", self::createExtension('aarch64')->getVendorArchiveURL('12.3', $options));
    }

    /**
     * @return void
     */
    public function testOtherArchitecturesAreLeftToTheVendor()
    {
        $base = 'https://downloads.saxonica.com/SaxonC/HE/';
        $options = self::createOptions('HE');
        $riscv = new Extensions\saxon(self::createPHP(64, 'riscv64'), self::createOS(), self::createDownloader());
        self::assertSame("{$base}13/SaxonCHE-linux-riscv64-13-1-1.zip", $riscv->getVendorArchiveURL('13.1.1', $options));
        self::assertSame("{$base}11/libsaxon-HEC-linux-riscv64-v11.6.zip", $riscv->getVendorArchiveURL('11.6', $options));
        $php32 = new Extensions\saxon(self::createPHP(32), self::createOS(), self::createDownloader());
        self::assertSame("{$base}13/SaxonCHE-linux-i386-13-1-1.zip", $php32->getVendorArchiveURL('13.1.1', $options));
    }

    /**
     * @return void
     */
    public function testWindows()
    {
        $os = M::mock(OS\Windows::class);
        $os->shouldReceive('getDisplayName')->andReturn('The OS');
        $extension = new Extensions\saxon(self::createPHP(), $os, self::createDownloader());
        $this->expectException(Exception\UnsupportedOS::class);
        $extension->getVendorArchiveURL('13.1.1', self::createOptions('HE'));
    }

    /**
     * @return void
     */
    public function testMacOS()
    {
        $commands = [];
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('brew')->andReturn('/opt/homebrew/bin/brew');
        $fs->shouldReceive('run')->andReturnUsing(static function (Filesystem\Command $command) use (&$commands) {
            $commands[] = array_merge([$command->getCommand()], $command->getArguments());

            return [0, $command->getArguments() === ['--prefix'] ? "/opt/homebrew\n" : ''];
        });
        $fs->shouldReceive('isDirectory')->with('/opt/homebrew/saxonc/lib')->andReturn(false);
        $fs->shouldReceive('createDirectory')->once()->with('/opt/homebrew/saxonc/lib');
        $fs->shouldReceive('isDirectory')->with('/src/php/src')->andReturn(true);
        $os = M::mock(OS\MacOS::class);
        $os->shouldReceive('getDisplayName')->andReturn('The OS');
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getSystemPackager')->andReturn(new Homebrew($os));
        $page = <<<'EOT'
<a href="https://downloads.saxonica.com/SaxonC/HE/13/SaxonCHE-linux-arm64-13-1-1.zip" />
<a href="https://downloads.saxonica.com/SaxonC/HE/13/SaxonCHE-macos-arm64-13-0-0.zip" />
EOT;
        $options = self::createOptions('HE');
        $extension = new Extensions\saxon(self::createPHP(64, 'arm64'), $os, self::createDownloader($page));
        self::assertSame('13.0.0', $extension->getVendorArchiveVersion('', $options));
        $base = 'https://downloads.saxonica.com/SaxonC/HE/';
        self::assertSame("{$base}13/SaxonCHE-macos-arm64-13-1-1.zip", $extension->getVendorArchiveURL('13.1.1', $options));
        self::assertSame("{$base}12/SaxonCHE-macos-aarch64-12-8-0.zip", $extension->getVendorArchiveURL('12.8.0', $options));
        $context = self::createBuildContext('12.10.1', $options, '/src');
        self::assertSame(['EXTRA_LDFLAGS' => '-Wl,-rpath,/opt/homebrew/saxonc/lib'], $extension->getConfigureEnvironment($context));
        self::assertSame('/src/php/src', $extension->prepareVendorSource($context));
        self::assertSame(
            [
                ['/opt/homebrew/bin/brew', '--prefix'],
                ['find', '/src', '(', '-type', 'f', '-o', '-type', 'l', ')', '-name', 'libsaxonc-*.dylib', '-exec', 'cp', '-R', '{}', '/opt/homebrew/saxonc/lib/', ';'],
                ['find', '/src', '-type', 'f', '-name', 'php8_XsltExecutable.cpp', '-exec', 'sed', '-i', '', 's/Z_TYPE_P(val) != NULL/Z_TYPE_P(val) != IS_NULL/g', '{}', ';'],
            ],
            $commands
        );
        $this->expectException(Exception\UnsupportedOS::class);
        $extension->getVendorArchiveURL('12.3', $options);
    }

    /**
     * @return void
     */
    public function testLatestVersionOfUnavailableArchitecture()
    {
        $page = '<a href="https://downloads.saxonica.com/SaxonC/HE/13/SaxonCHE-linux-x86_64-13-1-1.zip" />';
        $extension = new Extensions\saxon(self::createPHP(32), self::createOS(), self::createDownloader($page));
        $this->expectException(Exception\IO\NotFound::class);
        $extension->getVendorArchiveVersion('', self::createOptions('HE'));
    }

    /**
     * @return void
     */
    public function testConfigureArguments()
    {
        $extension = self::createExtension();
        $options = self::createOptions('PE');
        self::assertSame(['--with-saxon=/src/SaxonCPE'], $extension->getVendorConfigureArguments(self::createBuildContext('12.3', $options, '/src')));
        self::assertSame(['--with-saxonc=/src/SaxonCPE', '--with-saxonc-core=/src/SaxonCCorePE'], $extension->getVendorConfigureArguments(self::createBuildContext('13.0.0', $options, '/src')));
    }
}
