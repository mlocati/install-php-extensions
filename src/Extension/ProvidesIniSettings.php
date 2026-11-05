<?php

namespace IPE\Extension;

/**
 * Interface for compiled extensions that need additional settings in the ini file that enables them.
 */
interface ProvidesIniSettings
{
    /**
     * @return string one setting per line, each one terminated by a line feed
     */
    public function getIniSettings();
}
