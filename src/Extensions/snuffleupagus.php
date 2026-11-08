<?php

namespace IPE\Extensions;

use IPE\Extension;
use IPE\Option;
use IPE\SystemDependencies;

/**
 * @see https://github.com/jvoisin/snuffleupagus
 */
final class snuffleupagus extends Extension\GitHubExtension implements
    Extension\DeclaresOptions,
    Extension\DeclaresThreadSafetySupport,
    Extension\ProvidesConfigureArguments,
    Extension\ProvidesIniSettings,
    Extension\RequiresPHPScriptArguments
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'snuffleupagus';

    /**
     * @public
     * @var string
     */
    const OPTION_ALLOW_IPE = 'allow-ipe';

    /**
     * @public
     * @var string
     */
    const VERSION = '0.14.0';

    /**
     * The functions that IPE never uses, so they don't need exceptions.
     *
     * @var list<non-empty-string>
     */
    private static $functionsNotUsedByIPE = [
        'mail',
        'mb_send_mail',
        'move_uploaded_file',
    ];

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getHandle()
     */
    public static function getHandle()
    {
        return self::HANDLE;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getSupportedPHPVersions()
     */
    public static function getSupportedPHPVersions()
    {
        return ['7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension::getInstalledNames()
     */
    public static function getInstalledNames()
    {
        return ['snuffleupagus'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\RequiresPHPScriptArguments::getPHPScriptArguments()
     */
    public function getPHPScriptArguments()
    {
        // The default rules may block the scripts we run
        return ['-d', 'sp.configuration_file='];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresOptions::getOptions()
     */
    public static function getOptions()
    {
        if (self::getCLIRootDir() === '') {
            return [];
        }

        return [
            new Option\CustomOption(self::OPTION_ALLOW_IPE, 'Let install-php-extensions bypass the default rules (any PHP code could do the same by using its files: delete install-php-extensions when you no longer need it)', false),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\CompiledFromVendorArchive::prepareVendorSource()
     */
    public function prepareVendorSource(Extension\BuildContext $context)
    {
        $rootDir = $context->getSourceDirectory();
        $version = $context->getVersion();
        $fs = $this->os->getFilesystem();
        $rules = $fs->readFile("{$rootDir}/config/default.rules");
        // This option has been introduced in 0.8.0 and removed in 0.14.0
        if (version_compare($version, '0.8.0') >= 0 && version_compare($version, '0.14.0') < 0) {
            $rules .= "\n# Disable \"PHP version is not officially maintained anymore\" message\nsp.global.show_old_php_warning.disable();\n";
        }
        $ipeRoot = self::getCLIRootDir();
        if ($ipeRoot !== '' && $context->getOptionValues()->hasCustomValue(self::OPTION_ALLOW_IPE)) {
            // The first matching rule wins
            $rules = self::buildIPEExceptions($rules, $ipeRoot) . $rules;
        }
        $fs->writeFile($this->getRulesFile(), $rules);

        return "{$rootDir}/src";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesConfigureArguments::getConfigureArguments()
     */
    public function getConfigureArguments(Extension\BuildContext $context)
    {
        return ['--enable-snuffleupagus'];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\ProvidesIniSettings::getIniSettings()
     */
    public function getIniSettings()
    {
        return "sp.configuration_file={$this->getRulesFile()}\n";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getGitHubRepository()
     */
    protected function getGitHubRepository()
    {
        return 'jvoisin/snuffleupagus';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getGitReference()
     */
    protected function getGitReference($version)
    {
        return "v{$version}";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getLatestGitHubVersion()
     */
    protected function getLatestGitHubVersion()
    {
        $phpVersion = $this->php->getMajorMinorVersion();
        if ($phpVersion <= 800) {
            return '0.9.0';
        }

        return $phpVersion <= 801 ? '0.10.0' : self::VERSION;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\GitHubExtension::getSourceDependencies()
     */
    protected function getSourceDependencies($version)
    {
        return [SystemDependencies\libpcre::HANDLE];
    }

    /**
     * When IPE is used as a library, the projects using it handle the rules.
     *
     * @return string empty string if IPE is not running as a program
     */
    private static function getCLIRootDir()
    {
        return defined('IPE_CLI_ROOT_DIR') ? IPE_CLI_ROOT_DIR : '';
    }

    /**
     * snuffleupagus ignores the function_r() rules for the functions that have function() rules, so we need an exception for every function.
     *
     * @param string $rules
     * @param non-empty-string $ipeRoot the directory containing the IPE files (it may start with phar://)
     *
     * @return string
     */
    private static function buildIPEExceptions($rules, $ipeRoot)
    {
        $functions = [];
        if (preg_match_all('/^[^#\r\n]*\.function\("([^"]+)"\)/m', $rules, $matches)) {
            foreach ($matches[1] as $chain) {
                foreach (explode('>', $chain) as $function) {
                    if (!in_array($function, self::$functionsNotUsedByIPE, true)) {
                        $functions[$function] = true;
                    }
                }
            }
        }
        if ($functions === []) {
            return '';
        }
        ksort($functions);
        if (strpos($ipeRoot, 'phar://') === 0) {
            $ipeRoot = substr($ipeRoot, strlen('phar://'));
        }
        $paths = [$ipeRoot];
        $realPath = realpath($ipeRoot);
        if ($realPath !== false && $realPath !== $ipeRoot) {
            $paths[] = $realPath;
        }
        // snuffleupagus removes a backslash before every character
        $regex = addcslashes('^(phar://)?(' . implode('|', array_map('preg_quote', $paths)) . ')(/|$)', '\\"');
        $result = "# BEGIN install-php-extensions rules (--snuffleupagus:allow-ipe)\n";
        foreach (array_keys($functions) as $function) {
            $result .= "sp.disable_function.function(\"{$function}\").filename_r(\"{$regex}\").allow();\n";
        }

        return "{$result}# END install-php-extensions rules\n\n";
    }

    /**
     * @return non-empty-string
     */
    private function getRulesFile()
    {
        return "{$this->php->getIniScanDir()}/snuffleupagus.rules";
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresThreadSafetySupport::supportsZTS()
     */
    public function supportsZTS()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\DeclaresThreadSafetySupport::supportsNTS()
     */
    public function supportsNTS()
    {
        return true;
    }
}
