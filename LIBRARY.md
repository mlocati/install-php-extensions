# Using IPE as a library

IPE can be installed with Composer and used from PHP code.
At the moment, IPE can install extensions only on Debian and Alpine Linux.

All the services are available from the application container returned by `\IPE\createApp()`:

```php
$ipe = \IPE\createApp();
```

Every call to `\IPE\createApp()` returns a new container, with its own services and settings: the examples below use the same `$ipe` instance.

## Installing extensions

```php
$extensionsToBeInstalled = [
    // An extension supported by IPE, with optional version requirements
    new \IPE\Extension\Asked\FromHandle(
        'handle_of_a_supported_extension',
        '^1.2'
    ),
    // An extension published as a Composer package
    new \IPE\Extension\Asked\FromComposer(
        'vendor/name',
        '^2.3'
    ),
    // The source code of an extension in a local directory
    new \IPE\Extension\Asked\FromDirectory(
        '/path/to/an/extension/source/code'
    ),
    // An archive with the source code of an extension
    new \IPE\Extension\Asked\FromURL(
        'https://example.com/extension/source/code.tgz',
        'sub/directory/inside/tarball'
    ),
];

$report = $ipe->make(\IPE\Installer\ExtensionsInstaller::class)->installAsked($extensionsToBeInstalled);
```

`installAsked()` resolves the asked extensions (adding the extensions they require, skipping the ones already installed, ...) and installs them.
To inspect the resolved extensions before installing them, you can perform the two steps separately, sharing the same installation state:

```php
$state = $ipe->make(\IPE\Installer\State::class);
$resolvedExtensions = $ipe->make(\IPE\Extension\Asked\Resolver::class)->resolveAskedExtensions($extensionsToBeInstalled, $state);
$report = $ipe->make(\IPE\Installer\ExtensionsInstaller::class)->install($resolvedExtensions, $state);
```

### Options of the resolver

To change how the asked extensions are resolved (before resolving them):

```php
$ipe->extend(
    \IPE\Extension\Asked\Resolver\Options::class,
    static function (\IPE\Extension\Asked\Resolver\Options $options, $app) {
        return $options
            // Skip extensions that IPE doesn't know
            ->withIgnoreUnknownExtensions(true)
            // Skip extensions that IPE is pretty sure can't be installed
            ->withOnUnsupportedExtensions(\IPE\Extension\Asked\Resolver\Options::ONUNSUPPORTED_SKIP)
            // When PHP has been installed by the system packager, never install the extensions from its packages
            ->withSystemPackages(\IPE\Extension\Asked\Resolver\Options::SYSTEMPACKAGES_NEVER)
        ;
    }
);
```

### Options of the extensions

The last argument of the constructors of the `\IPE\Extension\Asked\From...` classes are the options of the extension (the same that you can specify with `--handle:option-name=value` when running IPE as a program):

```php
new \IPE\Extension\Asked\FromHandle(
    'handle',
    '',
    [
        'option-with-value' => 'value',
        'option-without-value' => true,
    ]
);
```

The options of the system dependencies (`--dependency-handle:option-name=value` when running IPE as a program) are the second argument of `installAsked()`:

```php
$report = $ipe->make(\IPE\Installer\ExtensionsInstaller::class)->installAsked(
    $extensionsToBeInstalled,
    [
        'dependency-handle' => [
            'option-name' => 'value',
        ],
    ]
);
```

When you resolve and install the extensions separately, they are specified in the installation state:

```php
$state = $ipe->make(\IPE\Installer\State::class)
    ->withSystemDependencyOptions([
        'dependency-handle' => [
            'option-name' => 'value',
        ],
    ])
;
```

### Enabling the extensions

By default, the installed extensions are enabled.
You can tell IPE not to enable them, and you can keep the cache of the system packager (for example, the APT lists):

```php
$report = $ipe->make(\IPE\Installer\ExtensionsInstaller::class)->installAsked(
    $extensionsToBeInstalled,
    // The options of the system dependencies
    [],
    // Disable the extensions at the end of the installation
    false,
    // Don't delete the cache of the system packager
    false
);
```

The `iniFiles` of the installation report tell which ini files are not active.
To enable (or disable) them later:

```php
$ipe->make(\IPE\Installer\Enabler::class)->enableIniFile('/usr/local/etc/php/conf.d/docker-php-ext-gd.ini');
```

By default, the ini files are named `{priority}-{name}.ini` (for example `80-apcu.ini`), and before enabling an extension IPE checks that PHP loads it correctly.
To change that:

```php
$ipe->extend(
    \IPE\Installer\Enabler\Options::class,
    static function (\IPE\Installer\Enabler\Options $options, $app) {
        return $options
            // Other names of the ini files ({priority} must come before {name})
            ->withIniBasename('php-{priority}-{name}')
            // Don't check that PHP loads the extensions
            ->withSkipCheck(true)
        ;
    }
);
```

