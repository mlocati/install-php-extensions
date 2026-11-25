<?php

namespace IPE\Test\CLI\Argv;

use IPE\CLI\Argv\InstallPHPExtensions;
use IPE\Exception;
use IPE\Extension\Asked;
use IPE\Extension\Manifest;
use IPE\Extension\Registry;
use IPE\Extension\Resolved;
use IPE\Option\ConfigureOption;
use IPE\Option\Validator;
use IPE\SystemDependency;
use IPE\Test\Support\TestCase;

final class InstallPHPExtensionsTest extends TestCase
{
    /**
     * @return \IPE\CLI\Argv\InstallPHPExtensions
     */
    private static function createArgv()
    {
        return new InstallPHPExtensions((new Registry(self::createApp()))->registerDefaultExtensions(), (new SystemDependency\Registry(self::createApp(), new Validator(new \IPE\Logger()), new \IPE\Logger()))->registerDefaultDependencies());
    }

    /**
     * @return void
     */
    public function testHelpListsTheDeclaredOptions()
    {
        $help = self::createArgv()->buildCLIHelpString();
        self::assertStringContainsString("--gd:without-avif\n", $help);
        self::assertStringContainsString("--saxon:edition[=]value\n", $help);
        self::assertStringContainsString('Default: EE', $help);
        self::assertStringContainsString("--oci8:with-oci8[=]value\n", $help);
        self::assertStringContainsString("--lib-icu:en-only\n", $help);
        self::assertStringContainsString("--oracle-instantclient:basic\n", $help);
        self::assertStringContainsString("--enable[=]value\n", $help);
        self::assertStringNotContainsString('--create-enable-scripts', $help);
        self::assertStringContainsString("--fix-letsencrypt[=]<yes|no>\n  Fix the root CA certificate of Let's Encrypt on old systems\n  Default: yes in the official Docker PHP images, no otherwise\n", $help);
        self::assertStringContainsString("--from-composer-json[=value]\n  Also install the extensions listed in the require and require-dev sections of a composer.json file\n  Default: composer.json in the current directory\n", $help);
        self::assertStringNotContainsString('(default', $help);
    }

    /**
     * @return void
     */
    public function testDescribeExtensionOptions()
    {
        $argv = self::createArgv();
        $manifest = new Manifest(Manifest::TYPE_PECL, 'fakeext', 'fakeext');
        $manifest->addConfigureOption(new ConfigureOption('enable-fake-lz4', 'enable lz4 compression?', null, 'no'));
        $manifest->addConfigureOption(new ConfigureOption('with-fake-lib', 'Use the system library', true));
        $resolved = new Resolved(null, '/src', '1.2.3', Resolved::ORIGIN_PECL, $manifest);

        self::assertSame(
            "fakeext 1.2.3 (from pecl):\n--fakeext:enable-fake-lz4[=value]\n  enable lz4 compression?\n  Default: no\n--fakeext:with-fake-lib[=]value\n  Use the system library",
            $argv->describeExtensionOptions(new Asked\FromHandle('fakeext', ''), $resolved)
        );
        self::assertStringContainsString("--vendor/fakeext:enable-fake-lz4[=value]\n", $argv->describeExtensionOptions(new Asked\FromComposer('vendor/fakeext'), $resolved));
        self::assertSame("fakeext 1.2.3 (from pecl):\nNo options are available", $argv->describeExtensionOptions(new Asked\FromHandle('fakeext', ''), new Resolved(null, '/src', '1.2.3', Resolved::ORIGIN_PECL, new Manifest(Manifest::TYPE_PECL, 'fakeext', 'fakeext'))));
        self::assertStringContainsString("--fakeext:with-fake-lib[=]value\n", $argv->describeExtensionOptions(new Asked\FromDirectory('/src'), $resolved));
        self::assertStringContainsString("--fakeext:with-fake-lib[=]value\n", $argv->describeExtensionOptions(new Asked\FromURL('https://example.com/fakeext.tgz'), $resolved));
        $composerManifest = new Manifest(Manifest::TYPE_COMPOSER, 'vendor/fakeext', '');
        $composerManifest->addConfigureOption(new ConfigureOption('with-fake-lib', 'Use the system library', true));
        self::assertStringContainsString("--vendor/fakeext:with-fake-lib[=]value\n", $argv->describeExtensionOptions(new Asked\FromDirectory('/src'), new Resolved(null, '/src', '', Resolved::ORIGIN_DIRECTORY, $composerManifest)));
        self::assertStringContainsString("No options are available (the source code doesn't include", $argv->describeExtensionOptions(new Asked\FromDirectory('/src'), new Resolved(null, '/src', '', Resolved::ORIGIN_DIRECTORY)));
        self::assertStringContainsString('no options can be specified', $argv->describeExtensionOptions(new Asked\FromHandle('fakeext', ''), new Resolved(null, '/etc/fakeext.ini', '', Resolved::ORIGIN_DISABLED)));
        self::assertStringContainsString('--system-packages=never', $argv->describeExtensionOptions(new Asked\FromHandle('fakeext', ''), new Resolved(null, 'php-fakeext', '', Resolved::ORIGIN_SYSTEM_PACKAGE)));
        self::assertStringContainsString("--show-options\n", $argv->buildCLIHelpString());
    }

