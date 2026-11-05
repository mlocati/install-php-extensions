<?php

namespace IPE\Service\BasicXmlParser;

/**
 * An XML element parsed by BasicXmlParser.
 */
final class Element
{
    /**
     * @var non-empty-string
     */
    private $name;

    /**
     * @var array<string, string>
     */
    private $attributes;

    /**
     * @var string
     */
    private $text = '';

    /**
     * @var list<\IPE\Service\BasicXmlParser\Element>
     */
    private $children = [];

    /**
     * @param non-empty-string $name
     * @param array<string, string> $attributes
     */
    public function __construct($name, array $attributes = [])
    {
        $this->name = $name;
        $this->attributes = $attributes;
    }

    /**
     * @return non-empty-string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array<string, string>
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * @param string $name
     * @param string $default
     *
     * @return string
     */
    public function getAttribute($name, $default = '')
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : $default;
    }

    /**
     * Get the text directly contained in this element (not the one of its children).
     *
     * @param bool $trim
     *
     * @return string
     */
    public function getText($trim = true)
    {
        return $trim ? trim($this->text) : $this->text;
    }

    /**
     * @return \IPE\Service\BasicXmlParser\Element[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param string $name
     *
     * @return \IPE\Service\BasicXmlParser\Element[]
     */
    public function getChildrenByName($name)
    {
        return array_values(array_filter(
            $this->children,
            static function (Element $child) use ($name) {
                return $child->getName() === $name;
            }
        ));
    }

    /**
     * Get the first child with a specific name.
     *
     * @param string $name
     *
     * @return \IPE\Service\BasicXmlParser\Element|null
     */
    public function getChild($name)
    {
        foreach ($this->children as $child) {
            if ($child->getName() === $name) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Get the trimmed text of the first child with a specific name.
     *
     * @param string $name
     * @param string $default
     *
     * @return string
     */
    public function getChildText($name, $default = '')
    {
        $child = $this->getChild($name);

        return $child === null ? $default : $child->getText();
    }

    /**
     * Get the first descendant at a path like 'dependencies/required/php'.
     *
     * @param string $path
     *
     * @return \IPE\Service\BasicXmlParser\Element|null
     */
    public function find($path)
    {
        $all = $this->findAll($path);

        return $all === [] ? null : $all[0];
    }

    /**
     * Get all the descendants at a path like 'dependencies/required/extension'.
     *
     * @param string $path
     *
     * @return \IPE\Service\BasicXmlParser\Element[]
     */
    public function findAll($path)
    {
        $current = [$this];
        foreach (explode('/', trim($path, '/')) as $name) {
            if ($name === '') {
                continue;
            }
            $next = [];
            foreach ($current as $element) {
                $next = array_merge($next, $element->getChildrenByName($name));
            }
            $current = $next;
        }

        return $current === [$this] ? [] : $current;
    }

    /**
     * @return array{name: non-empty-string, attributes: array<string, string>, text: string, children: list<array<string, mixed>>}
     */
    public function toArray()
    {
        return [
            'name' => $this->name,
            'attributes' => $this->attributes,
            'text' => $this->getText(),
            'children' => array_map(
                static function (Element $child) {
                    return $child->toArray();
                },
                $this->children
            ),
        ];
    }

    /**
     * @internal used by the parser while building the tree
     *
     * @return $this
     */
    public function addChild(self $child)
    {
        $this->children[] = $child;

        return $this;
    }

    /**
     * @internal used by the parser while building the tree
     *
     * @param string $text
     *
     * @return $this
     */
    public function appendText($text)
    {
        $this->text .= $text;

        return $this;
    }
}
