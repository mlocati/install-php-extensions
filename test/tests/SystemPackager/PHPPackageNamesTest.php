<?php

namespace IPE\Test\SystemPackager;

use IPE\OS;
use IPE\SystemPackager;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class PHPPackageNamesTest extends TestCase
{
    /**
     * @return list<array{\IPE\SystemPackager, \IPE\SystemPackager\Package, non-empty-string, non-empty-string, list<non-empty-string>}>
     */
    public static function providePHPPackageNamesCases()
    {
        return [
            [new SystemPackager\APT(M::mock(OS::class)), new SystemPackager\Package('php8.4-cli', '8.4.27-1~deb13u1', 'php8.4'), 'php8.4-dev', 'pdo_mysql', ['php8.4-pdo-mysql']],
            [new SystemPackager\APT(M::mock(OS::class)), new SystemPackager\Package('php5-cli', '5.5.9+dfsg-1ubuntu4.29', 'php5'), 'php5-dev', 'redis', ['php5-redis']],
            [new SystemPackager\APK(M::mock(OS::class)), new SystemPackager\Package('php84', '8.4.27-r0', 'php84'), 'php84-dev', 'pdo_mysql', ['php84-pdo_mysql', 'php84-pecl-pdo_mysql']],
            [new SystemPackager\APK(M::mock(OS::class)), new SystemPackager\Package('php84', '8.4.27-r0', 'php84'), 'php84-dev', 'imagick', ['php84-imagick', 'php84-pecl-imagick']],
            [new SystemPackager\APT(M::mock(OS::class)), new SystemPackager\Package('php8.4-cli', '8.4.27-1~deb13u1', 'php8.4'), 'php8.4-dev', 'SimpleXML', ['php8.4-simplexml']],
            [new SystemPackager\APK(M::mock(OS::class)), new SystemPackager\Package('php84', '8.4.27-r0', 'php84'), 'php84-dev', 'SimpleXML', ['php84-simplexml', 'php84-pecl-simplexml']],
            [new SystemPackager\APT(M::mock(OS::class)), new SystemPackager\Package('php8.4-cli', '8.4.27-1~deb13u1', 'php8.4'), 'php8.4-dev', 'Zend OPcache', []],
            [new SystemPackager\APK(M::mock(OS::class)), new SystemPackager\Package('php84', '8.4.27-r0', 'php84'), 'php84-dev', 'Zend OPcache', []],
        ];
    }

    /**
     * @dataProvider providePHPPackageNamesCases
     *
     * @param non-empty-string $expectedDevelopmentPackage
     * @param non-empty-string $extensionName
     * @param list<non-empty-string> $expectedExtensionPackages
     *
     * @return void
     */
    public function testPHPPackageNames(SystemPackager $packager, SystemPackager\Package $phpPackage, $expectedDevelopmentPackage, $extensionName, array $expectedExtensionPackages)
    {
        self::assertSame($expectedDevelopmentPackage, $packager->getPHPDevelopmentPackageName($phpPackage));
        self::assertSame($expectedExtensionPackages, $packager->getPHPExtensionPackageCandidates($phpPackage, $extensionName));
    }
}
