<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\lz4;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class Lz4Test extends TestCase
{
    /**
     * @param 'alpine'|'debian'|'ubuntu' $osName
     * @param int $osVersion
     *
     * @return \IPE\OS&\Mockery\MockInterface
     */
    private static function createOS($osName, $osVersion)
    {
        if ($osName === 'alpine') {
            $os = M::mock(OS\Alpine::class);
        } elseif ($osName === 'debian') {
            $os = M::mock(OS\Debian::class);
        } else {
            $os = M::mock(OS\Ubuntu::class);
        }
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);

        return $os;
    }

    /**
     * @return array<array{'alpine'|'debian'|'ubuntu', int, int, string}> the OS, its version, the PHP version, and the expected version
     */
    public static function provideDefaultVersions()
    {
        return [
            ['debian', OS\Debian::STRETCH, 704, '0.4.4'],
            ['debian', OS\Debian::BOOKWORM, 704, lz4::VERSION],
            ['debian', OS\Debian::BOOKWORM, 800, '0.4.4'],
            ['debian', OS\Debian::BOOKWORM, 801, lz4::VERSION],
            ['ubuntu', OS\Ubuntu::BIONIC, 801, '0.4.4'],
            ['ubuntu', OS\Ubuntu::FOCAL, 801, lz4::VERSION],
            ['alpine', 313, 800, '0.4.4'],
            ['alpine', 313, 806, lz4::VERSION],
        ];
    }

    /**
     * @dataProvider provideDefaultVersions
     *
     * @param 'alpine'|'debian'|'ubuntu' $osName
     * @param int $osVersion
     * @param int $phpVersion
     * @param string $expected
     *
     * @return void
     */
    public function testDefaultVersion($osName, $osVersion, $phpVersion, $expected)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($phpVersion);
        self::assertSame($expected, (new lz4($php, self::createOS($osName, $osVersion)))->getVendorArchiveVersion('', new Values()));
    }
}
