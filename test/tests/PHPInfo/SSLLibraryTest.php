<?php

namespace IPE\Test\PHPInfo;

use IPE\Exception;
use IPE\PHPInfo\SSLLibrary;
use IPE\Test\Support\TestCase;

final class SSLLibraryTest extends TestCase
{
    /**
     * @return list<array{string, string|null, string, string, int, string}> the text, and the expected type, version, base version, major version and major.minor version
     */
    public static function provideVersionTexts()
    {
        return [
            ['OpenSSL 1.0.2q  20 Nov 2018', SSLLibrary::TYPE_OPENSSL, '1.0.2q', '1.0.2', 1, '1.0'],
            ['OpenSSL 1.0.2k-fips  26 Jan 2017', SSLLibrary::TYPE_OPENSSL, '1.0.2k-fips', '1.0.2', 1, '1.0'],
            ['OpenSSL 1.1.1w  11 Sep 2023', SSLLibrary::TYPE_OPENSSL, '1.1.1w', '1.1.1', 1, '1.1'],
            ['OpenSSL 3.0.15 3 Sep 2024', SSLLibrary::TYPE_OPENSSL, '3.0.15', '3.0.15', 3, '3.0'],
            ['LibreSSL 2.6.5', SSLLibrary::TYPE_LIBRESSL, '2.6.5', '2.6.5', 2, '2.6'],
            ['LibreSSL 3.8', SSLLibrary::TYPE_LIBRESSL, '3.8', '3.8', 3, '3.8'],
            ['', null, '', '', 0, ''],
            ['BoringSSL', null, '', '', 0, ''],
            ['OpenSSL', null, '', '', 0, ''],
            ['OpenSSL x.y', null, '', '', 0, ''],
        ];
    }

    /**
     * @dataProvider provideVersionTexts
     *
     * @param string $text
     * @param string|null $expectedType NULL if $text must not be recognized
     * @param string $expectedVersion
     * @param string $expectedBaseVersion
     * @param int $expectedMajorVersion
     * @param string $expectedMajorMinorVersion
     *
     * @return void
     */
    public function testFromVersionText($text, $expectedType, $expectedVersion, $expectedBaseVersion, $expectedMajorVersion, $expectedMajorMinorVersion)
    {
        $library = SSLLibrary::fromVersionText($text);
        if ($expectedType === null) {
            self::assertNull($library);

            return;
        }
        self::assertNotNull($library);
        self::assertSame($expectedType, $library->getType());
        self::assertSame($expectedVersion, $library->getVersion());
        self::assertSame($expectedBaseVersion, $library->getBaseVersion());
        self::assertSame($expectedMajorVersion, $library->getMajorVersion());
        self::assertSame($expectedMajorMinorVersion, $library->getMajorMinorVersion());
    }

    /**
     * @return void
     */
    public function testInvalidVersion()
    {
        $this->expectException(Exception\InvalidArgument::class);
        new SSLLibrary(SSLLibrary::TYPE_OPENSSL, 'foo');
    }
}