    /**
     * @return void
     */
    public function testHiddenOptionsCanBeUsed()
    {
        self::assertTrue(self::createArgv()->addArgv(['script', '--create-enable-scripts'])->getFlag('create-enable-scripts'));
    }

    /**
     * @return void
     */
    public function testKeepSystemPackagerCache()
    {
        // When it's not specified, the default depends on the environment
        self::assertNull(self::createArgv()->addArgv(['ipe'])->get('keep-systempackager-cache'));
        self::assertTrue(self::createArgv()->addArgv(['ipe', '--keep-systempackager-cache=yes'])->getBool('keep-systempackager-cache', false));
        self::assertFalse(self::createArgv()->addArgv(['ipe', '--keep-systempackager-cache', 'no'])->getBool('keep-systempackager-cache', true));
    }

    /**
     * @return void
     */
    public function testSystemDependencyOptions()
    {
        $argv = self::createArgv()->addArgv(['install-php-extensions', '--lib-icu:en-only', '--saxon:edition=HE', 'oci8']);
        self::assertSame(['lib-icu' => ['en-only' => true]], $argv->getSystemDependencyOptions());
        self::assertSame(['saxon' => ['edition' => 'HE']], $argv->getOptionsByExtension());
    }

    /**
     * @return void
     */
    public function testSystemDependencyFlagsDontAcceptValues()
    {
        $this->expectException(Exception\InvalidOption::class);
        self::createArgv()->addArgv(['install-php-extensions', '--lib-icu:en-only=yes']);
    }

    /**
     * @return void
     */
    public function testUnknownSystemDependencyOption()
    {
        $this->expectException(Exception\InvalidOption::class);
        self::createArgv()->addArgv(['install-php-extensions', '--lib-icu:foo']);
    }

    /**
     * @return void
     */
    public function testExtensionOptions()
    {
        $argv = self::createArgv();
        $argv->addArgv([
            'install-php-extensions',
            '--verbose',
            '--lzf:enable-lzf-better-compression=yes',
            '--xdebug:enable-xdebug-dev',
            '--xdebug:with-xdebug-dir=/opt/xdebug',
            '-lzf:keep',
            'lzf',
            '--',
            'xdebug',
        ]);
        self::assertTrue($argv->get('verbose'));
        self::assertSame(['lzf', 'xdebug'], $argv->getArguments());
        self::assertSame(
            [
                'lzf' => ['enable-lzf-better-compression' => 'yes', 'keep' => true],
                'xdebug' => ['enable-xdebug-dev' => true, 'with-xdebug-dir' => '/opt/xdebug'],
            ],
            $argv->getOptionsByExtension()
        );
        self::assertSame(['enable-xdebug-dev' => true, 'with-xdebug-dir' => '/opt/xdebug'], $argv->getOptionsForExtension('xdebug'));
        self::assertSame([], $argv->getOptionsForExtension('nope'));
        self::assertNull($argv->get('nope:option'));
        self::assertStringContainsString('--verbose', $argv->buildCLIHelpString());
    }

