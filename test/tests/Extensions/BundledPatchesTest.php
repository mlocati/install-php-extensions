<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\dba;
use IPE\Extensions\intl;
use IPE\Extensions\odbc;
use IPE\Extensions\sockets;
use IPE\Extensions\tokenizer;
use IPE\Filesystem;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class BundledPatchesTest extends TestCase
{
    const SOURCE_DIR = '/tmp/ipe/ext';

    /**
     * @param string $version
     *
     * @return \IPE\PHPInfo
     */
    private static function createPHP($version)
    {
        $chunks = explode('.', $version);
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getVersion')->andReturn($version);
        $php->shouldReceive('getBaseVersion')->andReturn($version);
        $php->shouldReceive('getMajorMinorVersion')->andReturn((int) $chunks[0] * 100 + (int) $chunks[1]);

        return $php;
    }

    /**
     * @param class-string<\IPE\OS> $class
     * @param int $version
     *
     * @return \IPE\OS&\Mockery\MockInterface
     */
    private static function createOS($class, $version)
    {
        $os = M::mock($class);
        self::assertInstanceOf(OS::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($version);

        return $os;
    }

    /**
     * @param class-string<\IPE\Extension&\IPE\Extension\PatchesSourceCode> $class
     * @param \IPE\OS&\Mockery\MockInterface $os
     * @param string $original
     * @param string|null $expected the expected new content of config.m4 (null if it must not change)
     *
     * @return void
     */
    private static function assertPatch($class, PHPInfo $php, $os, $original, $expected)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('readFile')->with(self::SOURCE_DIR . '/config.m4')->andReturn($original);
        if ($expected === null) {
            $fs->shouldNotReceive('writeFile');
        } else {
            $fs->shouldReceive('writeFile')->once()->with(self::SOURCE_DIR . '/config.m4', $expected);
        }
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        (new $class($php, $os))->patchSourceCode(self::createBuildContext('', null, self::SOURCE_DIR));
    }

    /**
     * @return void
     */
    public function testDba()
    {
        $original = 'PHP_DBA_DB_CHECK(4, db-5.1 db-5.0 db)';
        self::assertPatch(dba::class, self::createPHP('5.5.38'), self::createOS(OS\Debian::class, OS\Debian::JESSIE), $original, 'PHP_DBA_DB_CHECK(4, db-5.3 db-5.1 db-5.0 db)');
        self::assertPatch(dba::class, self::createPHP('5.6.40'), self::createOS(OS\Debian::class, OS\Debian::JESSIE), $original, null);
    }

    /**
     * @return void
     */
    public function testIntl()
    {
        $original = 'PHP_CXX_COMPILE_STDCXX(11, mandatory, PHP_INTL_STDCXX)';
        $patched = 'PHP_CXX_COMPILE_STDCXX(17, mandatory, PHP_INTL_STDCXX)';
        self::assertPatch(intl::class, self::createPHP('8.1.32'), self::createOS(OS\Alpine::class, 322), $original, $patched);
        self::assertPatch(intl::class, self::createPHP('8.1.33'), self::createOS(OS\Alpine::class, 322), $original, null);
        self::assertPatch(intl::class, self::createPHP('8.1.32'), self::createOS(OS\Alpine::class, 321), $original, null);
        self::assertPatch(intl::class, self::createPHP('8.2.1'), self::createOS(OS\Alpine::class, 322), $original, null);
        self::assertPatch(intl::class, self::createPHP('8.1.32'), self::createOS(OS\Debian::class, OS\Debian::TRIXIE), $original, null);
    }

    /**
     * @return void
     */
    public function testSockets()
    {
        $lines = array_fill(0, 80, 'line');
        $original = implode("\n", $lines);
        $expected = $lines;
        array_splice($expected, 69, 0, ['#ifndef _GNU_SOURCE', '#define _GNU_SOURCE', '#endif']);
        self::assertPatch(sockets::class, self::createPHP('8.1.2'), self::createOS(OS\Debian::class, OS\Debian::BOOKWORM), $original, implode("\n", $expected));
        self::assertPatch(sockets::class, self::createPHP('8.0.15'), self::createOS(OS\Debian::class, OS\Debian::BOOKWORM), $original, implode("\n", $expected));
        self::assertPatch(sockets::class, self::createPHP('8.1.3'), self::createOS(OS\Debian::class, OS\Debian::BOOKWORM), $original, null);
    }

    /**
     * @return void
     */
    public function testTokenizer()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('isFile')->once()->with(self::SOURCE_DIR . '/Makefile.frag')->andReturn(true);
        $fs->shouldReceive('deleteFile')->once()->with(self::SOURCE_DIR . '/Makefile.frag');
        $os = M::mock(OS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        (new tokenizer(self::createPHP('8.3.0'), $os))->patchSourceCode(self::createBuildContext('', null, self::SOURCE_DIR));
    }

    /**
     * @return void
     */
    public function testOdbc()
    {
        $original = "keep\ntest \"\$PHP_IODBC\" = \"no\" && PHP_IODBC=yes\nkeep too\n";
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('readFile')->with(self::SOURCE_DIR . '/configure')->andReturn($original);
        $fs->shouldReceive('writeFile')->once()->with(self::SOURCE_DIR . '/configure', "keep\n#test \"\$PHP_IODBC\" = \"no\" && PHP_IODBC=yes\nkeep too\n");
        $os = M::mock(OS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        (new odbc(self::createPHP('8.4.1'), $os))->patchConfigureScript(self::createBuildContext('', null, self::SOURCE_DIR));
    }
}
