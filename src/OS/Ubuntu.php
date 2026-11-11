<?php

namespace IPE\OS;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Service\Container;
use IPE\SystemPackager;
use IPE\Traits;

class Ubuntu implements Posix
{
    use Traits\PosixMachineArchitecture;

    /**
     * Lucid Lynx.
     *
     * @public
     * @var positive-int
     */
    const LUCID = 1004;

    /**
     * Maverick Meerkat.
     *
     * @public
     * @var positive-int
     */
    const MAVERICK = 1010;

    /**
     * Natty Narwhal.
     *
     * @public
     * @var positive-int
     */
    const NATTY = 1104;

    /**
     * Oneiric Ocelot.
     *
     * @public
     * @var positive-int
     */
    const ONEIRIC = 1110;

    /**
     * Precise Pangolin.
     *
     * @public
     * @var positive-int
     */
    const PRECISE = 1204;

    /**
     * Quantal Quetzal.
     *
     * @public
     * @var positive-int
     */
    const QUANTAL = 1210;

    /**
     * Raring Ringtail.
     *
     * @public
     * @var positive-int
     */
    const RARING = 1304;

    /**
     * Saucy Salamander.
     *
     * @public
     * @var positive-int
     */
    const SAUCY = 1310;

    /**
     * Trusty Tahr.
     *
     * @public
     * @var positive-int
     */
    const TRUSTY = 1404;

    /**
     * Utopic Unicorn.
     *
     * @public
     * @var positive-int
     */
    const UTOPIC = 1410;

    /**
     * Vivid Vervet.
     *
     * @public
     * @var positive-int
     */
    const VIVID = 1504;

    /**
     * Wily Werewolf.
     *
     * @public
     * @var positive-int
     */
    const WILY = 1510;

    /**
     * Xenial Xerus.
     *
     * @public
     * @var positive-int
     */
    const XENIAL = 1604;

    /**
     * Yakkety Yak.
     *
     * @public
     * @var positive-int
     */
    const YAKKETY = 1610;

    /**
     * Zesty Zapus.
     *
     * @public
     * @var positive-int
     */
    const ZESTY = 1704;

    /**
     * Artful Aardvark.
     *
     * @public
     * @var positive-int
     */
    const ARTFUL = 1710;

    /**
     * Bionic Beaver.
     *
     * @public
     * @var positive-int
     */
    const BIONIC = 1804;

    /**
     * Cosmic Cuttlefish.
     *
     * @public
     * @var positive-int
     */
    const COSMIC = 1810;

    /**
     * Disco Dingo.
     *
     * @public
     * @var positive-int
     */
    const DISCO = 1904;

    /**
     * Eoan Ermine.
     *
     * @public
     * @var positive-int
     */
    const EOAN = 1910;

    /**
     * Focal Fossa.
     *
     * @public
     * @var positive-int
     */
    const FOCAL = 2004;

    /**
     * Groovy Gorilla.
     *
     * @public
     * @var positive-int
     */
    const GROOVY = 2010;

    /**
     * Hirsute Hippo.
     *
     * @public
     * @var positive-int
     */
    const HIRSUTE = 2104;

    /**
     * Impish Indri.
     *
     * @public
     * @var positive-int
     */
    const IMPISH = 2110;

    /**
     * Jammy Jellyfish.
     *
     * @public
     * @var positive-int
     */
    const JAMMY = 2204;

    /**
     * Kinetic Kudu.
     *
     * @public
     * @var positive-int
     */
    const KINETIC = 2210;

    /**
     * Lunar Lobster.
     *
     * @public
     * @var positive-int
     */
    const LUNAR = 2304;

    /**
     * Mantic Minotaur.
     *
     * @public
     * @var positive-int
     */
    const MANTIC = 2310;

    /**
     * Noble Numbat.
     *
     * @public
     * @var positive-int
     */
    const NOBLE = 2404;

    /**
     * Oracular Oriole.
     *
     * @public
     * @var positive-int
     */
    const ORACULAR = 2410;

    /**
     * Plucky Puffin.
     *
     * @public
     * @var positive-int
     */
    const PLUCKY = 2504;

    /**
     * Questing Quokka.
     *
     * @public
     * @var positive-int
     */
    const QUESTING = 2510;