    /**
     * @return void
     */
    public function testComposerExtensionOptions()
    {
        $argv = self::createArgv();
        $argv->addArgv(['install-php-extensions', '--vendor/name:enable-foo', '--vendor/name:with-bar=/usr', 'vendor/name:^1']);
        self::assertSame(['vendor/name:^1'], $argv->getArguments());
        self::assertSame(['vendor/name' => ['enable-foo' => true, 'with-bar' => '/usr']], $argv->getOptionsByExtension());
    }

    /**
     * @return void
     */
    public function testNoProxyIsAFlag()
    {
        self::assertFalse(self::createArgv()->addArgv(['install-php-extensions'])->get('no-proxy'));
        $argv = self::createArgv()->addArgv(['install-php-extensions', '--no-proxy', 'apcu']);
        self::assertTrue($argv->get('no-proxy'));
        self::assertSame(['apcu'], $argv->getArguments());
    }

    /**
     * @return array<array{string}>
     */
    public static function provideBooleanOptions()
    {
        return [
            ['fix-letsencrypt'],
            ['apt-debian-fix-archived'],
        ];
    }

    /**
     * @dataProvider provideBooleanOptions
     *
     * @param non-empty-string $name
     *
     * @return void
     */
    public function testBooleanOptions($name)
    {
        $argv = self::createArgv()->addArgv(['install-php-extensions']);
        self::assertTrue($argv->getBool($name, true));
        self::assertFalse($argv->getBool($name, false));
        foreach (['yes', 'YES', 'on', '1', 'true'] as $value) {
            self::assertTrue(self::createArgv()->addArgv(['install-php-extensions', "--{$name}={$value}"])->getBool($name, false), $value);
        }
        foreach (['no', 'NO', 'off', '0', 'false'] as $value) {
            self::assertFalse(self::createArgv()->addArgv(['install-php-extensions', "--{$name}={$value}"])->getBool($name, true), $value);
        }
        self::assertTrue(self::createArgv()->addArgv(['install-php-extensions', "--{$name}", 'yes'])->getBool($name, false));
        self::assertStringContainsString("--{$name}[=]<yes|no>\n", self::createArgv()->buildCLIHelpString());
    }

    /**
     * @return array<array{string}>
     */
    public static function provideInvalidBooleanValues()
    {
        return [
            ['maybe'],
            ['2'],
            [''],
            [' '],
        ];
    }

    /**
     * @dataProvider provideInvalidBooleanValues
     *
     * @param string $value
     *
     * @return void
     */
    public function testInvalidBooleanValues($value)
    {
        $this->expectException(Exception\InvalidOption::class);
        $this->expectExceptionMessage('must be yes or no');
        self::createArgv()->addArgv(['install-php-extensions', "--fix-letsencrypt={$value}"]);
    }

    /**
     * @return array{string}[]
     */
    public static function provideInvalidOptions()
    {
        return [
            ['--nope'],
            ['--:option'],
            ['--Not-An-Handle:option'],
            ['--lzf:'],
            ['--vendor/name:'],
            ['--vendor/name/other:option'],
        ];
    }

    /**
     * @dataProvider provideInvalidOptions
     *
     * @param string $option
     *
     * @return void
     */
    public function testInvalidOptions($option)
    {
        try {
            self::createArgv()->addArgv(['install-php-extensions', $option]);
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidOption $x) {
            self::assertStringContainsString('Unrecognized option', $x->getMessage());
        }
    }
}
