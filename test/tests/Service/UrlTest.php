<?php

namespace IPE\Test\Service;

use IPE\Exception;
use IPE\Service\Url;
use IPE\Test\Support\TestCase;

final class UrlTest extends TestCase
{
    /**
     * @return list<array{non-empty-string}>
     */
    public static function provideValidCases()
    {
        return [
            ['http://example.com/'],
            ['https://example.com/path'],
            ['https://example.com:8080/path/to/file.tgz'],
            ['https://user:pass@example.com/path?query=1&other=2#fragment'],
            ['ftp://example.com/dir/'],
            ['http://127.0.0.1/path'],
        ];
    }

    /**
     * @dataProvider provideValidCases
     *
     * @param non-empty-string $url
     *
     * @return void
     */
    public function testValid($url)
    {
        self::assertSame($url, Url::validate($url));
    }

    /**
     * @return list<array{string}>
     */
    public static function provideInvalidCases()
    {
        return [
            [''],
            [' '],
            ['example.com/path'],
            ['//example.com/path'],
            ['http://'],
            ['http://example.com'],
            ['http:///path'],
            ['http://exa mple.com/path'],
            ['not a url'],
        ];
    }

    /**
     * @dataProvider provideInvalidCases
     *
     * @param string $url
     *
     * @return void
     */
    public function testInvalid($url)
    {
        try {
            Url::validate($url);
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidArgument $x) {
            self::assertSame("Invalid argument \$url: '{$url}' is not a valid URL", $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testArgumentNameInMessage()
    {
        try {
            Url::validate('nope', '$config.archiveUrls[security]');
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidArgument $x) {
            self::assertSame("Invalid argument \$config.archiveUrls[security]: 'nope' is not a valid URL", $x->getMessage());
        }
    }
}
