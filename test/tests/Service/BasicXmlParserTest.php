<?php

namespace IPE\Test\Service;

use IPE\Exception;
use IPE\Service\BasicXmlParser;
use IPE\Test\Support\TestCase;

final class BasicXmlParserTest extends TestCase
{
    /**
     * @return list<array{string}>
     */
    public static function provideValidCases()
    {
        $cases = [];
        foreach (scandir(IPE_ROOT_DIR . '/test/assets/basic-xml-parser/valid') as $file) {
            if (preg_match('/.\.xml$/', $file)) {
                $cases[] = [basename($file, '.xml')];
            }
        }

        return $cases;
    }

    /**
     * @dataProvider provideValidCases
     *
     * @param non-empty-string $case
     *
     * @return void
     */
    public function testValidCase($case)
    {
        $filesBase = IPE_ROOT_DIR . '/test/assets/basic-xml-parser/valid/' . $case;
        $xml = file_get_contents($filesBase . '.xml');
        self::assertIsString($xml);
        $txt = $filesBase . '.txt';
        if (!is_file($txt)) {
            $expectedTrimmed = false;
        } else {
            $expectedTrimmed = file_get_contents($txt);
            $parsed = [];
            $parser = new BasicXmlParser();
            $parser->listElementsWithTextContent(
                $xml,
                static function (array $elements, $text) use (&$parsed) {
                    $parsed[] = implode('.', $elements) . ': ' . $text . "\n";
                }
            );
            $actual = implode('', $parsed);
            self::assertSame($expectedTrimmed, $actual, 'Trimmed');
        }
        $txt = $filesBase . '_not-trimmed.txt';
        if (!is_file($txt)) {
            $expectedNotTrimmed = false;
        } else {
            $expectedNotTrimmed = file_get_contents($txt);
            $parsed = [];
            $parser = new BasicXmlParser();
            $parser->listElementsWithTextContent(
                $xml,
                static function (array $elements, $text) use (&$parsed) {
                    $parsed[] = implode('.', $elements) . ': ' . $text . "\n";
                },
                false
            );
            $actual = implode('', $parsed);
            self::assertSame($expectedNotTrimmed, $actual, 'Not trimmed');
        }
        self::assertTrue($expectedTrimmed !== false || $expectedNotTrimmed !== false, 'No expected output provided');
    }

    /**
     * @return list<array{string}>
     */
    public static function provideInvalidValidCases()
    {
        $cases = [];
        foreach (scandir(IPE_ROOT_DIR . '/test/assets/basic-xml-parser/invalid') as $file) {
            if (preg_match('/.\.txt$/', $file)) {
                $cases[] = [basename($file, '.txt')];
            }
        }

        return $cases;
    }

    /**
     * @dataProvider provideInvalidValidCases
     *
     * @param non-empty-string $case
     *
     * @return void
     */
    public function testInvalidCase($case)
    {
        $xml = file_get_contents(IPE_ROOT_DIR . '/test/assets/basic-xml-parser/invalid/' . $case . '.txt');
        self::assertIsString($xml);
        $parser = new BasicXmlParser();
        try {
            $parser->listElementsWithTextContent($xml, static function (array $elements, $text) {});
            self::fail('Expected exception not thrown');
        } catch (\Exception $x) {
            self::assertInstanceOf(Exception\InvalidArgument::class, $x);
        }
    }
}
