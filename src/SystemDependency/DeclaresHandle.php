<?php

namespace IPE\SystemDependency;

/**
 * In PHP 5 a method of a class can't be both abstract and static without a strict standards error: an interface doesn't have this limit.
 */
interface DeclaresHandle
{
    /**
     * @return non-empty-string
     */
    public static function getHandle();
}
