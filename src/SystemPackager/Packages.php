<?php

namespace IPE\SystemPackager;

/**
 * Lists of system packages: the persistent ones are kept after the installation, the volatile ones are removed.
 */
final class Packages
{
    /**
     * @var list<non-empty-string>
     */
    private $persistent;

    /**
     * @var list<non-empty-string>
     */
    private $volatile;

    /**
     * @param list<non-empty-string> $persistent
     * @param list<non-empty-string> $volatile
     */
    public function __construct(array $persistent = [], array $volatile = [])
    {
        $this->persistent = self::normalize($persistent);
        $this->volatile = self::normalize($volatile);
    }

    /**
     * @return list<non-empty-string>
     */
    public function getPersistent()
    {
        return $this->persistent;
    }

    /**
     * @return list<non-empty-string>
     */
    public function getVolatile()
    {
        return $this->volatile;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return $this->persistent === [] && $this->volatile === [];
    }

    /**
     * @return static
     */
    public function merge(self $other)
    {
        return new self(
            array_merge($this->persistent, $other->persistent),
            array_merge($this->volatile, $other->volatile)
        );
    }

    /**
     * @param list<non-empty-string> $volatile
     *
     * @return static
     */
    public function withVolatile(array $volatile)
    {
        return new self($this->persistent, array_merge($this->volatile, $volatile));
    }

    /**
     * @param list<non-empty-string> $packages
     *
     * @return list<non-empty-string>
     */
    private static function normalize(array $packages)
    {
        return array_values(array_unique($packages));
    }
}
