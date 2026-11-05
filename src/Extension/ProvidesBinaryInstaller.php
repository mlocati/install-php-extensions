<?php

namespace IPE\Extension;

use IPE\Option;

/**
 * Interface for extensions whose archive contains an installer provided by the vendor, which also takes care of enabling the extension.
 */
interface ProvidesBinaryInstaller extends AvailableAsBinary
{
    /**
     * Does the extracted archive contain what the installer needs for the current environment?
     *
     * @param non-empty-string $rootDir the root directory of the archive
     *
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    public function canRunBinaryInstaller($rootDir);

    /**
     * @param non-empty-string $rootDir the root directory of the archive
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function runBinaryInstaller($rootDir, Option\Values $options);
}
