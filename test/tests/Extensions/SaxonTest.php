<?php

namespace IPE\Test\Extensions;

use IPE\Exception;
use IPE\Extensions;
use IPE\Filesystem;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class SaxonTest extends TestCase
{
    /**
     * @param string $architecture
     * @param string $rawArchitecture
     *
     * @return \IPE\OS\Posix
     */
    private static function createOS($architecture = OS\Architecture::X64, $rawArchitecture = 'x86_64')
    {
        $os = M::mock(OS\Debian::class);
        $os->shouldReceive('getMachineArchitecture')->andReturn($architecture);
        $os->shouldReceive('getRawMachineArchitecture')->andReturn($rawArchitecture);

        return $os;
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
     *
     * @return \IPE\PHPInfo
     */
    private static function createPHP($bits = 64)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getBits')->andReturn($bits);

        return $php;
    }

    /**
     * @param string $architecture
     * @param string $page
     *
     * @return \IPE\Extensions\saxon
     */
    private static function createExtension($architecture = OS\Architecture::X64, $page = '')
    {
        return new Extensions\saxon(self::createPHP(), self::createOS($architecture), self::createDownloader($page));
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
        self::assertSame('13.1.1', self::createExtension(OS\Architecture::X64, $page)->getVendorArchiveVersion('', self::createOptions('HE')));
        self::assertSame('13.0.0', self::createExtension(OS\Architecture::ARM64, $page)->getVendorArchiveVersion('', self::createOptions('HE')));
        self::assertSame('12.3', self::createExtension(OS\Architecture::X64, $page)->getVendorArchiveVersion('12.3', self::createOptions('HE')));
        $this->expectException(Exception\IO::class);
        self::createExtension(OS\Architecture::X64, $page)->getVendorArchiveVersion('', self::createOptions('PE'));
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
        self::assertSame("{$base}HE/12/SaxonCHE-linux-aarch64-12-8-0.zip", self::createExtension(OS\Architecture::ARM64)->getVendorArchiveURL('12.8.0', $options));
        self::assertSame("{$base}HE/12/SaxonCHE-linux-arm64-12-10-1.zip", self::createExtension(OS\Architecture::ARM64)->getVendorArchiveURL('12.10.1', $options));
        self::assertSame("{$base}HE/12/libsaxon-HEC-linux-arm64-v12.3.zip", self::createExtension(OS\Architecture::ARM64)->getVendorArchiveURL('12.3', $options));
    }

    /**
     * @return void
     */
    public function testOtherArchitecturesAreLeftToTheVendor()
    {
        $base = 'https://downloads.saxonica.com/SaxonC/HE/';
        $options = self::createOptions('HE');
        $riscv = new Extensions\saxon(self::createPHP(), self::createOS(OS\Architecture::OTHER, 'riscv64'), self::createDownloader());
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
        self::assertSame(['--with-saxon=/src/SaxonCPE'], $extension->getVendorConfigureArguments('/src', '12.3', $options));
        self::assertSame(['--with-saxonc=/src/SaxonCPE', '--with-saxonc-core=/src/SaxonCCorePE'], $extension->getVendorConfigureArguments('/src', '13.0.0', $options));
    }
}
