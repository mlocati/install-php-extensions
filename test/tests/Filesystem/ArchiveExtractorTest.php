<?php

namespace IPE\Test\Filesystem;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\ArchiveExtractor;
use IPE\Filesystem\ArchiveExtractor\FormatExtractor;
use IPE\Filesystem\ArchiveFormat;
use IPE\Filesystem\Command;
use IPE\Test\Support\ArchiveExtractorTest\NonLocalLikeFilesystem;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class ArchiveExtractorTest extends TestCase
{
    /**
     * @var \IPE\Filesystem\Local
     */
    private static $fs;

    /**
     * @var string
     */
    private static $tarOutput;

    /**
     * @var string
     */
    private static $bsdtarOutput;

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Test\Support\TestCaseBase::doSetUpBeforeClass()
     */
    public static function doSetUpBeforeClass()
    {
        if (self::$fs !== null) {
            return;
        }
        self::$fs = self::createLocalFilesystem();
        $tar = self::$fs->resolveCommand('tar');
        if ($tar === '') {
            self::$tarOutput = '';
        } else {
            list($rc, $output) = self::$fs->run(Command::create($tar, ['--help']));
            if ($rc !== 0) {
                throw new \RuntimeException("Running {$tar} --help failed: {$output}");
            }
            self::$tarOutput = $output;
        }
        $bsdtar = self::$fs->resolveCommand('bsdtar');
        if ($bsdtar === '') {
            self::$bsdtarOutput = '';
        } else {
            list($rc, $output) = self::$fs->run(Command::create($bsdtar, ['--help']));
            if ($rc !== 0) {
                throw new \RuntimeException("Running {$bsdtar} --help failed: {$output}");
            }
            self::$bsdtarOutput = $output;
        }
    }

    /**
     * @return \Generator<array{\IPE\Filesystem\ArchiveExtractor\FormatExtractor, non-empty-string}>
     */
    public static function provideValidFormatExtractorCases()
    {
        foreach (self::getAvailableExtractors() as list($extractorClass, $format, $testFilename)) {
            switch ($extractorClass) {
                case FormatExtractor\WithBsdtarCommand::class:
                    yield [self::requireExtractor(FormatExtractor\WithBsdtarCommand::createIfAvailable(self::$fs, new \IPE\Logger())), $testFilename];
                    break;

                case FormatExtractor\WithPharExtension::class:
                    yield [self::requireExtractor(FormatExtractor\WithPharExtension::createIfAvailable($format, self::$fs, new \IPE\Logger())), $testFilename];
                    break;

                case FormatExtractor\WithPowershellCommand::class:
                    foreach (['pwsh', 'powershell'] as $command) {
                        $resolved = self::$fs->resolveCommand($command);
                        if ($resolved === '') {
                            continue;
                        }
                        /** @var \IPE\Filesystem\Local&\Mockery\MockInterface $fs */
                        $fs = M::mock(self::$fs)->makePartial();
                        $fs->shouldReceive('resolveCommand')->andReturnUsing(static function ($wanted) use ($command, $resolved) {
                            return $wanted === $command ? $resolved : '';
                        });
                        yield [self::requireExtractor(FormatExtractor\WithPowershellCommand::createIfAvailable($fs, new \IPE\Logger())), $testFilename];
                    }
                    break;

                case FormatExtractor\WithTarCommand::class:
                    yield [self::requireExtractor(FormatExtractor\WithTarCommand::createIfAvailable($format, self::$fs, new \IPE\Logger())), $testFilename];
                    break;

                case FormatExtractor\WithUnzipCommand::class:
                    yield [self::requireExtractor(FormatExtractor\WithUnzipCommand::createIfAvailable(self::$fs, new \IPE\Logger())), $testFilename];
                    break;

                case FormatExtractor\WithZipExtension::class:
                    yield [self::requireExtractor(FormatExtractor\WithZipExtension::createIfAvailable(self::$fs, new \IPE\Logger())), $testFilename];
                    $nonLocaLkelFS = new NonLocalLikeFilesystem(new \IPE\Logger(), IPE_TEST_TMPDIR);
                    yield [self::requireExtractor(FormatExtractor\WithZipExtension::createIfAvailable($nonLocaLkelFS, new \IPE\Logger())), $testFilename];
                    break;

                default:
                    throw new \RuntimeException("Unrecognized format extractor {$extractorClass}");
            }
        }
    }

    /**
     * getAvailableExtractors() already checked the availability: a null here is a bug in the test.
     *
     * @template T of \IPE\Filesystem\ArchiveExtractor\FormatExtractor
     *
     * @param T|null $extractor
     *
     * @return T
     */
    private static function requireExtractor($extractor)
    {
        if ($extractor === null) {
            throw new \RuntimeException('The format extractor is unexpectedly unavailable');
        }

        return $extractor;
    }

    /**
     * @dataProvider provideValidFormatExtractorCases
     *
     * @param non-empty-string $archive
     *
     * @return void
     */
    public function testFormatExtractor(FormatExtractor $formatExtractor, $archive)
    {
        $vol = self::$fs->createVolatileDirectory();
        $volPath = $vol->getPath();
        $extractedDir = $formatExtractor->extractTo(IPE_ROOT_DIR . '/test/assets/webserver/' . $archive, $volPath);
        self::assertSame($volPath . self::$fs->getDirectorySeparator() . 'rootDir', $extractedDir);
        self::checkExtractedArchiveContents($extractedDir);
    }

    /**
     * @return list<array{non-empty-string, non-empty-string}>
     */
    public static function provideValidArchiveExtractorCases()
    {
        $result = [];
        foreach (self::getAvailableExtractors() as list(, $format, $testFilename)) {
            $case = [$testFilename, $format];
            if (!in_array($case, $result, true)) {
                $result[] = $case;
            }
        }

        return $result;
    }

    /**
     * @dataProvider provideValidArchiveExtractorCases
     *
     * @param non-empty-string $archive
     * @param non-empty-string $format
     *
     * @return void
     */
    public function testArchiveExtractor($archive, $format)
    {
        $archiveExtractor = new ArchiveExtractor(self::$fs, new \IPE\Logger());
        self::assertTrue($archiveExtractor->canHandleFormat($format));
        foreach (['', $format] as $passedFormat) {
            $vol = self::$fs->createVolatileDirectory();
            $volPath = $vol->getPath();
            $extractedDir = $archiveExtractor->extractTo(IPE_ROOT_DIR . '/test/assets/webserver/' . $archive, $volPath, $passedFormat);
            self::assertSame($volPath . self::$fs->getDirectorySeparator() . 'rootDir', $extractedDir);
            self::checkExtractedArchiveContents($extractedDir);
        }
    }

    /**
     * @return void
     */
    public function testExtractorIsChosenEveryTime()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->twice()->with('bsdtar')->andReturn('');
        $fs->shouldReceive('resolveCommand')->twice()->with('unzip')->andReturn('/usr/bin/unzip');
        $archiveExtractor = new ArchiveExtractor($fs, new \IPE\Logger());
        self::assertTrue($archiveExtractor->canHandleFormat(ArchiveFormat::ZIP));
        self::assertTrue($archiveExtractor->canHandleFormat(ArchiveFormat::ZIP));
    }

    /**
     * @return void
     */
    public function testPharExtensionDecompressesZipEntries()
    {
        $extractor = self::getPharZipExtractor();
        $vol = self::$fs->createVolatileDirectory();
        $content = str_repeat("Some compressible content\n", 100);
        $archive = $vol->getPath() . '/deflated.zip';
        self::$fs->writeFile($archive, self::createDeflatedZip(['rootDir/sub/file' => $content, 'rootDir/other' => "x\n"]));
        $destination = $vol->getPath() . '/out';
        self::$fs->createDirectory($destination);
        $extractedDir = $extractor->extractTo($archive, $destination);
        self::assertSame($content, self::$fs->readFile("{$extractedDir}/sub/file"));
        self::assertSame("x\n", self::$fs->readFile("{$extractedDir}/other"));
    }

    /**
     * @return void
     */
    public function testPharExtensionRejectsZipEntriesOutsideOfTheDestination()
    {
        $extractor = self::getPharZipExtractor();
        $vol = self::$fs->createVolatileDirectory();
        $archive = $vol->getPath() . '/evil.zip';
        self::$fs->writeFile($archive, self::createDeflatedZip(['../evil' => "evil\n"]));
        $destination = $vol->getPath() . '/a/b';
        self::$fs->createDirectory($destination);
        try {
            $extractor->extractTo($archive, $destination);
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertFalse(self::$fs->isFile($vol->getPath() . '/a/evil'));
        }
    }

    /**
     * @return \IPE\Filesystem\ArchiveExtractor\FormatExtractor\WithPharExtension
     */
    private static function getPharZipExtractor()
    {
        if (!self::isExtractorAvailable(FormatExtractor\WithPharExtension::class, ArchiveFormat::ZIP)) {
            self::markTestSkipped('The Phar extension can\'t handle zip archives');
        }

        return self::requireExtractor(FormatExtractor\WithPharExtension::createIfAvailable(ArchiveFormat::ZIP, self::$fs, new \IPE\Logger()));
    }

    /**
     * @param array<string, string> $files
     *
     * @return string
     */
    private static function createDeflatedZip(array $files)
    {
        $local = '';
        $central = '';
        foreach ($files as $name => $content) {
            $deflated = (string) gzdeflate($content);
            $header = pack('vvvvvVVVvv', 20, 0, 8, 0, 0x21, crc32($content), strlen($deflated), strlen($content), strlen($name), 0);
            $central .= "PK\x01\x02" . pack('v', 20) . $header . pack('vvvVV', 0, 0, 0, 0, strlen($local)) . $name;
            $local .= "PK\x03\x04" . $header . $name . $deflated;
        }

        return $local . $central . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($files), count($files), strlen($central), strlen($local), 0);
    }

    /**
     * @param non-empty-string $extractedDir
     *
     * @return void
     */
    private function checkExtractedArchiveContents($extractedDir)
    {
        self::assertSame("Content of file in root\n", self::$fs->readFile("{$extractedDir}/FileInRoot"));
        self::assertSame("Content of file#1 in sub\n", self::$fs->readFile("{$extractedDir}/SubDir/FileInSub1"));
        self::assertSame("Content of file#2 in sub\n", self::$fs->readFile("{$extractedDir}/SubDir/FileInSub2"));
    }

    /**
     * @return list<array{class-string, non-empty-string, non-empty-string}>
     */
    private static function getAvailableExtractors()
    {
        self::doSetUpBeforeClass();
        /** @var list<array{class-string, non-empty-string, non-empty-string}>|null $result */
        static $result;
        if ($result === null) {
            $result = [];
            foreach ([
                ArchiveFormat::TAR,
                ArchiveFormat::TAR_BZ,
                ArchiveFormat::TAR_GZ,
                ArchiveFormat::TAR_XZ,
                ArchiveFormat::ZIP,
            ] as $format) {
                $filename = 'archive.' . $format;
                foreach ([
                    FormatExtractor\WithBsdtarCommand::class,
                    FormatExtractor\WithPharExtension::class,
                    FormatExtractor\WithPowershellCommand::class,
                    FormatExtractor\WithTarCommand::class,
                    FormatExtractor\WithUnzipCommand::class,
                    FormatExtractor\WithZipExtension::class,
                ] as $extractorClass) {
                    if (self::isExtractorAvailable($extractorClass, $format)) {
                        $result[] = [
                            $extractorClass,
                            $format,
                            $filename,
                        ];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param class-string $extractorClass
     * @param non-empty-string $format
     *
     * @return bool
     */
    private static function isExtractorAvailable($extractorClass, $format)
    {
        switch ($extractorClass) {
            case FormatExtractor\WithBsdtarCommand::class:
                return $format === ArchiveFormat::ZIP && self::$bsdtarOutput !== '';

            case FormatExtractor\WithPharExtension::class:
                if (!extension_loaded('Phar')) {
                    return false;
                }

                return $format === ArchiveFormat::TAR
                    || ($format === ArchiveFormat::TAR_BZ && array_intersect(['BZIP2', \Phar::BZ2], \Phar::getSupportedCompression()) !== [])
                    || ($format === ArchiveFormat::TAR_GZ && array_intersect(['GZ', \Phar::GZ], \Phar::getSupportedCompression()) !== [])
                    || ($format === ArchiveFormat::ZIP && array_intersect(['GZ', \Phar::GZ], \Phar::getSupportedCompression()) !== []);

            case FormatExtractor\WithPowershellCommand::class:
                return $format === ArchiveFormat::ZIP && (self::$fs->resolveCommand('pwsh') !== '' || self::$fs->resolveCommand('powershell') !== '');

            case FormatExtractor\WithTarCommand::class:
                if (self::$tarOutput === '' && self::$bsdtarOutput === '') {
                    return false;
                }
                if ($format === ArchiveFormat::TAR) {
                    return true;
                }
                if ($format === ArchiveFormat::TAR_BZ) {
                    if (preg_match('#(^|\s|,)-j($|\s|,)#', self::$tarOutput . "\n" . self::$bsdtarOutput)) {
                        if (\DIRECTORY_SEPARATOR === '\\' || self::$fs->resolveCommand('bzip2') !== '') {
                            return true;
                        }
                    }
                } elseif ($format === ArchiveFormat::TAR_GZ) {
                    if (preg_match('#(^|\s|,)-z($|\s|,)#', self::$tarOutput . "\n" . self::$bsdtarOutput)) {
                        if (\DIRECTORY_SEPARATOR === '\\' || self::$fs->resolveCommand('gzip') !== '') {
                            return true;
                        }
                    }
                } elseif ($format === ArchiveFormat::TAR_XZ) {
                    if (preg_match('#(^|\s|,)-J($|\s|,)#', self::$tarOutput . "\n" . self::$bsdtarOutput)) {
                        if (\DIRECTORY_SEPARATOR === '\\' || self::$fs->resolveCommand('xz') !== '') {
                            return true;
                        }
                    }
                }

                return false;

            case FormatExtractor\WithUnzipCommand::class:
                return $format === ArchiveFormat::ZIP && self::$fs->resolveCommand('unzip') !== '';

            case FormatExtractor\WithZipExtension::class:
                return $format === ArchiveFormat::ZIP && extension_loaded('zip');
        }
        throw new \RuntimeException("Unrecognized format extractor {$extractorClass}");
    }
}
