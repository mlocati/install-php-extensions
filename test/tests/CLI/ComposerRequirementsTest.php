<?php

namespace IPE\Test\CLI;

use IPE\CLI\ComposerRequirements;
use IPE\Exception;
use IPE\Extension\Asked;
use IPE\Extension\Registry;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Service\JSON;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class ComposerRequirementsTest extends TestCase
{
    /**
     * @param list<string> $installed
     * @param \IPE\Extension\Asked\Resolver\Options|null $options
     *
     * @return \IPE\CLI\ComposerRequirements
     */
    private static function create(array $installed = ['Core', 'json', 'SPL', 'Zend OPcache'], $options = null)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getInstalledExtensions')->andReturn($installed);
        $php->shouldReceive('isExtensionInstalled')->andReturn(false);
        $app = self::createApp();
        $app->instance(PHPInfo::class, $php);
        $app->instance(OS::class, M::mock(OS::class));
        $registry = new Registry($app);
        $registry->registerDefaultExtensions();

        return new ComposerRequirements(self::createLocalFilesystem(), $php, $registry, $options === null ? new Asked\Resolver\Options() : $options, new \IPE\Logger());
    }

    /**
     * @param array<mixed> $data
     *
     * @return non-empty-string
     */
    private static function writeJson(array $data)
    {
        $file = self::createLocalFilesystem()->createTemporaryFile();
        self::createLocalFilesystem()->writeFile($file, JSON::encode($data));

        return $file;
    }

    /**
     * @param list<\IPE\Extension\Asked\FromHandle> $asked
     *
     * @return array<string, string>
     */
    private static function describe(array $asked)
    {
        $result = [];
        foreach ($asked as $extension) {
            $result[$extension->getHandle()] = $extension->getVersionRequirements();
        }

        return $result;
    }

    /**
     * @return void
     */
    public function testComposerJson()
    {
        $file = self::writeJson([
            'require' => ['php' => '>=8', 'lib-curl' => '*', 'foo/bar' => '^1', 'ext-json' => '*', 'ext-apcu' => '^5', 'EXT-lzf' => '*'],
            'require-dev' => ['ext-mcrypt' => '*', 'ext-zend-opcache' => '*', 'phpunit/phpunit' => '*'],
        ]);
        self::assertSame(['apcu' => '^5', 'lzf' => '', 'mcrypt' => ''], self::describe(self::create()->getAskedExtensions($file, '', true)));
        self::assertSame(['apcu' => '^5', 'lzf' => ''], self::describe(self::create()->getAskedExtensions($file, '', false)));
    }

    /**
     * @return void
     */
    public function testComposerJsonProvidedExtensions()
    {
        $file = self::writeJson(['require' => ['ext-apcu' => '*', 'ext-lzf' => '*', 'ext-mcrypt' => '*'], 'provide' => ['ext-apcu' => '*'], 'replace' => ['ext-lzf' => '*']]);
        self::assertSame(['mcrypt' => ''], self::describe(self::create()->getAskedExtensions($file, '', true)));
    }

    /**
     * @return void
     */
    public function testComposerLock()
    {
        $file = self::writeJson([
            'platform' => ['php' => '>=8', 'ext-apcu' => '^5'],
            'platform-dev' => ['ext-lzf' => '*'],
            'packages' => [
                ['name' => 'a/a', 'require' => ['ext-apcu' => '>=5.1', 'ext-json' => '*']],
                ['name' => 'b/b', 'require' => ['ext-mcrypt' => '^1 || ^2']],
                ['name' => 'c/c', 'require' => ['ext-mcrypt' => '>=1.5']],
                ['name' => 'not-a-package'],
                'invalid',
            ],
            'packages-dev' => [
                ['name' => 'd/d', 'require' => ['ext-parallel' => '*']],
            ],
        ]);
        self::assertSame(
            ['apcu' => '^5, >=5.1', 'lzf' => '', 'mcrypt' => '^1, >=1.5 || ^2, >=1.5'],
            self::describe(self::create()->getAskedExtensions('', $file, true))
        );
        self::assertSame(
            ['apcu' => '^5, >=5.1', 'mcrypt' => '^1, >=1.5 || ^2, >=1.5'],
            self::describe(self::create()->getAskedExtensions('', $file, false))
        );
    }

    /**
     * @return void
     */
    public function testComposerLockProvidedExtensions()
    {
        $file = self::writeJson([
            'platform' => ['ext-apcu' => '*'],
            'packages' => [
                ['name' => 'a/a', 'require' => ['ext-lzf' => '*', 'ext-mcrypt' => '*']],
                ['name' => 'polyfill/apcu', 'provide' => ['ext-apcu' => '*']],
                ['name' => 'polyfill/lzf', 'replace' => ['ext-lzf' => '*']],
            ],
            'packages-dev' => [
                ['name' => 'polyfill/mcrypt', 'provide' => ['ext-mcrypt' => '*']],
            ],
        ]);
        self::assertSame(['mcrypt' => ''], self::describe(self::create()->getAskedExtensions('', $file, true)));
    }

    /**
     * @return void
     */
    public function testBothFilesAreMerged()
    {
        $json = self::writeJson(['require' => ['ext-apcu' => '^5', 'ext-lzf' => '*']]);
        $lock = self::writeJson(['platform' => ['ext-apcu' => '^5'], 'packages' => [['require' => ['ext-apcu' => '>=5.1', 'ext-mcrypt' => '*']]]]);
        self::assertSame(['apcu' => '^5, >=5.1', 'lzf' => '', 'mcrypt' => ''], self::describe(self::create()->getAskedExtensions($json, $lock, true)));
    }

    /**
     * @return void
     */
    public function testExtensionsSpecifiedByTheUserWin()
    {
        $file = self::writeJson(['require' => ['ext-apcu' => '^5', 'ext-lzf' => '^1', 'ext-mcrypt' => '*', 'ext-bcmath' => '*']]);
        $asked = self::create()->getAskedExtensions($file, '', true, [new Asked\FromHandle('lzf', '^2'), new Asked\FromComposer('APCu/apcu', '^4'), new Asked\FromComposer('php/bcmath')], ['mcrypt' => ['some-option' => 'value']]);
        self::assertSame(['mcrypt' => ''], self::describe($asked));
        self::assertSame(['some-option' => 'value'], $asked[0]->getOptions());
    }

    /**
     * @return void
     */
    public function testUnknownExtensions()
    {
        $file = self::writeJson(['require' => ['ext-not-known' => '*', 'ext-apcu' => '*']]);
        try {
            self::create()->getAskedExtensions($file, '', true);
            self::fail('Expected exception not thrown');
        } catch (Exception\UnsupportedExtension $x) {
            self::assertStringContainsString('not-known', $x->getMessage());
        }
        $options = (new Asked\Resolver\Options())->withIgnoreUnknownExtensions(true);
        self::assertSame(['apcu' => ''], self::describe(self::create([], $options)->getAskedExtensions($file, '', true)));
    }

    /**
     * @return void
     */
    public function testInvalidFiles()
    {
        $file = self::createLocalFilesystem()->createTemporaryFile();
        self::createLocalFilesystem()->writeFile($file, '{');
        try {
            self::create()->getAskedExtensions($file, '', true);
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidArgument $x) {
            self::assertStringContainsString($file, $x->getMessage());
        }
        try {
            self::create()->getAskedExtensions('', $file . '-not-existing', true);
            self::fail('Expected exception not thrown');
        } catch (Exception\IO $x) {
            self::assertStringContainsString('-not-existing', $x->getMessage());
        }
    }
}
