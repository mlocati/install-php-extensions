# Contributing

Pull requests are welcome!

## Generated sections

Some sections of `README.md` are generated automatically when a new version is released, and some sections of `CONTRIBUTING.md` are generated automatically when the `main` branch changes.

They are delimited by lines like `<!-- START OF ... -->` and `<!-- END OF ... -->`.

Please don't change them in your pull requests (the tests check it).

## Testing extensions in pull requests

The tests of a pull request install some PHP extensions in all the official Docker PHP images where they are supported.

The supported extensions whose files have been changed are tested automatically: `src/Extensions/<handle>.php`, `test/extensions/<handle>.php` and the files in `test/extensions/<handle>/`.

You can test other extensions by adding to the messages of the commits lines like these:

```
Test: xdebug
Test: apcu, igbinary redis
Test: memcached+msgpack
Test: vendor/name
```

- you can separate the extensions with spaces, commas or semicolons
- use `+` to install more extensions together
- you can specify supported extensions (`xdebug`), PECL extensions (`name`) and Composer packages (`vendor/name`)

By default, the extensions are tested with all the PHP versions they support: for the extensions that are not supported by IPE, this means all the PHP versions.
You can limit the PHP versions with a `Test-PHP:` line, which applies to all the extensions listed in the same commit:

```
Test: vendor/name
Test-PHP: 8.3 8.4
```

For the extensions not supported by IPE we strongly suggest using `Test-PHP:`: otherwise the tests will fail with the PHP versions that the extension doesn't support.

Likewise, you can limit the distros of the Docker images with a `Test-Distro:` line, using the names listed in [Available distros](#available-distros) (`*` matches any sequence of characters):

```
Test: xdebug
Test-Distro: alpine3.24 trixie *-sury
```

The commits are inspected starting from the most recent one: if an extension (or a group of extensions joined with `+`) is listed in more than one commit, only the most recent commit is considered (including its `Test-PHP:` and `Test-Distro:` lines); this applies also to the extensions tested automatically because their files have been changed.
If you write `-STOP-` in a `Test:` line, the older commits won't be inspected, and the extensions whose files have been changed won't be tested automatically:

```
Test: xdebug -STOP-
```

The extensions that take long to be compiled (listed in `src/Dev/SlowExtensions.php`) are tested in their own jobs.

If a pull request changes the code of IPE (like `bin/install-php-extensions` or the PHP files in `src`), how it's built, or how the extensions are tested, at least one extension must be tested, otherwise the tests fail.
If no extension needs to be tested, write `Test: -STOP-`.

### Available distros

The distros whose names end with `-apk` or `-apt` use the PHP packages of the distro, and the ones ending with `-sury` use the PHP packages of [packages.sury.org](https://packages.sury.org/php/); the other ones use the official Docker PHP images.

<!-- START OF TEST DISTROS -->
<!-- END OF TEST DISTROS -->
