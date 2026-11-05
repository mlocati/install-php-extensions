<?php

namespace IPE\SystemDependencies;

use IPE\SystemDependency;

/**
 * The system packages of libxslt provide libexslt too.
 */
final class libexslt extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-exslt';

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getHandle()
     */
    public static function getHandle()
    {
        return self::HANDLE;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getRequiredDependencies()
     */
    public function getRequiredDependencies()
    {
        return [libxslt::HANDLE];
    }
}
