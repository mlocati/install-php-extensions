<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\solr;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class SolrTest extends TestCase
{
    /**
     * @return void
     */
    public function testMakeEnvironment()
    {
        $php = M::mock(PHPInfo::class);
        $context = self::createBuildContext('2.9.3');
        self::assertSame([], (new solr($php, M::mock(OS\Debian::class)))->getMakeEnvironment($context));
        self::assertSame(['EXTRA_CFLAGS' => '-std=gnu17'], (new solr($php, M::mock(OS\MacOS::class)))->getMakeEnvironment($context));
        self::assertSame([], (new solr($php, M::mock(OS\MacOS::class)))->getMakeEnvironment(self::createBuildContext('2.9.4')));
    }
}
