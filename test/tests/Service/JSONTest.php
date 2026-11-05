<?php

namespace IPE\Test\Service;

use IPE\Exception;
use IPE\Service\JSON;
use IPE\Test\Support\TestCase;

final class JSONTest extends TestCase
{
    /**
     * @return void
     */
    public function testEncode()
    {
        self::assertSame('{"url":"https://example.com/","list":[1,2],"empty":{}}', JSON::encode(['url' => 'https://example.com/', 'list' => [1, 2], 'empty' => (object) []]));
        self::assertSame("{\n    \"a\": [\n        1\n    ]\n}", JSON::encode(['a' => [1]], true));
    }

    /**
     * @return void
     */
    public function testDecode()
    {
        self::assertNull(JSON::decode('null'));
        self::assertSame(['a' => [1]], JSON::decode('{"a":[1]}', true));
        $object = JSON::decode('{"a":1}');
        self::assertInstanceOf('stdClass', $object);
        self::assertSame(1, $object->a);
    }

    /**
     * @return void
     */
    public function testFailureThrowsAnIPEException()
    {
        $calls = [
            ['json_encode failed: Malformed UTF-8 characters, possibly incorrectly encoded', static function () {
                JSON::encode(['a' => "\xff"]);
            }],
            ['json_encode failed: Inf and NaN cannot be JSON encoded', static function () {
                JSON::encode(NAN);
            }],
            ['json_decode failed: Syntax error', static function () {
                JSON::decode('{');
            }],
            ['json_decode failed: Syntax error', static function () {
                JSON::decode('');
            }],
        ];
        foreach ($calls as $index => $call) {
            try {
                $call[1]();
                self::fail("Call #{$index}: expected exception not thrown");
            } catch (Exception\JSON $x) {
                // PHP 8.6 adds the location of syntax errors (for example "Syntax error near location 1:2")
                self::assertStringStartsWith($call[0], $x->getMessage());
            }
        }
    }
}
