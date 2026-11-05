<?php

namespace IPE\Extension\Version\Lister;

use IPE\ComposerRepository;
use IPE\Exception;
use IPE\Filesystem\Downloader;

/**
 * Lists the versions of the Composer packages, looking for them in the registered Composer repositories.
 */
class Composer
{
    /**
     * @var \IPE\Logger
     */
    private $logger;

    /**
     * @public
     * @var string
     * @see https://github.com/composer/packagist/blob/6a93b31b2d56d922bff87c056664fe5eaac68f85/src/Validator/ValidPackageRepositoryValidator.php#L93
     */
    const RX_VALID_VENDOR = '#^[A-Za-z0-9]([_.-]?[A-Za-z0-9]+)*$#D';

    /**
     * @public
     * @var string
     * @see https://github.com/composer/packagist/blob/6a93b31b2d56d922bff87c056664fe5eaac68f85/src/Validator/ValidPackageRepositoryValidator.php#L93
     */
    const RX_VALID_NAME = '#^[A-Za-z0-9]([_.-]?[A-Za-z0-9]+)*$#D';

    /**
     * @public
     * @var string
     * @see https://github.com/composer/packagist/blob/6a93b31b2d56d922bff87c056664fe5eaac68f85/src/Validator/ValidPackageRepositoryValidator.php#L93
     */
    const RX_VALID_FULLNAME = '#^(?<vendor>[A-Za-z0-9]([_.-]?[A-Za-z0-9]+)*)/(?<name>[A-Za-z0-9]([_.-]?[A-Za-z0-9]+)*)$#D';

    /**
     * @var \IPE\Filesystem\Downloader
     */
    private $downloader;

    /**
     * @var \IPE\ComposerRepository\Registry
     */
    private $repositories;

    public function __construct(Downloader $downloader, ComposerRepository\Registry $repositories, \IPE\Logger $logger)
    {
        $this->logger = $logger;
        $this->downloader = $downloader;
        $this->repositories = $repositories;
    }

    /**
     * Get the versions from the repository with the highest priority that provides the package (like the canonical repositories of Composer).
     *
     * @param string $vendor
     * @param string $package
     * @param bool $includeDev
     *
     * @throws \IPE\Exception\InvalidArgument
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Extension\Version\Composer[]
     */
    public function getVersions($vendor, $package, $includeDev = false)
    {
        if (!preg_match(self::RX_VALID_VENDOR, $vendor)) {
            throw new Exception\InvalidArgument('$vendor is not valid');
        }
        if (!preg_match(self::RX_VALID_NAME, $package)) {
            throw new Exception\InvalidArgument('$package is not valid');
        }
        foreach ($this->repositories->getRepositories() as $repository) {
            $versions = $repository->getVersions($this->downloader, "{$vendor}/{$package}", $includeDev);
            if ($versions !== null) {
                $this->logger->debug("Using the Composer repository {$repository->getURL()} for {$vendor}/{$package}");

                return $versions;
            }
        }

        return [];
    }
}
