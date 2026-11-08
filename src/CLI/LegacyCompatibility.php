<?php

namespace IPE\CLI;

use IPE\CLI\Argv\Option\Definition;
use IPE\Extension;

/**
 * Translates the environment variables and the arguments of the shell version of install-php-extensions (used in the official Docker PHP images).
 *
 * @internal
 */
final class LegacyCompatibility
{
    /**
     * The environment variables that enable a flag (with an optional value).
     *
     * @var array<non-empty-string, array<non-empty-string, string|null>>
     */
    private static $flagVariables = [
        'IPE_DEBUG' => ['verbose' => null],
        'IPE_DONT_ENABLE' => ['dont-enable' => null, 'create-enable-scripts' => null],
        'IPE_KEEP_SYSPKG_CACHE' => ['keep-systempackager-cache' => 'yes'],
        'IPE_INSECURE' => ['download-insecure' => null],
        'IPE_SKIP_CHECK' => ['skip-check' => null],
        'IPE_NOSTRIP' => ['build-type' => 'with-symbols'],
        'IPE_FIX_CACERTS' => ['fix-letsencrypt' => 'yes'],
        'IPE_ICU_EN_ONLY' => ['lib-icu:en-only' => null],
        'IPE_INSTANTCLIENT_BASIC' => ['oracle-instantclient:basic' => null],
        'IPE_GD_WITHOUTAVIF' => ['gd:without-avif' => null],
        'IPE_SWOOLE_WITHOUT_IOURING' => ['swoole:without-iouring' => null],
        'IPE_NEWRELIC_DAEMON' => ['newrelic:install-daemon' => null],
        'IPE_NEWRELIC_KEEPLOG' => ['newrelic:keep-log' => null],
        'IPE_DD_APPSEC' => ['ddtrace:enable-appsec' => null],
        'IPE_DD_PROFILING' => ['ddtrace:enable-profiling' => null],
        'IPE_LZF_BETTERCOMPRESSION' => ['lzf:enable-lzf-better-compression' => 'yes'],
    ];

    /**
     * The environment variables whose value is the value of options (%s is replaced by the value).
     *
     * @var array<non-empty-string, array<non-empty-string, non-empty-string>>
     */
    private static $valueVariables = [
        'IPE_PROCESSOR_COUNT' => ['cpus' => '%s'],
        'IPE_SAVE_PERMDEPS_TO' => ['save-persistent-packages-to' => '%s'],
        'IPE_SAVE_VOLDEPS_TO' => ['save-volatile-packages-to' => '%s'],
        'IPE_ASPELL_LANGUAGES' => ['lib-aspell:languages' => '%s'],
        'IPE_SAXON_EDITION' => ['saxon:edition' => '%s'],
        'IPE_EVENT_NAMESPACE' => ['event:with-event-ns' => '%s'],
        // The old script replaced only the host part of the Debian URLs
        'IPE_DEB_ARCHIVE' => ['apt-debian-archiveurl-default' => '%s/debian', 'apt-debian-archiveurl-updates' => '%s/debian'],
        'IPE_DEB_ARCHIVE_SECURITY' => ['apt-debian-archiveurl-security' => '%s'],
    ];

    /**
     * The old names of the extensions.
     *
     * @var array<non-empty-string, non-empty-string>
     */
    private static $aliases = [
        'datadog_trace' => 'ddtrace',
        'ioncube' => 'ioncube_loader',
        'ioncube loader' => 'ioncube_loader',
        'libsodium' => 'sodium',
        'pecl_http' => 'http',
        'saxonc' => 'saxon',
        'zend opcache' => 'opcache',
    ];

    /**
     * The PECL stability names, and the corresponding Composer stability flags.
     *
     * @var array<non-empty-string, non-empty-string>
     */
    private static $stabilities = [
        'snapshot' => 'dev',
        'devel' => 'dev',
        'alpha' => 'alpha',
        'beta' => 'beta',
        'stable' => 'stable',
    ];

    /**
     * @var \IPE\CLI\Argv\Option\Definition[]
     */
    private $definitions;

    /**
     * @var array<string, true>
     */
    private $handles = [];

    /**
     * The names of the options specified in the command line, or added by us.
     *
     * @var array<string, true>
     */
    private $presentOptions = [];

    /**
     * @var list<string>
     */
    private $addedOptions = [];

