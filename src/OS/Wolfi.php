<?php

namespace IPE\OS;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Service\Container;
use IPE\SystemPackager;
use IPE\Traits;

/**
 * Wolfi is a rolling release: the VERSION_ID of /etc/os-release is a date that has never changed so far.
 */
class Wolfi implements Posix\AlpineLike
{
    use Traits\OSArchitecture\Posix;

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
     * @example 20230201
     */
    private $numericVersion;

    /**
     * @var \IPE\SystemPackager\APK|null
     */
    private $systemPackager = null;

    /**
     * @param string $version eg '20230201'
     *
     * @throws \IPE\Exception\InvalidArgument
     */
    public function __construct($version, Filesystem $fs, Container $app)
    {
        $this->fs = $fs;
        $this->app = $app;
        $numericVersion = static::parseNumericVersion($version);
        if ($numericVersion === null) {
            throw new Exception\InvalidArgument('Invalid $version');
        }
        $this->numericVersion = $numericVersion;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getIdentifier()
     */
    public static function getIdentifier()
    {
        return 'wolfi';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::parseNumericVersion()
     */
    public static function parseNumericVersion($version)
    {
        return preg_match('/^[1-9]\d{7}$/D', $version) ? max(1, (int) $version) : null;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS\Posix\WithOSRelease::fromOSRelease()
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
     */
    public function getDisplayName()
    {
        return 'Wolfi';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getNumericVersion()
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
