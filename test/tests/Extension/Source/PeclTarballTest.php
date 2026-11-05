<?php

namespace IPE\Test\Extension\Source;

use IPE\Extension\Source\PeclTarball;
use IPE\Filesystem;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class PeclTarballTest extends TestCase
{
    /**
     * @param list<string> $items the contents of /x (the names ending with / are directories)
     *
     * @return \IPE\Filesystem&\Mockery\MockInterface
     */
    private static function createFilesystem(array $items)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('listDirectoryContents')->with('/x')->andReturn(array_map(static function ($item) { return rtrim($item, '/'); }, $items));
        foreach ($items as $item) {
            $fs->shouldReceive('isDirectory')->with('/x/' . rtrim($item, '/'))->andReturn(substr($item, -1) === '/');
        }

        return $fs;
    }

    /**
     * @return void
     */
    public function testPackageXmlIsMovedIntoTheSourceDirectory()
    {
        $fs = self::createFilesystem(['package.xml', 'foo-1.0.0/']);
        $fs->shouldReceive('exists')->with('/x/foo-1.0.0/package.xml')->andReturn(false);
        $fs->shouldReceive('rename')->once()->with('/x/package.xml', '/x/foo-1.0.0/package.xml');
        self::assertSame('/x/foo-1.0.0', PeclTarball::findSourceDir('/x', $fs));
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function provideNotPeclTarballs()
    {
        return [
            'config.m4 next to the only subdirectory' => [['config.m4', 'shared/', 'foo.c']],
            'config0.m4 next to the only subdirectory' => [['config0.m4', 'src/']],
            'config.w32 next to the only subdirectory' => [['config.w32', 'src/']],
            'more subdirectories' => [['package.xml', 'a/', 'b/']],
            'no subdirectories' => [['config.m4', 'foo.c']],
        ];
    }

    /**
     * @dataProvider provideNotPeclTarballs
     *
     * @param list<string> $items
     *
     * @return void
     */
    public function testNotPeclTarballs(array $items)
    {
        $fs = self::createFilesystem($items);
        $fs->shouldNotReceive('rename');
        self::assertSame('/x', PeclTarball::findSourceDir('/x', $fs));
    }
}
