<?php

namespace IPE\Test\CLI;

use IPE\CLI\PharBuilder;
use IPE\Exception;
use IPE\Test\Support\TestCase;

final class PharBuilderTest extends TestCase
{
    /**
     * @return void
     */
    public function testBinContainsTheVersionLine()
    {
        $content = file_get_contents(IPE_ROOT_DIR . '/bin/install-php-extensions');
        self::assertIsString($content);
        // The release workflow builds the PHAR files by replacing this line
        self::assertSame(1, substr_count($content, PharBuilder::VERSION_LINE));
    }

    /**
     * @return array<array{string}>
     */
    public static function provideValidVersions()
    {
        return [
            ['0.0.0'],
            ['1.2.3'],
            ['10.20.30'],
            ['1.2.3-alpha'],
            ['1.2.3-beta2'],
            ['1.2.3-RC10'],
        ];
    }

    /**
     * @dataProvider provideValidVersions
     *
     * @param string $version
     *
     * @return void
     */
    public function testValidVersions($version)
    {
        self::assertSame($version, (new PharBuilder())->setVersion($version)->getVersion());
    }

    /**
     * @return array<array{string}>
     */
    public static function provideInvalidVersions()
    {
        return [
            ['1'],
            ['1.2'],
            ['1.2.3.4'],
            ['v1.2.3'],
            ['01.2.3'],
            ['1.02.3'],
            ['1.2.03'],
            ['1.2.3-'],
            ['1.2.3-rc1'],
            ['1.2.3-RC0'],
            ['1.2.3-RC01'],
            ['1.2.3-beta.2'],
            ['1.2.3-dev'],
            ["1.2.3\n"],
        ];
    }

    /**
     * @dataProvider provideInvalidVersions
     *
     * @param string $version
     *
     * @return void
     */
    public function testInvalidVersions($version)
    {
        $this->expectException(Exception\UserMessage::class);
        (new PharBuilder())->setVersion($version);
    }

    /**
     * @return void
     */
    public function testInjectVersion()
    {
        self::assertSame(
            "<?php\n\nconst IPE_VERSION = '1.2.3-RC1';\n\necho IPE_VERSION;\n",
            PharBuilder::injectVersion("<?php\n\n" . PharBuilder::VERSION_LINE . "\n\necho IPE_VERSION;\n", '1.2.3-RC1')
        );
    }

    /**
     * @return array<array{string}>
     */
    public static function provideContentsWithoutTheVersionLine()
    {
        return [
            ["<?php\n\nconst IPE_VERSION = '1.0.0';\n"],
            ["<?php\n\n" . PharBuilder::VERSION_LINE . "\n" . PharBuilder::VERSION_LINE . "\n"],
        ];
    }

    /**
     * @dataProvider provideContentsWithoutTheVersionLine
     *
     * @param string $content
     *
     * @return void
     */
    public function testInjectVersionWithoutTheVersionLine($content)
    {
        $this->expectException(Exception\UserMessage::class);
        PharBuilder::injectVersion($content, '1.2.3');
    }
}
