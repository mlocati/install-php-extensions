<?php

namespace IPE\Test\Extension\Manifest;

use IPE\Exception;
use IPE\Extension\Manifest;
use IPE\Extension\Manifest\Reader;
use IPE\Extension\Manifest\Reader\Format;
use IPE\Option\ConfigureOption;
use IPE\Service\BasicXmlParser;
use IPE\Test\Support\TestCase;

final class ReaderTest extends TestCase
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
     * @return string
     */
    private static function getAssetsDir()
    {
        return self::$fs->getRealPath(IPE_ROOT_DIR . '/test/assets/resolver/directories');
    }

    /**
     * @param string $directory
     * @param string $file
     *
     * @return string
     */
    private static function readAsset($directory, $file)
    {
        return self::$fs->readFile(self::getAssetsDir() . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . $file);
    }

    /**
     * @return array<non-empty-string, array{string, bool|null, string|null}>
     */
    private static function describeConfigureOptions(Manifest $manifest)
    {
        $result = [];
        foreach ($manifest->getConfigureOptions() as $option) {
            $result[$option->getName()] = [$option->getDescription(), $option->needsValue(), $option->getDefault()];
        }

        return $result;
    }

    /**
     * @return void
     */
    public function testParseComposerJson()
    {
        $manifest = (new Format\ComposerJson(new \IPE\Logger()))->parse(self::readAsset('known-composer', 'composer.json'));
        self::assertInstanceOf(Manifest::class, $manifest);
        self::assertSame(Manifest::TYPE_COMPOSER, $manifest->getType());
        self::assertSame('apcu/apcu', $manifest->getPackageName());
        self::assertSame('apcu', $manifest->getExtensionName());
        self::assertSame('', $manifest->getVersion());
        self::assertFalse($manifest->isZendExtension());
        self::assertSame('>=7.0', $manifest->getPHPConstraint());
        self::assertSame(['json' => '*', 'spl' => '^8'], $manifest->getRequiredExtensions());
        self::assertSame(['igbinary' => '*'], $manifest->getOptionalExtensions());
        self::assertSame(['curl' => '>=7.50', 'zlib' => '*'], $manifest->getRequiredLibraries());
        self::assertFalse($manifest->supportsZTS());
        self::assertTrue($manifest->supportsNTS());
        self::assertSame(40, $manifest->getPriority());
        self::assertSame('src', $manifest->getBuildPath());
        self::assertSame([], $manifest->getOSFamilies());
        self::assertSame(['windows'], $manifest->getOSFamiliesExclude());
        self::assertSame(
            [
                'enable-apcu-debug' => ['Enable internal debugging', false, null],
                'with-apcu-dir' => ['Path to something', true, null],
                'enable-apcu-clear-signal' => ['Install a signal handler for SIGUSR1', false, null],
            ],
            self::describeConfigureOptions($manifest)
        );
        self::assertInstanceOf(ConfigureOption::class, $manifest->getConfigureOption('--with-apcu-dir'));
        self::assertNull($manifest->getConfigureOption('nope'));
        self::assertSame('php-ext', $manifest->getRawData()['type']);
    }

    /**
     * @return void
     */
    public function testParseMinimalComposerJson()
    {
        $reader = new Format\ComposerJson(new \IPE\Logger());
        $manifest = $reader->parse('{"name": "xdebug/xdebug", "type": "php-ext-zend", "version": "3.4.0"}');
        self::assertInstanceOf(Manifest::class, $manifest);
        self::assertSame('xdebug', $manifest->getExtensionName());
        self::assertSame('3.4.0', $manifest->getVersion());
        self::assertTrue($manifest->isZendExtension());
        self::assertSame('', $manifest->getPHPConstraint());
        self::assertSame([], $manifest->getRequiredExtensions());
        self::assertSame([], $manifest->getRequiredLibraries());
        self::assertTrue($manifest->supportsZTS());
        self::assertTrue($manifest->supportsNTS());
        self::assertNull($manifest->getPriority());
        self::assertSame('', $manifest->getBuildPath());
        self::assertSame([], $manifest->getConfigureOptions());

        $manifest = $reader->parse('{"name": "foo/bar-baz", "type": "php-ext"}');
        self::assertInstanceOf(Manifest::class, $manifest);
        self::assertSame('bar-baz', $manifest->getExtensionName());
        self::assertNull($reader->parse('{"name": "foo/bar", "type": "library"}'));
        self::assertNull($reader->parse('{"name": "foo/bar"}'));
    }

    /**
     * @return void
     */
    public function testParseConflicts()
    {
        $manifest = (new Format\ComposerJson(new \IPE\Logger()))->parse('{"name": "foo/bar", "type": "php-ext", "conflict": {"ext-one": "*", "ext-two": "<2", "foo/baz": "*"}}');
        self::assertInstanceOf(Manifest::class, $manifest);
        self::assertSame(['one' => '*', 'two' => '<2'], $manifest->getConflictingExtensions());
        $reader = new Format\PackageXml(new \IPE\Logger(), new BasicXmlParser());
        $manifest = $reader->parse('<package version="2.0"><name>foo</name><version><release>1.0.0</release></version><dependencies><required><extension><name>one</name><conflicts/></extension><extension><name>two</name><min>2.0</min><conflicts/></extension><extension><name>three</name></extension></required></dependencies></package>');
        self::assertInstanceOf(Manifest::class, $manifest);
        self::assertSame(['one' => '*', 'two' => '>=2.0'], $manifest->getConflictingExtensions());
        self::assertSame(['three' => '*'], $manifest->getRequiredExtensions());
        $manifest = $reader->parse('<package version="1.0"><name>foo</name><release><version>1.0.0</version><deps><dep type="ext" rel="not">one</dep><dep type="ext">two</dep></deps></release></package>');
        self::assertInstanceOf(Manifest::class, $manifest);
        self::assertSame(['one' => '*'], $manifest->getConflictingExtensions());
        self::assertSame(['two' => '*'], $manifest->getRequiredExtensions());
    }

    /**
     * @return array{string}[]
     */
    public static function provideInvalidComposerJsonCases()
    {
        return [
            [''],
            ['   '],
            ['{'],
            ['"just a string"'],
        ];
    }

    /**
     * @dataProvider provideInvalidComposerJsonCases
     *
     * @param string $json
     *
     * @return void
     */
    public function testParseInvalidComposerJson($json)
    {
        try {
            (new Format\ComposerJson(new \IPE\Logger()))->parse($json);
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidArgument $x) {
            self::assertNotSame('', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testParsePackageXml2()
    {
        $manifest = (new Format\PackageXml(new \IPE\Logger(), new BasicXmlParser()))->parse(self::readAsset('known-pecl', 'package.xml'));
        self::assertInstanceOf(Manifest::class, $manifest);
        self::assertSame(Manifest::TYPE_PECL, $manifest->getType());
        self::assertSame('lzf', $manifest->getPackageName());
        self::assertSame('LZF', $manifest->getExtensionName());
        self::assertSame('1.7.0', $manifest->getVersion());
        self::assertFalse($manifest->isZendExtension());
        self::assertSame('>=7.0.0 <=8.99.99 !=8.0.0', $manifest->getPHPConstraint());
        self::assertSame(['spl' => '*', 'json' => '>=1.2.0'], $manifest->getRequiredExtensions());
        self::assertSame(['zlib' => '*'], $manifest->getOptionalExtensions());
        self::assertSame([], $manifest->getRequiredLibraries());
        self::assertTrue($manifest->supportsZTS());
        self::assertTrue($manifest->supportsNTS());
        self::assertNull($manifest->getPriority());
        self::assertSame('', $manifest->getBuildPath());
        self::assertSame([], $manifest->getOSFamilies());
        self::assertSame(['windows'], $manifest->getOSFamiliesExclude());
        self::assertSame(
            [
                'enable-lzf-better-compression' => ['Sacrifice speed in favour of compression ratio?', null, 'no'],
                'with-lzf-dir' => ['Path to the "lzf" library', null, null],
            ],
            self::describeConfigureOptions($manifest)
        );
        self::assertSame('package', $manifest->getRawData()['name']);
    }

    /**
     * @return void
     */
    public function testParsePackageXml1()
    {
        $manifest = (new Format\PackageXml(new \IPE\Logger(), new BasicXmlParser()))->parse(self::readAsset('package-v1', 'package.xml'));
        self::assertInstanceOf(Manifest::class, $manifest);
        self::assertSame(Manifest::TYPE_PECL, $manifest->getType());
        self::assertSame('oldext', $manifest->getPackageName());
        self::assertSame('old_ext', $manifest->getExtensionName());
        self::assertSame('0.5.1', $manifest->getVersion());
        self::assertSame('>=4.3.0 <6.0.0', $manifest->getPHPConstraint());
        self::assertSame(['pcre' => '*'], $manifest->getRequiredExtensions());
        self::assertSame(['json' => '>=1.2'], $manifest->getOptionalExtensions());
        self::assertSame(['z' => '>=1.2.3', 'ltdl' => '*'], $manifest->getRequiredLibraries());
        self::assertSame(['linux'], $manifest->getOSFamilies());
        self::assertSame(
            [
                'with-oldext-dir' => ['Where is the oldext library installed?', null, '/usr'],
            ],
            self::describeConfigureOptions($manifest)
        );
    }

    /**
     * @return void
     */
    public function testParsePackageXmlWithoutSchemaVersion()
    {
        $reader = new Format\PackageXml(new \IPE\Logger(), new BasicXmlParser());
        $manifest = $reader->parse('<package><name>foo</name><version><release>2.1</release></version></package>');
        self::assertInstanceOf(Manifest::class, $manifest);
        self::assertSame('foo', $manifest->getExtensionName());
        self::assertSame('2.1', $manifest->getVersion());
        $manifest = $reader->parse('<package><name>foo</name><release><version>1.1</version></release></package>');
        self::assertInstanceOf(Manifest::class, $manifest);
        self::assertSame('1.1', $manifest->getVersion());
        self::assertNull($reader->parse('<package><name>foo</name></package>'));
    }

    /**
     * @return void
     */
    public function testParseNonPackageXml()
    {
        $reader = new Format\PackageXml(new \IPE\Logger(), new BasicXmlParser());
        self::assertNull($reader->parse('<root><child>value</child></root>'));
        self::assertNull($reader->parse('<package version="2.0"><channel>pecl.php.net</channel></package>'));
        try {
            $reader->parse('<package><name>foo</package>');
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidArgument $x) {
            self::assertNotSame('', $x->getMessage());
        }
    }

    /**
     * @return array{string, string, string, string}[]
     */
    public static function provideDirectoryCases()
    {
        return [
            ['known-pecl', Manifest::TYPE_PECL, 'lzf', '1.7.0'],
            ['package-v1', Manifest::TYPE_PECL, 'oldext', '0.5.1'],
            ['known-composer', Manifest::TYPE_COMPOSER, 'apcu/apcu', ''],
            ['unknown', Manifest::TYPE_COMPOSER, 'foo/bar', '9.9.9'],
            ['nomanifest', '', '', ''],
            ['notextension', '', '', ''],
        ];
    }

    /**
     * @dataProvider provideDirectoryCases
     *
     * @param string $directory
     * @param string $expectedType empty if no manifest is expected
     * @param string $expectedPackageName
     * @param string $expectedVersion
     *
     * @return void
     */
    public function testReadFromDirectory($directory, $expectedType, $expectedPackageName, $expectedVersion)
    {
        $manifest = (new Reader(new \IPE\Logger()))->readFromDirectory(self::getAssetsDir() . DIRECTORY_SEPARATOR . $directory, self::$fs);
        if ($expectedType === '') {
            self::assertNull($manifest);
        } else {
            self::assertInstanceOf(Manifest::class, $manifest);
            self::assertSame($expectedType, $manifest->getType());
            self::assertSame($expectedPackageName, $manifest->getPackageName());
            self::assertSame($expectedVersion, $manifest->getVersion());
        }
    }

    /**
     * @return void
     */
    public function testReadFromDirectoryOrder()
    {
        $dir = self::$fs->createTemporaryDirectory();
        self::$fs->writeFile($dir . DIRECTORY_SEPARATOR . 'composer.json', '{"name": "foo/bar", "type": "php-ext", "version": "2.0.0"}');
        self::$fs->writeFile($dir . DIRECTORY_SEPARATOR . 'package.xml', '<package version="2.0"><name>bar</name><version><release>1.0.0</release></version></package>');
        self::$fs->writeFile($dir . DIRECTORY_SEPARATOR . 'package2.xml', '<package version="2.0"><name>bar</name><version><release>1.5.0</release></version></package>');
        // The first manifest found wins: composer.json, then package2.xml, then package.xml
        self::assertSame('2.0.0', self::readVersionFromDirectory(new Reader(new \IPE\Logger()), $dir));
        $xmlOnly = new Reader(new \IPE\Logger(), [new Format\PackageXml(new \IPE\Logger(), new BasicXmlParser())]);
        self::assertSame('1.5.0', self::readVersionFromDirectory($xmlOnly, $dir));
        self::$fs->deleteFile($dir . DIRECTORY_SEPARATOR . 'package2.xml');
        self::assertSame('1.0.0', self::readVersionFromDirectory($xmlOnly, $dir));
        // An invalid manifest is skipped in favour of the next one
        self::$fs->writeFile($dir . DIRECTORY_SEPARATOR . 'composer.json', '{');
        self::assertSame('1.0.0', self::readVersionFromDirectory(new Reader(new \IPE\Logger()), $dir));
    }

    /**
     * @return void
     */
    public function testReadFromComposerMetadata()
    {
        $metadata = ['name' => 'foo/bar', 'type' => 'php-ext', 'version' => 'v1.2.3', 'php-ext' => ['extension-name' => 'baz', 'build-path' => 'ext']];
        $manifest = (new Reader(new \IPE\Logger()))->readFromComposerMetadata($metadata);
        self::assertInstanceOf(Manifest::class, $manifest);
        self::assertSame(Manifest::TYPE_COMPOSER, $manifest->getType());
        self::assertSame('baz', $manifest->getExtensionName());
        self::assertSame('ext', $manifest->getBuildPath());
        self::assertNull((new Reader(new \IPE\Logger()))->readFromComposerMetadata(['name' => 'foo/bar', 'type' => 'library']));
        self::assertNull((new Reader(new \IPE\Logger(), [new Format\PackageXml(new \IPE\Logger(), new BasicXmlParser())]))->readFromComposerMetadata($metadata));
    }

    /**
     * @param non-empty-string $dir
     *
     * @return string
     */
    private static function readVersionFromDirectory(Reader $reader, $dir)
    {
        $manifest = $reader->readFromDirectory($dir, self::$fs);
        self::assertInstanceOf(Manifest::class, $manifest);

        return $manifest->getVersion();
    }

    /**
     * @return void
     */
    public function testReaderFormats()
    {
        $formats = (new Reader(new \IPE\Logger()))->getFormats();
        self::assertCount(2, $formats);
        self::assertInstanceOf(Format\ComposerJson::class, $formats[0]);
        self::assertInstanceOf(Format\PackageXml::class, $formats[1]);
    }

    /**
     * @return void
     */
    public function testPackageXmlOfZendExtension()
    {
        $xml = str_replace('extsrcrelease', 'zendextsrcrelease', self::readAsset('known-pecl', 'package.xml'));
        $manifest = (new Format\PackageXml(new \IPE\Logger(), new BasicXmlParser()))->parse($xml);
        self::assertInstanceOf(Manifest::class, $manifest);
        self::assertTrue($manifest->isZendExtension());
    }
}
