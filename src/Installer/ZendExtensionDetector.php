<?php

namespace IPE\Installer;

use IPE\Filesystem;
use IPE\Filesystem\Command;

/**
 * Detects if a compiled module (ELF, Mach-O or PE) is a Zend extension.
 */
final class ZendExtensionDetector
{
    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    /**
     * @var \IPE\Logger
     */
    private $logger;

    public function __construct(Filesystem $fs, \IPE\Logger $logger)
    {
        $this->fs = $fs;
        $this->logger = $logger;
    }

    /**
     * @param non-empty-string $modulePath
     *
     * @throws \IPE\Exception\IO
     *
     * @return bool
     */
    public function isZendExtension($modulePath)
    {
        $contents = $this->fs->readFile($modulePath);
        if (substr($contents, 0, 4) === "\x7fELF") {
            $result = $this->checkWithReadelf($modulePath);
            if ($result !== null) {
                return $result;
            }
        }
        $this->logger->debug("Checking if {$modulePath} is a Zend extension by searching its contents");

        // The exported names are null-terminated ASCII strings in every format (with a leading underscore in Mach-O)
        return preg_match('/\0_?zend_extension_entry\0/', $contents) === 1;
    }

    /**
     * readelf reads the actual symbol table, whereas linkers may store a symbol name as the tail of a longer one.
     *
     * @param non-empty-string $modulePath an ELF file
     *
     * @throws \IPE\Exception\IO
     *
     * @return bool|null NULL if readelf is not available or if it fails
     */
    private function checkWithReadelf($modulePath)
    {
        $readelf = $this->fs->resolveCommand('readelf');
        if ($readelf === '') {
            return null;
        }
        list($rc, $output) = $this->fs->run(Command::create($readelf, ['--wide', '--dyn-syms', $modulePath])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));
        if ($rc !== 0) {
            $this->logger->debug("readelf failed to read {$modulePath} (return code: {$rc})");

            return null;
        }
        $this->logger->debug("Checking if {$modulePath} is a Zend extension with readelf");
        foreach (explode("\n", $output) as $line) {
            // Num: Value Size Type Bind Vis Ndx Name
            $columns = preg_split('/\s+/', trim($line)) ?: [];
            $count = count($columns);
            // UND: the module uses the symbol without defining it
            if ($count >= 2 && $columns[$count - 1] === 'zend_extension_entry' && $columns[$count - 2] !== 'UND') {
                return true;
            }
        }

        return false;
    }
}
