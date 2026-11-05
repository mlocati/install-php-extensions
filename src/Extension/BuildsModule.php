<?php

namespace IPE\Extension;

use IPE\Installer\BuildTools;
use IPE\Option;

/**
 * Interface for extensions whose source code can't be compiled with phpize, configure and make.
 * The archive returned by getBinaryArchiveURL() contains the source code.
 */
interface BuildsModule extends AvailableAsBinary
{
    /**
     * @param non-empty-string $rootDir the root directory of the archive
     * @param positive-int $parallelJobs
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string the path to the compiled module
     */
    public function buildModule($rootDir, Option\Values $options, BuildTools $buildTools, $parallelJobs);
}
