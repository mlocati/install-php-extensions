<?php

namespace IPE\Test\Extension\Version;

use IPE\Extension\Version\Composer;
use IPE\Test\Support\TestCase;

final class ComposerTest extends TestCase
{
    /**
     * @return void
     */
    public function testDownloadMethods()
    {
        self::assertSame([Composer::DOWNLOADMETHOD_COMPOSER], (new Composer('1.0.0', []))->getDownloadMethods());
        self::assertSame([Composer::DOWNLOADMETHOD_COMPOSER], (new Composer('1.0.0', ['php-ext' => ['download-url-method' => []]]))->getDownloadMethods());
        self::assertSame([Composer::DOWNLOADMETHOD_PREPACKAGED_SOURCE], (new Composer('1.0.0', ['php-ext' => ['download-url-method' => 'pre-packaged-source']]))->getDownloadMethods());
        self::assertSame(['pre-packaged-binary', 'composer-default'], (new Composer('1.0.0', ['php-ext' => ['download-url-method' => ['pre-packaged-binary', 'composer-default']]]))->getDownloadMethods());
    }

    /**
     * @return void
     */
    public function testDist()
    {
        $version = new Composer('1.0.0', ['name' => 'foo/bar', 'dist' => ['type' => 'tar', 'url' => 'https://example.com/foo-bar-1.0.0.tar']]);
        self::assertSame('foo/bar', $version->getPackageName());
        self::assertSame('tar', $version->getDownloadType());
        self::assertSame('https://example.com/foo-bar-1.0.0.tar', $version->getDownloadURL());
        $version = new Composer('1.0.0', []);
        self::assertSame('', $version->getPackageName());
        self::assertSame('', $version->getDownloadType());
        self::assertSame('', $version->getDownloadURL());
    }

    /**
     * @return void
     */
    public function testExtensionName()
    {
        self::assertSame('foo', (new Composer('1.0.0', ['name' => 'vendor/ext-bar', 'php-ext' => ['extension-name' => 'ext-foo']]))->getExtensionName());
        self::assertSame('bar', (new Composer('1.0.0', ['name' => 'vendor/ext-bar']))->getExtensionName());
        self::assertSame('', (new Composer('1.0.0', []))->getExtensionName());
    }

    /**
     * @return void
     */
    public function testGitHubRepository()
    {
        self::assertSame('owner/repo', (new Composer('1.0.0', ['dist' => ['url' => 'https://api.github.com/repos/owner/repo/zipball/0123456789abcdef']]))->getGitHubRepository());
        self::assertSame('owner/repo', (new Composer('1.0.0', ['dist' => ['url' => 'https://example.com/a.zip'], 'source' => ['url' => 'https://github.com/owner/repo.git']]))->getGitHubRepository());
        self::assertSame('owner/repo', (new Composer('1.0.0', ['source' => ['url' => 'https://github.com/owner/repo']]))->getGitHubRepository());
        self::assertSame('', (new Composer('1.0.0', ['source' => ['url' => 'https://gitlab.com/owner/repo.git']]))->getGitHubRepository());
    }

    /**
     * @return void
     */
    public function testPrePackagedSourceAssetNames()
    {
        self::assertSame(
            ['php_mongodb-2.1.0rc1-src.tgz', 'php_mongodb-2.1.0rc1-src.zip', 'mongodb-2.1.0rc1.tgz'],
            (new Composer('2.1.0RC1', ['php-ext' => ['extension-name' => 'MongoDB']]))->getPrePackagedSourceAssetNames()
        );
        self::assertSame([], (new Composer('1.0.0', []))->getPrePackagedSourceAssetNames());
    }

    /**
     * @return void
     */
    public function testBuildPath()
    {
        self::assertSame('ext', (new Composer('1.0.0', ['php-ext' => ['build-path' => 'ext']]))->getBuildPath());
        self::assertSame('', (new Composer('1.0.0', ['php-ext' => []]))->getBuildPath());
        self::assertSame('', (new Composer('1.0.0', []))->getBuildPath());
    }
}
