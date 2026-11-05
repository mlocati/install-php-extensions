<?php

namespace IPE\Test\SystemPackager;

use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\OS;
use IPE\SystemPackager\APK;
use IPE\SystemPackager\APT;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class InstalledPackageOwningFileTest extends TestCase
{
    /**
     * @param list<array{non-empty-string, list<string>, int, string}> $runs the command, its arguments, its exit code and its output
     *
     * @return \IPE\OS&\Mockery\MockInterface
     */
    private static function createOS(array $runs)
    {
        $fs = M::mock(Filesystem::class);
        foreach ($runs as list($command, $arguments, $rc, $output)) {
            $fs->shouldReceive('run')->once()->with(self::isCommand(Command::create($command, $arguments)->withOutput(Command::OUTPUT_CAPTURE_STDOUT)))->andReturn([$rc, $output]);
        }
        $os = M::mock(OS::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);

        return $os;
    }

    /**
     * @return void
     */
    public function testAPT()
    {
        $os = self::createOS([
            ['dpkg-query', ['--search', '/usr/bin/php8.4'], 0, "diversion by foo from: /usr/bin/php8.4\nphp8.4-cli: /usr/bin/php8.4\n"],
            ['dpkg-query', ['--show', '--showformat=${Package}|${Version}|${source:Package}\n', 'php8.4-cli'], 0, "php8.4-cli|8.4.27-1~deb13u1|php8.4\n"],
        ]);
        $package = (new APT($os))->getInstalledPackageOwningFile('/usr/bin/php8.4');
        self::assertNotNull($package);
        self::assertSame('php8.4-cli', $package->getName());
        self::assertSame('8.4.27-1~deb13u1', $package->getVersion());
        self::assertSame('php8.4', $package->getSource());
    }

    /**
     * @return void
     */
    public function testAPTWithArchitecture()
    {
        $os = self::createOS([
            ['dpkg-query', ['--search', '/usr/lib/x86_64-linux-gnu/libfoo.so.1'], 0, "libfoo1:amd64, libfoo1:i386: /usr/lib/x86_64-linux-gnu/libfoo.so.1\n"],
            ['dpkg-query', ['--show', '--showformat=${Package}|${Version}|${source:Package}\n', 'libfoo1'], 0, "libfoo1|1:1.2-3|foo\nlibfoo1|1:1.2-3|foo\n"],
        ]);
        $package = (new APT($os))->getInstalledPackageOwningFile('/usr/lib/x86_64-linux-gnu/libfoo.so.1');
        self::assertNotNull($package);
        self::assertSame('libfoo1', $package->getName());
        self::assertSame('1:1.2-3', $package->getVersion());
        self::assertSame('foo', $package->getSource());
    }

    /**
     * @return void
     */
    public function testAPTWithMergedUsr()
    {
        $os = self::createOS([
            ['dpkg-query', ['--search', '/usr/bin/dash'], 1, ''],
            ['dpkg-query', ['--search', '/bin/dash'], 0, "dash: /bin/dash\n"],
            ['dpkg-query', ['--show', '--showformat=${Package}|${Version}|${source:Package}\n', 'dash'], 0, "dash|0.5.12-2|dash\n"],
        ]);
        $package = (new APT($os))->getInstalledPackageOwningFile('/usr/bin/dash');
        self::assertNotNull($package);
        self::assertSame('dash', $package->getName());
    }

    /**
     * @return void
     */
    public function testAPTNotOwnedWithMergedUsr()
    {
        $os = self::createOS([
            ['dpkg-query', ['--search', '/usr/lib/x86_64-linux-gnu/libfoo.so'], 1, ''],
            ['dpkg-query', ['--search', '/lib/x86_64-linux-gnu/libfoo.so'], 1, ''],
        ]);
        self::assertNull((new APT($os))->getInstalledPackageOwningFile('/usr/lib/x86_64-linux-gnu/libfoo.so'));
    }

    /**
     * @return void
     */
    public function testAPTNotOwned()
    {
        $os = self::createOS([
            ['dpkg-query', ['--search', '/usr/local/bin/php'], 1, ''],
        ]);
        self::assertNull((new APT($os))->getInstalledPackageOwningFile('/usr/local/bin/php'));
    }

    /**
     * @return void
     */
    public function testAPK()
    {
        $os = self::createOS([
            ['apk', ['info', '--quiet', '--who-owns', '/usr/bin/php84'], 0, "php84\n"],
            ['apk', ['list', '--installed', 'php84'], 0, "php84-8.4.27-r0 x86_64 {php84} (PHP-3.01 BSD-3-Clause LGPL-2.0-or-later MIT Zend-2.0) [installed]\n"],
        ]);
        $package = (new APK($os))->getInstalledPackageOwningFile('/usr/bin/php84');
        self::assertNotNull($package);
        self::assertSame('php84', $package->getName());
        self::assertSame('8.4.27-r0', $package->getVersion());
        self::assertSame('php84', $package->getSource());
    }

    /**
     * @return void
     */
    public function testAPKNotOwned()
    {
        $os = self::createOS([
            ['apk', ['info', '--quiet', '--who-owns', '/usr/local/bin/php'], 1, ''],
        ]);
        self::assertNull((new APK($os))->getInstalledPackageOwningFile('/usr/local/bin/php'));
    }
}
