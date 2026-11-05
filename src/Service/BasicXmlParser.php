<?php

namespace IPE\Service;

use IPE\Exception;
use IPE\Service\BasicXmlParser\Element;

/**
 * A very basic and not fully reliable XML parser, to be used since the DOM/SimpleXML extensions may not be available.
 */
final class BasicXmlParser
{
    /**
     * Parse an XML document into a tree of elements.
     *
     * @param string $xml
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return \IPE\Service\BasicXmlParser\Element the root element
     */
    public function parse($xml)
    {
        if ($xml === '') {
            throw new Exception\InvalidArgument('Empty XML');
        }
        // Let's take out the CDATA while we analyze the XML (we'll add them back later)
        list($xml, $cdataMap) = $this->replaceCDATASections($xml);
        // Strip out comments
        $xml = Regex::replace('/<!--.*?-->/s', '', $xml);
        // Strip out processing instructions
        $xml = Regex::replace('/<\?.*?\?>*/s', '', $xml);
        // Strip out the document type declaration
        $xml = Regex::replace('/<!DOCTYPE[^>]*>/i', '', $xml);
        $root = null;
        $stack = [];
        $tokens = null;
        preg_match_all(
            '#</?([\w:\-]+)[^>]*>|([^<]+)#s',
            $xml,
            $tokens,
            PREG_SET_ORDER
        );
        foreach ($tokens as $t) {
            if (isset($t[1]) && $t[1] !== '') {
                // element
                $rawTag = $t[0]; // eg. "<name>", "</name>", "<file name="x" />"
                $tag = $t[1]; // eg. "name"
                $isClosing = $rawTag[1] === '/';
                if (!$isClosing) {
                    $element = new Element($tag, $this->parseAttributes($rawTag, $cdataMap));
                    if ($stack === []) {
                        if ($root !== null) {
                            throw new Exception\InvalidArgument('Malformed XML: multiple root elements');
                        }
                        $root = $element;
                    } else {
                        $stack[count($stack) - 1]->addChild($element);
                    }
                    $isSelfClosing = substr(rtrim($rawTag), -2) === '/>';
                    if (!$isSelfClosing) {
                        $stack[] = $element;
                    }
                } else {
                    if ($stack === []) {
                        throw new Exception\InvalidArgument('Closing XML element without corresponding opening element');
                    }
                    $lastElement = array_pop($stack);
                    if ($lastElement->getName() !== $tag) {
                        throw new Exception\InvalidArgument('Malformed XML structure: mismatched closing element');
                    }
                }
            } elseif (isset($t[2]) && $stack !== []) {
                $stack[count($stack) - 1]->appendText($this->decodeText($t[2], $cdataMap));
            }
        }
        if ($stack !== []) {
            throw new Exception\InvalidArgument('Malformed XML: some elements were not closed');
        }
        if ($root === null) {
            throw new Exception\InvalidArgument('Malformed XML: no root element found');
        }

        return $root;
    }

    /**
     * Call a function for every element that directly contains text (children are visited before their parent).
     *
     * @param string $xml
     * @param \Closure(non-empty-list<non-empty-string>, non-empty-string): void $visitor receives the path of the element and its text content
     * @param bool $trimValues
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return void
     */
    public function listElementsWithTextContent($xml, \Closure $visitor, $trimValues = true)
    {
        $this->visitElementsWithTextContent($this->parse($xml), [], $visitor, $trimValues);
    }

    /**
     * @param non-empty-string[] $parentPath
     * @param bool $trimValues
     *
     * @return void
     */
    private function visitElementsWithTextContent(Element $element, array $parentPath, \Closure $visitor, $trimValues)
    {
        $path = array_merge($parentPath, [$element->getName()]);
        foreach ($element->getChildren() as $child) {
            $this->visitElementsWithTextContent($child, $path, $visitor, $trimValues);
        }
        if ($element->getText() !== '') {
            $visitor($path, $element->getText($trimValues));
        }
    }

    /**
     * @param string $rawTag
     * @param array<string, string> $cdataMap
     *
     * @return array<string, string>
     */
    private function parseAttributes($rawTag, array $cdataMap)
    {
        $result = [];
        $matches = null;
        preg_match_all('/([\w:\-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/', $rawTag, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $value = isset($match[3]) ? $match[3] : (isset($match[2]) ? $match[2] : '');
            $result[$match[1]] = $this->decodeText($value, $cdataMap);
        }

        return $result;
    }

    /**
     * @param string $text
     * @param array<string, string> $cdataMap
     *
     * @return string
     */
    private function decodeText($text, array $cdataMap)
    {
        $text = html_entity_decode($text, \ENT_QUOTES | \ENT_XML1);

        return $cdataMap === [] ? $text : strtr($text, $cdataMap);
    }

    /**
     * @param non-empty-string $xml
     *
     * @throws \IPE\Exception\Regex
     *
     * @return array{string, array<string, string>} the XML with placeholders and the map from placeholders to the CDATA contents
     */
    private function replaceCDATASections($xml)
    {
        $phPrefix = '_CDATA';
        for ($i = 1; strpos($xml, $phPrefix) !== false; $i++) {
            $phPrefix = "_CDATA{$i}";
        }
        $cdataMap = [];
        $index = 0;
        $xml = Regex::replaceCallback(
            '#<!\[CDATA\[(?<content>.*?)\]\]>#s',
            static function (array $matches) use ($phPrefix, &$cdataMap, &$index) {
                $ph = array_search($matches['content'], $cdataMap, true);
                if ($ph === false) {
                    $ph = "{$phPrefix}_{$index}_";
                    $index++;
                    $cdataMap[$ph] = $matches['content'];
                }

                return $ph;
            },
            $xml
        );

        return [$xml, $cdataMap];
    }
}
