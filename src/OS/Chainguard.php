<?php

namespace IPE\OS;

/**
 * The licensed Chainguard images: they use the Wolfi packages, but /etc/os-release defines ID=chainguard (without ID_LIKE).
 */
class Chainguard extends Posix\WolfiLike
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getIdentifier()
     */
    public static function getIdentifier()
    {
        return 'chainguard';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getDisplayName()
     */
    public function getDisplayName()
    {
        return 'Chainguard';
    }
}