    /**
     * Resolute Raccoon.
     *
     * @public
     * @var positive-int
     */
    const RESOLUTE = 2604;

    /**
     * Stonking Stingray.
     *
     * @public
     * @var positive-int
     */
    const STONKING = 2610;

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
     * @example 'noble'
     */
    private $codename;

    /**
     * @var string
     *
     * @example '24.04.3 LTS'
     */
    private $version;

    /**
     * @var positive-int
     *
     * @example 2404
     */
    private $numericVersion;

    /**
     * @var string
     *
     * @example 'Noble Numbat'
     */
    private $extendedName;

    /**
     * @var \IPE\SystemPackager\APT|null
     */
    private $systemPackager = null;

    /**
     * @param non-empty-string $codename eg 'noble'
     * @param non-empty-string $version eg '24.04.3 LTS'
     * @param non-empty-string $extendedName eg 'Noble Numbat'
     */
    public function __construct($codename, $version, $extendedName, Filesystem $fs, Container $app)
    {
        $this->fs = $fs;
        $this->app = $app;
        if (!preg_match('/^[a-z]+$/D', $codename)) {
            throw new Exception\InvalidArgument('Invalid $codename');
        }
        $this->codename = $codename;
        $matches = null;
        if (!preg_match('/(?<major>\d\d?)\.(?<minor>\d\d?)\b/', $version, $matches)) {
            throw new Exception\InvalidArgument('Invalid $version');
        }
        $numericVersion = ((int) $matches['major']) * 100 + (int) $matches['minor'];
        if ($numericVersion <= 0) {
            throw new Exception\InvalidArgument('Invalid $version');
        }
        $this->version = $version;
        $this->numericVersion = $numericVersion;
        if (!preg_match('/^[A-Z][a-z]* [A-Z][a-z]*$/D', $extendedName)) {
            throw new Exception\InvalidArgument('Invalid $extendedName');
        }
        $this->extendedName = $extendedName;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getIdentifier()
     */
    public static function getIdentifier()
    {
        return 'ubuntu';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::parseNumericVersion()
     */
    public static function parseNumericVersion($version)
    {
        if (!preg_match('/^\d\d?\.\d\d?$/D', $version)) {
            return null;
        }
        list($majorVersion, $minorVersion) = explode('.', $version);
        $numericVersion = (int) $majorVersion * 100 + (int) $minorVersion;

        return $numericVersion > 0 ? $numericVersion : null;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS\Posix::fromOSRelease()
     */
    public static function fromOSRelease(array $data, Container $app)
    {
        $version = isset($data['VERSION']) ? $data['VERSION'] : '';
        $matches = null;
        if (preg_match('/^(?<version>\d\d?(\.\d\d?)+( LTS)?) \((?<extendedName>[A-Z][a-z]* [A-Z][a-z]*)\)$/D', $version, $matches)) {
            $codename = isset($data['VERSION_CODENAME']) ? $data['VERSION_CODENAME'] : '';
        } elseif (preg_match('/^(?<version>\d\d?(\.\d\d?)+( LTS)?), (?<extendedName>(?<codename>[A-Z][a-z]*) [A-Z][a-z]*)$/D', $version, $matches)) {
            // Ubuntu 14.04: VERSION is like "14.04.6 LTS, Trusty Tahr", and VERSION_CODENAME is missing
            $codename = strtolower($matches['codename']);
        } else {
            throw new Exception\UnsupportedOS("'/etc/os-release' defines an invalid VERSION for Ubuntu ({$version})");
        }
        try {
            return $app->build(static::class, [
                $codename,
                $matches['version'],
                $matches['extendedName'],
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
     * @example 'Ubuntu 24.04 (Noble Numbat)'
     */
    public function getDisplayName()
    {
        return 'Ubuntu ' . $this->getVersion() . ' (' . ucfirst($this->extendedName) . ')';
    }

    /**
     * @return string
     *
     * @example 'noble'
     */
    public function getCodename()
    {
        return $this->codename;
    }

    /**
     * @return string
     *
     * @example '24.04.3 LTS'
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
     * @example 2204
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
     * @return \IPE\SystemPackager\APT
     */
    public function getSystemPackager()
    {
        if ($this->systemPackager === null) {
            $this->systemPackager = $this->app->make(SystemPackager\APT::class, ['os' => $this]);
        }

        return $this->systemPackager;
    }
}
