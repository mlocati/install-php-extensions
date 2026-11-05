<?php

namespace IPE\Extension;

/**
 * Interface for extensions whose archive contains the module to be copied to the PHP extension directory.
 */
interface ProvidesBinaryModule extends AvailableAsBinary
{
    /**
     * Find the module for the specified PHP in the extracted archive.
     *
     * @param non-empty-string $rootDir the root directory of the archive
     * @param string $versionRequirements the version requirements (empty string for the latest version)
     *
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\IO
     *
     * @return string the full path of the module (empty string if the archive doesn't provide it)
     */
    public function findBinaryModule($rootDir, $versionRequirements);

    /**
     * Change the module copied to the PHP extension directory, if the vendor requires it.
     *
     * @param non-empty-string $modulePath
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function postProcessBinaryModule($modulePath);

    /**
     * Get the additional settings to be written to the ini file that enables the module.
     *
     * @param non-empty-string $rootDir the root directory of the archive
     *
     * @throws \IPE\Exception\IO
     *
     * @return string
     */
    public function getBinaryIniSettings($rootDir);

    /**
     * Must the module be loaded with zend_extension instead of extension?
     *
     * @return bool
     */
    public function isZendExtension();
}