    /**
     * @var list<string>
     */
    private $warnings = [];

    public function __construct(Argv\InstallPHPExtensions $options, Extension\Registry $extensions)
    {
        $this->definitions = $options->getDefinitions();
        foreach ($extensions->getExtensionClasses() as $class) {
            $this->handles[$class::getHandle()] = true;
        }
    }

    /**
     * Get the IPE_... environment variables.
     *
     * @return array<string, string>
     */
    public static function getEnvironment()
    {
        // getenv() without arguments requires PHP 7.1
        $all = PHP_VERSION_ID >= 70100 ? getenv() : $_SERVER;
        $result = [];
        foreach ($all as $name => $value) {
            if (is_string($name) && strpos($name, 'IPE_') === 0 && is_string($value) && $value !== '') {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    /**
     * @param non-empty-list<string> $argv the command line arguments (the first one is the script name)
     * @param array<string, string> $environment the environment variables
     *
     * @return array{non-empty-list<string>, list<string>} the new command line arguments, and the deprecation warnings
     */
    public function process(array $argv, array $environment)
    {
        $this->presentOptions = [];
        $this->addedOptions = [];
        $this->warnings = [];
        $tokens = $this->classifyTokens(array_slice($argv, 1));
        foreach ($tokens as list($kind, $token)) {
            if ($kind === 'option') {
                $this->presentOptions[self::getOptionName($token)] = true;
            }
        }
        $this->processEnvironment($environment);
        $result = [];
        foreach ($tokens as list($kind, $token)) {
            if ($kind === 'option' && $token === '--cleanup') {
                $this->warnings[] = 'The --cleanup option is deprecated and has been ignored (install-php-extensions always cleans up everything)';
            } elseif ($kind === 'argument') {
                $converted = $this->processArgument($token);
                if ($converted !== '') {
                    $result[] = $converted;
                }
            } else {
                $result[] = $token;
            }
        }

        return [array_merge([$argv[0]], $this->addedOptions, $result), $this->warnings];
    }

    /**
     * @param list<string> $args
     *
     * @return list<array{non-empty-string, string}> the kind of each token (option, value, separator or argument), and the token
     */
    private function classifyTokens(array $args)
    {
        $result = [];
        $afterSeparator = false;
        $expectValue = false;
        foreach ($args as $arg) {
            if ($afterSeparator) {
                $result[] = ['argument', $arg];
            } elseif ($expectValue) {
                $result[] = ['value', $arg];
                $expectValue = false;
            } elseif ($arg === '--') {
                $result[] = ['separator', $arg];
                $afterSeparator = true;
            } elseif ($arg !== '' && $arg[0] === '-') {
                $result[] = ['option', $arg];
                $name = self::getOptionName($arg);
                $expectValue = strpos($arg, '=') === false && isset($this->definitions[$name]) && $this->definitions[$name] instanceof Definition\Value;
            } else {
                $result[] = ['argument', $arg];
            }
        }

        return $result;
    }

    /**
     * Same logic as Argv::addArgv().
     *
     * @param string $option
     *
     * @return string
     */
    private static function getOptionName($option)
    {
        $chunks = explode('=', $option, 2);
        // @phpstan-ignore cast.useless (substr() may return false before PHP 8)
        $name = (string) substr($chunks[0], 1);

        // @phpstan-ignore cast.useless (substr() may return false before PHP 8)
        return $name !== '' && $name[0] === '-' ? (string) substr($name, 1) : $name;
    }

    /**
     * @param array<string, string> $environment
     *
     * @return void
     */
    private function processEnvironment(array $environment)
    {
        ksort($environment);
        foreach ($environment as $name => $value) {
            if ($value === '') {
                continue;
            }
            if (isset(self::$flagVariables[$name])) {
                // The old script considered as enabled the values 1 and the ones starting with y
                if ($value === '1' || strtolower($value[0]) === 'y') {
                    $this->addOptions(self::$flagVariables[$name], "The {$name} environment variable");
                }
            } elseif (isset(self::$valueVariables[$name])) {
                if ($name === 'IPE_ASPELL_LANGUAGES') {
                    // The old script used a space-separated list
                    $value = implode(',', preg_split('/\s+/', trim($value)) ?: []);
                } elseif ($name === 'IPE_DEB_ARCHIVE') {
                    $value = rtrim($value, '/');
                }
                $options = [];
                foreach (self::$valueVariables[$name] as $optionName => $format) {
                    $options[$optionName] = sprintf($format, $value);
                }
                $this->addOptions($options, "The {$name} environment variable");
            } elseif (strpos($name, 'IPE_LIBVERSION_') === 0) {
                $this->warnings[] = "The {$name} environment variable is not supported anymore and has been ignored";
            }
        }
    }

    /**
     * @param array<non-empty-string, string|null> $options the option names, and their values
     * @param non-empty-string $deprecatedThing
     *
     * @return void
     */
    private function addOptions(array $options, $deprecatedThing)
    {
        $added = [];
        foreach ($options as $name => $value) {
            if (!isset($this->presentOptions[$name])) {
                $this->presentOptions[$name] = true;
                $added[] = $value === null ? "--{$name}" : "--{$name}={$value}";
            }
        }
        if ($added === []) {
            $this->warnings[] = "{$deprecatedThing} is deprecated and has been ignored since the --" . implode(' and --', array_keys($options)) . (count($options) === 1 ? ' option is' : ' options are') . ' specified';

            return;
        }
        $this->addedOptions = array_merge($this->addedOptions, $added);
        $this->warnings[] = "{$deprecatedThing} is deprecated: use " . implode(' and ', $added) . ' instead';
    }

    /**
     * @param string $argument
     *
     * @return string the new argument (empty string to remove it)
     */
    private function processArgument($argument)
    {
        $matches = null;
        if ($argument === '@composer' || preg_match('/^@composer-(?<version>.+)$/', $argument, $matches)) {
            $options = ['install-composer' => null];
            if ($matches !== null) {
                $options['composer-version'] = $matches['version'];
            }
            $this->addOptions($options, "The '{$argument}' argument");

            return '';
        }
        if ($argument === '@fix_letsencrypt') {
            $this->addOptions(['fix-letsencrypt' => 'yes'], "The '{$argument}' argument");

            return '';
        }
        $converted = $this->convertGitHubShortcut($argument) ?: $this->convertHandle($argument);
        if ($converted !== '' && $converted !== $argument) {
            $this->warnings[] = "The '{$argument}' argument is deprecated: use '{$converted}' instead";

            return $converted;
        }

        return $argument;
    }

    /**
     * Convert vendor/repository@ref to gh:vendor/repository@ref.
     *
     * @param string $argument
     *
     * @return string empty string if $argument is not a GitHub shortcut of the old script
     */
    private function convertGitHubShortcut($argument)
    {
        if (!preg_match('#^[a-zA-Z0-9_.\-]+/[a-zA-Z0-9_.\-]+@.+$#', $argument) || preg_match('#^\.{1,2}/#', $argument)) {
            return '';
        }

        return "gh:{$argument}";
    }

    /**
     * Convert the old names of the extensions, and the old name-version syntax.
     *
     * @param string $argument
     *
     * @return string empty string if $argument is not the handle of a known extension
     */
    private function convertHandle($argument)
    {
        if (strpos($argument, ':') !== false) {
            return '';
        }
        $chunks = explode('-', $argument, 2);
        $handle = strtolower($chunks[0]);
        if (isset(self::$aliases[$handle])) {
            $handle = self::$aliases[$handle];
        }
        if (!isset($this->handles[$handle])) {
            return '';
        }
        if (!isset($chunks[1]) || $chunks[1] === '') {
            return $handle;
        }

        return "{$handle}:" . self::convertVersion($chunks[1]);
    }

    /**
     * @param non-empty-string $version
     *
     * @return string
     */
    private static function convertVersion($version)
    {
        $lowerCase = strtolower($version);
        if (isset(self::$stabilities[$lowerCase])) {
            return '@' . self::$stabilities[$lowerCase];
        }
        // With the old script, ^1.2 meant "the most recent 1.2.x version"
        $matches = null;
        if (!preg_match('/^\^(?<version>[^@]+)(@(?<stability>\w+))?$/', $version, $matches)) {
            return $version;
        }
        $result = "{$matches['version']}.*";
        if (isset($matches['stability'])) {
            $stability = strtolower($matches['stability']);
            $result .= '@' . (isset(self::$stabilities[$stability]) ? self::$stabilities[$stability] : $matches['stability']);
        }

        return $result;
    }
}
