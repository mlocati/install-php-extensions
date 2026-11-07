<?php

namespace IPE\CLI\Argv;

use IPE\CLI\Argv;
use IPE\Extension;
use IPE\Extension\Asked\Resolver;
use IPE\Installer;
use IPE\Installer\BuildOptions;
use IPE\SystemDependency;
use IPE\SystemPackager\APT;

/**
 * @internal
 */
final class InstallPHPExtensions extends Argv
{
    /**
     * The definitions of the options declared by the extensions: only for the help, since they are parsed by getOptionDefinition() and validated when resolving the extensions.
     *
     * @var \IPE\CLI\Argv\Option\Definition[]
     */
    private $extensionDefinitions = [];

    /**
     * @var array<string, true>
     */
    private $systemDependencyDefinitionNames = [];

    public function __construct(Extension\Registry $extensions, SystemDependency\Registry $systemDependencies)
    {
        $this->addDefinitions([
            (new Option\Definition\StringValue('php', 'The php command (or the path of the PHP CLI binary) for which the extensions should be installed'))->setDefaultDescription('the PHP running install-php-extensions'),
            (new Option\Definition\OptionalValue('from-composer-json', 'Also install the extensions listed in the require and require-dev sections of a composer.json file'))->setDefaultDescription('composer.json in the current directory'),
            (new Option\Definition\OptionalValue('from-composer-lock', 'Also install the extensions required by the project and by its dependencies, as listed in a composer.lock file'))->setDefaultDescription('composer.lock in the current directory'),
            new Option\Definition\Flag('no-dev', 'Ignore the development requirements of the project in --from-composer-json and --from-composer-lock'),
            new Option\Definition\Flag('ignore-unknown-extensions', 'Ignore unknown extensions instead of aborting'),
            new Option\Definition\ValueInList('unsupported-extensions', 'What to do with extensions that are known not to work in the current environment.', [Resolver\Options::ONUNSUPPORTED_ABORT, Resolver\Options::ONUNSUPPORTED_FORCE, Resolver\Options::ONUNSUPPORTED_SKIP], Resolver\Options::ONUNSUPPORTED_ABORT),
            new Option\Definition\ValueInList('build-type', 'How to compile the extensions: optimized (debug symbols stripped), with-symbols (optimized, keeping the debug symbols), debug (no optimizations, with debug symbols)', BuildOptions::getTypes(), BuildOptions::TYPE_OPTIMIZED),
            (new Option\Definition\IntValue('cpus', 'Number of parallel processors to be used while compiling (0: all the available ones)', null, 0))->setDefaultDescription('all the available ones'),
            new Option\Definition\Flag('skip-check', "Don't check that PHP loads the extensions correctly before enabling them"),
            new Option\Definition\Flag('dont-enable', 'Disable the extensions at the end of the installation (they can be enabled later with --enable)'),
            new Option\Definition\HiddenFlag('create-enable-scripts', 'With --dont-enable, create the docker-php-ext-enable-<name> scripts that enable the extensions, like the shell script did with IPE_DONT_ENABLE (only in the official Docker PHP images)'),
            new Option\Definition\StringList('enable', 'Enable the extension loaded by this ini file, by uncommenting its extension= and zend_extension= lines, or with phpenmod for the files in the mods-available directory of Debian and Ubuntu (can be specified multiple times)'),
            new Option\Definition\StringList('disable', 'Disable the extension loaded by this ini file, by commenting out its extension= and zend_extension= lines, or with phpdismod for the files in the mods-available directory of Debian and Ubuntu (can be specified multiple times)'),
            (new Option\Definition\StringValue('ini-basename', 'The name of the ini files that enable the extensions (without .ini): use the {priority} and {name} placeholders, or compat for the names used by the official Docker PHP images (see https://github.com/mlocati/install-php-extensions#names-of-the-ini-files)'))->setDefaultDescription('compat in the official Docker PHP images, ' . Installer\Enabler::INI_BASENAME_DEFAULT . ' otherwise'),
            (new Option\Definition\BoolValue('keep-systempackager-cache', 'Keep the system packager (APT/APK) cache after installing PHP extensions'))->setDefaultDescription('no in the official Docker PHP images, yes otherwise'),
            (new Option\Definition\BoolValue('apt-debian-fix-archived', 'Update the APT repositories for archived Debian systems'))->setDefaultDescription('yes in the official Docker PHP images, no otherwise'),
            new Option\Definition\StringValue('apt-debian-archiveurl-default', 'The URL for APT for archived Debian systems', APT\OnDebian::ARCHIVEURL_DEFAULT),
            new Option\Definition\StringValue('apt-debian-archiveurl-security', 'The security URL for APT for archived Debian systems', APT\OnDebian::ARCHIVEURL_SECURITY),
            new Option\Definition\StringValue('apt-debian-archiveurl-updates', 'The updates URL for APT for archived Debian systems', APT\OnDebian::ARCHIVEURL_UPDATES),
            new Option\Definition\Flag('no-proxy', "Don't use the proxy configured in the HTTP_PROXY (or http_proxy) environment variable"),
            new Option\Definition\Flag('insecure', "Don't verify the TLS certificates of the servers when downloading files"),
            (new Option\Definition\BoolValue('fix-letsencrypt', "Fix the root CA certificate of Let's Encrypt on old systems"))->setDefaultDescription('yes in the official Docker PHP images, no otherwise'),
            new Option\Definition\StringValue('cache-dir', 'The path to an existing writable directory for dowloaded files cache'),
            new Option\Definition\StringValue('cache-maxage', "The maximum age of cached files (e.g. '30' or '30s' for 30 seconds, '8h' for 8 hours, '7d' for 7 days). With 0 they never expire, so new versions of the extensions won't be detected", '0', '/^\d+[smhd]?$/D'),
            new Option\Definition\StringValue('temp-dir', 'The path to an existing writable directory where temporary files should be stored'),
            new Option\Definition\Flag('verbose', 'Print debug information'),
            new Option\Definition\StringValue('save-report-to', 'Write to this file a JSON report of what has been installed, and how'),
            new Option\Definition\StringValue('save-persistent-packages-to', 'Write to this file the space-separated list of the system packages that have been installed and kept after the installation'),
            new Option\Definition\StringValue('save-volatile-packages-to', 'Write to this file the space-separated list of the system packages that have been installed and removed after the installation'),
            new Option\Definition\Flag('install-composer', 'Install Composer'),
            new Option\Definition\StringValue('composer-path', 'The directory (or full path) where Composer should be installed (must be specified on Windows)'),
            new Option\Definition\StringValue('composer-version', 'The Composer version to be installed (stable, 1, 2.2, 2, 2.9.1, ...)', 'stable'),
        ]);
        foreach ($extensions->getExtensionClasses() as $class) {
            if (is_subclass_of($class, Extension\DeclaresOptions::class)) {
                foreach ($class::getOptions() as $option) {
                    $this->extensionDefinitions[] = self::createDefinition($class::getHandle(), $option, true);
                }
            }
        }
        foreach ($systemDependencies->getDependencyClasses() as $class) {
            foreach ($class::getOptions() as $option) {
                $definition = self::createDefinition($class::getHandle(), $option, false);
                $this->addDefinition($definition);
                $this->systemDependencyDefinitionNames[$definition->getName()] = true;
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\CLI\Argv::buildCLIHelpString()
     */
    public function buildCLIHelpString()
    {
        $general = array_diff_key($this->getDefinitions(), $this->systemDependencyDefinitionNames);
        $systemDependencies = array_intersect_key($this->getDefinitions(), $this->systemDependencyDefinitionNames);

        return $this->formatDefinitions($general)
            . "\n\nOptions of the extensions (the ones declared by the source code of the extensions are not listed here):\n"
            . $this->formatDefinitions($this->extensionDefinitions)
            . "\n\nOptions of the system dependencies:\n"
            . $this->formatDefinitions($systemDependencies);
    }

    /**
     * Get the options specified for the system dependencies.
     *
     * @return array<string, array<string, string|true>> the keys are the handles of the system dependencies
     */
    public function getSystemDependencyOptions()
    {
        $result = [];
        foreach (array_keys($this->systemDependencyDefinitionNames) as $definitionName) {
            $value = isset($this->optionValues[$definitionName]) ? $this->optionValues[$definitionName] : null;
            if ($value === true || is_string($value)) {
                list($handle, $optionName) = explode(':', $definitionName, 2) + [1 => ''];
                $result[$handle][$optionName] = $value;
            }
        }

        return $result;
    }

    /**
     * @param non-empty-string $handle
     * @param bool $allowOptionalValue the system dependencies don't support options with an optional value: their values are read from the Flag and Value definitions
     *
     * @return \IPE\CLI\Argv\Option\Definition
     */
    private static function createDefinition($handle, \IPE\Option $option, $allowOptionalValue)
    {
        $name = "{$handle}:{$option->getName()}";
        $description = $option->getDescription();
        if ($description === '') {
            $description = 'No description available';
        }
        if ($option->needsValue() === false) {
            return new Option\Definition\Flag($name, $description);
        }
        if ($option->needsValue() === null && $allowOptionalValue) {
            return new Option\Definition\OptionalValue($name, $description);
        }

        return new Option\Definition\StringValue($name, $description, (string) $option->getDefault());
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\CLI\Argv::getOptionDefinition()
     */
    protected function getOptionDefinition($optionName)
    {
        if (!isset($this->definitions[$optionName])) {
            list($handle, $optionForExtension) = explode(':', $optionName, 2) + [1 => ''];
            if ($optionForExtension !== '' && (preg_match(Extension::RX_VALID_HANDLE, $handle) || preg_match(Extension\Version\Lister\Composer::RX_VALID_FULLNAME, $handle))) {
                return new Option\Definition\OptionalValue($optionName, '');
            }
        }

        return parent::getOptionDefinition($optionName);
    }

    /**
     * Get the options specified for the extensions (--<handle>:<option>[=<value>] or --<vendor>/<name>:<option>[=<value>]), grouped by extension handle or package name.
     *
     * @return array<non-empty-string, array<non-empty-string, string|true>>
     */
    public function getOptionsByExtension()
    {
        $result = [];
        foreach ($this->optionalValues as $optionName => $optionValue) {
            list($handle, $optionForExtension) = explode(':', $optionName, 2) + [1 => ''];
            if ($handle === '' || $optionForExtension === '') {
                continue;
            }
            if (!isset($result[$handle])) {
                $result[$handle] = [];
            }
            $result[$handle][$optionForExtension] = $optionValue;
        }

        return $result;
    }

    /**
     * @param non-empty-string $extensionHandle
     *
     * @return array<non-empty-string, string|true>
     */
    public function getOptionsForExtension($extensionHandle)
    {
        $all = $this->getOptionsByExtension();

        return isset($all[$extensionHandle]) ? $all[$extensionHandle] : [];
    }
}
