<?php

namespace IPE\Test\Support\ArchiveExtractorTest;

use IPE\Filesystem\Local;

class NonLocalLikeFilesystem extends Local
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::isLocal()
     * @see \IPE\Filesystem\Local::isLocal()
     */
    public function isLocal()
    {
        return false;
    }
}
