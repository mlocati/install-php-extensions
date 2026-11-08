<?php

namespace IPE\PHPInfo;

use IPE\Exception;

/**
 * The SSL library PHP has been built with.
 */
final class SSLLibrary
{
    /**
     * @public
     * @var string
     */
    const TYPE_OPENSSL = 'openssl';

    /**
     * @public
     * @var string
     */
    const TYPE_LIBRESSL = 'libressl';

    /**
     * @var self::TYPE_*
     */
    private $type;

    /**
     * @var non-empty-string
     *
     * @example '1.0.2q'
     */
    private $version;

    /**
     * @var non-empty-string
     *
     * @example '1.0.2' for '1.0.2q'
     */
    private $baseVersion;

    /**
     * @var int
     *
     * @example 1 for '1.0.2q'
     */
    private $majorVersion;

    /**
     * @var non-empty-string
     *
     * @example '1.0' for '1.0.2q'
     */
    private $majorMinorVersion;

    /**
     * @param self::TYPE_* $type
     * @param string $version
     *
     * @throws \IPE\Exception\InvalidArgument if $version is not valid
     *
     * @example new SSLLibrary(SSLLibrary::TYPE_OPENSSL, '1.0.2q')
     */
    public function __construct($type, $version)
    {
        $matches = null;
        if (!preg_match('/^(?<major>\d+)\.(?<minor>\d+)(?<patch>\.\d+)?/', $version, $matches)) {
            throw new Exception\InvalidArgument("Invalid version of the SSL library: '{$version}'");
        }
        $this->type = $type;
        $this->version = $version;
        $this->majorVersion = (int) $matches['major'];
        $this->majorMinorVersion = "{$matches['major']}.{$matches['minor']}";
        $this->baseVersion = $this->majorMinorVersion . (isset($matches['patch']) ? $matches['patch'] : '');
    }

    /**
     * @param string $text the value of the OPENSSL_VERSION_TEXT PHP constant
     *
     * @return self|null NULL if $text is not recognized
     *
     * @example fromVersionText('OpenSSL 1.0.2q  20 Nov 2018')
     * @example fromVersionText('LibreSSL 2.6.5')
     */
    public static function fromVersionText($text)
    {
        $matches = null;
        if (!preg_match('/^(?<name>OpenSSL|LibreSSL) (?<version>\d+\.\d+\S*)/', $text, $matches)) {
            return null;
        }

        return new self($matches['name'] === 'OpenSSL' ? self::TYPE_OPENSSL : self::TYPE_LIBRESSL, $matches['version']);
    }

    /**
     * @return self::TYPE_*
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return non-empty-string
     *
     * @example '1.0.2q'
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return non-empty-string
     *
     * @example '1.0.2' for '1.0.2q'
     */
    public function getBaseVersion()
    {
        return $this->baseVersion;
    }

    /**
     * @return int
     *
     * @example 1 for '1.0.2q'
     */
    public function getMajorVersion()
    {
        return $this->majorVersion;
    }

    /**
     * @return non-empty-string
     *
     * @example '1.0' for '1.0.2q'
     */
    public function getMajorMinorVersion()
    {
        return $this->majorMinorVersion;
    }
}
