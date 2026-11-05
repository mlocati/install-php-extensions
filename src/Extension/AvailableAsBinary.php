<?php

namespace IPE\Extension;

/**
 * Interface for extensions that are available in binary form and can't be compiled.
 * They must also implement ProvidesBinaryModule or ProvidesBinaryInstaller.
 */
interface AvailableAsBinary
{
    /**
     * Get the URL of the archive (in .tar.gz format) containing the binary.
     *
     * @param string $versionRequirements the version requirements (empty string for the latest version)
     *
     * @throws \IPE\Exception\UnsupportedExtension if the version requirements can't be honoured
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\IO
     *
     * @return string empty string if there's nothing to be downloaded (because the installer does it)
     */
    public function getBinaryArchiveURL($versionRequirements);
}
