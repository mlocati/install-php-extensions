# install-php-extensions

[![Tests](https://github.com/mlocati/install-php-extensions/actions/workflows/tests.yml/badge.svg)](https://github.com/mlocati/install-php-extensions/actions/workflows/tests.yml)

> [!WARNING]
> IPE is still in alpha: its options, its behavior and the library API may change without notice, and some extensions may not install correctly.
> For production use, consider [docker-php-extension-installer](https://github.com/mlocati/docker-php-extension-installer) for the time being.

install-php-extensions (IPE) installs PHP extensions with a single command: it chooses a version of the extensions that is compatible with your PHP, installs the system packages they need, compiles them and enables them.

It works on:

- the official [Docker PHP images](https://hub.docker.com/_/php), both the Debian and the Alpine variants;
- Debian 9 (Stretch) or later, with PHP installed from the Debian packages or from the [repository of Ondřej Surý](https://packages.sury.org/php/);
- Ubuntu 16.04 or later, with PHP installed from the Ubuntu packages or from the packages of Ondřej Surý (his [PPA](https://launchpad.net/~ondrej/+archive/ubuntu/php) or his [repository](https://packages.sury.org/php/));
- Alpine Linux 3.15 or later, with PHP installed from the Alpine packages.

When PHP has been installed by the system packager (APT or APK), IPE may install the extensions from its packages whenever they are available (see the `--system-packages` option).

    install-php-extensions gd xdebug

## Getting IPE

IPE is distributed as a PHAR file attached to the [releases](https://github.com/mlocati/install-php-extensions/releases), in two variants:

- `install-php-extensions`: not compressed;
- `install-php-extensions-gz`: smaller, but it requires the zlib PHP extension (which should be always available in the official Docker PHP images).

Both the PHAR files are also available in the `ghcr.io/mlocati/install-php-extensions` Docker image.

IPE requires the phar PHP extension.
It's always available in the official Docker PHP images and with the Debian and Ubuntu packages, but the Alpine packages provide it separately: install the package with the name of the PHP package followed by `-phar` (for example, `apk add php84-phar`).
Also, on Alpine the `php` command is available only when PHP is installed with the default PHP package of the Alpine version (for example, `php85` for Alpine 3.24).
With the other PHP packages, you have to run IPE with the PHP command of that package: for example, with `php84` you have to use `php84 /usr/local/bin/install-php-extensions gd xdebug`.

### Downloading IPE with `ADD`

```Dockerfile
FROM php:8.4-cli

ADD --chmod=0755 https://github.com/mlocati/install-php-extensions/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions gd xdebug
```

### Downloading IPE with `curl`

```Dockerfile
FROM php:8.4-cli

RUN curl -sSLf \
        -o /usr/local/bin/install-php-extensions \
        https://github.com/mlocati/install-php-extensions/releases/latest/download/install-php-extensions-gz && \
    chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions gd xdebug
```

The same commands work outside Docker too (IPE must be run as root, since it installs system packages).

### Copying IPE from the Docker image

```Dockerfile
FROM php:8.4-cli

COPY --from=ghcr.io/mlocati/install-php-extensions:latest /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions gd xdebug
```

The image is available for all the platforms of the official Docker PHP images.
It also contains the compressed variant, `/usr/bin/install-php-extensions-gz` (which requires the zlib PHP extension):

```Dockerfile
COPY --from=ghcr.io/mlocati/install-php-extensions:latest /usr/bin/install-php-extensions-gz /usr/local/bin/install-php-extensions
```

### Using a specific version of IPE

The URLs and the image above provide the most recent release of IPE, so a new release may change what your builds do.
To always use the same version, replace `latest/download` with `download/<version>`, where `<version>` is the version of a [release](https://github.com/mlocati/install-php-extensions/releases) (for example `0.0.1`):

```Dockerfile
ADD --chmod=0755 https://github.com/mlocati/install-php-extensions/releases/download/0.0.1/install-php-extensions /usr/local/bin/
```

Likewise, replace the `latest` tag of the Docker image with the version (for example `0.0.1`).
The image is also tagged with the major and minor versions (for example `1.2` for the most recent `1.2.x` release) and, starting from version 1, with the major version (for example `1`).

```Dockerfile
COPY --from=ghcr.io/mlocati/install-php-extensions:0.0.1 /usr/bin/install-php-extensions /usr/local/bin/
```

Pre-releases (like `1.0.0-beta1`) are never provided by the `latest` URLs and tags: you can use them only by specifying their version.

### Building IPE

You can also build the PHAR files from the source code (you need PHP with the `phar.readonly` setting turned off, which the `build-phar` Composer script does for you):

```sh
git clone https://github.com/mlocati/install-php-extensions.git
cd install-php-extensions
composer install --no-dev
# Not compressed
composer run-script build-phar -- install-php-extensions
# Compressed with gzip
composer run-script build-phar -- --compress=gz install-php-extensions-gz
```

## Specifying the extensions

Every argument identifies an extension to be installed:

| Argument | Example | Description |
|---|---|---|
| Handle | `xdebug` | One of the [supported PHP extensions](#supported-php-extensions): depending on the extension and on the PHP version, IPE takes it from the PHP source code, from Packagist/Composer or from PECL |
| Composer package | `vendor/name` | A PHP extension published as a Composer package on Packagist (like the ones installed by PIE) |
| GitHub shortcut | `gh:organization/repository@ref` | The source code of a GitHub repository, where `ref` is a commit, a tag or a branch |
| URL | `https://example.com/extension.tar.gz` | An archive containing the source code of the extension |
| Local directory | `./path/to/extension` | A directory containing the source code of the extension (the path must start with `/`, `./` or `../`) |

If the source code is not in the root directory of the GitHub repository or of the archive, append `#` followed by its path, for example:

- `gh:organization/repository@main#path/to/extension`
- `https://example.com/archive.tar.gz#path/to/extension`

## Specifying the versions

By default, IPE installs the most recent stable version of the extensions compatible with your PHP (some extensions use a different default for some PHP versions).

To choose a version of an extension specified with its handle or with its Composer package name, append `:` followed by the version requirements, using the [Composer syntax](https://getcomposer.org/doc/articles/versions.md#writing-version-constraints):

> [!WARNING]
>
> The arguments containing spaces or characters like `*`, `<`, `>` and `|` must be quoted, for example `'xdebug:3.3.*'` or `'xdebug:>=3.2 <3.4'`.

| Argument | Installed version |
|---|---|
| `xdebug:^3.3` | The most recent 3.x version, starting from 3.3.0 |
| `xdebug:~3.3.0` or `xdebug:3.3.*` | The most recent 3.3.x version |
| `xdebug:3.3.2` | Exactly 3.3.2 |
| `xdebug:3.4.0beta1` | Exactly 3.4.0beta1 |
| `xdebug:@beta` | The most recent version whose stability is beta or better |
| `xdebug:^3.4@RC` | The most recent 3.x version, starting from 3.4.0, whose stability is RC or better |

Please remark that:

- the stability levels are, from the lowest to the highest, `dev`, `alpha`, `beta`, `RC` and `stable`: without `@`, only stable versions are considered
- a version without operators is an exact version: `xdebug:3.3` means 3.3.0, not the most recent 3.3.x version
- exact versions are installed whatever their stability, and without checking if they are compatible with your PHP
- for the extensions bundled with PHP, the version is the one of PHP: if it doesn't satisfy the requirements, IPE looks for the extension in the other sources

## Supported PHP extensions

The extensions marked with &#x24D8; have some [notes](#extension-notes).

<!-- START OF SUPPORTED PHP EXTENSIONS TABLE -->
<!-- Generated by scripts/update-readme.php: do not edit -->

| Extension | PHP 8.6 | PHP 8.5 | PHP 8.4 | PHP 8.3 | PHP 8.2 | PHP 8.1 | PHP 8.0 | PHP 7.4 | PHP 7.3 | PHP 7.2 | PHP 7.1 | PHP 7.0 | PHP 5.6 | PHP 5.5 |
|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| amqp |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| apcu |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| apcu_bc |  |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; |  |  |
| ast | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| bcmath | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| bitset | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| blackfire | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| brotli | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| bz2 | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| calendar | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| cassandra[&#x24D8;](#cassandra) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |
| cmark |  |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; |  |  |
| csv | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |
| dba | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| ddtrace[&#x24D8;](#ddtrace) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| decimal |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| ds |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| ecma_intl[&#x24D8;](#ecma_intl) |  |  |  | &check; | &check; |  |  |  |  |  |  |  |  |  |
| enchant | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| ev |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| event | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| excimer |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |
| exif | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| ffi | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |
| ftp | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| gd[&#x24D8;](#gd) | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| gearman |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| geoip |  |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| geos[&#x24D8;](#geos) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| geospatial | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| gettext | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| gmagick |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| gmp | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| gnupg |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| grpc |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| http |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| igbinary |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| imagick |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| imap |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| inotify | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| interbase |  |  |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; |
| intl | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| ion |  | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |  |
| ioncube_loader |  | &check; | &check; | &check; | &check; | &check; |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| ip2location | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |
| jsmin |  |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| json_post |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| jsonpath | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |
| judy[&#x24D8;](#judy) | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |
| ldap | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| luasandbox |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| lz4[&#x24D8;](#lz4) | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |
| lzf |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| mailparse | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| maxminddb | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |
| mbstring | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| mcrypt |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| md4c | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |
| memcache |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| memcached |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| memprof[&#x24D8;](#memprof) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| mongo |  |  |  |  |  |  |  |  |  |  |  |  | &check; | &check; |
| mongodb | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| mosquitto |  |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| msgpack |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| mssql |  |  |  |  |  |  |  |  |  |  |  |  | &check; | &check; |
| mysql |  |  |  |  |  |  |  |  |  |  |  |  | &check; | &check; |
| mysqli | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| newrelic[&#x24D8;](#newrelic) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| nsq |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| oauth |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| oci8[&#x24D8;](#oci8) | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| odbc | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| opcache | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| opencensus |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| openssl | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| openswoole |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |
| opentelemetry | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |
| operator[&#x24D8;](#operator) |  | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |  |  |
| parallel[&#x24D8;](#parallel) | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |
| parle[&#x24D8;](#parle) |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| pcntl | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pcov |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| pdo_dblib | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pdo_firebird | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pdo_mysql | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pdo_oci[&#x24D8;](#pdo_oci) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pdo_odbc | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pdo_pgsql | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pdo_snowflake[&#x24D8;](#pdo_snowflake) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| pdo_sqlsrv[&#x24D8;](#pdo_sqlsrv) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| pgsql | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| phalcon |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |
| php_trie |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |
| phpy[&#x24D8;](#phpy) |  | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |  |
| pkcs11 |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |
| pq |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| propro |  |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| protobuf |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pspell |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| psr | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pthreads[&#x24D8;](#pthreads) |  |  |  |  |  |  |  |  |  |  |  | &check; | &check; | &check; |
| raphf |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| rdkafka |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| recode[&#x24D8;](#recode) |  |  |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; |
| redis |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| relay[&#x24D8;](#relay) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |
| rrd |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| saxon[&#x24D8;](#saxon) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| seasclick[&#x24D8;](#seasclick) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| seaslog |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| shmop | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| simdjson[&#x24D8;](#simdjson) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |
| smbclient |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| snappy | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| snmp | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| snuffleupagus[&#x24D8;](#snuffleupagus) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| soap | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sockets | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sodium[&#x24D8;](#sodium) | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |
| solr |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sourceguardian |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| spx |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sqlsrv[&#x24D8;](#sqlsrv) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| ssh2 |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| statgrab[&#x24D8;](#statgrab) | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |
| stomp |  |  | &check; | &check; | &check; |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| swoole[&#x24D8;](#swoole) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sybase_ct |  |  |  |  |  |  |  |  |  |  |  |  | &check; | &check; |
| sync |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sysvmsg | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sysvsem | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sysvshm | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| tensor |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |
| tideways |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| tidy | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| timezonedb | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| translit | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| uopz |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| uploadprogress |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| uuid | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| uv | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |
| v8js[&#x24D8;](#v8js) |  | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |
| vips[&#x24D8;](#vips) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| vld | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| wddx |  |  |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; |
| wikidiff2[&#x24D8;](#wikidiff2) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |
| xattr | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| xdebug |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| xdiff | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| xhprof | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| xlswriter |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| xmldiff |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| xmlrpc |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| xpass[&#x24D8;](#xpass) | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |
| xsl | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| yac | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| yajl_stream |  | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |  |  |
| yaml |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| yar |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| zephir_parser | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| zip | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| zmq |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| zookeeper[&#x24D8;](#zookeeper) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| zstd | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |

*Number of supported extensions: 165*

<!-- Generated by scripts/update-readme.php: do not edit -->
<!-- END OF SUPPORTED PHP EXTENSIONS TABLE -->


## Command line options

These options apply to all the extensions (the options of specific extensions are listed in the [extension notes](#extension-notes)):

<!-- START OF GENERAL CLI OPTIONS -->
<!-- Generated by scripts/update-readme.php: do not edit -->

| Option | Description | Default |
|---|---|---|
| `--php[=]value` | The php command (or the path of the PHP CLI binary) for which the extensions should be installed | the PHP running install-php-extensions |
| `--from-composer-json[=value]` | Also install the extensions listed in the require and require-dev sections of a composer.json file | composer.json in the current directory |
| `--from-composer-lock[=value]` | Also install the extensions required by the project and by its dependencies, as listed in a composer.lock file | composer.lock in the current directory |
| `--no-dev` | Ignore the development requirements of the project in --from-composer-json and --from-composer-lock |  |
| `--ignore-unknown-extensions` | Ignore unknown extensions instead of aborting |  |
| `--unsupported-extensions[=]<abort\|force\|skip>` | What to do with extensions that are known not to work in the current environment. | `abort` |
| `--build-type[=]<optimized\|with-symbols\|debug>` | How to compile the extensions: optimized (debug symbols stripped), with-symbols (optimized, keeping the debug symbols), debug (no optimizations, with debug symbols) | `optimized` |
| `--cpus[=]value` | Number of parallel processors to be used while compiling (0: all the available ones) | all the available ones |
| `--skip-check` | Don't check that PHP loads the extensions correctly before enabling them |  |
| `--dont-enable` | Disable the extensions at the end of the installation (they can be enabled later with --enable) |  |
| `--enable[=]value` | Enable the extension loaded by this ini file, by uncommenting its extension= and zend_extension= lines (can be specified multiple times) |  |
| `--disable[=]value` | Disable the extension loaded by this ini file, by commenting out its extension= and zend_extension= lines (can be specified multiple times) |  |
| `--ini-basename[=]value` | The name of the ini files that enable the extensions (without .ini): use the {priority} and {name} placeholders, or compat for the names used by the official Docker PHP images (see https://github.com/mlocati/install-php-extensions#names-of-the-ini-files) | compat in the official Docker PHP images, {priority}-{name} otherwise |
| `--keep-systempackager-cache[=]<yes\|no>` | Keep the system packager (APT/APK) cache after installing PHP extensions | no in the official Docker PHP images, yes otherwise |
| `--apt-debian-fix-archived[=]<yes\|no>` | Update the APT repositories for archived Debian systems | yes in the official Docker PHP images, no otherwise |
| `--apt-debian-archiveurl-default[=]value` | The URL for APT for archived Debian systems | `http://archive.debian.org/debian` |
| `--apt-debian-archiveurl-security[=]value` | The security URL for APT for archived Debian systems | `http://archive.debian.org/debian-security` |
| `--apt-debian-archiveurl-updates[=]value` | The updates URL for APT for archived Debian systems | `http://archive.debian.org/debian` |
| `--no-proxy` | Don't use the proxy configured in the HTTP_PROXY (or http_proxy) environment variable |  |
| `--download-insecure` | Don't verify the TLS certificates of the servers when downloading files |  |
| `--download-retry[=]value` | How many times a failed download should be retried | `0` |
| `--download-delay[=]value` | The number of seconds to wait before retrying a failed download | `5` |
| `--fix-letsencrypt[=]<yes\|no>` | Fix the root CA certificate of Let's Encrypt on old systems | yes in the official Docker PHP images, no otherwise |
| `--cache-dir[=]value` | The path to an existing writable directory for dowloaded files cache |  |
| `--cache-maxage[=]value` | The maximum age of cached files (e.g. '30' or '30s' for 30 seconds, '8h' for 8 hours, '7d' for 7 days). With 0 they never expire, so new versions of the extensions won't be detected | `0` |
| `--temp-dir[=]value` | The path to an existing writable directory where temporary files should be stored |  |
| `--verbose` | Print debug information |  |
| `--save-report-to[=]value` | Write to this file a JSON report of what has been installed, and how |  |
| `--save-persistent-packages-to[=]value` | Write to this file the space-separated list of the system packages that have been installed and kept after the installation |  |
| `--save-volatile-packages-to[=]value` | Write to this file the space-separated list of the system packages that have been installed and removed after the installation |  |
| `--install-composer` | Install Composer |  |
| `--composer-path[=]value` | The directory (or full path) where Composer should be installed (must be specified on Windows) |  |
| `--composer-version[=]value` | The Composer version to be installed (stable, 1, 2.2, 2, 2.9.1, ...) | `stable` |

<!-- Generated by scripts/update-readme.php: do not edit -->
<!-- END OF GENERAL CLI OPTIONS -->

### Names of the ini files

`--ini-basename` sets the name of the ini files that enable the extensions (without `.ini`), with these placeholders:

- `{priority}`: the priority of the extension, from 10 to 99 (80 for most extensions), so that the extensions are loaded in the right order
- `{name}`: the name of the extension

Each placeholder must be used once, `{priority}` before `{name}`.
The other characters can be letters, digits, `_`, `-` and `.`, but the name can't start or end with a dot.
For example, with the default `{priority}-{name}` the calendar extension is enabled by `80-calendar.ini`.

In the official Docker PHP images the default is `compat`: the ini files are named like the ones created by `docker-php-ext-enable` (`docker-php-ext-<name>.ini`), or `xx-php-ext-<name>.ini` for the extensions that must be loaded after the others.


## Extension notes

Some extensions have limitations, or specific command line options:

<!-- START OF EXTENSION NOTES -->
<!-- Generated by scripts/update-readme.php: do not edit -->

### cassandra

Not available on Debian 8 to 9 (the extension can't be compiled with the compiler or the libraries of this system)

### ddtrace

Not available on Debian 8 (the extension can't be installed on this system)

Command line options:

| Option | Description | Default |
|---|---|---|
| `--ddtrace:enable-appsec` | Enable the application security module |  |
| `--ddtrace:enable-profiling` | Enable the profiling module |  |

### ecma_intl

Not available on:

- Debian 10 to 11 (the extension is not compatible with the version of the ICU library of this system)
- Debian 13 (the extension is not compatible with the version of the ICU library of this system)
- Alpine 3.22 to 3.24 (the extension is not compatible with the version of the ICU library of this system)

### gd

Command line options:

| Option | Description | Default |
|---|---|---|
| `--gd:without-avif` | Skip the AVIF support if it would require compiling libaom, libyuv and libavif |  |

### geos

Not available on:

- Alpine 3.0 to 3.8 (this system doesn't provide the GEOS library)
- Alpine 3.9 to 3.10 (the extension can't be compiled with the compiler or the libraries of this system)

### judy

Not available on Alpine 3.13 to 3.15 (the extension can't be compiled with the compiler or the libraries of this system)

### lz4

Not available on:

- Alpine 3.0 to 3.8 (the version of the LZ4 library of this system is too old)
- Debian 8 (the version of the LZ4 library of this system is too old)

### memprof

Not available on:

- thread-safe (ZTS) builds of PHP
- Alpine 3.0 to 3.8 (this system doesn't provide the Judy library)
- Alpine 3.9 to 3.15 (the extension can't be compiled with the compiler or the libraries of this system)

### newrelic

Not available on thread-safe (ZTS) builds of PHP

Command line options:

| Option | Description | Default |
|---|---|---|
| `--newrelic:install-daemon` | Also install the New Relic daemon |  |
| `--newrelic:keep-log` | Keep the log file of the New Relic installer |  |

### oci8

Command line options:

| Option | Description | Default |
|---|---|---|
| `--oci8:with-oci8[=]value` | The kind and the path of the Oracle client |  |

### operator

Not available on thread-safe (ZTS) builds of PHP

### parallel

Not available on non-thread-safe (NTS) builds of PHP

### parle

Not available on Debian 8 (the extension can't be compiled with the compiler or the libraries of this system)

### pdo_oci

Command line options:

| Option | Description | Default |
|---|---|---|
| `--pdo_oci:with-pdo-oci[=]value` | The kind and the path of the Oracle client |  |

### pdo_snowflake

Not available on:

- Alpine (the vendor only supports glibc)
- Debian 8 (the extension requires OpenSSL 1.1)

### pdo_sqlsrv

Not available on Alpine 3.7 to 3.8 (the Microsoft ODBC Driver requires unixODBC 2.3.6 or later)

### phpy

Not available on Debian 10 (the version of Python of this system is too old)

### pthreads

Not available on non-thread-safe (NTS) builds of PHP

### recode

Not available on Alpine 3.11 to 3.15 (the recode 3.7 library provided by this system does not work)

### relay

Not available on:

- Alpine 3.10 to 3.16 with PHP ZTS (the vendor doesn't publish ZTS builds for this system)
- Alpine 3.17 with PHP ZTS (the ZTS build requires the ck library, which is not available)
- Alpine 3.22 to 3.24 with PHP ZTS (the ZTS build requires libhiredis 1.1.0, which is not available)
- Debian 10 to 11 with PHP ZTS (the vendor doesn't publish ZTS builds for this system)
- Debian 12 with PHP ZTS (the ZTS build requires libhiredis 1.1.0, which is not available)

### saxon

Not available on Alpine with PHP 7 (the source code of SaxonC does not compile (int64_t is not defined))

Command line options:

| Option | Description | Default |
|---|---|---|
| `--saxon:edition[=]value` | The edition to be installed: EE (Enterprise), PE (Professional) or HE (Home) | `EE` |

### seasclick

Not available on Alpine 3.23 to 3.24 (the extension can't be compiled with the compiler or the libraries of this system)

### simdjson

Not available on Debian 8 to 9 (the extension can't be compiled with the compiler or the libraries of this system)

### snuffleupagus

IPE installs snuffleupagus with its default rules, which also block IPE itself.
To run IPE again after snuffleupagus has been installed, you have two options:

- run it with the rules disabled: `php -d sp.configuration_file= /usr/local/bin/install-php-extensions ...`
- install snuffleupagus with the `--snuffleupagus:allow-ipe` option.

The `--snuffleupagus:allow-ipe` option adds to the rules some exceptions for the files of IPE, using the path where IPE is when it installs snuffleupagus (if you later run IPE from another path, it will be blocked).
Since snuffleupagus ignores the `function_r()` rules for the functions that have `function()` rules, IPE adds an exception for every function mentioned in the default rules.

Please remark that, with this option, any PHP code can bypass the rules of snuffleupagus by including the files of IPE and using its classes: delete IPE when you no longer need it (or remove the exceptions between `# BEGIN install-php-extensions rules` and `# END install-php-extensions rules` at the beginning of the `snuffleupagus.rules` file).

The option is available only when running IPE as a program (`bin/install-php-extensions` or the phar): when using IPE as a library, it's up to you to handle the rules of snuffleupagus (see [LIBRARY.md](LIBRARY.md#snuffleupagus)).

Not available on thread-safe (ZTS) builds of PHP

Command line options:

| Option | Description | Default |
|---|---|---|
| `--snuffleupagus:allow-ipe` | Let install-php-extensions bypass the default rules (any PHP code could do the same by using its files: delete install-php-extensions when you no longer need it) |  |

### sodium

Not available on Debian 8 (libsodium 1.0.0 is too old - at least 1.0.9 is required)

### sqlsrv

Not available on Alpine 3.7 to 3.8 (the Microsoft ODBC Driver requires unixODBC 2.3.6 or later)

### statgrab

Not available on Alpine 3.12 to 3.16 (the extension can't be compiled with the compiler or the libraries of this system)

### swoole

Command line options:

| Option | Description | Default |
|---|---|---|
| `--swoole:without-iouring` | Don't enable the io_uring support |  |

### v8js

Not available on:

- Debian 10 to 11 (the version of the V8 library of this system is too old)
- Alpine 3.12 to 3.18 (the version of the V8 library of this system is too old)

### vips

Not available on:

- Alpine 3.9 (the extension can't be compiled with the compiler or the libraries of this system)
- Debian 8 (the extension can't be compiled with the compiler or the libraries of this system)

### wikidiff2

Not available on Debian 8 to 9 (the extension can't be compiled with the compiler or the libraries of this system)

### xpass

Not available on Debian 10 (the extension can't be compiled with the compiler or the libraries of this system)

### zookeeper

Not available on thread-safe (ZTS) builds of PHP 8.2, 8.3, 8.4, 8.5

<!-- Generated by scripts/update-readme.php: do not edit -->
<!-- END OF EXTENSION NOTES -->


## System dependencies

Some system dependencies can be customized with these command line options:

<!-- START OF SYSTEM DEPENDENCY OPTIONS -->
<!-- Generated by scripts/update-readme.php: do not edit -->

| Dependency | Option | Description | Default |
|---|---|---|---|
| lib-aspell | `--lib-aspell:languages[=]value` | The comma-separated list of the languages of the dictionaries to be installed | `en` |
| lib-icu | `--lib-icu:en-only` | Install a smaller, English-only, ICU library on Alpine 3.16 and later |  |
| oracle-instantclient | `--oracle-instantclient:basic` | Install the Basic package instead of the much smaller Basic Light one |  |

<!-- Generated by scripts/update-readme.php: do not edit -->
<!-- END OF SYSTEM DEPENDENCY OPTIONS -->


## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md).


## Usage as a PHP library

See [`LIBRARY.md`](LIBRARY.md).


## Do you want to really say thank you?

You can offer me a [monthly coffee](https://github.com/sponsors/mlocati) or a [one-time coffee](https://paypal.me/mlocati) &#x1F609;
