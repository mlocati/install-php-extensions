<?php

namespace IPE\OS;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Service\Container;
use IPE\SystemPackager;
use IPE\Traits;

class Alpine implements Posix
{
    use Traits\PosixMachineArchitecture;

    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    /**
     * @var \IPE\Service\Container
     */
    private $app;

    /**
     * @var string
     *
     * @example '3.22.4'
     */
    private $version;

    /**
     * @var positive-int
     *
     * @example 322
     */
    private $numericVersion;

    /**
     * @var \IPE\SystemPackager\APK|null
     */
    private $systemPackager = null;

    /**
     * @param non-empty-string $version eg '3.22.4'
     *
     * @throws \IPE\Exception\InvalidArgument
     */
    public function __construct($version, Filesystem $fs, Container $app)
    {
        $this->fs = $fs;
        $this->app = $app;
        if (!preg_match('/^[1-9]\d*\.\d\d?(\.\d+)*$/D', $version)) {
            throw new Exception\InvalidArgument('Invalid $version');
        }
        list($majorVersion, $minorVersion) = explode('.', $version);
        $numericVersion = ((int) $majorVersion) * 100 + (int) $minorVersion;
        if ($numericVersion <= 0) {
            throw new Exception\InvalidArgument('Invalid $version');
        }
        $this->version = $version;
        $this->numericVersion = $numericVersion;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getIdentifier()
     */
    public static function getIdentifier()
    {
        return 'alpine';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::parseNumericVersion()
     */
    public static function parseNumericVersion($version)
    {
        if (!preg_match('/^[1-9]\d*\.\d\d?$/D', $version)) {
            return null;
        }
        list($majorVersion, $minorVersion) = explode('.', $version);

        return max(1, (int) $majorVersion * 100 + (int) $minorVersion);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS\Posix::fromOSRelease()
     */
    public static function fromOSRelease(array $data, Container $app)
    {
        try {
            return $app->build(static::class, [
                'version' => isset($data['VERSION_ID']) ? $data['VERSION_ID'] : '',
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
     * @example 'Alpine 3.22'
     */
    public function getDisplayName()
    {
        return 'Alpine ' . $this->getVersion();
    }

    /**
     * @return string
     *
     * @example '3.22'
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getNumericVersion()
     *
     * @example 322
     */
    public function getNumericVersion()
    {
        return $this->numericVersion;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS\Posix::getSystemPackager()
     *
     * @return \IPE\SystemPackager\APK
     */
    public function getSystemPackager()
    {
        if ($this->systemPackager === null) {
            $this->systemPackager = $this->app->build(SystemPackager\APK::class, ['os' => $this]);
        }

        return $this->systemPackager;
    }
}
