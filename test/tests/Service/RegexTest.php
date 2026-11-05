<?php

namespace IPE\Test\Service;

use IPE\Exception;
use IPE\Service\Regex;
use IPE\Test\Support\TestCase;

final class RegexTest extends TestCase
{
    /**
     * @return void
     */
    public function testFailureThrowsAnIPEException()
    {
        $invalidUTF8 = "\xff";
        $calls = [
            'preg_replace' => static function () use ($invalidUTF8) {
                Regex::replace('/a/u', '', $invalidUTF8);
            },
            'preg_replace_callback' => static function () use ($invalidUTF8) {
                Regex::replaceCallback('/a/u', static function () {
                    return '';
                }, $invalidUTF8);
            },
            'preg_split' => static function () use ($invalidUTF8) {
                Regex::split('/a/u', $invalidUTF8);
            },
        ];
        foreach ($calls as $function => $call) {
            try {
                $call();
                self::fail("{$function}: expected exception not thrown");
            } catch (Exception\Regex $x) {
                self::assertStringStartsWith("{$function} failed: ", $x->getMessage());
            }
        }
    }
}
