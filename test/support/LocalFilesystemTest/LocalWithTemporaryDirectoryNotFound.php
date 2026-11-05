<?php

namespace IPE\Test\Support\LocalFilesystemTest;

use IPE\Filesystem\Local;

class LocalWithTemporaryDirectoryNotFound extends Local
{
    public function __construct()
    {
        parent::__construct(new \IPE\Logger());
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\Local::listPotentialTempPaths()
     */
    protected function listPotentialTempPaths()
    {
        return [];
    }
}
