<?php

namespace IPE\Filesystem;

/**
 * Case values should be the related default file extensions.
 */
class ArchiveFormat
{
    /**
     * Tarball (uncompressed).
     *
     * @var string
     * @public
     */
    const TAR = 'tar';

    /**
     * Tarball (compressed with bzip2).
     *
     * @var string
     * @public
     */
    const TAR_BZ = 'tar.bz';

    /**
     * Tarball (compressed with gzip).
     *
     * @var string
     * @public
     */
    const TAR_GZ = 'tar.gz';

    /**
     * Tarball (compressed with xz).
     *
     * @var string
     * @public
     */
    const TAR_XZ = 'tar.xz';

    /**
     * ZIP file format.
     *
     * @var string
     * @public
     */
    const ZIP = 'zip';
}
