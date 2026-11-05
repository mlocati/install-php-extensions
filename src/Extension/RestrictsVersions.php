<?php

namespace IPE\Extension;

/**
 * Interface for extensions that can't be installed from some versions published on Composer repositories or on PECL (the other versions or sources are used).
 * The restriction applies also to the versions explicitly asked by the user.
 */
interface RestrictsVersions
{
    /**
     * @param string $origin one of the \IPE\Extension\Resolved::ORIGIN_COMPOSER or \IPE\Extension\Resolved::ORIGIN_PECL constants
     * @param string $version
     *
     * @return bool
     */
    public function canUseVersion($origin, $version);
}
