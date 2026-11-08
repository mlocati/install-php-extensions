<?php

namespace IPE\Test\Filesystem\Downloader;

use IPE\Exception;
use IPE\Filesystem\Downloader;
use IPE\Filesystem\Downloader\Retrying;
use IPE\Logger;
use IPE\Test\Support\PSRLogger;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class RetryingTest extends TestCase
{
    /**
     * @return void
     */
    public function testSucceedsAfterRetries()
    {
        $calls = 0;
        $actual = M::mock(Downloader::class);
        $actual->shouldReceive('getContent')->with('https://example.com/a')->times(3)->andReturnUsing(static function () use (&$calls) {
            $calls++;
            if ($calls === 1) {
                throw new Exception\IO('Connection refused');
            }
            if ($calls === 2) {
                throw new Exception\IO('Connection timed out');
            }

            return 'content';
        });
        $target = new PSRLogger();
        $downloader = new Retrying($actual, new Logger($target), 3, 0);

        self::assertSame('content', $downloader->getContent('https://example.com/a'));
        self::assertSame(['warning', 'warning'], array_column($target->loggedStuff, 'level'));
        self::assertStringContainsString('retry 1 of 3', $target->loggedStuff[0]['message']);
        self::assertStringContainsString('Connection refused', $target->loggedStuff[0]['message']);
        self::assertStringContainsString('retry 2 of 3', $target->loggedStuff[1]['message']);
        self::assertStringContainsString('Connection timed out', $target->loggedStuff[1]['message']);
    }

    /**
     * @return void
     */
    public function testSaveAsIsRetried()
    {
        $calls = 0;
        $actual = M::mock(Downloader::class);
        $actual->shouldReceive('saveAs')->with('https://example.com/a', '/tmp/a')->twice()->andReturnUsing(static function () use (&$calls) {
            $calls++;
            if ($calls === 1) {
                throw new Exception\IO('Connection refused');
            }
        });
        $downloader = new Retrying($actual, new Logger(), 1, 0);

        $downloader->saveAs('https://example.com/a', '/tmp/a');
    }

    /**
     * @return void
     */
    public function testGivesUpAfterRetries()
    {
        $actual = M::mock(Downloader::class);
        $actual->shouldReceive('getContent')->times(3)->andThrow(new Exception\IO('Connection refused'));
        $downloader = new Retrying($actual, new Logger(), 2, 0);

        $this->expectException(Exception\IO::class);
        $this->expectExceptionMessage('Connection refused');
        $downloader->getContent('https://example.com/a');
    }

    /**
     * @return array<array{\IPE\Exception\IO}>
     */
    public static function provideNotRetriedErrors()
    {
        return [
            [new Exception\IO\NotFound('Not found')],
            [new Exception\IO\Forbidden('Forbidden')],
        ];
    }

    /**
     * @dataProvider provideNotRetriedErrors
     *
     * @return void
     */
    public function testNotRetriedErrors(Exception\IO $error)
    {
        $actual = M::mock(Downloader::class);
        $actual->shouldReceive('getContent')->once()->andThrow($error);
        $downloader = new Retrying($actual, new Logger(), 2, 0);

        $this->expectException(get_class($error));
        $downloader->getContent('https://example.com/a');
    }
}
