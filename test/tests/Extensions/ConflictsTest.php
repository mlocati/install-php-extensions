<?php

namespace IPE\Test\Extensions;

use IPE\Extension\Registry;
use IPE\Extensions\ddtrace;
use IPE\Extensions\http;
use IPE\Extensions\imap;
use IPE\Extensions\mssql;
use IPE\Extensions\parallel;
use IPE\Extensions\recode;
use IPE\Extensions\sybase_ct;
use IPE\Extensions\xdebug;
use IPE\Filesystem\Downloader;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Service\PHPScript;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class ConflictsTest extends TestCase
{
    /**
     * @return void
     */
    public function testConflicts()
    {
        $php = M::mock(PHPInfo::class);
        $alpine = M::mock(OS\Alpine::class);
        $debian = M::mock(OS\Debian::class);
        self::assertSame(['sybase_ct'], array_keys((new mssql($php, $debian))->getConflictingExtensions('5.6.40')));
        self::assertSame(['mssql'], array_keys((new sybase_ct($php, $alpine))->getConflictingExtensions('5.6.40')));
        self::assertSame(['imap'], array_keys((new recode($php, $alpine))->getConflictingExtensions('7.3.33')));
        self::assertSame(['recode'], array_keys((new imap($php, $alpine))->getConflictingExtensions('7.3.33')));
        self::assertSame([], array_keys((new recode($php, $debian))->getConflictingExtensions('7.3.33')));
        self::assertSame([], array_keys((new imap($php, $debian))->getConflictingExtensions('7.3.33')));
        self::assertSame(['parallel'], array_keys((new http($php, $debian))->getConflictingExtensions('4.3.1')));
        self::assertSame(['http'], array_keys((new parallel($php, $debian))->getConflictingExtensions('1.2.15')));
    }

    /**
     * @return void
     */
    public function testDdtraceAndXdebug()
    {
        $debian = M::mock(OS\Debian::class);
        $php70 = M::mock(PHPInfo::class);
        $php70->shouldReceive('getMajorMinorVersion')->andReturn(700);
        $php71 = M::mock(PHPInfo::class);
        $php71->shouldReceive('getMajorMinorVersion')->andReturn(701);
        $downloader = M::mock(Downloader::class);
        $phpScript = new PHPScript($php70, M::mock(Registry::class));
        self::assertSame(['xdebug'], array_keys((new ddtrace($php70, $debian, $downloader, $phpScript))->getConflictingExtensions('1.14.0')));
        self::assertSame([], array_keys((new ddtrace($php71, $debian, $downloader, $phpScript))->getConflictingExtensions('1.14.0')));
        self::assertSame(['ddtrace'], array_keys((new xdebug($php70, $debian))->getConflictingExtensions('2.6.1')));
        self::assertSame(['ddtrace'], array_keys((new xdebug($php71, $debian))->getConflictingExtensions('2.9.4')));
        self::assertSame([], array_keys((new xdebug($php71, $debian))->getConflictingExtensions('2.9.5')));
        self::assertSame([], array_keys((new xdebug($php71, $debian))->getConflictingExtensions('')));
    }
}
