<?php

namespace IPE\Test\Service;

use IPE\Exception;
use IPE\Service\BasicXmlParser;
use IPE\Service\BasicXmlParser\Element;
use IPE\Test\Support\TestCase;

final class BasicXmlParserTreeTest extends TestCase
{
    /**
     * @return void
     */
    public function testTree()
    {
        $xml = <<<'EOT'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE package SYSTEM "http://pear.php.net/dtd/package-1.0">
<!-- a comment -->
<root version="2.0" xmlns="http://example.com/ns">
    <name>  spaced  </name>
    <empty />
    <item id="1" kind='a&amp;b'>first</item>
    <item id="2">second<![CDATA[ <raw> ]]></item>
    <group>
        <item id="3" />
        text in group
    </group>
    <escaped>&lt;tag&gt; &amp; &#39;quote&#39;</escaped>
</root>
EOT;
        $root = (new BasicXmlParser())->parse($xml);
        self::assertSame('root', $root->getName());
        self::assertSame(['version' => '2.0', 'xmlns' => 'http://example.com/ns'], $root->getAttributes());
        self::assertSame('2.0', $root->getAttribute('version'));
        self::assertSame('', $root->getAttribute('missing'));
        self::assertSame('x', $root->getAttribute('missing', 'x'));
        self::assertTrue($root->hasAttribute('version'));
        self::assertFalse($root->hasAttribute('missing'));
        self::assertSame('', $root->getText());
        self::assertCount(6, $root->getChildren());

        self::assertSame('spaced', $root->getChildText('name'));
        $name = $root->getChild('name');
        self::assertInstanceOf(Element::class, $name);
        self::assertSame('  spaced  ', $name->getText(false));
        self::assertSame('', $root->getChildText('missing'));
        self::assertSame('default', $root->getChildText('missing', 'default'));
        self::assertNull($root->getChild('missing'));

        $empty = $root->getChild('empty');
        self::assertInstanceOf(Element::class, $empty);
        self::assertSame([], $empty->getAttributes());
        self::assertSame('', $empty->getText());
        self::assertSame([], $empty->getChildren());

        $items = $root->getChildrenByName('item');
        self::assertCount(2, $items);
        self::assertSame(['id' => '1', 'kind' => 'a&b'], $items[0]->getAttributes());
        self::assertSame('first', $items[0]->getText());
        self::assertSame('second <raw> ', $items[1]->getText(false));
        self::assertSame('second <raw>', $items[1]->getText());

        $group = $root->getChild('group');
        self::assertInstanceOf(Element::class, $group);
        self::assertSame('text in group', $group->getText());
        $groupItem = $group->getChild('item');
        self::assertInstanceOf(Element::class, $groupItem);
        self::assertSame('3', $groupItem->getAttribute('id'));

        self::assertSame("<tag> & 'quote'", $root->getChildText('escaped'));
    }

    /**
     * @return void
     */
    public function testFind()
    {
        $xml = '<a><b><c>1</c><c>2</c></b><b><c>3</c></b><d/></a>';
        $root = (new BasicXmlParser())->parse($xml);
        $all = $root->findAll('b/c');
        self::assertCount(3, $all);
        self::assertSame(['1', '2', '3'], array_map(static function (Element $e) { return $e->getText(); }, $all));
        $first = $root->find('b/c');
        self::assertInstanceOf(Element::class, $first);
        self::assertSame('1', $first->getText());
        $first = $root->find('/b/c/');
        self::assertInstanceOf(Element::class, $first);
        self::assertSame('1', $first->getText());
        self::assertNull($root->find('b/x'));
        self::assertSame([], $root->findAll('x'));
        self::assertSame([], $root->findAll(''));
        $d = $root->getChild('d');
        self::assertInstanceOf(Element::class, $d);
        self::assertSame(
            [
                'name' => 'd',
                'attributes' => [],
                'text' => '',
                'children' => [],
            ],
            $d->toArray()
        );
        self::assertSame('a', $root->toArray()['name']);
        self::assertCount(3, $root->toArray()['children']);
    }

    /**
     * @return array{string}[]
     */
    public static function provideInvalidCases()
    {
        return [
            [''],
            ['   '],
            ['just text'],
            ['<a></a><b></b>'],
            ['<a><b></a>'],
            ['</a>'],
            ['<a>'],
        ];
    }

    /**
     * @dataProvider provideInvalidCases
     *
     * @param string $xml
     *
     * @return void
     */
    public function testInvalid($xml)
    {
        try {
            (new BasicXmlParser())->parse($xml);
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidArgument $x) {
            self::assertNotSame('', $x->getMessage());
        }
    }
}
