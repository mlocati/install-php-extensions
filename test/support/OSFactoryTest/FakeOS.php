<?php

namespace IPE\Test\Support\OSFactoryTest;

use IPE\Exception;
use IPE\OS\Architecture;
use IPE\OS\Posix;
use IPE\Service\Container;

final class FakeOS implements Posix
{
    /**
     * @var string
     */
    private $version;

    /**
     * @param string $version
     */
    public function __construct($version)
    {
        $this->version = $version;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getIdentifier()
     */
    public static function getIdentifier()
    {
        return 'fakeos';
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
        return new self(isset($data['VERSION_ID']) ? $data['VERSION_ID'] : '');
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getFilesystem()
     */
    public function getFilesystem()
    {
        throw new Exception\IO('Not implemented');
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getDisplayName()
     */
    public function getDisplayName()
    {
        return "Fake OS {$this->version}";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getNumericVersion()
     */
    public function getNumericVersion()
    {
        return max(1, (int) $this->version);
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS\Posix::getSystemPackager()
     */
    public function getSystemPackager()
    {
        throw new Exception\IO('Not implemented');
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS\Posix::getMachineArchitecture()
     */
    public function getMachineArchitecture()
    {
        return Architecture::X64;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS\Posix::getRawMachineArchitecture()
     */
    public function getRawMachineArchitecture()
    {
        return 'x86_64';
    }
}
