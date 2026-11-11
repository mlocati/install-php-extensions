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
     * Install the extensions from the packages of the system packager when available, otherwise compile them.
     *
     * @public
     * @var non-empty-string
     */
    const SYSTEMPACKAGES_AUTO = 'auto';

    /**
     * Never install the extensions from the packages of the system packager.
     *
     * @public
     * @var non-empty-string
     */
    const SYSTEMPACKAGES_NEVER = 'never';

    /**
     * Install the extensions only from the packages of the system packager.
     *
     * @public
     * @var non-empty-string
     */
    const SYSTEMPACKAGES_ONLY = 'only';

    /**
     * @var bool
     */
    private $ignoreUnknownExtensions = false;

    /**
     * @var non-empty-string
     *
     * @see \IPE\Extension\Asked\Resolver\Options::SYSTEMPACKAGES_AUTO
     * @see \IPE\Extension\Asked\Resolver\Options::SYSTEMPACKAGES_NEVER
     * @see \IPE\Extension\Asked\Resolver\Options::SYSTEMPACKAGES_ONLY
     */
    private $systemPackages = self::SYSTEMPACKAGES_AUTO;

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

    /**
     * Set when the extensions should be installed from the packages of the system packager (used only if PHP has been installed by a system package).
     *
     * @param string $value
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return static
     *
     * @see \IPE\Extension\Asked\Resolver\Options::SYSTEMPACKAGES_AUTO
     * @see \IPE\Extension\Asked\Resolver\Options::SYSTEMPACKAGES_NEVER
     * @see \IPE\Extension\Asked\Resolver\Options::SYSTEMPACKAGES_ONLY
     */
    public function withSystemPackages($value)
    {
        if (!in_array($value, [self::SYSTEMPACKAGES_AUTO, self::SYSTEMPACKAGES_NEVER, self::SYSTEMPACKAGES_ONLY], true)) {
            throw new Exception\InvalidArgument('Invalid $value');
        }
        $result = clone $this;
        $result->systemPackages = $value;

        return $result;
    }

    /**
     * @return non-empty-string
     *
     * @see \IPE\Extension\Asked\Resolver\Options::SYSTEMPACKAGES_AUTO
     * @see \IPE\Extension\Asked\Resolver\Options::SYSTEMPACKAGES_NEVER
     * @see \IPE\Extension\Asked\Resolver\Options::SYSTEMPACKAGES_ONLY
     */
    public function getSystemPackages()
    {
        return $this->systemPackages;
    }
}
