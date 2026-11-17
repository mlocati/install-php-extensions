<?php

namespace IPE\Test\Extensions;

use IPE\Exception;
use IPE\Extensions\imap;
use IPE\Extensions\stomp;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class OSFamiliesTest extends TestCase
{
    /**
     * @return array<array{class-string<\IPE\Extension&\IPE\Extension\ProvidesConfigureArguments>, class-string<\IPE\OS>, list<string>}>
     */
    public static function provideConfigureArgumentsCases()
    {
        return [
            [imap::class, OS\Alpine::class, ['--with-kerberos', '--with-imap-ssl', '--with-libdir=lib']],
            [imap::class, OS\Debian::class, ['--with-kerberos', '--with-imap-ssl', '--with-libdir=lib']],
            [imap::class, OS\Ubuntu::class, ['--with-kerberos', '--with-imap-ssl', '--with-libdir=lib']],
            [imap::class, OS\AlmaLinux::class, ['--with-kerberos', '--with-imap-ssl']],
            [imap::class, OS\Fedora::class, ['--with-kerberos', '--with-imap-ssl']],
            [stomp::class, OS\Alpine::class, ['--with-openssl-dir=/usr']],
            [stomp::class, OS\Debian::class, ['--with-openssl-dir=yes']],
            [stomp::class, OS\AmazonLinux::class, ['--with-openssl-dir=yes']],
        ];
    }

    /**
     * @dataProvider provideConfigureArgumentsCases
     *
     * @param class-string<\IPE\Extension&\IPE\Extension\ProvidesConfigureArguments> $extensionClass
     * @param class-string<\IPE\OS> $osClass
     * @param list<string> $expected
     *
     * @return void
     */
    public function testConfigureArguments($extensionClass, $osClass, array $expected)
    {
        $os = M::mock($osClass);
        self::assertInstanceOf(OS::class, $os);
        self::assertSame($expected, (new $extensionClass(M::mock(PHPInfo::class), $os))->getConfigureArguments(self::createBuildContext()));
    }

    /**
     * @return array<array{class-string<\IPE\Extension&\IPE\Extension\ProvidesConfigureArguments>}>
     */
    public static function provideUnknownOSCases()
    {
        return [
            [imap::class],
            [stomp::class],
        ];
    }

    /**
     * @dataProvider provideUnknownOSCases
     *
     * @param class-string<\IPE\Extension&\IPE\Extension\ProvidesConfigureArguments> $extensionClass
     *
     * @return void
     */
    public function testUnknownOS($extensionClass)
    {
        $os = M::mock(OS\Posix::class);
        $os->shouldReceive('getDisplayName')->andReturn('Test OS');
        $this->expectException(Exception\UnsupportedOS::class);
        $this->expectExceptionMessage(' is not known for Test OS');
        (new $extensionClass(M::mock(PHPInfo::class), $os))->getConfigureArguments(self::createBuildContext());
    }
}
