<?php

namespace IPE;

use IPE\SystemPackager\Packages;

/**
 * Something provided by the operating system that PHP extensions may need (a library, a compiler, a build tool, ...).
 *
 * Instances are built once per run by the registry (through the container), so they can keep state between the phases:
 * getSystemPackages() => install() => the PHP extensions are compiled => finalize().
 */
abstract class SystemDependency implements SystemDependency\DeclaresHandle
{
    /**
     * Libraries use the same handle as the composer platform packages (eg 'lib-curl'); tools use their plain name (eg 'cmake').
     *
     * @public
     * @var string
     */
    const RX_VALID_HANDLE = '#^[a-z]([_-]?[a-z0-9]+)*$#D';

    /**
     * @var \IPE\PHPInfo
     * @readonly
     */
    protected $php;

    /**
     * @var \IPE\OS\Posix
     * @readonly
     */
    protected $os;

    /**
     * @var \IPE\Option\Values
     * @readonly
     */
    protected $options;

    /**
     * @param \IPE\Option\Values $options the values of the options returned by getOptions()
     */
    public function __construct(PHPInfo $php, OS\Posix $os, Option\Values $options)
    {
        $this->php = $php;
        $this->os = $os;
        $this->options = $options;
    }

    /**
     * Get the options accepted by this dependency: read their values from $this->options.
     * It's static because we need the options before detecting PHP and the operating system (for example to show them in the CLI help).
     *
     * @return \IPE\Option\CustomOption[]
     */
    public static function getOptions()
    {
        return [];
    }

    /**
     * Get the handles of the other system dependencies this one needs (eg the tools to compile it).
     *
     * @throws \IPE\Exception\IO
     *
     * @return list<non-empty-string>
     */
    public function getRequiredDependencies()
    {
        return [];
    }

    /**
     * Get the system packages that provide the dependency: the persistent ones are needed at runtime, the volatile ones only to compile.
     *
     * @throws \IPE\Exception\UnsupportedOS if the packages for the operating system are not known
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\SystemPackager\Packages
     */
    public function getSystemPackages(Installer\State $state)
    {
        return new Packages();
    }

    /**
     * Called after the system packages have been installed and before the PHP extensions are compiled: compile or install here what the packages don't provide.
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     * @throws \IPE\Exception\UnsupportedOS
     *
     * @return void
     */
    public function install(Installer\State $state)
    {
    }

    /**
     * Called after the PHP extensions have been compiled and enabled, before the volatile packages are removed: remove here what was needed only to compile.
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedOS
     *
     * @return void
     */
    public function finalize(Installer\State $state)
    {
    }

    /**
     * @return \IPE\Exception\UnsupportedOS
     */
    protected function createUnknownPackagesException()
    {
        return new Exception\UnsupportedOS('The system packages providing ' . static::getHandle() . " are not known for {$this->os->getDisplayName()}");
    }

    /**
     * @return \IPE\Exception\UnsupportedOS
     */
    protected function createNotProvidedException()
    {
        return new Exception\UnsupportedOS("{$this->os->getDisplayName()} doesn't provide " . static::getHandle());
    }
}
