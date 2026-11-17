<?php

namespace IPE\OS\Posix;

use IPE\Exception;
use IPE\Filesystem;
use IPE\OS\Posix;
use IPE\Service\Container;
use IPE\SystemPackager;
use IPE\Traits;

/**
 * The systems that use RPM packages and dnf (Enterprise Linux, Fedora, Amazon Linux).
 */
abstract class RHELLike implements Posix
{
    use Traits\PosixMachineArchitecture;

    /**
     * The oldest supported major version.
     *
     * @var positive-int
     */
    const MINIMUM_VERSION = 1;

    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    /**
     * @var \IPE\Service\Container
     */
    private $app;

    /**
     * @var non-empty-string
     *
     * @example 'AlmaLinux'
     */
    private $name;

    /**
     * @var non-empty-string
     *
     * @example '9.8'
     */
    private $version;

    /**
     * @var positive-int
     *
     * @example 9
     */
    private $numericVersion;

    /**
     * @var class-string<\IPE\SystemPackager\DNF>
     */
    private $systemPackagerClass;

    /**
     * @var \IPE\SystemPackager\DNF|null
     */
    private $systemPackager = null;

    /**
     * @param non-empty-string $name eg 'AlmaLinux'
     * @param non-empty-string $version eg '9.8'
     * @param class-string<\IPE\SystemPackager\DNF> $systemPackagerClass see SystemPackager\DNF::getImplementationClass()
     *
     * @throws \IPE\Exception\InvalidArgument
     */
    public function __construct($name, $version, $systemPackagerClass, Filesystem $fs, Container $app)
    {
        $this->fs = $fs;
        $this->app = $app;
        if (!is_subclass_of($systemPackagerClass, SystemPackager\DNF::class)) {
            throw new Exception\InvalidArgument('Invalid $systemPackagerClass');
        }
        $this->systemPackagerClass = $systemPackagerClass;
        $this->name = $name;
        $this->version = $version;
        $this->numericVersion = self::parseVersionID($name, $version);
    }

    /**
     * @param non-empty-string $name eg 'AlmaLinux'
     * @param string $version eg '9.8'
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return positive-int the major version
     */
    private static function parseVersionID($name, $version)
    {
        $match = null;
        if (!preg_match('/^(?<major>[1-9]\d{0,3})(?:\.\d+)?$/D', $version, $match)) {
            throw new Exception\InvalidArgument('Invalid $version');
        }
        $numericVersion = max(1, (int) $match['major']);
        if ($numericVersion < static::MINIMUM_VERSION) {
            throw new Exception\InvalidArgument("{$name} {$version} is not supported");
        }

        return $numericVersion;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::parseNumericVersion()
     */
    public static function parseNumericVersion($version)
    {
        return preg_match('/^[1-9]\d{0,3}$/D', $version) ? max(1, (int) $version) : null;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS\Posix::fromOSRelease()
     */
    public static function fromOSRelease(array $data, Container $app)
    {
        $name = isset($data['NAME']) && $data['NAME'] !== '' ? $data['NAME'] : static::getIdentifier();
        $version = isset($data['VERSION_ID']) ? $data['VERSION_ID'] : '';
        try {
            self::parseVersionID($name, $version);
        } catch (Exception\InvalidArgument $x) {
            throw new Exception\UnsupportedOS($x->getMessage() . "\nData from /etc/os-release file:\n" . print_r($data, true));
        }
        try {
            $systemPackagerClass = SystemPackager\DNF::getImplementationClass($app->make(Filesystem::class));
        } catch (Exception\SystemPackager $x) {
            throw new Exception\UnsupportedOS($x->getMessage());
        }
        try {
            return $app->build(static::class, [
                'name' => $name,
                'version' => $version,
                'systemPackagerClass' => $systemPackagerClass,
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
     * @example 'AlmaLinux 9.8'
     */
    public function getDisplayName()
    {
        return "{$this->name} {$this->version}";
    }

    /**
     * @return non-empty-string
     *
     * @example '9.8'
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
     * @example 9
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
     * @return \IPE\SystemPackager\DNF
     */
    public function getSystemPackager()
    {
        if ($this->systemPackager === null) {
            $this->systemPackager = $this->app->build($this->systemPackagerClass, ['os' => $this]);
        }

        return $this->systemPackager;
    }
}
