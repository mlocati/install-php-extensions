<?php

namespace IPE\OS;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;
use IPE\Service\Container;
use IPE\SystemPackager;
use IPE\Traits;

class MacOS implements Posix
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
     * @var string
     *
     * @example '15.7.1'
     */
    private $version;

    /**
     * @var positive-int
     *
     * @example 1507
     */
    private $numericVersion;

    /**
     * @var \IPE\SystemPackager\Homebrew|null
     */
    private $systemPackager = null;

    /**
     * @var string|null
     */
    private $sdkPath = null;

    /**
     * @param non-empty-string $version eg '15.7.1'
     *
     * @throws \IPE\Exception\InvalidArgument
     */
    public function __construct($version, Filesystem $fs, Container $app)
    {
        $this->fs = $fs;
        $this->app = $app;
        $match = null;
        // The patch version (like the .1 of 15.7.1) doesn't count
        $numericVersion = preg_match('/^(?<majorMinor>[1-9]\d*(\.\d+)?)(\.\d+)*$/D', $version, $match) ? self::parseNumericVersion($match['majorMinor']) : null;
        if ($numericVersion === null) {
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
        return 'macos';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::parseNumericVersion()
     *
     * @example 1500 for '15'
     * @example 1507 for '15.7'
     */
    public static function parseNumericVersion($version)
    {
        if (!preg_match('/^[1-9]\d*(\.\d\d?)?$/D', $version)) {
            return null;
        }
        $chunks = explode('.', $version) + [1 => '0'];

        return max(1, (int) $chunks[0] * 100 + (int) $chunks[1]);
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
     * @example 'macOS 15.7.1'
     */
    public function getDisplayName()
    {
        return 'macOS ' . $this->getVersion();
    }

    /**
     * @return string
     *
     * @example '15.7.1'
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
     * @example 1507
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
     * @return \IPE\SystemPackager\Homebrew
     */
    public function getSystemPackager()
    {
        if ($this->systemPackager === null) {
            $this->systemPackager = $this->app->build(SystemPackager\Homebrew::class, ['os' => $this]);
        }

        return $this->systemPackager;
    }

    /**
     * The headers and the libraries of the system (like zlib) are only in the SDK: /usr/include doesn't exist.
     *
     * @throws \IPE\Exception\IO
     *
     * @return string empty string if the Xcode Command Line Tools are not installed
     *
     * @example '/Library/Developer/CommandLineTools/SDKs/MacOSX.sdk'
     */
    public function getSDKPath()
    {
        if ($this->sdkPath === null) {
            list($rc, $output) = $this->fs->run(Command::create('xcrun', ['--show-sdk-path'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
            $this->sdkPath = $rc === 0 ? rtrim(trim($output), '/') : '';
        }

        return $this->sdkPath;
    }
}