With the PHP packages of Debian and Ubuntu, the ini files are created in the `mods-available` directory (for example `/etc/php/8.3/mods-available/apcu.ini`) and they are enabled for all the SAPIs with `phpenmod`.
In this case, the names of the ini files can't be changed.

### Installation report

`installAsked()` and `install()` return an `\IPE\Installer\Report` with:

- the installed extensions (how they have been installed, their module and ini files, ...);
- the extensions skipped while resolving the asked ones (for example, because they are already installed);
- the system libraries that IPE compiled from their source code;
- the system packages that have been installed (the ones kept after the installation and the ones already removed) and the ones that were already installed.

The report can be serialized to JSON (it's what IPE writes with `--save-report-to` when running as a program):

```php
$json = \IPE\Service\JSON::encode($report, true);
```

## Configuration

The settings described in this section must be applied before using IPE to resolve or install extensions: the services are created when they are first needed.

### Logging

By default, IPE doesn't log anything.
To use a PSR-3 logger (it can be changed at any time, even after the services have been created):

```php
$ipe->make(\IPE\Logger::class)->setTarget($yourPsr3Logger);
```

You can also use `\IPE\Logger\Console`, which prints the messages to the standard output and error.

### Filesystem

By default, IPE works on the local filesystem.
You can implement your own `\IPE\Filesystem` (for example, a filesystem that uses SSH to install PHP extensions on a remote server):

```php
$ipe->singleton(\IPE\Filesystem::class, static function () {
    return new YourFilesystem();
});
```

### PHP instance

By default, IPE uses the PHP installation that's executed by the `php` command.
To use another PHP installation:

```php
$ipe->singleton(
    \IPE\PHPInfo::class,
    static function ($app) {
        return $app->call(
            [\IPE\PHPInfo::class, 'fromPHPBin'],
            ['phpBin' => '/path/to/bin/php']
        );
    }
);
```

### Downloads

To cache the downloaded files, to use a proxy, or to skip the verification of the TLS certificates:

```php
$ipe->when(\IPE\Filesystem\Downloader\Factory::class)
    ->needs('$config')
    ->give(static function () {
        return [
            'cacheDir' => '/path/to/cache/directory',
            // An integer (seconds), or a number followed by s, m, h or d (0: no limit)
            'cacheMaxAge' => '1d',
            'proxy' => 'http://proxy.example.com:8080',
            'insecure' => true,
        ];
    })
;
```

### Composer repositories

The extensions published as Composer packages are looked for in the registered Composer repositories, starting from the ones with the highest priority.
By default, only packagist.org is registered (with the priority `\IPE\ComposerRepository\Registry::PACKAGIST_PRIORITY`, lower than the default priority of the other repositories).
IPE supports the version 2 of the Composer repository protocol (the one used by packagist.org, Private Packagist and Satis).

```php
$ipe->make(\IPE\ComposerRepository\Registry::class)
    // Searched before packagist.org (the default priority is 0)
    ->register(new \IPE\ComposerRepository('https://satis.example.com'))
    // Searched before the previous one
    ->register(new \IPE\ComposerRepository('https://repo.example.com', 10))
    // Searched only for the packages that packagist.org doesn't provide
    ->register(new \IPE\ComposerRepository('https://fallback.example.com', \IPE\ComposerRepository\Registry::PACKAGIST_PRIORITY - 1))
;
```

When a repository provides a package, the repositories with a lower priority are not searched for that package.
The repositories with the same priority are searched in the order they have been registered.

To not use packagist.org at all:

```php
$ipe->make(\IPE\ComposerRepository\Registry::class)->unregister(\IPE\ComposerRepository\Registry::PACKAGIST_URL);
```

### Compilation options

By default, IPE compiles the PHP extensions and the system libraries using all the available processors, and strips the debug symbols from the PHP extensions.
To change that:

```php
$ipe->singleton(\IPE\Installer\BuildOptions::class, static function () {
    return (new \IPE\Installer\BuildOptions())
        // 0: one job per available processor
        ->withParallelJobs(2)
        // TYPE_OPTIMIZED (default), TYPE_WITH_SYMBOLS or TYPE_DEBUG
        ->withType(\IPE\Installer\BuildOptions::TYPE_WITH_SYMBOLS)
    ;
});
```

## snuffleupagus

The `--snuffleupagus:allow-ipe` option is not available when using IPE as a library: it's up to you to handle the rules of snuffleupagus.
Since the default rules of snuffleupagus block IPE, run the PHP processes that use IPE with `-d sp.configuration_file=` once snuffleupagus is installed.
