<?php

namespace IPE\Test\Installer;

use IPE\Exception;
use IPE\Extension\Resolved;
use IPE\Filesystem;
use IPE\Installer\BuildOptions;
use IPE\Installer\State;
use IPE\Installer\VendorArchivePreparer;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\ResolverTest\FakeExtension;
use IPE\Test\Support\ResolverTest\FakeVendorArchiveExtension;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class VendorArchivePreparerTest extends TestCase
{
    /**
     * @return void
     */
    public function testPrepare()
    {
        $fs = new Filesystem\Local(new \IPE\Logger());
        $php = M::mock(PHPInfo::class);
        $state = new State($php, $fs);
        $directory = $state->getVolatileDir()->createSubdir();
        $archive = $directory . $fs->getDirectorySeparator() . VendorArchivePreparer::ARCHIVE_FILENAME;
        $fs->writeFile($archive, 'zip');
        $extractor = M::mock(Filesystem\ArchiveExtractor::class);
        $extractor->shouldReceive('extractTo')->once()->with($archive, M::type('string'), Filesystem\ArchiveFormat::ZIP)->andReturn('/root');
        $resolved = new Resolved(new FakeVendorArchiveExtension($php, M::mock(OS::class)), $directory, '1.2.3', Resolved::ORIGIN_VENDOR_ARCHIVE, null, new Values(['--user=1']));
        $prepared = (new VendorArchivePreparer($extractor, $fs, new BuildOptions()))->prepare($resolved, $state);
        self::assertFalse($fs->exists($archive));
        self::assertSame('/root/src', $prepared->getPath());
        self::assertSame('1.2.3', $prepared->getVersion());
        self::assertSame(['--with-lib=/root/lib', '--user=1'], $prepared->getOptionValues()->getConfigureArguments());
    }

    /**
     * @return void
     */
    public function testWrongExtension()
    {
        $fs = new Filesystem\Local(new \IPE\Logger());
        $this->expectException(Exception\InvalidArgument::class);
        $php = M::mock(PHPInfo::class);
        (new VendorArchivePreparer(M::mock(Filesystem\ArchiveExtractor::class), $fs, new BuildOptions()))->prepare(new Resolved(new FakeExtension($php, M::mock(OS::class)), '/src', '1.2.3', Resolved::ORIGIN_VENDOR_ARCHIVE), new State($php, $fs));
    }
}
