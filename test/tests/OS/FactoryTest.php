<?php

namespace IPE\Test\OS;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\OS;
use IPE\SystemPackager;
use IPE\Test\Support\OSFactoryTest\FakeFactory;
use IPE\Test\Support\OSFactoryTest\FakeOS;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class FactoryTest extends TestCase
{
    /**
     * @return void
     */
    public function testDetectingWindows()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('\\');
        $powershellExe = 'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
        $fs->shouldReceive('resolveCommand')->with('pwsh')->andReturn('');
        $fs->shouldReceive('resolveCommand')->with('powershell')->andReturn($powershellExe);
        $fs
            ->shouldReceive('run')
            ->with(M::on(static function (Command $command) use ($powershellExe) {
                $arguments = $command->getArguments();

                return $command->getCommand() === $powershellExe
                    && $command->getOutput() === Command::OUTPUT_CAPTURE_STDOUT
                    && $arguments[0] === '-NoProfile'
                    && $arguments[2] === '-EncodedCommand'
                    && base64_decode($arguments[3], true) !== false;
            }))
            ->andReturn([
                0,
                implode("\r\n", [
                    'Caption=Microsoft Windows 11 Pro',
                    'Version=10.1.23456',
                    'OSArchitecture=64 bit',
                    '',
                ]),
            ])
        ;
        $app = self::createApp();
        $app->singleton(Filesystem::class, static function () use ($fs) { return $fs; });
        $factory = new OS\Factory($app, new \IPE\Logger());
        $os = $factory->detectOS();
        self::assertInstanceOf(OS\Windows::class, $os);
        self::assertSame($fs, $os->getFilesystem());
        self::assertSame('Microsoft Windows 11 Pro [10.1.23456]', $os->getDisplayName());
        self::assertSame('10.1.23456', $os->getVersion());
        self::assertSame(100123456, $os->getNumericVersion());
    }

    /**
     * @return void
     */
    public function testDetectingWindowsWithoutPowerShell()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('\\');
        $fs->shouldReceive('resolveCommand')->andReturn('');
        $fs->shouldReceive('isLocal')->andReturn(true);
        $app = self::createApp();
        $app->singleton(Filesystem::class, static function () use ($fs) { return $fs; });
        $factory = new OS\Factory($app, new \IPE\Logger());
        if (DIRECTORY_SEPARATOR === '\\') {
            // php_uname() describes the actual Windows system
            $os = $factory->detectOS();
            self::assertInstanceOf(OS\Windows::class, $os);
            self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $os->getVersion());
            self::assertStringStartsWith('Microsoft Windows', $os->getDisplayName());
        } else {
            // php_uname() describes a non-Windows system
            try {
                $factory->detectOS();
                self::fail('Expected exception not thrown');
            } catch (Exception\UnsupportedOS $x) {
                self::assertStringContainsString('Failed to detect the Windows version', $x->getMessage());
            }
        }
    }

    /**
     * @return void
     */
    public function testDetectingRemoteWindowsWithoutPowerShell()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('\\');
        $fs->shouldReceive('resolveCommand')->andReturn('');
        $fs->shouldReceive('isLocal')->andReturn(false);
        $app = self::createApp();
        $app->singleton(Filesystem::class, static function () use ($fs) { return $fs; });
        $factory = new OS\Factory($app, new \IPE\Logger());
        try {
            $factory->detectOS();
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedOS $x) {
            self::assertStringContainsString('PowerShell is required', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testExtendedFactoryDetectsItsOS()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('isFile')->withArgs(['/etc/os-release'])->andReturn(true);
        $fs->shouldReceive('readFile')->withArgs(['/etc/os-release'])->andReturn("ID=fakeos\nVERSION_ID=5\n");
        $app = self::createApp();
        $app->singleton(Filesystem::class, static function () use ($fs) { return $fs; });
        $os = (new FakeFactory($app, new \IPE\Logger()))->detectOS();
        self::assertInstanceOf(FakeOS::class, $os);
        self::assertSame(5, $os->getNumericVersion());
        try {
            (new OS\Factory($app, new \IPE\Logger()))->detectOS();
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedOS $x) {
            self::assertStringContainsString('unsupported distribution (fakeos)', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testWindowsIsNotDetectedFromOSRelease()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('isFile')->withArgs(['/etc/os-release'])->andReturn(true);
        $fs->shouldReceive('readFile')->withArgs(['/etc/os-release'])->andReturn("ID=windows\n");
        $app = self::createApp();
        $app->singleton(Filesystem::class, static function () use ($fs) { return $fs; });
        try {
            (new OS\Factory($app, new \IPE\Logger()))->detectOS();
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedOS $x) {
            self::assertStringContainsString('unsupported distribution (windows)', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testRHELLikeWithoutDNF()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('isFile')->withArgs(['/etc/os-release'])->andReturn(true);
        $fs->shouldReceive('readFile')->withArgs(['/etc/os-release'])->andReturn("NAME=\"AlmaLinux\"\nID=\"almalinux\"\nVERSION_ID=\"9.8\"\n");
        $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('dnf', ['--version'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT)))->andReturn([127, '']);
        $app = self::createApp();
        $app->singleton(Filesystem::class, static function () use ($fs) { return $fs; });
        try {
            (new OS\Factory($app, new \IPE\Logger()))->detectOS();
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedOS $x) {
            self::assertStringContainsString('Unsupported dnf version', $x->getMessage());
        }
    }

    /**
     * @return array<array{class-string<\IPE\OS>, string, int|null}>
     */
    public static function provideNumericVersions()
    {
        return [
            [OS\Alpine::class, '3.22', 322],
            [OS\Alpine::class, '3.9', 309],
            [OS\Alpine::class, '3', null],
            [OS\Alpine::class, '3.22.4', null],
            [OS\Debian::class, '13', 13],
            [OS\Debian::class, '13.1', null],
            [OS\Debian::class, '0', null],
            [OS\Ubuntu::class, '24.04', 2404],
            [OS\Ubuntu::class, '24', null],
            [OS\Windows::class, '10.0.26200', 100026200],
            [OS\Windows::class, '10.0', null],
            [OS\Posix\RHELLike\EnterpriseLinux::class, '9', 9],
            [OS\Posix\RHELLike\EnterpriseLinux::class, '9.8', null],
            [OS\RockyLinux::class, '10', 10],
            [OS\Fedora::class, '44', 44],
            [OS\Fedora::class, 'rawhide', null],
            [OS\AmazonLinux::class, '2023', 2023],
            [OS\AmazonLinux::class, '0', null],
        ];
    }

    /**
     * @dataProvider provideNumericVersions
     *
     * @param class-string<\IPE\OS> $class
     * @param string $version
     * @param int|null $expected
     *
     * @return void
     */
    public function testParseNumericVersion($class, $version, $expected)
    {
        self::assertSame($expected, $class::parseNumericVersion($version));
    }

    /**
     * @return void
     */
    public function testMissingOSReleaseFile()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('isFile')->withArgs(['/etc/os-release'])->andReturn(false);
        $app = self::createApp();
        $app->singleton(Filesystem::class, static function () use ($fs) { return $fs; });
        $factory = new OS\Factory($app, new \IPE\Logger());
        try {
            $factory->detectOS();
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedOS $x) {
            self::assertStringContainsString('Failed to detect the operating system', $x->getMessage());
        }
    }

    /**
     * @return array{string, string}[]
     */
    public static function provideUnsupportedOSReleaseFileCases()
    {
        return [
            [
                '',
                'does not define the distribution ID',
            ],
            [
                'ID=',
                'does not define the distribution ID',
            ],
            [
                "I\nD=test",
                'does not define the distribution ID',
            ],
            [
                "ID\n=test",
                'does not define the distribution ID',
            ],
            [
                'ID=test',
                'defines an unsupported distribution (test)',
            ],
            [
                'ID=alpine',
                'Invalid $version',
            ],
            [
                'ID = alpine ',
                'Invalid $version',
            ],
            [
                '  ID = "alpine" ',
                'Invalid $version',
            ],
            [
                "  ID = 'alpine' ",
                'Invalid $version',
            ],
            [
                '  ID = "alpine " ',
                'defines an unsupported distribution (alpine )',
            ],
            [
                "  ID = 'alpine ' ",
                'defines an unsupported distribution (alpine )',
            ],
            [
                "ID=alpine\nVERSION_ID=",
                'Invalid $version',
            ],
            [
                "ID=alpine\nVERSION_ID=3.122.4",
                'Invalid $version',
            ],
            [
                'ID=debian',
                'does not define VERSION for Debian',
            ],
            [
                "ID=debian\nVERSION=",
                'does not define VERSION for Debian',
            ],
            [
                "ID=debian\nVERSION=not valid",
                'defines an invalid VERSION for Debian',
            ],
            [
                "ID=debian\nVERSION=0 (trixie)",
                'defines an invalid VERSION for Debian',
            ],
            [
                "ID=debian\nVERSION=1111111111111111111111 (trixie)",
                'defines an invalid VERSION for Debian',
            ],
            [
                "ID=debian\nVERSION=8 (Trixie)",
                'defines an invalid VERSION for Debian',
            ],
            [
                "ID=debian\nVERSION=8.1 (trixie)",
                'defines an invalid VERSION for Debian',
            ],
            [
                'ID=ubuntu',
                'invalid VERSION for Ubuntu',
            ],
            [
                "ID=ubuntu\nVERSION_CODENAME=noble",
                'invalid VERSION for Ubuntu',
            ],
            [
                "ID=ubuntu\nVERSION_CODENAME=noble\nVERSION=",
                'invalid VERSION for Ubuntu',
            ],
            [
                "ID=ubuntu\nVERSION_CODENAME=noble\nVERSION=a",
                'invalid VERSION for Ubuntu',
            ],
            [
                "ID=ubuntu\nVERSION_CODENAME=noble\nVERSION=\"24.04.3\"",
                'invalid VERSION for Ubuntu',
            ],
            [
                "ID=ubuntu\nVERSION_CODENAME=noble\nVERSION=\"24.04.3 (foo bar)\"",
                'invalid VERSION for Ubuntu',
            ],
            [
                "ID=ubuntu\nVERSION_CODENAME=noble\nVERSION=\"24.04.3 LTS (Foo bar)\"",
                'invalid VERSION for Ubuntu',
            ],
            [
                "ID=ubuntu\nVERSION=\"24.04.3 LTS (Noble Numbat)\"\nVERSION_CODENAME=",
                'Invalid $codename',
            ],
            [
                "ID=ubuntu\nVERSION=\"24.04.3 LTS (Noble Numbat)\"\nVERSION_CODENAME=not valid",
                'Invalid $codename',
            ],
            [
                "ID=ubuntu\nVERSION=\"24.04.3 LTS (Noble Numbat)\"\nVERSION_CODENAME=Noble",
                'Invalid $codename',
            ],
            [
                "ID=el\nVERSION_ID=9",
                'defines an unsupported distribution (el)',
            ],
            [
                'ID=almalinux',
                'Invalid $version',
            ],
            [
                "ID=rocky\nVERSION_ID=9.8.1",
                'Invalid $version',
            ],
            [
                "NAME=\"CentOS Linux\"\nID=centos\nVERSION_ID=7",
                'CentOS Linux 7 is not supported',
            ],
            [
                "ID=fedora\nVERSION_ID=rawhide",
                'Invalid $version',
            ],
            [
                "NAME=\"Amazon Linux\"\nID=amzn\nVERSION_ID=2",
                'Amazon Linux 2 is not supported',
            ],
        ];
    }

    /**
     * @dataProvider provideUnsupportedOSReleaseFileCases
     *
     * @param string $osReleaseFileContents
     * @param string $expectedExceptionMessagePart
     *
     * @return void
     */
    public function testUnsupportedOSReleaseFile($osReleaseFileContents, $expectedExceptionMessagePart)
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('isFile')->withArgs(['/etc/os-release'])->andReturn(true);
        $fs->shouldReceive('readFile')->withArgs(['/etc/os-release'])->andReturn($osReleaseFileContents);
        $app = self::createApp();
        $app->singleton(Filesystem::class, static function () use ($fs) { return $fs; });
        $factory = new OS\Factory($app, new \IPE\Logger());
        try {
            $factory->detectOS();
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedOS $x) {
            self::assertStringContainsString($expectedExceptionMessagePart, $x->getMessage());
        }
    }

    /**
     * @return list<array{0: list<string>, 1: array<string, mixed>, 2?: class-string<\IPE\OS\Posix\RHELLike>, 3?: string}> the lines of /etc/os-release, the expected results of the OS getters, and (for dnf) the expected class and the output of dnf --version
     */
    public static function provideSupportedOSReleaseFileCases()
    {
        return [
            [
                [
                    'NAME="Alpine Linux"',
                    'ID=alpine',
                    'VERSION_ID=3.8.2',
                    'PRETTY_NAME="Alpine Linux v3.8"',
                    'HOME_URL="http://alpinelinux.org"',
                    'BUG_REPORT_URL="http://bugs.alpinelinux.org"',
                ],
                [
                    'getDisplayName' => 'Alpine 3.8.2',
                    'getNumericVersion' => 308,
                    'getVersion' => '3.8.2',
                ],
            ],
            [
                [
                    'NAME="Alpine Linux"',
                    'ID=alpine',
                    'VERSION_ID=3.21.4',
                    'PRETTY_NAME="Alpine Linux v3.21"',
                    'HOME_URL="https://alpinelinux.org/"',
                    'BUG_REPORT_URL="https://gitlab.alpinelinux.org/alpine/aports/-/issues"',
                ],
                [
                    'getDisplayName' => 'Alpine 3.21.4',
                    'getNumericVersion' => 321,
                    'getVersion' => '3.21.4',
                ],
            ],
            [
                [
                    'PRETTY_NAME="Debian GNU/Linux 8 (jessie)"',
                    'NAME="Debian GNU/Linux"',
                    'VERSION_ID="8"',
                    'VERSION="8 (jessie)"',
                    'ID=debian',
                    'HOME_URL="http://www.debian.org/"',
                    'SUPPORT_URL="http://www.debian.org/support"',
                    'BUG_REPORT_URL="https://bugs.debian.org/"',
                ],
                [
                    'getDisplayName' => 'Debian 8 (Jessie)',
                    'getNumericVersion' => OS\Debian::JESSIE,
                    'getCodename' => 'jessie',
                ],
            ],
            [
                [
                    'PRETTY_NAME="Debian GNU/Linux 13 (trixie)"',
                    'NAME="Debian GNU/Linux"',
                    'VERSION_ID="13"',
                    'VERSION="13 (trixie)"',
                    'VERSION_CODENAME=trixie',
                    'DEBIAN_VERSION_FULL=13.1',
                    'ID=debian',
                    'HOME_URL="https://www.debian.org/"',
                    'SUPPORT_URL="https://www.debian.org/support"',
                    'BUG_REPORT_URL="https://bugs.debian.org/"',
                ],
                [
                    'getDisplayName' => 'Debian 13 (Trixie)',
                    'getNumericVersion' => OS\Debian::TRIXIE,
                    'getCodename' => 'trixie',
                ],
            ],
            [
                [
                    'PRETTY_NAME="Ubuntu 24.04.3 LTS"',
                    'NAME="Ubuntu"',
                    'VERSION_ID="24.04"',
                    'VERSION="24.04.3 LTS (Noble Numbat)"',
                    'VERSION_CODENAME=noble',
                    'ID=ubuntu',
                    'ID_LIKE=debian',
                    'HOME_URL="https://www.ubuntu.com/"',
                    'SUPPORT_URL="https://help.ubuntu.com/"',
                    'BUG_REPORT_URL="https://bugs.launchpad.net/ubuntu/"',
                    'PRIVACY_POLICY_URL="https://www.ubuntu.com/legal/terms-and-policies/privacy-policy"',
                    'UBUNTU_CODENAME=noble',
                    'LOGO=ubuntu-logo',
                ],
                [
                    'getDisplayName' => 'Ubuntu 24.04.3 LTS (Noble Numbat)',
                    'getCodename' => 'noble',
                    'getVersion' => '24.04.3 LTS',
                    'getNumericVersion' => 2404,
                ],
            ],
            [
                [
                    'NAME="Ubuntu"',
                    'VERSION="14.04.6 LTS, Trusty Tahr"',
                    'ID=ubuntu',
                    'ID_LIKE=debian',
                    'PRETTY_NAME="Ubuntu 14.04.6 LTS"',
                    'VERSION_ID="14.04"',
                    'HOME_URL="http://www.ubuntu.com/"',
                    'SUPPORT_URL="http://help.ubuntu.com/"',
                    'BUG_REPORT_URL="http://bugs.launchpad.net/ubuntu/"',
                ],
                [
                    'getDisplayName' => 'Ubuntu 14.04.6 LTS (Trusty Tahr)',
                    'getCodename' => 'trusty',
                    'getVersion' => '14.04.6 LTS',
                    'getNumericVersion' => 1404,
                ],
            ],
            [
                [
                    'NAME="AlmaLinux"',
                    'VERSION="8.10 (Cerulean Leopard)"',
                    'ID="almalinux"',
                    'ID_LIKE="rhel centos fedora"',
                    'VERSION_ID="8.10"',
                    'PLATFORM_ID="platform:el8"',
                    'PRETTY_NAME="AlmaLinux 8.10 (Cerulean Leopard)"',
                    'ANSI_COLOR="0;34"',
                    'LOGO="fedora-logo-icon"',
                    'CPE_NAME="cpe:/o:almalinux:almalinux:8::baseos"',
                    'HOME_URL="https://almalinux.org/"',
                    'DOCUMENTATION_URL="https://wiki.almalinux.org/"',
                    'BUG_REPORT_URL="https://bugs.almalinux.org/"',
                    '',
                    'ALMALINUX_MANTISBT_PROJECT="AlmaLinux-8"',
                    'ALMALINUX_MANTISBT_PROJECT_VERSION="8.10"',
                    'REDHAT_SUPPORT_PRODUCT="AlmaLinux"',
                    'REDHAT_SUPPORT_PRODUCT_VERSION="8.10"',
                    'SUPPORT_END=2029-06-01',
                ],
                [
                    'getDisplayName' => 'AlmaLinux 8.10',
                    'getVersion' => '8.10',
                    'getNumericVersion' => 8,
                ],
                OS\AlmaLinux::class,
                "4.7.0\n",
            ],
            [
                [
                    'NAME="Rocky Linux"',
                    'VERSION="9.8 (Blue Onyx)"',
                    'RELEASE_TYPE="stable"',
                    'ID="rocky"',
                    'ID_LIKE="rhel centos fedora"',
                    'VERSION_ID="9.8"',
                    'PLATFORM_ID="platform:el9"',
                    'PRETTY_NAME="Rocky Linux 9.8 (Blue Onyx)"',
                    'ANSI_COLOR="0;32"',
                    'LOGO="fedora-logo-icon"',
                    'CPE_NAME="cpe:/o:rocky:rocky:9::baseos"',
                    'HOME_URL="https://rockylinux.org/"',
                    'VENDOR_NAME="RESF"',
                    'VENDOR_URL="https://resf.org/"',
                    'BUG_REPORT_URL="https://bugs.rockylinux.org/"',
                    'SUPPORT_END="2032-05-31"',
                    'ROCKY_SUPPORT_PRODUCT="Rocky-Linux-9"',
                    'ROCKY_SUPPORT_PRODUCT_VERSION="9.8"',
                    'REDHAT_SUPPORT_PRODUCT="Rocky Linux"',
                    'REDHAT_SUPPORT_PRODUCT_VERSION="9.8"',
                ],
                [
                    'getDisplayName' => 'Rocky Linux 9.8',
                    'getVersion' => '9.8',
                    'getNumericVersion' => 9,
                ],
                OS\RockyLinux::class,
                "4.14.0\n",
            ],
            [
                [
                    'NAME="Red Hat Enterprise Linux"',
                    'VERSION="9.9 (Plow)"',
                    'ID="rhel"',
                    'ID_LIKE="fedora"',
                    'VERSION_ID="9.9"',
                    'PLATFORM_ID="platform:el9"',
                    'PRETTY_NAME="Red Hat Enterprise Linux 9.9 (Plow)"',
                    'ANSI_COLOR="0;31"',
                    'LOGO="fedora-logo-icon"',
                    'CPE_NAME="cpe:/o:redhat:enterprise_linux:9::baseos"',
                    'HOME_URL="https://www.redhat.com/"',
                    'DOCUMENTATION_URL="https://access.redhat.com/documentation/en-us/red_hat_enterprise_linux/9"',
                    'BUG_REPORT_URL="https://issues.redhat.com/"',
                    '',
                    'REDHAT_BUGZILLA_PRODUCT="Red Hat Enterprise Linux 9"',
                    'REDHAT_BUGZILLA_PRODUCT_VERSION=9.9',
                    'REDHAT_SUPPORT_PRODUCT="Red Hat Enterprise Linux"',
                    'REDHAT_SUPPORT_PRODUCT_VERSION="9.9"',
                ],
                [
                    'getDisplayName' => 'Red Hat Enterprise Linux 9.9',
                    'getVersion' => '9.9',
                    'getNumericVersion' => 9,
                ],
                OS\RHEL::class,
                "4.14.0\n",
            ],
            [
                [
                    'NAME="Oracle Linux Server"',
                    'VERSION="9.8"',
                    'ID="ol"',
                    'ID_LIKE="fedora"',
                    'VARIANT="Server"',
                    'VARIANT_ID="server"',
                    'VERSION_ID="9.8"',
                    'PLATFORM_ID="platform:el9"',
                    'PRETTY_NAME="Oracle Linux Server 9.8"',
                    'ANSI_COLOR="0;31"',
                    'CPE_NAME="cpe:/o:oracle:linux:9:8:server"',
                    'HOME_URL="https://linux.oracle.com/"',
                    'BUG_REPORT_URL="https://github.com/oracle/oracle-linux"',
                    '',
                    'ORACLE_BUGZILLA_PRODUCT="Oracle Linux 9"',
                    'ORACLE_BUGZILLA_PRODUCT_VERSION=9.8',
                    'ORACLE_SUPPORT_PRODUCT="Oracle Linux"',
                    'ORACLE_SUPPORT_PRODUCT_VERSION=9.8',
                ],
                [
                    'getDisplayName' => 'Oracle Linux Server 9.8',
                    'getVersion' => '9.8',
                    'getNumericVersion' => 9,
                ],
                OS\OracleLinux::class,
                "4.14.0\n",
            ],
            [
                [
                    'NAME="CentOS Stream"',
                    'VERSION="10 (Coughlan)"',
                    'RELEASE_TYPE=stable',
                    'ID="centos"',
                    'ID_LIKE="rhel fedora"',
                    'VERSION_ID="10"',
                    'PLATFORM_ID="platform:el10"',
                    'PRETTY_NAME="CentOS Stream 10 (Coughlan)"',
                    'ANSI_COLOR="0;31"',
                    'LOGO="fedora-logo-icon"',
                    'CPE_NAME="cpe:/o:centos:centos:10"',
                    'HOME_URL="https://centos.org/"',
                    'VENDOR_NAME="CentOS"',
                    'VENDOR_URL="https://centos.org/"',
                    'BUG_REPORT_URL="https://issues.redhat.com/"',
                    'REDHAT_SUPPORT_PRODUCT="Red Hat Enterprise Linux 10"',
                    'REDHAT_SUPPORT_PRODUCT_VERSION="CentOS Stream"',
                ],
                [
                    'getDisplayName' => 'CentOS Stream 10',
                    'getVersion' => '10',
                    'getNumericVersion' => 10,
                ],
                OS\CentOS::class,
                "4.20.0\n",
            ],
            [
                [
                    'NAME="Fedora Linux"',
                    'VERSION="44 (Container Image)"',
                    'RELEASE_TYPE=stable',
                    'ID=fedora',
                    'VERSION_ID=44',
                    'VERSION_CODENAME=""',
                    'PRETTY_NAME="Fedora Linux 44 (Container Image)"',
                    'ANSI_COLOR="0;38;2;60;110;180"',
                    'LOGO=fedora-logo-icon',
                    'CPE_NAME="cpe:/o:fedoraproject:fedora:44"',
                    'DEFAULT_HOSTNAME="fedora"',
                    'HOME_URL="https://fedoraproject.org/"',
                    'DOCUMENTATION_URL="https://docs.fedoraproject.org/en-US/fedora/f44/"',
                    'SUPPORT_URL="https://ask.fedoraproject.org/"',
                    'BUG_REPORT_URL="https://bugzilla.redhat.com/"',
                    'REDHAT_BUGZILLA_PRODUCT="Fedora"',
                    'REDHAT_BUGZILLA_PRODUCT_VERSION=44',
                    'REDHAT_SUPPORT_PRODUCT="Fedora"',
                    'REDHAT_SUPPORT_PRODUCT_VERSION=44',
                    'SUPPORT_END=2027-05-19',
                    'VARIANT="Container Image"',
                    'VARIANT_ID=container',
                ],
                [
                    'getDisplayName' => 'Fedora Linux 44',
                    'getVersion' => '44',
                    'getNumericVersion' => 44,
                ],
                OS\Fedora::class,
                "dnf5 version 5.4.6.0\ndnf5 plugin API version 2.0\n",
            ],
            [
                [
                    'NAME="Amazon Linux"',
                    'VERSION="2023"',
                    'ID="amzn"',
                    'ID_LIKE="fedora"',
                    'VERSION_ID="2023"',
                    'PLATFORM_ID="platform:al2023"',
                    'PRETTY_NAME="Amazon Linux 2023.12.20261102"',
                    'ANSI_COLOR="0;33"',
                    'CPE_NAME="cpe:2.3:o:amazon:amazon_linux:2023"',
                    'HOME_URL="https://aws.amazon.com/linux/amazon-linux-2023/"',
                    'DOCUMENTATION_URL="https://docs.aws.amazon.com/linux/"',
                    'SUPPORT_URL="https://aws.amazon.com/premiumsupport/"',
                    'BUG_REPORT_URL="https://github.com/amazonlinux/amazon-linux-2023"',
                    'VENDOR_NAME="AWS"',
                    'VENDOR_URL="https://aws.amazon.com/"',
                    'SUPPORT_END="2029-06-30"',
                ],
                [
                    'getDisplayName' => 'Amazon Linux 2023',
                    'getVersion' => '2023',
                    'getNumericVersion' => 2023,
                ],
                OS\AmazonLinux::class,
                "4.14.0\n",
            ],
        ];
    }

    /**
     * @dataProvider provideSupportedOSReleaseFileCases
     *
     * @param array<string> $osReleaseFileContents
     * @param array<string, mixed> $gettersAndExpectedValues
     * @param class-string<\IPE\OS\Posix\RHELLike>|'' $expectedClass
     * @param string $dnfVersion
     *
     * @return void
     */
    public function testSupportedOSReleaseFile(array $osReleaseFileContents, array $gettersAndExpectedValues, $expectedClass = '', $dnfVersion = '')
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->andReturn('/');
        $fs->shouldReceive('isFile')->withArgs(['/etc/os-release'])->andReturn(true);
        $fs->shouldReceive('readFile')->withArgs(['/etc/os-release'])->andReturn(implode("\n", $osReleaseFileContents) . "\n");
        if ($dnfVersion !== '') {
            $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create('dnf', ['--version'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT)))->andReturn([0, $dnfVersion]);
        }
        $app = self::createApp();
        $app->singleton(Filesystem::class, static function () use ($fs) { return $fs; });
        $factory = new OS\Factory($app, new \IPE\Logger());
        $os = $factory->detectOS();
        self::assertInstanceOf(OS\Posix::class, $os);
        if ($expectedClass !== '') {
            self::assertSame($expectedClass, get_class($os));
        }
        self::assertSame($fs, $os->getFilesystem());
        foreach ($gettersAndExpectedValues as $getter => $expectedValue) {
            $actualValue = $os->{$getter}();
            self::assertSame($expectedValue, $actualValue, "Result of {$getter}()");
        }
        $packager = $os->getSystemPackager();
        self::assertSame($os, $packager->getOS());
        if ($os instanceof OS\Alpine) {
            self::assertInstanceOf(SystemPackager\APK::class, $packager);
        }
        if ($os instanceof OS\Debian) {
            self::assertInstanceOf(SystemPackager\APT::class, $packager);
            self::assertInstanceOf(SystemPackager\APT\OnDebian::class, $packager);
        }
        if ($os instanceof OS\Ubuntu) {
            self::assertInstanceOf(SystemPackager\APT::class, $packager);
            self::assertNotInstanceOf(SystemPackager\APT\OnDebian::class, $packager);
        }
        if ($os instanceof OS\Posix\RHELLike) {
            self::assertInstanceOf($os instanceof OS\Fedora ? SystemPackager\DNF\v5::class : SystemPackager\DNF\v4::class, $packager);
            // The packager is created only once
            self::assertSame($packager, $os->getSystemPackager());
        }
    }
}
