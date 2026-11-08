<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\event;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\PHPInfo\SSLLibrary;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class EventTest extends TestCase
{
    /**
     * @return array<array{class-string<\IPE\OS>, int, string, string}> the OS, its version, the value of OPENSSL_VERSION_TEXT in PHP, and the expected OpenSSL argument
     */
    public static function provideOpenSSLArguments()
    {
        return [
            [OS\Debian::class, OS\Debian::VERSION_JESSIE, 'OpenSSL 1.0.1t  3 May 2016', '--with-event-openssl'],
            [OS\Debian::class, OS\Debian::VERSION_STRETCH, 'OpenSSL 1.0.2q  20 Nov 2018', '--without-event-openssl'],
            [OS\Debian::class, OS\Debian::VERSION_STRETCH, 'OpenSSL 1.1.0j  20 Nov 2018', '--with-event-openssl'],
            [OS\Debian::class, OS\Debian::VERSION_STRETCH, 'LibreSSL 2.6.5', '--with-event-openssl'],
            [OS\Debian::class, OS\Debian::VERSION_STRETCH, '', '--with-event-openssl'],
            [OS\Debian::class, OS\Debian::VERSION_BUSTER, 'OpenSSL 1.1.1d  10 Sep 2019', '--with-event-openssl'],
            [OS\Alpine::class, 308, 'OpenSSL 1.0.2u  20 Dec 2019', '--with-event-openssl'],
        ];
    }

    /**
     * @dataProvider provideOpenSSLArguments
     *
     * @param class-string<\IPE\OS> $osClass
     * @param int $osVersion
     * @param string $phpVersionText
     * @param string $expected
     *
     * @return void
     */
    public function testOpenSSLArgument($osClass, $osVersion, $phpVersionText, $expected)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn(506);
        $php->shouldReceive('getSSLLibrary')->andReturn(SSLLibrary::fromVersionText($phpVersionText));
        $php->shouldReceive('getInstalledExtensions')->andReturn([]);
        $os = M::mock($osClass);
        self::assertInstanceOf(OS::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($osVersion);
        $arguments = (new event($php, $os))->getConfigureArguments('', new Values());
        self::assertContains($expected, $arguments);
        self::assertNotContains($expected === '--with-event-openssl' ? '--without-event-openssl' : '--with-event-openssl', $arguments);
    }
}
