<?php

namespace IPE\Test\CLI;

use IPE\CLI\Argv\InstallPHPExtensions;
use IPE\CLI\LegacyCompatibility;
use IPE\Extension;
use IPE\Extension\Registry;
use IPE\Option\Validator;
use IPE\SystemDependency;
use IPE\Test\Support\TestCase;

final class LegacyCompatibilityTest extends TestCase
{
    /**
     * @var \IPE\Extension\Registry|null
     */
    private static $extensions;

    /**
     * @return \IPE\Extension\Registry
     */
    private static function getExtensions()
    {
        if (self::$extensions === null) {
            self::$extensions = (new Registry(self::createApp()))->registerDefaultExtensions();
        }

        return self::$extensions;
    }

    /**
     * @return \IPE\CLI\Argv\InstallPHPExtensions
     */
    private static function createArgv()
    {
        return new InstallPHPExtensions(self::getExtensions(), (new SystemDependency\Registry(self::createApp(), new Validator(new \IPE\Logger()), new \IPE\Logger()))->registerDefaultDependencies());
    }

    /**
     * @param list<string> $args
     * @param array<string, string> $environment
     *
     * @return array{non-empty-list<string>, list<string>}
     */
    private static function process(array $args, array $environment = [])
    {
        return (new LegacyCompatibility(self::createArgv(), self::getExtensions()))->process(array_merge(['ipe'], $args), $environment);
    }

    /**
     * @return void
     */
    public function testNothingToDo()
    {
        $args = ['--verbose', '--cpus', '2', 'xdebug:^3.3', 'vendor/name:^1@beta', 'gh:org/repo@main', 'https://example.com/a@b.tgz', './ext@1', '/opt/ext', '--', 'apcu'];
        self::assertSame([array_merge(['ipe'], $args), []], self::process($args, ['PATH' => '/usr/bin', 'IPE_EXTVERSION_SPX' => '1.0']));
    }

    /**
     * @return array<array{string, string}>
     */
    public static function provideConvertedArguments()
    {
        return [
            ['datadog_trace', 'ddtrace'],
            ['ioncube', 'ioncube_loader'],
            ['ioncube loader', 'ioncube_loader'],
            ['libsodium', 'sodium'],
            ['pecl_http', 'http'],
            ['saxonc', 'saxon'],
            ['Zend OPcache', 'opcache'],
            ['XDebug', 'xdebug'],
            ['xdebug-3.3.2', 'xdebug:3.3.2'],
            ['xdebug-3.4.0beta1', 'xdebug:3.4.0beta1'],
            ['xdebug-beta', 'xdebug:@beta'],
            ['xdebug-devel', 'xdebug:@dev'],
            ['xdebug-^3.2', 'xdebug:3.2.*'],
            ['xdebug-^3@beta', 'xdebug:3.*@beta'],
            ['pecl_http-^4.2@snapshot', 'http:4.2.*@dev'],
            ['php-memcached-dev/php-memcached@8f106564e6bb005ca6100b12ccc89000daafa9d8', 'gh:php-memcached-dev/php-memcached@8f106564e6bb005ca6100b12ccc89000daafa9d8'],
        ];
    }

    /**
     * @dataProvider provideConvertedArguments
     *
     * @param string $old
     * @param string $new
     *
     * @return void
     */
    public function testConvertedArguments($old, $new)
    {
        self::assertSame([['ipe', $new], ["The '{$old}' argument is deprecated: use '{$new}' instead"]], self::process([$old]));
        self::assertSame(['ipe', '--', $new], self::process(['--', $old])[0]);
    }

    /**
     * @return array<array{string}>
     */
    public static function provideUnchangedArguments()
    {
        return [
            ['Unknown'],
            ['unknown-1.0'],
            ['unknown_alias'],
            ['xdebug:3.3'],
            ['XDebug:3.3'],
            ['vendor/name'],
        ];
    }

    /**
     * @dataProvider provideUnchangedArguments
     *
     * @param string $argument
     *
     * @return void
     */
    public function testUnchangedArguments($argument)
    {
        self::assertSame([['ipe', $argument], []], self::process([$argument]));
    }

    /**
     * @return void
     */
    public function testOptionValuesAreNotConverted()
    {
        self::assertSame(
            [['ipe', '--temp-dir', 'XDebug', '--composer-path=pecl_http', 'xdebug'], ["The 'XDebug' argument is deprecated: use 'xdebug' instead"]],
            self::process(['--temp-dir', 'XDebug', '--composer-path=pecl_http', 'XDebug'])
        );
    }

    /**
     * @return void
     */
    public function testPseudoExtensions()
    {
        self::assertSame(
            [
                ['ipe', '--install-composer', '--fix-letsencrypt=yes', 'apcu'],
                [
                    "The '@composer' argument is deprecated: use --install-composer instead",
                    'The --cleanup option is deprecated and has been ignored (install-php-extensions always cleans up everything)',
                    "The '@fix_letsencrypt' argument is deprecated: use --fix-letsencrypt=yes instead",
                ],
            ],
            self::process(['@composer', '--cleanup', 'apcu', '@fix_letsencrypt'])
        );
        self::assertSame(
            [['ipe', '--install-composer', '--composer-version=2.2'], ["The '@composer-2.2' argument is deprecated: use --install-composer and --composer-version=2.2 instead"]],
            self::process(['@composer-2.2'])
        );
        self::assertSame(
            [['ipe', '--composer-version=1', '--install-composer'], ["The '@composer-2.2' argument is deprecated and has been ignored since the --install-composer and --composer-version options are specified"]],
            self::process(['--composer-version=1', '@composer-2.2', '--install-composer'])
        );
    }

