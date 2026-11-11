# Notes for the maintainers

## Releases

Tags and releases must be created only with the [Release workflow](.github/workflows/release.yml): don't create or push tags manually, and don't create releases from the GitHub web interface.

To publish a new release, run the Release workflow from the Actions tab, on the `main` branch, specifying the tag to be created.

The tag must be:

- a version made of three non-negative numbers without leading zeros (for example `1.2.3`), optionally followed by `-alpha`, `-beta` or `-RC` and an optional number (for example `1.2.3-alpha`, `1.2.3-beta2`, `1.2.3-RC1`);
- greater than the versions of the existing tags.

The release is a pre-release if the version has a suffix (`-alpha`, `-beta`, `-RC`); otherwise, it's marked as the latest release.

The workflow:

1. checks the tag;
2. builds the `install-php-extensions` PHAR file (not compressed) and the `install-php-extensions-gz` PHAR file (compressed with gzip), with the version in the line `const IPE_VERSION = '';` of `bin/install-php-extensions` (that line must not be changed);
3. checks that the PHAR files report the right version;
4. updates the automatically generated sections of `README.md` with `scripts/update-readme.php`: if they changed, it commits them and pushes the commit to the `main` branch;
5. builds the multi-platform `ghcr.io/mlocati/install-php-extensions` Docker image with the two PHAR files, tagged with the version and (except for pre-releases) with the major and minor versions, the major version (from version 1) and `latest`, and pushes it to the GitHub Container Registry;
6. creates the release (and the tag, on the commit created in the step 4, or on the commit the workflow runs on) with the two PHAR files attached.

Since the workflow pushes to the `main` branch, the rules that protect the branch must allow it.

The repository has release immutability enabled: once published, the tag and the files of a release can't be changed.

## CONTRIBUTING.md

When the Tests workflow runs on the `main` branch (except for pull requests), after PHPUnit it updates the automatically generated sections of `CONTRIBUTING.md` with `scripts/update-contributing.php`: if they changed, it commits them and pushes the commit to the `main` branch (so the rules that protect the branch must allow it).
