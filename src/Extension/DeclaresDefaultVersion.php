<?php

namespace IPE\Extension;

/**
 * Interface for extensions that pick a specific version when the user doesn't ask for one (eg the last version supporting an old PHP).
 */
interface DeclaresDefaultVersion
{
    /**
     * Get the version requirements to be used when the user didn't specify any.
     * An exact version is always used, whatever its stability and the PHP versions declared by the maintainers of the extension.
     *
     * @throws \IPE\Exception\UnsupportedExtension
     *
     * @return string a composer version constraint (empty string for no preference)
     */
    public function getDefaultVersionRequirements();
}
