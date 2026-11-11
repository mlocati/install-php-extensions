# Notes for AI agents

Read [`CONTRIBUTING.md`](CONTRIBUTING.md) before changing anything: it explains which parts of `README.md` and `CONTRIBUTING.md` must not be edited, and how to choose the extensions tested in pull requests (with `Test:` lines in the commit messages).

Other things to know:

- `bin/`, `src/` and `test/` must be compatible with PHP 5.5 (see the exclusions in `phpstan.php55.neon.dist` and in the `php-syntax` job of `.github/workflows/tests.yml`), while the scripts in `scripts/` require PHP 8.5.
- `composer run test` checks the coding style, runs PHPUnit and runs PHPStan: all of them must pass.
- `src/CLI/` and `src/Dev/` are not part of the library distributed with Composer; `src/Dev/` is not included in the PHAR files either.
- [`LIBRARY.md`](LIBRARY.md) describes how to use IPE as a PHP library, and [`MAINTAINERS.md`](MAINTAINERS.md) how releases are created.
