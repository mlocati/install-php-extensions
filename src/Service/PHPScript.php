<?php

namespace IPE\Service;

use IPE\Extension;
use IPE\Filesystem\Command;
use IPE\PHPInfo;

/**
 * Creates the commands that IPE uses to run PHP scripts.
 */
final class PHPScript
{
    /**
     * @var \IPE\PHPInfo
     */
    private $php;

    /**
     * @var \IPE\Extension\Registry
     */
    private $extensions;

    public function __construct(PHPInfo $php, Extension\Registry $extensions)
    {
        $this->php = $php;
        $this->extensions = $extensions;
    }

    /**
     * @param non-empty-string $script
     * @param list<string> $arguments the arguments for the script
     *
     * @return \IPE\Filesystem\Command
     */
    public function createCommand($script, array $arguments = [])
    {
        $phpArguments = [];
        foreach ($this->extensions->getExtensionClasses() as $class) {
            if (!is_subclass_of($class, Extension\RequiresPHPScriptArguments::class)) {
                continue;
            }
            $extension = $this->extensions->getExtensionByHandle($class::getHandle());
            if ($extension instanceof Extension\RequiresPHPScriptArguments && $this->php->isExtensionInstalled($extension)) {
                $phpArguments = array_merge($phpArguments, $extension->getPHPScriptArguments());
            }
        }

        return Command::create($this->php->getPHPBin(), array_merge($phpArguments, [$script], $arguments));
    }
}
