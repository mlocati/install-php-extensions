<?php

namespace IPE\Extension\Asked\Resolver;

use IPE\Exception;

class Options
{
    /**
     * @public
     * @var non-empty-string
     */
    const ONUNSUPPORTED_ABORT = 'abort';

    /**
     * @public
     * @var non-empty-string
     */
    const ONUNSUPPORTED_FORCE = 'force';

    /**
     * @public
     * @var non-empty-string
     */
    const ONUNSUPPORTED_SKIP = 'skip';

    /**
     * @var bool
     */
    private $ignoreUnknownExtensions = false;

    /**
     * @var non-empty-string
     *
     * @see \IPE\Extension\Asked\Resolver\Options::ONUNSUPPORTED_ABORT
     * @see \IPE\Extension\Asked\Resolver\Options::ONUNSUPPORTED_FORCE
     * @see \IPE\Extension\Asked\Resolver\Options::ONUNSUPPORTED_SKIP
     */
    private $onUnsupportedExtensions = self::ONUNSUPPORTED_ABORT;

    /**
     * @param bool $value
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return static
     */
    public function withIgnoreUnknownExtensions($value)
    {
        $result = clone $this;
        $result->ignoreUnknownExtensions = $value;

        return $result;
    }

    /**
     * @return bool
     */
    public function isIgnoreUnknownExtensions()
    {
        return $this->ignoreUnknownExtensions;
    }

    /**
     * @param string $value
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return static
     *
     * @see \IPE\Extension\Asked\Resolver\Options::ONUNSUPPORTED_ABORT
     * @see \IPE\Extension\Asked\Resolver\Options::ONUNSUPPORTED_FORCE
     * @see \IPE\Extension\Asked\Resolver\Options::ONUNSUPPORTED_SKIP
     */
    public function withOnUnsupportedExtensions($value)
    {
        if (!in_array($value, [self::ONUNSUPPORTED_ABORT, self::ONUNSUPPORTED_FORCE, self::ONUNSUPPORTED_SKIP], true)) {
            throw new Exception\InvalidArgument('Invalid $value');
        }
        $result = clone $this;
        $result->onUnsupportedExtensions = $value;

        return $result;
    }

    /**
     * @return non-empty-string
     *
     * @see \IPE\Extension\Asked\Resolver\Options::ONUNSUPPORTED_ABORT
     * @see \IPE\Extension\Asked\Resolver\Options::ONUNSUPPORTED_FORCE
     * @see \IPE\Extension\Asked\Resolver\Options::ONUNSUPPORTED_SKIP
     */
    public function getOnUnsupportedExtensions()
    {
        return $this->onUnsupportedExtensions;
    }
}
