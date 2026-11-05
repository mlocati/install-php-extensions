<?php

namespace IPE\Extension\Source;

use IPE\Filesystem;

/**
 * The layout of the tarballs created by "pecl package" (also published by some vendors on GitHub).
 */
final class PeclTarball
{
    /**
     * The tarballs contain package.xml next to the directory with the source code: move it inside that directory.
     *
     * @param non-empty-string $extractedDir
     *
     * @throws \IPE\Exception\IO
     *
     * @return non-empty-string the directory containing the source code
     */
    public static function findSourceDir($extractedDir, Filesystem $fs)
    {
        $sep = $fs->getDirectorySeparator();
        $xmlFiles = [];
        $subdirs = [];
        foreach ($fs->listDirectoryContents($extractedDir) as $item) {
            $itemPath = $extractedDir . $sep . $item;
            if ($fs->isDirectory($itemPath)) {
                $subdirs[] = $itemPath;
            } elseif (preg_match('/^package2?\.xml$/i', $item)) {
                $xmlFiles[] = $item;
            } elseif (preg_match('/^(config[09]?\.m4|config\.w32)$/', $item)) {
                // Not a PECL tarball: the source code is already here (its only subdirectory may contain shared code, for example)
                return $extractedDir;
            }
        }
        if (count($subdirs) !== 1) {
            return $extractedDir;
        }
        $sourceDir = $subdirs[0];
        foreach ($xmlFiles as $xmlFile) {
            if (!$fs->exists($sourceDir . $sep . $xmlFile)) {
                $fs->rename($extractedDir . $sep . $xmlFile, $sourceDir . $sep . $xmlFile);
            }
        }

        return $sourceDir;
    }
}
