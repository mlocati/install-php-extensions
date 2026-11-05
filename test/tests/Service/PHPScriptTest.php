<?php

namespace IPE\Test\Service;

use IPE\Extension\Registry;
use IPE\Extensions\snuffleupagus;
use IPE\Extensions\xdebug;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Service\PHPScript;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class PHPScriptTest extends TestCase
{
    /**
     * @return void
     */
    public function testWithoutSnuffleupagus()
    {
        $command = self::createPHPScript(false)->createCommand('/tmp/setup.php', ['--foo']);
        self::assertSame('/usr/local/bin/php', $command->getCommand());
        self::assertSame(['/tmp/setup.php', '--foo'], $command->getArguments());
    }

    /**
     * @return void
     */
    public function testWithSnuffleupagus()
    {
        $command = self::createPHPScript(true)->createCommand('/tmp/setup.php', ['--foo']);
        self::assertSame('/usr/local/bin/php', $command->getCommand());
        self::assertSame(['-d', 'sp.configuration_file=', '/tmp/setup.php', '--foo'], $command->getArguments());
    }

    /**
     * @param bool $snuffleupagusInstalled
     *
     * @return \IPE\Service\PHPScript
     */
    private static function createPHPScript($snuffleupagusInstalled)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getPHPBin')->andReturn('/usr/local/bin/php');
        $snuffleupagus = new snuffleupagus($php, M::mock(OS\Debian::class));
        $php->shouldReceive('isExtensionInstalled')->with($snuffleupagus)->andReturn($snuffleupagusInstalled);
        $registry = M::mock(Registry::class);
        $registry->shouldReceive('getExtensionClasses')->andReturn([xdebug::class, snuffleupagus::class]);
        $registry->shouldReceive('getExtensionByHandle')->with('snuffleupagus')->andReturn($snuffleupagus);

        return new PHPScript($php, $registry);
    }
}
