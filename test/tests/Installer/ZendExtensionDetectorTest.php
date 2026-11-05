<?php

namespace IPE\Test\Installer;

use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Installer\ZendExtensionDetector;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class ZendExtensionDetectorTest extends TestCase
{
    const MODULE_PATH = '/ext/module.so';

    /**
     * @return array<string, array{string, bool}>
     */
    public static function provideContents()
    {
        return [
            'ELF or PE regular extension' => ["\x7fELF\0get_module\0zend_get_module\0", false],
            'ELF or PE Zend extension' => ["\x7fELF\0zend_extension_entry\0", true],
            'ELF or PE hybrid extension' => ["\x7fELF\0get_module\0zend_extension_entry\0extension_version_info\0", true],
            'Mach-O regular extension' => ["\xcf\xfa\xed\xfe\0_get_module\0", false],
            'Mach-O Zend extension' => ["\xcf\xfa\xed\xfe\0_zend_extension_entry\0", true],
            'name in a text' => ["\x7fELF\0Missing zend_extension_entry symbol\0", false],
            'longer symbol name' => ["\x7fELF\0zend_extension_entry_count\0my_zend_extension_entry\0", false],
            'two leading underscores' => ["\x7fELF\0__zend_extension_entry\0", false],
        ];
    }

    /**
     * @dataProvider provideContents
     *
     * @param string $contents
     * @param bool $expected
     *
     * @return void
     */
    public function testWithoutReadelf($contents, $expected)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('readelf')->andReturn('');
        $fs->shouldReceive('readFile')->with(self::MODULE_PATH)->andReturn($contents);

        self::assertSame($expected, (new ZendExtensionDetector($fs, new \IPE\Logger()))->isZendExtension(self::MODULE_PATH));
    }

    /**
     * @dataProvider provideContents
     *
     * @param string $contents
     * @param bool $expected
     *
     * @return void
     */
    public function testReadelfFailure($contents, $expected)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('readelf')->andReturn('/usr/bin/readelf');
        // readelf is used only for ELF files
        $fs->shouldReceive('run')->times(substr($contents, 0, 4) === "\x7fELF" ? 1 : 0)->with(self::isCommand(self::createReadelfCommand()))->andReturn([1, '']);
        $fs->shouldReceive('readFile')->with(self::MODULE_PATH)->andReturn($contents);

        self::assertSame($expected, (new ZendExtensionDetector($fs, new \IPE\Logger()))->isZendExtension(self::MODULE_PATH));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function provideReadelfOutputs()
    {
        $header = implode("\n", [
            '',
            "Symbol table '.dynsym' contains 4 entries:",
            '   Num:    Value          Size Type    Bind   Vis      Ndx Name',
            '     0: 0000000000000000     0 NOTYPE  LOCAL  DEFAULT  UND ',
            '',
        ]);

        return [
            'regular extension' => [$header . "     1: 0000000000001120    10 FUNC    GLOBAL DEFAULT   11 get_module\n", false],
            'Zend extension' => [$header . "     1: 0000000000004020   168 OBJECT  GLOBAL DEFAULT   22 zend_extension_entry\n", true],
            'hybrid extension' => [$header . "     1: 0000000000001120    10 FUNC    GLOBAL DEFAULT   11 get_module\n     2: 0000000000004020   168 OBJECT  GLOBAL DEFAULT   22 zend_extension_entry\n", true],
            'used but not defined' => [$header . "     1: 0000000000000000     0 OBJECT  GLOBAL DEFAULT  UND zend_extension_entry\n", false],
            'longer symbol name' => [$header . "     1: 0000000000004020   168 OBJECT  GLOBAL DEFAULT   22 my_zend_extension_entry\n", false],
        ];
    }

    /**
     * @dataProvider provideReadelfOutputs
     *
     * @param string $output
     * @param bool $expected
     *
     * @return void
     */
    public function testWithReadelf($output, $expected)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('readelf')->andReturn('/usr/bin/readelf');
        $fs->shouldReceive('run')->once()->with(self::isCommand(self::createReadelfCommand()))->andReturn([0, $output]);
        // Without the symbol name, so that only readelf can find it
        $fs->shouldReceive('readFile')->with(self::MODULE_PATH)->andReturn("\x7fELF\0");

        self::assertSame($expected, (new ZendExtensionDetector($fs, new \IPE\Logger()))->isZendExtension(self::MODULE_PATH));
    }

    /**
     * @return \IPE\Filesystem\Command
     */
    private static function createReadelfCommand()
    {
        return Command::create('/usr/bin/readelf', ['--wide', '--dyn-syms', self::MODULE_PATH])->withOutput(Command::OUTPUT_CAPTURE_STDOUT);
    }
}
