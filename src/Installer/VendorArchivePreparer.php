<?php

namespace IPE\Installer;

use IPE\Exception;
use IPE\Extension;
use IPE\Extension\Resolved;
use IPE\Filesystem;
use IPE\Filesystem\ArchiveExtractor;
use IPE\Filesystem\ArchiveFormat;

/**
 * Extracts the archive of the extensions published by their vendors, so that they can be compiled.
 */
final class VendorArchivePreparer
{
    /**
     * @public
     * @var string
     */
    const ARCHIVE_FILENAME = 'archive.zip';

    /**
     * @var \IPE\Filesystem\ArchiveExtractor
     */
    private $archiveExtractor;

    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    public function __construct(ArchiveExtractor $archiveExtractor, Filesystem $fs)
    {
        $this->archiveExtractor = $archiveExtractor;
        $this->fs = $fs;
    }

    /**
     * @throws \IPE\Exception\InvalidArgument if the extension is not published by its vendor as an archive
     * @throws \IPE\Exception\InvalidOptionValues
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     *
     * @return \IPE\Extension\Resolved the extension, ready to be compiled
     */
    public function prepare(Resolved $resolved, State $state)
    {
        $extension = $resolved->getKnownExtension();
        $version = $resolved->getVersion();
        if (!$extension instanceof Extension\CompiledFromVendorArchive || $version === '') {
            throw new Exception\InvalidArgument("{$resolved} is not published by its vendor as an archive");
        }
        $archive = $resolved->getPath() . $this->fs->getDirectorySeparator() . self::ARCHIVE_FILENAME;
        $rootDir = $this->archiveExtractor->extractTo($archive, $state->getVolatileDir()->createSubdir(), ArchiveFormat::ZIP);
        $this->fs->deleteFile($archive);
        $context = new Extension\BuildContext($rootDir, $version, $resolved->getOptionValues());
        $sourceDir = $extension->prepareVendorSource($context);
        $configureArguments = $extension->getVendorConfigureArguments($context);

        return $resolved->withSource($sourceDir, $resolved->getOptionValues()->withConfigureArgumentsBefore($configureArguments));
    }
}
