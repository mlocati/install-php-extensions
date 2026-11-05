<?php

namespace IPE\Test\Service;

use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\OS;
use IPE\Service\LetsEncryptFixer;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class LetsEncryptFixerTest extends TestCase
{
    /**
     * @return array{class-string, int, bool, bool}[]
     */
    public static function provideFixCases()
    {
        return [
            [OS\Debian::class, OS\Debian::VERSION_JESSIE, false, false],
            [OS\Debian::class, OS\Debian::VERSION_JESSIE, true, true],
            [OS\Debian::class, OS\Debian::VERSION_STRETCH, false, false],
            [OS\Debian::class, OS\Debian::VERSION_STRETCH, true, true],
            [OS\Debian::class, OS\Debian::VERSION_BUSTER, false, false],
            [OS\Debian::class, OS\Debian::VERSION_BUSTER, true, false],
            [OS\Alpine::class, 307, false, false],
            [OS\Alpine::class, 307, true, true],
            [OS\Alpine::class, 308, false, false],
            [OS\Alpine::class, 308, true, true],
            [OS\Alpine::class, 309, false, false],
            [OS\Alpine::class, 309, true, false],
        ];
    }

    /**
     * @dataProvider provideFixCases
     *
     * @param class-string<\IPE\OS> $osClass
     * @param int $osVersion
     * @param bool $certWithRootCAX3
     * @param bool $expectedFix
     *
     * @return void
     */
    public function testFix($osClass, $osVersion, $certWithRootCAX3, $expectedFix)
    {
        $fs = M::mock(Filesystem::class);
        /** @var \IPE\OS&\Mockery\MockInterface $os */
        $os = M::mock($osClass);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);
        if ($certWithRootCAX3) {
            $fs->shouldReceive('readFile')->with('/etc/ca-certificates.conf')->andReturn(implode("\n", [
                'mozilla/ACCVRAIZ1.crt',
                'mozilla/DST_Root_CA_X3.crt',
                'mozilla/ISRG_Root_X1.crt',
                'mozilla/XRamp_Global_CA_Root.crt',
            ]));
        } else {
            $fs->shouldReceive('readFile')->with('/etc/ca-certificates.conf')->andReturn(implode("\n", [
                'mozilla/ACCVRAIZ1.crt',
                'mozilla/ISRG_Root_X1.crt',
                'mozilla/XRamp_Global_CA_Root.crt',
            ]));
        }
        if ($expectedFix) {
            $fs->shouldReceive('writeFile')->with('/etc/ca-certificates.conf', implode("\n", [
                'mozilla/ACCVRAIZ1.crt',
                '!mozilla/DST_Root_CA_X3.crt',
                'mozilla/ISRG_Root_X1.crt',
                'mozilla/XRamp_Global_CA_Root.crt',
            ]));
            $fs->shouldReceive('run')->with(self::isCommand(Command::create('update-ca-certificates', ['-f'])))->andReturn([0, 'ok']);
        } else {
            $fs->shouldReceive('readFile')->with('/etc/ca-certificates.conf')->andReturn(implode("\n", [
                'mozilla/ACCVRAIZ1.crt',
                'mozilla/ISRG_Root_X1.crt',
                'mozilla/XRamp_Global_CA_Root.crt',
            ]));
        }
        $service = new LetsEncryptFixer(new \IPE\Logger());
        self::assertSame($expectedFix, $service->fix($os));
    }

    /**
     * @return list<array{class-string<\IPE\OS>, int}>
     */
    public static function provideFixNotApplicableCases()
    {
        return [
            [OS\Debian::class, OS\Debian::VERSION_BUSTER],
            [OS\Alpine::class, 309],
            [OS\Windows::class, 100026200],
        ];
    }

    /**
     * @dataProvider provideFixNotApplicableCases
     *
     * @param class-string<\IPE\OS> $osClass
     * @param int $osVersion
     *
     * @return void
     */
    public function testFixNotApplicable($osClass, $osVersion)
    {
        $fs = M::mock(Filesystem::class);
        /** @var \IPE\OS&\Mockery\MockInterface $os */
        $os = M::mock($osClass);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);

        $service = new LetsEncryptFixer(new \IPE\Logger());
        self::assertFalse($service->fix($os));
    }
}
