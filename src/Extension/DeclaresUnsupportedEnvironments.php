<?php

namespace IPE\Extension;

/**
 * Interface for extensions that don't work with some combinations of the supported PHP versions and of the operating systems.
 */
interface DeclaresUnsupportedEnvironments
{
    /**
     * @return non-empty-array<non-empty-string, non-empty-string> the keys are the environments (see \IPE\Extension\UnsupportedEnvironment), the values are the reasons
     *
     * @example ['debian8' => 'The system library is too old', 'alpine3.10-php7.1' => 'The build fails because...', 'debian12-zts' => 'The ZTS build requires...']
     */
    public static function getUnsupportedEnvironments();
}
