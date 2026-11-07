<?php

namespace IPE\OS;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Service\Container;
use IPE\SystemPackager;
use IPE\Traits;

class Debian implements Posix
{
    use Traits\PosixMachineArchitecture;

    /**
     * @public
     * @var positive-int
     */
    const JESSIE = 8;

    /**
     * @public
     * @var positive-int
     */
    const STRETCH = 9;

    /**
     * @public
     * @var positive-int
     */
    const BUSTER = 10;

    /**
     * @public
     * @var positive-int
     */
    const BULLSEYE = 11;

    /**
     * @public
     * @var positive-int
     */
    const BOOKWORM = 12;

    /**
     * @public
     * @var positive-int
     */
    const TRIXIE = 13;

    /**
     * @public
     * @var positive-int
     */
    const FORKY = 14;

    /**
     * @public
     * @var positive-int
     */
    const DUKE = 15;

    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    /**
     * @var \IPE\Service\Container
     */
    private $app;

    /**
     * @var positive-int
     *
     * @example 13
     */
    private $version;

    /**
     * @var string
     *
     * @example 'trixie'
     */
    private $codename;

    /**
     * @var \IPE\SystemPackager\APT\OnDebian|null
     */
    private $systemPackager = null;

    /**
     * @param positive-int $version eg 13
     * @param non-empty-string $codename eg 'trixie'
     *
     * @throws \IPE\Exception\InvalidArgument
     */
    public function __construct($version, $codename, Filesystem $fs, Container $app)
    {
        $this->fs = $fs;
        $this->app = $app;
        $this->version = $version;
        $this->codename = $codename;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getIdentifier()
     */
    public static function getIdentifier()
    {
        return 'debian';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::parseNumericVersion()
     */
    public static function parseNumericVersion($version)
    {
        return preg_match('/^[1-9]\d*$/D', $version) ? max(1, (int) $version) : null;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS\Posix::fromOSRelease()
     */
    public static function fromOSRelease(array $data, Container $app)
    {
        $version = isset($data['VERSION']) ? $data['VERSION'] : '';
        if ($version === '') {
            throw new Exception\UnsupportedOS("'/etc/os-release' does not define VERSION for Debian");
        }
        $matches = null;
        if (!preg_match('/^(?<version>[1-9]\d{0,3}) \((?<codename>[a-z]+)\)$/D', $version, $matches)) {
            throw new Exception\UnsupportedOS("'/etc/os-release' defines an invalid VERSION for Debian ({$version})");
        }
        try {
            return $app->build(static::class, [
                'version' => (int) $matches['version'],
                'codename' => $matches['codename'],
            ]);
        } catch (Exception\InvalidArgument $x) {
            throw new Exception\UnsupportedOS($x->getMessage() . "\nData from /etc/os-release file:\n" . print_r($data, true));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getFilesystem()
     */
    public function getFilesystem()
    {
        return $this->fs;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getDisplayName()
     *
     * @example 'Debian 13 (Trixie)'
     */
    public function getDisplayName()
    {
        return 'Debian ' . $this->getNumericVersion() . ' (' . ucfirst($this->getCodename() . ')');
    }

    /**
     * @return string
     *
     * @example 'trixie'
     */
    public function getCodename()
    {
        return $this->codename;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getNumericVersion()
     *
     * @example 13
     */
    public function getNumericVersion()
    {
        return $this->version;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS\Posix::getSystemPackager()
     *
     * @return \IPE\SystemPackager\APT\OnDebian
     */
    public function getSystemPackager()
    {
        if ($this->systemPackager === null) {
            $this->systemPackager = $this->app->build(SystemPackager\APT\OnDebian::class, ['os' => $this]);
        }

        return $this->systemPackager;
    }
}
