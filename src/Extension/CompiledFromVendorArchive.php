<?php

namespace IPE\Extension;

use IPE\Option;

/**
 * Interface for extensions that must be compiled from a .zip archive published by the vendor, which also contains the libraries they need.
 * The archive is extracted only after the system packages have been installed.
 */
interface CompiledFromVendorArchive
{
    /**
     * @param string $versionRequirements the version requirements (empty string for the latest version)
     *
     * @throws \IPE\Exception\UnsupportedExtension if the version requirements can't be honoured
     * @throws \IPE\Exception\InvalidOptionValues
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    public function getVendorArchiveVersion($versionRequirements, Option\Values $options);

    /**
     * @param non-empty-string $version
     *
     * @throws \IPE\Exception\InvalidOptionValues
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string
     */
    public function getVendorArchiveURL($version, Option\Values $options);

    /**
     * Install the libraries provided by the extracted archive, and fix its source code if needed.
     *
     * @param \IPE\Extension\BuildContext $context its source directory is the root directory of the extracted archive, and its version is never empty
     *
     * @throws \IPE\Exception\InvalidOptionValues
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string the directory containing the source code of the extension
     */
    public function prepareVendorSource(BuildContext $context);

    /**
     * Get the configure arguments that depend on the extracted archive (the others can be provided with ProvidesConfigureArguments).
     *
     * @param \IPE\Extension\BuildContext $context its source directory is the root directory of the extracted archive, and its version is never empty
     *
     * @throws \IPE\Exception\InvalidOptionValues
     *
     * @return list<non-empty-string>
     */
    public function getVendorConfigureArguments(BuildContext $context);
}
