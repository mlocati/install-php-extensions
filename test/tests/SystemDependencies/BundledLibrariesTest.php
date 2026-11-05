<?php

namespace IPE\Test\SystemDependencies;

use IPE\Exception;
use IPE\Extensions\enchant;
use IPE\Filesystem;
use IPE\Filesystem\Downloader;
use IPE\Installer\BuildOptions;
use IPE\Installer\BuildTools;
use IPE\Installer\State;
use IPE\Option\Validator;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\SystemDependencies\libargon2;
use IPE\SystemDependencies\libaspell;
use IPE\SystemDependencies\libcapstone;
use IPE\SystemDependencies\libcassandra;
use IPE\SystemDependencies\libcclient;
use IPE\SystemDependencies\libck;
use IPE\SystemDependencies\libenchant;
use IPE\SystemDependencies\libenchant2;
use IPE\SystemDependencies\libfbclient;
use IPE\SystemDependencies\libfreetype2;
use IPE\SystemDependencies\libgcrypt;
use IPE\SystemDependencies\libldap;
use IPE\SystemDependencies\libmaxminddb;
use IPE\SystemDependencies\libnode;
use IPE\SystemDependencies\libodbc;
use IPE\SystemDependencies\libpam;
use IPE\SystemDependencies\librdkafka;
use IPE\SystemDependencies\libtidy;
use IPE\SystemDependencies\libuv;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class BundledLibrariesTest extends TestCase
{
    /**
     * @param class-string<\IPE\OS\Posix> $class
     * @param int $version
     * @param list<string> $existingFiles
     *
     * @return \IPE\OS\Posix
     */
    private static function createOS($class, $version, array $existingFiles = [])
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('isFile')->andReturnUsing(static function ($path) use ($existingFiles) {
            return in_array($path, $existingFiles, true);
        });
        $os = M::mock($class);
        self::assertInstanceOf(OS\Posix::class, $os);
        $os->shouldReceive('getNumericVersion')->andReturn($version);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $os->shouldReceive('getDisplayName')->andReturn('Test OS');

        return $os;
    }

    /**
     * @param int $majorMinorVersion
     *
     * @return \IPE\PHPInfo
     */
    private static function createPHP($majorMinorVersion = 804)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getMajorMinorVersion')->andReturn($majorMinorVersion);

        return $php;
    }

    /**
     * @param list<string> $persistent
     * @param list<string> $volatile
     *
     * @return void
     */
    private static function assertPackages(array $persistent, array $volatile, Packages $packages)
    {
        self::assertSame($persistent, $packages->getPersistent());
        self::assertSame($volatile, $packages->getVolatile());
    }

    /**
     * @return \IPE\Installer\State
     */
    private static function createState()
    {
        return new State(M::mock(PHPInfo::class), M::mock(Filesystem::class));
    }

    /**
     * @return void
     */
    public function testLibodbc()
    {
        $php = self::createPHP();
        self::assertPackages(['libodbc1'], ['unixodbc-dev'], (new libodbc($php, self::createOS(OS\Debian::class, OS\Debian::VERSION_BOOKWORM), new Values()))->getSystemPackages(self::createState()));
        self::assertPackages(['libodbc2'], ['unixodbc-dev'], (new libodbc($php, self::createOS(OS\Debian::class, OS\Debian::VERSION_TRIXIE), new Values()))->getSystemPackages(self::createState()));
        self::assertPackages(['unixodbc'], ['unixodbc-dev'], (new libodbc($php, self::createOS(OS\Alpine::class, 321), new Values()))->getSystemPackages(self::createState()));
    }

    /**
     * @return void
     */
    public function testLibldap()
    {
        $php = self::createPHP();
        self::assertPackages([], ['libldap2-dev'], (new libldap($php, self::createOS(OS\Debian::class, OS\Debian::VERSION_JESSIE), new Values()))->getSystemPackages(self::createState()));
        self::assertPackages(['libldap-common'], ['libldap2-dev'], (new libldap($php, self::createOS(OS\Debian::class, OS\Debian::VERSION_STRETCH), new Values()))->getSystemPackages(self::createState()));
    }

    /**
     * @return void
     */
    public function testLibtidy()
    {
        $php = self::createPHP();
        self::assertPackages(['tidyhtml-libs'], ['tidyhtml-dev'], (new libtidy($php, self::createOS(OS\Alpine::class, 314), new Values()))->getSystemPackages(self::createState()));
        self::assertPackages(['tidyhtml'], ['tidyhtml-dev'], (new libtidy($php, self::createOS(OS\Alpine::class, 315), new Values()))->getSystemPackages(self::createState()));
    }

    /**
     * @return void
     */
    public function testLibaspellLanguages()
    {
        $os = self::createOS(OS\Debian::class, OS\Debian::VERSION_BOOKWORM);
        $state = self::createState();
        $validator = new Validator(new \IPE\Logger());
        $dependency = new libaspell(self::createPHP(), $os, $validator->validate([], libaspell::getOptions(), false, libaspell::HANDLE));
        self::assertPackages(['libaspell15', 'aspell-en'], ['libpspell-dev'], $dependency->getSystemPackages($state));
        $dependency = new libaspell(self::createPHP(), $os, $validator->validate([libaspell::OPTION_LANGUAGES => 'it, de'], libaspell::getOptions(), false, libaspell::HANDLE));
        self::assertPackages(['libaspell15', 'aspell-it', 'aspell-de'], ['libpspell-dev'], $dependency->getSystemPackages($state));
    }

    /**
     * @param class-string<\IPE\OS\Posix> $class
     * @param int $osVersion
     * @param int $phpVersion
     * @param list<string> $existingFiles
     *
     * @return \IPE\SystemDependencies\libenchant
     */
    private static function createLibenchant($class, $osVersion, $phpVersion, array $existingFiles = [])
    {
        $php = self::createPHP($phpVersion);

        return new libenchant($php, self::createOS($class, $osVersion, $existingFiles), new Values(), M::mock(Downloader::class), M::mock(Filesystem\ArchiveExtractor::class), new BuildTools($php, M::mock(Filesystem::class), new \IPE\Logger()), new BuildOptions(), new \IPE\Logger());
    }

    /**
     * @return void
     */
    public function testLibenchant()
    {
        $state = self::createState();
        self::assertPackages(['enchant'], ['enchant-dev'], self::createLibenchant(OS\Alpine::class, 311, 704)->getSystemPackages($state));
        self::assertPackages(['glib', 'aspell-libs', 'libhunspell'], ['glib-dev', 'aspell-dev', 'hunspell-dev'], self::createLibenchant(OS\Alpine::class, 312, 704)->getSystemPackages($state));
        self::assertPackages(['libenchant1c2a'], ['libenchant-dev'], self::createLibenchant(OS\Debian::class, OS\Debian::VERSION_BUSTER, 704)->getSystemPackages($state));
        self::assertPackages(['aspell-en', 'libhunspell-1.7-0'], ['libglib2.0-dev', 'libaspell-dev', 'libhunspell-dev'], self::createLibenchant(OS\Debian::class, OS\Debian::VERSION_BULLSEYE, 704)->getSystemPackages($state));
        self::assertTrue(self::createLibenchant(OS\Debian::class, OS\Debian::VERSION_BULLSEYE, 704, ['/usr/local/lib/libenchant.so'])->getSystemPackages($state)->isEmpty());
    }

    /**
     * @return void
     */
    public function testLibargon2()
    {
        $php = self::createPHP();
        $state = self::createState();
        self::assertPackages(['libargon2-0'], ['libargon2-0-dev'], (new libargon2($php, self::createOS(OS\Debian::class, OS\Debian::VERSION_STRETCH), new Values()))->getSystemPackages($state));
        self::assertPackages(['libargon2-1'], ['libargon2-dev'], (new libargon2($php, self::createOS(OS\Debian::class, OS\Debian::VERSION_TRIXIE), new Values()))->getSystemPackages($state));
        self::assertPackages(['argon2-libs'], ['argon2-dev'], (new libargon2($php, self::createOS(OS\Alpine::class, 308), new Values()))->getSystemPackages($state));
        self::assertUnsupportedOS(new libargon2($php, self::createOS(OS\Debian::class, OS\Debian::VERSION_JESSIE), new Values()));
        self::assertUnsupportedOS(new libargon2($php, self::createOS(OS\Alpine::class, 307), new Values()));
    }

    /**
     * @return void
     */
    public function testLibpam()
    {
        $php = self::createPHP();
        $state = self::createState();
        self::assertPackages(['libpam0g'], ['libpam0g-dev'], (new libpam($php, self::createOS(OS\Debian::class, OS\Debian::VERSION_BOOKWORM), new Values()))->getSystemPackages($state));
        self::assertPackages(['libpam0g'], ['libpam0g-dev', 'libaudit-dev'], (new libpam($php, self::createOS(OS\Debian::class, OS\Debian::VERSION_TRIXIE), new Values()))->getSystemPackages($state));
    }

    /**
     * @return void
     */
    public function testLibcapstone()
    {
        $php = self::createPHP();
        self::assertPackages(['capstone'], ['capstone-dev'], (new libcapstone($php, self::createOS(OS\Alpine::class, 309), new Values()))->getSystemPackages(self::createState()));
        self::assertUnsupportedOS(new libcapstone($php, self::createOS(OS\Alpine::class, 308), new Values()));
    }

    /**
     * @return void
     */
    public function testOldDebianPackages()
    {
        $php = self::createPHP();
        $state = self::createState();
        $jessie = self::createOS(OS\Debian::class, OS\Debian::VERSION_JESSIE);
        $stretch = self::createOS(OS\Debian::class, OS\Debian::VERSION_STRETCH);
        $buster = self::createOS(OS\Debian::class, OS\Debian::VERSION_BUSTER);
        $trixie = self::createOS(OS\Debian::class, OS\Debian::VERSION_TRIXIE);
        self::assertPackages(['libfreetype6'], ['libfreetype6-dev'], (new libfreetype2($php, $buster, new Values()))->getSystemPackages($state));
        self::assertPackages(['libfreetype6'], ['libfreetype-dev'], (new libfreetype2($php, $trixie, new Values()))->getSystemPackages($state));
        self::assertPackages(['librdkafka1'], ['librdkafka-dev'], (new librdkafka($php, $trixie, new Values()))->getSystemPackages($state));
        self::assertPackages([], ['libgcrypt20-dev'], (new libgcrypt($php, $stretch, new Values()))->getSystemPackages($state));
        self::assertUnsupportedOS(new libnode($php, $stretch, new Values()));
        self::assertPackages(['^libnode[0-9]+$'], ['libnode-dev'], (new libnode($php, $buster, new Values()))->getSystemPackages($state));
        self::assertPackages(['libck0'], [], (new libck($php, $stretch, new Values()))->getSystemPackages($state));
        self::assertUnsupportedOS(new libck($php, $buster, new Values()));
        self::assertPackages(['^libck0(t64)?$'], [], (new libck($php, $trixie, new Values()))->getSystemPackages($state));
        self::assertUnsupportedOS(new libuv($php, $jessie, new Values()));
        self::assertPackages(['^libuv1(t64)?$'], ['libuv1-dev'], (new libuv($php, $stretch, new Values()))->getSystemPackages($state));
        self::assertUnsupportedOS(new libcassandra($php, $jessie, new Values(), M::mock(Downloader::class), M::mock(Filesystem\ArchiveExtractor::class), new BuildTools($php, M::mock(Filesystem::class), new \IPE\Logger()), new BuildOptions(), new \IPE\Logger()));
        self::assertUnsupportedOS(new libmaxminddb($php, $jessie, new Values()));
        self::assertPackages(['^libmaxminddb[0-9]*$'], ['libmaxminddb-dev'], (new libmaxminddb($php, $stretch, new Values()))->getSystemPackages($state));
    }

    /**
     * @return void
     */
    private static function assertUnsupportedOS(SystemDependency $dependency)
    {
        try {
            $dependency->getSystemPackages(self::createState());
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedOS $x) {
            self::assertStringEndsWith("doesn't provide " . $dependency::getHandle(), $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testLibenchant2()
    {
        $php = self::createPHP();
        $state = self::createState();
        self::assertPackages(['enchant2'], ['enchant2-dev'], (new libenchant2($php, self::createOS(OS\Alpine::class, 312), new Values()))->getSystemPackages($state));
        self::assertPackages(['libenchant-2-2'], ['libenchant-2-dev'], (new libenchant2($php, self::createOS(OS\Debian::class, OS\Debian::VERSION_BULLSEYE), new Values()))->getSystemPackages($state));
        self::assertUnsupportedOS(new libenchant2($php, self::createOS(OS\Alpine::class, 311), new Values()));
        self::assertUnsupportedOS(new libenchant2($php, self::createOS(OS\Debian::class, OS\Debian::VERSION_BUSTER), new Values()));
    }

    /**
     * @return void
     */
    public function testEnchantExtensionChoosesTheEnchantVersion()
    {
        $cases = [
            [OS\Alpine::class, 311, 800, libenchant::HANDLE],
            [OS\Alpine::class, 312, 704, libenchant::HANDLE],
            [OS\Alpine::class, 312, 800, libenchant2::HANDLE],
            [OS\Debian::class, OS\Debian::VERSION_BUSTER, 800, libenchant::HANDLE],
            [OS\Debian::class, OS\Debian::VERSION_BULLSEYE, 704, libenchant::HANDLE],
            [OS\Debian::class, OS\Debian::VERSION_BULLSEYE, 800, libenchant2::HANDLE],
        ];
        foreach ($cases as $case) {
            $extension = new enchant(self::createPHP($case[2]), self::createOS($case[0], $case[1]));
            self::assertSame([$case[3]], $extension->getRequiredSystemDependencies('', new Values()), "{$case[0]} {$case[1]}, PHP {$case[2]}");
        }
    }

    /**
     * @param class-string<\IPE\OS\Posix> $class
     * @param int $osVersion
     * @param int $phpVersion
     * @param list<string> $existingFiles
     *
     * @return \IPE\SystemDependencies\libfbclient
     */
    private static function createLibfbclient($class, $osVersion, $phpVersion, array $existingFiles = [])
    {
        $php = self::createPHP($phpVersion);

        return new libfbclient($php, self::createOS($class, $osVersion, $existingFiles), new Values(), M::mock(Downloader::class), M::mock(Filesystem\ArchiveExtractor::class), new BuildTools($php, M::mock(Filesystem::class), new \IPE\Logger()), new BuildOptions(), new \IPE\Logger());
    }

    /**
     * @param class-string<\IPE\OS\Posix> $class
     * @param int $osVersion
     * @param list<string> $existingFiles
     *
     * @return \IPE\SystemDependencies\libcclient
     */
    private static function createLibcclient($class, $osVersion, array $existingFiles = [])
    {
        return new libcclient(self::createPHP(), self::createOS($class, $osVersion, $existingFiles), new Values(), M::mock(Downloader::class));
    }

    /**
     * @return void
     */
    public function testLibfbclient()
    {
        $state = self::createState();
        self::assertPackages(['libfbclient2'], ['firebird-dev', 'libib-util'], self::createLibfbclient(OS\Debian::class, OS\Debian::VERSION_BOOKWORM, 804)->getSystemPackages($state));
        self::assertSame([], self::createLibfbclient(OS\Debian::class, OS\Debian::VERSION_BOOKWORM, 804)->getRequiredDependencies());
        self::assertPackages([], ['icu-dev', 'ncurses-dev'], self::createLibfbclient(OS\Alpine::class, 321, 803)->getSystemPackages($state));
        self::assertSame([], self::createLibfbclient(OS\Alpine::class, 321, 803)->getRequiredDependencies());
        self::assertPackages(['libstdc++'], ['icu-dev', 'ncurses-dev', 'zlib-dev'], self::createLibfbclient(OS\Alpine::class, 321, 804)->getSystemPackages($state));
        self::assertSame(['lib-tommath', 'lib-tomcrypt'], self::createLibfbclient(OS\Alpine::class, 321, 804)->getRequiredDependencies());
        $installed = ['/usr/lib/libfbclient.so', '/usr/include/ibase.h'];
        self::assertTrue(self::createLibfbclient(OS\Alpine::class, 321, 804, $installed)->getSystemPackages($state)->isEmpty());
        self::assertSame([], self::createLibfbclient(OS\Alpine::class, 321, 804, $installed)->getRequiredDependencies());
    }

    /**
     * @return void
     */
    public function testLibcclient()
    {
        $state = self::createState();
        self::assertPackages(['c-client'], ['krb5-dev', 'imap-dev'], self::createLibcclient(OS\Alpine::class, 321)->getSystemPackages($state));
        self::assertPackages(['libc-client2007e'], ['libkrb5-dev', 'libc-client-dev'], self::createLibcclient(OS\Debian::class, OS\Debian::VERSION_BOOKWORM)->getSystemPackages($state));
        self::assertPackages(['sgml-base'], ['libkrb5-dev', 'libpam0g-dev'], self::createLibcclient(OS\Debian::class, OS\Debian::VERSION_TRIXIE)->getSystemPackages($state));
        self::assertPackages(['sgml-base'], ['libkrb5-dev'], self::createLibcclient(OS\Debian::class, OS\Debian::VERSION_TRIXIE, ['/usr/lib/libc-client.so.2007e', '/usr/include/c-client/utf8.h'])->getSystemPackages($state));
        self::assertSame(['lib-openssl'], self::createLibcclient(OS\Debian::class, OS\Debian::VERSION_TRIXIE)->getRequiredDependencies());
    }
}
