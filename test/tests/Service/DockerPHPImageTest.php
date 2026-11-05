<?php

namespace IPE\Test\Service;

use IPE\Filesystem;
use IPE\Service\DockerPHPImage;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class DockerPHPImageTest extends TestCase
{
    /**
     * @return void
     */
    public function testAllCommandsAvailable()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->times(4)->andReturn('/usr/local/bin/command');
        $subject = new DockerPHPImage($fs);
        self::assertTrue($subject->isInUse());
        // The result is cached
        self::assertTrue($subject->isInUse());
    }

    /**
     * @return void
     */
    public function testMissingCommand()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('resolveCommand')->with('docker-php-source')->andReturn('');
        $fs->shouldReceive('resolveCommand')->andReturn('/usr/local/bin/command');
        self::assertFalse((new DockerPHPImage($fs))->isInUse());
    }
}
