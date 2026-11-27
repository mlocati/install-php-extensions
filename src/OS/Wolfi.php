<?php

namespace IPE\OS;

class Wolfi extends Posix\WolfiLike
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getIdentifier()
     */
    public static function getIdentifier()
    {
        return 'wolfi';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getDisplayName()
     */
    public function getDisplayName()
    {
        return 'Wolfi';
    }
}
