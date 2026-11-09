<?php

namespace IPE\Extension;

use IPE\Installer\BuildTools;

/**
 * Interface for extensions whose source code can't be compiled with phpize, configure and make.
 * The archive returned by getBinaryArchiveURL() contains the source code.
 */
interface BuildsModule extends AvailableAsBinary
{
    /**
     * @param \IPE\Extension\BuildContext $context its source directory is the root directory of the archive
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string the path to the compiled module
     */
    public function buildModule(BuildContext $context, BuildTools $buildTools);
}