    /**
     * @return void
     */
    public function testKeepSystemPackagerCache()
    {
        self::assertSame(
            [['ipe', '--keep-systempackager-cache=yes', 'apcu'], ['The IPE_KEEP_SYSPKG_CACHE environment variable is deprecated: use --keep-systempackager-cache=yes instead']],
            self::process(['apcu'], ['IPE_KEEP_SYSPKG_CACHE' => '1'])
        );
    }

    /**
     * @return void
     */
    public function testEnvironmentVariables()
    {
        self::assertSame(
            [
                ['ipe', '--lib-aspell:languages=en,it', '--verbose', '--apt-debian-archiveurl-default=http://example.com/debian', '--apt-debian-archiveurl-updates=http://example.com/debian', '--build-type=debug', 'apcu'],
                [
                    'The IPE_ASPELL_LANGUAGES environment variable is deprecated: use --lib-aspell:languages=en,it instead',
                    'The IPE_DEBUG environment variable is deprecated: use --verbose instead',
                    'The IPE_DEB_ARCHIVE environment variable is deprecated: use --apt-debian-archiveurl-default=http://example.com/debian and --apt-debian-archiveurl-updates=http://example.com/debian instead',
                    'The IPE_LIBVERSION_AOM environment variable is not supported anymore and has been ignored',
                    'The IPE_NOSTRIP environment variable is deprecated and has been ignored since the --build-type option is specified',
                ],
            ],
            self::process(['--build-type=debug', 'apcu'], [
                'IPE_DEBUG' => '1',
                'IPE_NOSTRIP' => 'yes',
                'IPE_INSECURE' => '0',
                'IPE_SKIP_CHECK' => '',
                'IPE_DEB_ARCHIVE' => 'http://example.com/',
                'IPE_ASPELL_LANGUAGES' => ' en  it ',
                'IPE_LIBVERSION_AOM' => '3.0.0',
            ])
        );
    }

    /**
     * @return void
     */
    public function testGeneratedOptionsAreValid()
    {
        $environment = [];
        foreach (['IPE_DEBUG', 'IPE_DONT_ENABLE', 'IPE_KEEP_SYSPKG_CACHE', 'IPE_INSECURE', 'IPE_SKIP_CHECK', 'IPE_NOSTRIP', 'IPE_FIX_CACERTS', 'IPE_ICU_EN_ONLY', 'IPE_INSTANTCLIENT_BASIC', 'IPE_GD_WITHOUTAVIF', 'IPE_SWOOLE_WITHOUT_IOURING', 'IPE_NEWRELIC_DAEMON', 'IPE_NEWRELIC_KEEPLOG', 'IPE_DD_APPSEC', 'IPE_DD_PROFILING', 'IPE_LZF_BETTERCOMPRESSION'] as $name) {
            $environment[$name] = 'y';
        }
        $environment += [
            'IPE_PROCESSOR_COUNT' => '2',
            'IPE_SAVE_PERMDEPS_TO' => '/tmp/permanent',
            'IPE_SAVE_VOLDEPS_TO' => '/tmp/volatile',
            'IPE_ASPELL_LANGUAGES' => 'en',
            'IPE_SAXON_EDITION' => 'HE',
            'IPE_EVENT_NAMESPACE' => 'Foo',
            'IPE_DEB_ARCHIVE' => 'http://example.com',
            'IPE_DEB_ARCHIVE_SECURITY' => 'http://example.com/debian-security',
        ];
        list($argv, $warnings) = self::process(['@composer-2'], $environment);
        self::assertCount(count($environment) + 1, $warnings);
        foreach ($warnings as $warning) {
            self::assertStringContainsString('is deprecated: use', $warning);
        }
        // One option for each variable (two for IPE_DONT_ENABLE and IPE_DEB_ARCHIVE), and two for @composer-2
        self::assertCount(1 + count($environment) + 2 + 2, $argv);
        self::assertContains('--create-enable-scripts', $argv);
        // The extension options are not checked by the parser, so let's check the ones declared by IPE
        $argvParser = self::createArgv()->addArgv($argv);
        self::assertSame([], $argvParser->getArguments());
        $classes = [];
        foreach (self::getExtensions()->getExtensionClasses() as $class) {
            $classes[$class::getHandle()] = $class;
        }
        foreach ($argvParser->getOptionsByExtension() as $handle => $optionNames) {
            self::assertArrayHasKey($handle, $classes);
            $class = $classes[$handle];
            if (is_subclass_of($class, Extension\DeclaresOptions::class)) {
                $declared = [];
                foreach ($class::getOptions() as $option) {
                    $declared[] = $option->getName();
                }
                foreach (array_keys($optionNames) as $optionName) {
                    self::assertContains($optionName, $declared, "{$handle}:{$optionName}");
                }
            } else {
                self::assertContains($handle, ['event', 'lzf'], 'The options of these extensions are declared in their package.xml');
            }
        }
    }
}
