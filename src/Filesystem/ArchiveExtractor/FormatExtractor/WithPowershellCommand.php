<?php

namespace IPE\Filesystem\ArchiveExtractor\FormatExtractor;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\ArchiveExtractor\FormatExtractor;
use IPE\Filesystem\Command;

final class WithPowershellCommand extends FormatExtractor
{
    /**
     * Windows PowerShell started by pwsh inherits its PSModulePath, and would load the pwsh module (which requires .NET Core).
     *
     * @var non-empty-string
     */
    const IMPORT_MODULE = 'Import-Module -Name (Join-Path $PSHOME \'Modules\Microsoft.PowerShell.Archive\') -ErrorAction Stop';

    /**
     * @var \IPE\Filesystem\VolatileDirectory|null
     */
    private $scriptContainer = null;

    /**
     * @var non-empty-string
     */
    private $command;

    /**
     * @param non-empty-string $command
     */
    protected function __construct(Filesystem $fs, \IPE\Logger $logger, $command)
    {
        parent::__construct($fs, $logger);
        $this->scriptContainer = $fs->createVolatileDirectory();
        $this->command = $command;
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Filesystem\ArchiveExtractor\FormatExtractor\WithPowershellCommand|null
     */
    public static function createIfAvailable(Filesystem $fs, \IPE\Logger $logger)
    {
        foreach (['pwsh', 'powershell'] as $try) {
            $command = $fs->resolveCommand($try);
            if ($command === '') {
                continue;
            }
            // Not "Get-Command Expand-Archive": it may analyze all the installed modules, which is slow when there are many of them
            list($rc) = $fs->run(Command::create($command, ['-NoProfile', '-NonInteractive', '-Command', self::IMPORT_MODULE]));
            if ($rc !== 0) {
                continue;
            }

            return new self($fs, $logger, $command);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem\ArchiveExtractor\FormatExtractor::doExtractTo()
     */
    protected function doExtractTo($archive, $destinationDirectory)
    {
        $this->logger->debug('Extracting archive with PowerShell', ['archive' => $archive, 'destinationDirectory' => $destinationDirectory]);
        $container = $this->getScriptContainer();
        $archiveWithZipExtension = '';
        if (preg_match('/.\.zip$/Di', $archive)) {
            $archiveWithZipExtension = $archive;
        } else {
            $archiveWithZipExtension = $container->getPath() . $this->fs->getDirectorySeparator() . 'archive.zip';
            $this->fs->copyFile($archive, $archiveWithZipExtension);
        }
        try {
            $psArchive = $this->toLiteralString($archiveWithZipExtension);
            $psDestinationDirectory = $this->toLiteralString($destinationDirectory);
            $importModule = self::IMPORT_MODULE;
            $scriptContent = <<<EOT
try {
    \$Global:ProgressPreference = 'SilentlyContinue'
    {$importModule}
    Expand-Archive -Path {$psArchive} -DestinationPath {$psDestinationDirectory} -Force -ErrorAction Stop | Out-Null
} catch {
    \$_ | Write-Host
    exit 1
}
EOT;
            $scriptFile = $container->getPath() . $this->fs->getDirectorySeparator() . 'script.ps1';
            $this->fs->writeFile($scriptFile, $scriptContent);
            list($rc, $output) = $this->fs->run(Command::create($this->command, ['-NoProfile', '-NonInteractive', '-File', $scriptFile]));
        } finally {
            if ($archiveWithZipExtension !== $archive) {
                $this->fs->deleteFile($archiveWithZipExtension);
            }
        }
        if ($rc !== 0) {
            throw new Exception\IO("Failed to extract file '{$archive}' to '{$destinationDirectory}': {$output}");
        }
    }

    /**
     * @param string $str
     *
     * @return non-empty-string
     */
    private function toLiteralString($str)
    {
        return "'" . str_replace("'", "''", $str) . "'";
    }

    /**
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Filesystem\VolatileDirectory
     */
    private function getScriptContainer()
    {
        if ($this->scriptContainer === null) {
            $this->scriptContainer = $this->fs->createVolatileDirectory();
        }

        return $this->scriptContainer;
    }
}
