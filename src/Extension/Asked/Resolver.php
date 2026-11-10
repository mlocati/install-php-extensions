<?php

namespace IPE\Extension\Asked;

use Composer\Semver\Semver;
use IPE\Exception;
use IPE\Extension;
use IPE\Extension\Asked;
use IPE\Extension\Asked\Resolver\Options;
use IPE\Extension\Manifest;
use IPE\Extension\Resolved;
use IPE\Extension\Source;
use IPE\Extension\Version\Selector;
use IPE\Extension\Version\Stability;
use IPE\Filesystem\ArchiveExtractor;
use IPE\Filesystem\ArchiveFetcher;
use IPE\Filesystem\ArchiveFormat;
use IPE\Filesystem\Downloader;
use IPE\Installer\CACertificatesInstaller;
use IPE\Installer\Enabler;
use IPE\Installer\State;
use IPE\Installer\VendorArchivePreparer;
use IPE\Option;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Service\PHPSystemPackage;

/**
 * Turns the asked extensions into resolved ones: a local directory with the source code of a specific version.
 */
abstract class Resolver
{
    /**
     * @var \IPE\Logger
     */
    protected $logger;

    /**
     * @var \IPE\PHPInfo
     */
    protected $php;

    /**
     * @var \IPE\OS
     */
    protected $os;

    /**
     * @var \IPE\Filesystem\Downloader
     */
    protected $downloader;

    /**
     * @var \IPE\Filesystem\ArchiveExtractor
     */
    protected $archiveExtractor;

    /**
     * @var \IPE\Extension\Registry
     */
    protected $registry;

    /**
     * @var \IPE\Extension\Asked\Resolver\Options
     */
    protected $options;

    /**
     * @var \IPE\Extension\Source\Bundled
     */
    protected $bundledSource;

    /**
     * @var \IPE\Extension\Source\Composer
     */
    protected $composerSource;

    /**
     * @var \IPE\Extension\Source\Pecl
     */
    protected $peclSource;

    /**
     * @var \IPE\Filesystem\ArchiveFetcher
     */
    protected $archiveFetcher;

    /**
     * @var \IPE\Extension\Manifest\Reader
     */
    protected $manifestReader;

    /**
     * @var \IPE\Option\Validator
     */
    protected $optionValidator;

    /**
     * @var \IPE\Extension\UnsupportedEnvironment
     */
    protected $unsupportedEnvironment;

    /**
     * @var \IPE\Installer\Enabler
     */
    protected $enabler;

    /**
     * @var \IPE\Service\PHPSystemPackage
     */
    protected $phpSystemPackage;

    /**
     * @var \IPE\Installer\CACertificatesInstaller
     */
    protected $caCertificatesInstaller;

    public function __construct(
        PHPInfo $php,
        OS $os,
        Downloader $downloader,
        ArchiveExtractor $archiveExtractor,
        Extension\Registry $registry,
        Options $options,
        Source\Bundled $bundledSource,
        Source\Composer $composerSource,
        Source\Pecl $peclSource,
        ArchiveFetcher $archiveFetcher,
        Manifest\Reader $manifestReader,
        Option\Validator $optionValidator,
        Extension\UnsupportedEnvironment $unsupportedEnvironment,
        Enabler $enabler,
        PHPSystemPackage $phpSystemPackage,
        CACertificatesInstaller $caCertificatesInstaller,
        \IPE\Logger $logger
    ) {
        $this->logger = $logger;
        $this->php = $php;
        $this->os = $os;
        $this->downloader = $downloader;
        $this->archiveExtractor = $archiveExtractor;
        $this->registry = $registry;
        $this->options = $options;
        $this->bundledSource = $bundledSource;
        $this->composerSource = $composerSource;
        $this->peclSource = $peclSource;
        $this->archiveFetcher = $archiveFetcher;
        $this->manifestReader = $manifestReader;
        $this->optionValidator = $optionValidator;
        $this->unsupportedEnvironment = $unsupportedEnvironment;
        $this->enabler = $enabler;
        $this->phpSystemPackage = $phpSystemPackage;
        $this->caCertificatesInstaller = $caCertificatesInstaller;
    }

    /**
     * @param \IPE\Extension\Asked[] $askedExtensions
     *
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\UnsupportedExtension
     * @throws \IPE\Exception\InvalidOptionValues
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     * @throws \IPE\Exception\SystemPackager
     *
     * @return list<\IPE\Extension\Resolved> the asked extensions and the ones they require, ordered so that every extension comes after the ones it depends on (the skipped ones are stored in $state)
     */
    public function resolveAskedExtensions(array $askedExtensions, State $state)
    {
        if ($this->os instanceof OS\Alpine && $this->php->getMajorMinorVersion() <= 505) {
            throw new Exception\UnsupportedOS('PHP 5.5 is not supported on Alpine');
        }
        if ($this->options->getSystemPackages() === Options::SYSTEMPACKAGES_ONLY && $this->phpSystemPackage->getPackage() === null) {
            throw new Exception\UnsupportedOS("The extensions must be installed only from the system packages, but PHP hasn't been installed by the system packager");
        }
        $state->setSkippedExtensions([]);
        if ($askedExtensions !== []) {
            $this->caCertificatesInstaller->install();
        }
        $resolved = [];
        $asked = [];
        foreach ($askedExtensions as $askedExtension) {
            $extension = $this->resolveAskedExtension($askedExtension, $state);
            if ($extension !== null) {
                $resolved[] = $extension;
                $asked[] = $askedExtension;
            }
        }
        $askedCount = count($resolved);
        $requirements = [];
        $requiredBy = [];
        // $resolved grows while looping, so that we also process the dependencies of the dependencies
        for ($index = 0; $index < count($resolved); $index++) {
            foreach ($this->getRequiredExtensions($resolved[$index]) as $name => $constraint) {
                if ($this->isInstalledName($name)) {
                    continue;
                }
                $dependencyIndex = self::findResolved($name, $resolved);
                if ($dependencyIndex === null) {
                    $askedDependency = $this->createAskedDependency($name, $constraint, $resolved[$index], $state);
                    $dependency = $askedDependency === null ? null : $this->resolveAskedExtensionFromHandle($askedDependency, $state);
                    if ($dependency === null) {
                        continue;
                    }
                    $dependencyIndex = count($resolved);
                    $resolved[] = $dependency;
                    $asked[] = $askedDependency;
                }
                $requirements[$dependencyIndex][(string) $resolved[$index]] = $constraint;
                $dependentName = $resolved[$index]->getName();
                if ($dependentName !== '') {
                    $requiredBy[$dependencyIndex][$dependentName] = true;
                }
                $replacement = $this->checkRequirement($constraint, $resolved[$index], $resolved[$dependencyIndex], $asked[$dependencyIndex], $requirements[$dependencyIndex], $state);
                if ($replacement !== null) {
                    $resolved[$dependencyIndex] = $replacement;
                    if ($dependencyIndex < $index) {
                        // The new version may have different requirements
                        $index = $dependencyIndex - 1;
                        continue 2;
                    }
                }
            }
        }

        foreach ($resolved as $index => $extension) {
            $resolved[$index] = $extension->withRequirers($index < $askedCount, isset($requiredBy[$index]) ? array_map('strval', array_keys($requiredBy[$index])) : []);
        }

        return $this->sortByDependencies($this->withoutConflicts($resolved, $state));
    }

    /**
     * @param string $name
     * @param non-empty-string $reason one of the \IPE\Extension\Skipped::REASON_* constants
     * @param non-empty-string $message
     *
     * @return void
     */
    private function skip($name, $reason, $message, State $state)
    {
        $this->logger->warning($message);
        $state->addSkippedExtension(new Extension\Skipped($name === '' ? 'unknown extension' : $name, $reason, $message));
    }

    /**
     * @param array<int, \IPE\Extension\Resolved> $resolved
     *
     * @throws \IPE\Exception\UnsupportedExtension
     *
     * @return array<int, \IPE\Extension\Resolved> $resolved without the extensions to be skipped
     */
    private function withoutConflicts(array $resolved, State $state)
    {
        foreach ($resolved as $index => $extension) {
            foreach ($this->getConflictingExtensions($extension) as $name => $constraint) {
                $other = self::findResolved($name, $resolved);
                if ($other === $index) {
                    continue;
                }
                if ($other !== null) {
                    if (!$this->isVersionAccepted($resolved[$other]->getVersion(), $constraint)) {
                        continue;
                    }
                    $message = "The extension {$extension} conflicts with the extension {$resolved[$other]}";
                } elseif ($constraint === '*' && $this->isInstalledName($name)) {
                    // The version of the installed extensions is not known: we can't check more specific constraints
                    $message = "The extension {$extension} conflicts with the installed extension {$name}";
                } else {
                    continue;
                }
                if (!$this->handleUnsupported($message, $extension->getName(), $state, Extension\Skipped::REASON_CONFLICT)) {
                    unset($resolved[$index]);

                    return $this->withoutConflicts($this->withoutDependents($extension, $resolved, $state), $state);
                }
            }
        }

        return $resolved;
    }

    /**
     * @return array<string, string> the keys are the extension names, the values are composer version constraints
     */
    private function getConflictingExtensions(Resolved $resolved)
    {
        $known = $resolved->getKnownExtension();
        if ($known instanceof Extension\DeclaresConflicts) {
            return array_fill_keys($known->getConflictingExtensions($resolved->getVersion()), '*');
        }
        $manifest = $resolved->getManifest();

        return $manifest === null ? [] : $manifest->getConflictingExtensions();
    }

    /**
     * @param array<int, \IPE\Extension\Resolved> $resolved
     *
     * @return array<int, \IPE\Extension\Resolved> $resolved without the extensions that (even indirectly) require $removed
     */
    private function withoutDependents(Resolved $removed, array $resolved, State $state)
    {
        foreach ($resolved as $index => $extension) {
            foreach (array_keys($this->getRequiredExtensions($extension)) as $name) {
                if ($removed->hasName($name)) {
                    $this->skip($extension->getName(), Extension\Skipped::REASON_REQUIRES_SKIPPED, "Skipping the extension {$extension}: it requires the extension {$removed}", $state);
                    unset($resolved[$index]);
                    $resolved = $this->withoutDependents($extension, $resolved, $state);
                    break;
                }
            }
        }

        return $resolved;
    }

    /**
     * Check that an extension already resolved satisfies the requirement of an extension that depends on it.
     *
     * @param string $constraint a composer version constraint
     * @param array<string, string> $requirements all the constraints of the extensions that depend on $dependency
     *
     * @throws \IPE\Exception\UnsupportedExtension if no version can satisfy all the requirements
     * @throws \IPE\Exception\InvalidOptionValues
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     * @throws \IPE\Exception\SystemPackager
     *
     * @return \IPE\Extension\Resolved|null a version that satisfies all the requirements (NULL if $dependency can be kept)
     */
    private function checkRequirement($constraint, Resolved $dependent, Resolved $dependency, Asked $asked, array $requirements, State $state)
    {
        if ($this->isVersionAccepted($dependency->getVersion(), $constraint)) {
            return null;
        }
        $message = "{$dependent} requires version {$constraint} of the extension {$dependency}";
        if ($this->options->getOnUnsupportedExtensions() === Options::ONUNSUPPORTED_FORCE || self::isVersionDeclaredByExtension($dependency, $asked)) {
            $this->logger->warning("{$message}: keeping that version");

            return null;
        }
        if ($asked instanceof Asked\FromHandle) {
            $this->logger->info("{$message}: looking for another version");
            // Only the final outcome must be reported, not the attempts
            $skipped = $state->getSkippedExtensions();
            try {
                $merged = self::mergeVersionRequirements(array_merge([$asked->getVersionRequirements()], array_values($requirements)));
                $replacement = $merged === '' ? null : $this->resolveAskedExtensionFromHandle(new Asked\FromHandle($asked->getHandle(), $merged, $asked->getOptions()), $state);
                if ($replacement !== null) {
                    return $replacement;
                }
            } catch (Exception\UnsupportedExtension $x) {
                $this->logger->debug($x->getMessage());
            } catch (Exception\InvalidArgument $x) {
                $this->logger->debug($x->getMessage());
            } finally {
                $state->setSkippedExtensions($skipped);
            }
        }
        $details = [];
        foreach ($requirements as $requirer => $requirement) {
            $details[] = "- {$requirer} requires {$requirement}";
        }
        if ($asked instanceof Asked\FromHandle && $asked->getVersionRequirements() !== '') {
            $details[] = "- you asked for {$asked->getVersionRequirements()}";
        }
        throw new Exception\UnsupportedExtension("Unable to find a version of the extension {$dependency} that satisfies all the requirements:\n" . implode("\n", $details));
    }

    /**
     * @param string $version
     * @param string $versionRequirements
     *
     * @return bool
     */
    private function isVersionAccepted($version, $versionRequirements)
    {
        if ($version === '') {
            return true;
        }
        try {
            list($constraint) = Selector::parseRequirements($versionRequirements);

            return Semver::satisfies($version, $constraint);
        } catch (Exception\InvalidArgument $x) {
        } catch (\UnexpectedValueException $x) {
        }
        $this->logger->debug("Ignoring the unparsable requirement '{$versionRequirements}' for the version {$version}");

        return true;
    }

    /**
     * @throws \IPE\Exception\UnsupportedExtension
     *
     * @return bool
     */
    private function isVersionDeclaredByExtension(Resolved $resolved, Asked $asked)
    {
        $known = $resolved->getKnownExtension();

        return $known instanceof Extension\DeclaresDefaultVersion
            && $asked instanceof Asked\FromHandle
            && $asked->getVersionRequirements() === ''
            && Selector::isExactVersion($known->getDefaultVersionRequirements());
    }

    /**
     * @param string[] $versionRequirements
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return string empty string if they can't be merged (composer has no way to group alternative constraints)
     */
    private static function mergeVersionRequirements(array $versionRequirements)
    {
        $constraints = [];
        $stability = Stability::STABLE;
        foreach ($versionRequirements as $item) {
            list($constraint, $itemStability) = Selector::parseRequirements($item);
            if (strpos($constraint, '|') !== false) {
                return '';
            }
            if ($constraint !== '*') {
                $constraints[] = $constraint;
            }
            if (Stability::compare($itemStability, $stability) < 0) {
                $stability = $itemStability;
            }
        }

        return implode(' ', array_unique($constraints)) . "@{$stability}";
    }

    /**
     * @return array<string, string> the keys are the extension names, the values are composer version constraints
     */
    private function getRequiredExtensions(Resolved $resolved)
    {
        // Not skipped for the system packages: some don't depend on the packages of the extensions they need (for example, php8.2-http of Debian 12 doesn't require php8.2-raphf)
        $manifest = $resolved->getManifest();
        $result = $manifest === null ? [] : $manifest->getRequiredExtensions();
        $known = $resolved->getKnownExtension();
        if ($known instanceof Extension\DependsOnExtensions) {
            foreach ($known->getRequiredExtensions($resolved->getVersion()) as $handle) {
                if (!isset($result[$handle])) {
                    $result[$handle] = '*';
                }
            }
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function getOptionalExtensions(Resolved $resolved)
    {
        $manifest = $resolved->getManifest();
        $result = $manifest === null ? [] : array_map('strval', array_keys($manifest->getOptionalExtensions()));
        $known = $resolved->getKnownExtension();
        if ($known instanceof Extension\DependsOnExtensions) {
            $result = array_merge($result, $known->getOptionalExtensions($resolved->getVersion()));
        }

        return $result;
    }

    /**
     * @param string $name
     * @param string $constraint a composer version constraint
     *
     * @throws \IPE\Exception\UnsupportedExtension
     *
     * @return \IPE\Extension\Asked\FromHandle|null NULL if there's nothing to be installed
     */
    private function createAskedDependency($name, $constraint, Resolved $dependent, State $state)
    {
        $known = $name === '' ? null : ($this->registry->getExtensionByHandle($name) ?: $this->registry->getExtensionByInstalledName($name));
        if ($known === null) {
            $message = "{$dependent} requires the extension {$name}, which is not installed and is unknown";
            if ($this->options->isIgnoreUnknownExtensions()) {
                $this->skip($name, Extension\Skipped::REASON_UNKNOWN, $message, $state);

                return null;
            }
            throw new Exception\UnsupportedExtension($message);
        }
        if ($this->php->isExtensionInstalled($known)) {
            return null;
        }
        $this->logger->info("{$dependent} requires the extension " . $known::getHandle());
        // An exact version declared by the extension wins over the requirements of the other extensions
        $exactDefault = $known instanceof Extension\DeclaresDefaultVersion && Selector::isExactVersion($known->getDefaultVersionRequirements());

        return new Asked\FromHandle($known::getHandle(), $constraint === '*' || $exactDefault ? '' : $constraint);
    }

    /**
     * @param array<int, \IPE\Extension\Resolved> $resolved
     *
     * @throws \IPE\Exception\UnsupportedExtension in case of circular requirements
     *
     * @return list<\IPE\Extension\Resolved>
     */
    private function sortByDependencies(array $resolved)
    {
        $sorted = [];
        foreach (array_keys($resolved) as $index) {
            $this->addSorted($index, $resolved, $sorted, []);
        }

        return array_values($sorted);
    }

    /**
     * Depth-first visit: the dependencies are added before the extensions that depend on them.
     *
     * @param int $index
     * @param array<int, \IPE\Extension\Resolved> $resolved
     * @param array<int, \IPE\Extension\Resolved> $sorted
     * @param list<int> $visiting
     *
     * @throws \IPE\Exception\UnsupportedExtension in case of circular requirements
     *
     * @return void
     */
    private function addSorted($index, array $resolved, array &$sorted, array $visiting)
    {
        if (isset($sorted[$index])) {
            return;
        }
        $visiting[] = $index;
        foreach (array_keys($this->getRequiredExtensions($resolved[$index])) as $name) {
            $dependency = self::findResolved($name, $resolved);
            if ($dependency === null) {
                continue;
            }
            if (in_array($dependency, $visiting, true)) {
                $chain = array_map(static function ($i) use ($resolved) { return (string) $resolved[$i]; }, array_merge($visiting, [$dependency]));
                throw new Exception\UnsupportedExtension('Circular dependency between extensions: ' . implode(' -> ', $chain));
            }
            $this->addSorted($dependency, $resolved, $sorted, $visiting);
        }
        foreach ($this->getOptionalExtensions($resolved[$index]) as $name) {
            $dependency = self::findResolved($name, $resolved);
            // A circular optional dependency is not an error: we simply can't honour it
            if ($dependency !== null && !in_array($dependency, $visiting, true)) {
                $this->addSorted($dependency, $resolved, $sorted, $visiting);
            }
        }
        $sorted[$index] = $resolved[$index];
    }

    /**
     * @param string $name
     * @param array<int, \IPE\Extension\Resolved> $resolved
     *
     * @return int|null
     */
    private static function findResolved($name, array $resolved)
    {
        foreach ($resolved as $index => $extension) {
            if ($extension->hasName($name)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @throws \IPE\Exception\UnsupportedExtension
     * @throws \IPE\Exception\InvalidOptionValues
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\SystemPackager
     *
     * @return \IPE\Extension\Resolved|null
     */
    public function resolveAskedExtension(Asked $asked, State $state)
    {
        if (!$asked instanceof Asked\FromHandle && $this->options->getSystemPackages() === Options::SYSTEMPACKAGES_ONLY) {
            $name = self::describeAskedSource($asked);
            if (!$this->handleUnsupported("The extension from {$name} would be compiled, but the extensions must be installed only from the system packages", $name, $state)) {
                return null;
            }
        }
        if ($asked instanceof Asked\FromDirectory) {
            return $this->resolveAskedExtensionFromDirectory($asked, $state, Resolved::ORIGIN_DIRECTORY);
        }
        if ($asked instanceof Asked\FromHandle) {
            return $this->resolveAskedExtensionFromHandle($asked, $state);
        }
        if ($asked instanceof Asked\FromComposer) {
            return $this->resolveAskedExtensionFromComposer($asked, $state);
        }
        if ($asked instanceof Asked\FromURL) {
            return $this->resolveAskedExtensionFromURL($asked, $state);
        }
        throw new Exception\UnsupportedExtension('Unrecognized wanted extension class: ' . get_class($asked));
    }

    /**
     * @return non-empty-string
     */
    private static function describeAskedSource(Asked $asked)
    {
        if ($asked instanceof Asked\FromDirectory) {
            return $asked->getPath();
        }
        if ($asked instanceof Asked\FromComposer) {
            return $asked->getFullName();
        }
        if ($asked instanceof Asked\FromURL) {
            return $asked->getURL();
        }

        return get_class($asked);
    }

    /**
     * @param non-empty-string $origin one of the Resolved::ORIGIN_* constants
     *
     * @throws \IPE\Exception\UnsupportedExtension
     * @throws \IPE\Exception\InvalidOptionValues
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Extension\Resolved|null
     */
    protected function resolveAskedExtensionFromDirectory(Asked\FromDirectory $asked, State $state, $origin)
    {
        $path = $asked->getPath();
        $this->logger->info("Inspecting extension in directory {$path}");
        $manifest = $this->manifestReader->readFromDirectory($path, $state->getFilesystem());
        $known = $manifest === null ? null : $this->registry->getExtensionByManifest($manifest);
        if ($known === null) {
            if ($this->options->isIgnoreUnknownExtensions()) {
                $this->skip($path, Extension\Skipped::REASON_UNKNOWN, "The directory {$path} contains an unknown extension - skipping", $state);

                return null;
            }
            $this->logger->info("The directory {$path} contains an unknown extension");
            if ($manifest !== null && $this->isUnknownExtensionAlreadyInstalled($manifest->getExtensionName(), $state)) {
                return null;
            }
        } else {
            $this->logger->info("The directory {$path} contains the extension " . $known::getHandle());
            if ($this->isAlreadyInstalled($known, $state)) {
                return null;
            }
            $disabled = $this->resolveDisabled($known);
            if ($disabled !== null) {
                return $disabled;
            }
        }

        return $this->createResolved($known, $path, '', $origin, $manifest, $asked->getOptions(), $state);
    }

    /**
     * @throws \IPE\Exception\UnsupportedExtension
     * @throws \IPE\Exception\InvalidOptionValues
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     * @throws \IPE\Exception\SystemPackager
     *
     * @return \IPE\Extension\Resolved|null
     */
    protected function resolveAskedExtensionFromHandle(Asked\FromHandle $asked, State $state)
    {
        $handle = $asked->getHandle();
        $known = $this->registry->getExtensionByHandle($handle);
        if ($known === null) {
            // Like the extensions compiled into PHP (xmlwriter, tokenizer, ...)
            if ($this->isUnknownExtensionAlreadyInstalled($handle, $state)) {
                return null;
            }
            if ($this->options->isIgnoreUnknownExtensions()) {
                $this->skip($handle, Extension\Skipped::REASON_UNKNOWN, "Skipping unknown extension with handle {$handle}", $state);

                return null;
            }
            throw new Exception\UnsupportedExtension("'{$handle}' is the handle of an unknown extension");
        }

        return $this->resolveKnownExtension($known, $asked->getVersionRequirements(), $asked->getOptions(), $state, [
            Resolved::ORIGIN_SYSTEM_PACKAGE,
            Resolved::ORIGIN_BUNDLED,
            Resolved::ORIGIN_COMPOSER,
            Resolved::ORIGIN_PECL,
            Resolved::ORIGIN_BINARY,
            Resolved::ORIGIN_VENDOR_ARCHIVE,
        ]);
    }

    /**
     * @throws \IPE\Exception\UnsupportedExtension
     * @throws \IPE\Exception\InvalidOptionValues
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     * @throws \IPE\Exception\SystemPackager
     *
     * @return \IPE\Extension\Resolved|null
     */
    protected function resolveAskedExtensionFromComposer(Asked\FromComposer $asked, State $state)
    {
        $fullName = $asked->getFullName();
        $known = $this->registry->getExtensionByComposerPackageName($fullName);
        if ($known !== null) {
            return $this->resolveKnownExtension($known, $asked->getVersionRequirements(), $asked->getOptions(), $state, [Resolved::ORIGIN_COMPOSER]);
        }
        if ($this->options->isIgnoreUnknownExtensions()) {
            $this->skip($fullName, Extension\Skipped::REASON_UNKNOWN, "Skipping unknown extension with composer name {$fullName}", $state);

            return null;
        }
        $this->logger->info("Resolving unknown extension {$fullName} from the Composer repositories");
        $forcedVersion = $this->isExactVersion($asked->getVersionRequirements());
        $whyNone = '';
        $version = $this->composerSource->selectVersion($asked->getVendor(), $asked->getName(), $asked->getVersionRequirements(), $forcedVersion ? null : $this->getPHPToBeChecked(), null, $whyNone);
        if ($version === null) {
            throw new Exception\UnsupportedExtension(
                "Unable to find a suitable version of {$fullName} in the Composer repositories"
                . $this->describeRequirements($asked->getVersionRequirements())
                . ": {$whyNone}"
            );
        }
        $path = $this->composerSource->fetch($version, $state);
        $manifest = $this->manifestReader->readFromDirectory($path, $state->getFilesystem());
        if ($manifest === null) {
            // The pre-packaged sources may not include composer.json
            $manifest = $this->manifestReader->readFromComposerMetadata($version->getComposerJson());
        }
        // For example, a fork of a known extension
        $known = $manifest === null ? null : $this->registry->getExtensionByManifest($manifest);
        if ($known === null) {
            if ($manifest !== null && $this->isUnknownExtensionAlreadyInstalled($manifest->getExtensionName(), $state)) {
                return null;
            }
        } else {
            $this->logger->info("The Composer package {$fullName} contains the extension " . $known::getHandle());
            if ($this->isAlreadyInstalled($known, $state)) {
                return null;
            }
            $disabled = $this->resolveDisabled($known);
            if ($disabled !== null) {
                return $disabled;
            }
        }

        return $this->createResolved($known, $path, $version->getVersion(), Resolved::ORIGIN_COMPOSER, $manifest, $asked->getOptions(), $state, $forcedVersion);
    }

    /**
     * @throws \IPE\Exception\UnsupportedExtension
     * @throws \IPE\Exception\InvalidOptionValues
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     *
     * @return \IPE\Extension\Resolved|null
     */
    protected function resolveAskedExtensionFromURL(Asked\FromURL $asked, State $state)
    {
        $fs = $state->getFilesystem();
        $tarballDir = $state->getVolatileDir()->createSubdir();
        $tarballFile = $tarballDir . $fs->getDirectorySeparator() . 'extension-source';
        $this->logger->info("Downloading extension tarball from {$asked->getURL()}");
        $this->downloader->saveAs($asked->getURL(), $tarballFile);
        $extractTo = $state->getVolatileDir()->createSubdir();
        $this->logger->info("Extracting downloaded tarball to {$extractTo}");
        $sourceDir = $this->archiveExtractor->extractTo($tarballFile, $extractTo);
        if ($asked->getSubdir() !== '') {
            $sourceDir .= str_replace('/', $fs->getDirectorySeparator(), '/' . $asked->getSubdir());
        }
        $fs->deleteDirectory($tarballDir);
        $extensionFromDir = new Asked\FromDirectory($sourceDir, $asked->getOptions());

        return $this->resolveAskedExtensionFromDirectory($extensionFromDir, $state, Resolved::ORIGIN_URL);
    }

    /**
     * Pick the source and the version of a known extension, and fetch its source code.
     *
     * @param string $versionRequirements
     * @param array<string, string|true> $userOptions
     * @param non-empty-list<non-empty-string> $origins the sources to be tried, in order of preference
     *
     * @throws \IPE\Exception\UnsupportedExtension
     * @throws \IPE\Exception\InvalidOptionValues
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     * @throws \IPE\Exception\SystemPackager
     *
     * @return \IPE\Extension\Resolved|null
     */
    protected function resolveKnownExtension(Extension $extension, $versionRequirements, array $userOptions, State $state, array $origins)
    {
        $handle = $extension::getHandle();
        if ($this->isAlreadyInstalled($extension, $state)) {
            return null;
        }
        $disabled = $this->resolveDisabled($extension);
        if ($disabled !== null) {
            return $disabled;
        }
        $notes = [];
        // The packages are built for the system: what IPE knows about compiling the extensions doesn't apply to them
        if (in_array(Resolved::ORIGIN_SYSTEM_PACKAGE, $origins, true)) {
            $fromPackage = $this->resolveFromSystemPackage($extension, $versionRequirements, $userOptions, $notes);
            if ($fromPackage !== null) {
                return $fromPackage;
            }
            if ($this->options->getSystemPackages() === Options::SYSTEMPACKAGES_ONLY) {
                return $this->failResolution($handle, $versionRequirements, $notes, $state);
            }
            if (isset($notes[Resolved::ORIGIN_SYSTEM_PACKAGE])) {
                $this->logger->info("Not using a system package for {$handle}: {$notes[Resolved::ORIGIN_SYSTEM_PACKAGE]}");
            }
        }
        if (!$this->checkEnvironment($extension, $state)) {
            return null;
        }
        if ($extension instanceof Extension\DeclaresThreadSafetySupport && !$this->checkThreadSafety($extension->supportsZTS(), $extension->supportsNTS(), $handle, $state)) {
            return null;
        }
        if ($versionRequirements === '' && $extension instanceof Extension\DeclaresDefaultVersion) {
            $versionRequirements = $extension->getDefaultVersionRequirements();
            if ($versionRequirements !== '') {
                $this->logger->info("Using the default version requirements of {$handle} for PHP {$this->php->getVersion()}: {$versionRequirements}");
            }
        }
        $forcedVersion = $this->isExactVersion($versionRequirements);
        $phpToBeChecked = $forcedVersion ? null : $this->getPHPToBeChecked();
        $this->logger->info("Resolving extension {$handle}" . $this->describeRequirements($versionRequirements));
        foreach ($origins as $origin) {
            switch ($origin) {
                case Resolved::ORIGIN_SYSTEM_PACKAGE:
                    break;
                case Resolved::ORIGIN_BUNDLED:
                    if (!($extension instanceof Extension\BundledInPHPSource) || !$this->bundledSource->isAvailable($extension, $this->php)) {
                        break;
                    }
                    list($constraint) = Selector::parseRequirements($versionRequirements);
                    if (!Semver::satisfies($this->php->getBaseVersion(), $constraint)) {
                        $notes[$origin] = "PHP {$this->php->getVersion()} doesn't satisfy the version requirements";
                        break;
                    }
                    $this->logger->info("Using the version of {$handle} bundled in PHP {$this->php->getVersion()}");
                    $path = $this->bundledSource->fetch($extension, $state);

                    return $this->createResolvedFromFetched($extension, $path, $this->php->getBaseVersion(), $origin, $userOptions, $state);
                case Resolved::ORIGIN_COMPOSER:
                    if (!($extension instanceof Extension\AvailableAsComposerPackage)) {
                        break;
                    }
                    $fullName = $extension::getComposerPackageName();
                    list($vendor, $name) = explode('/', $fullName, 2);
                    $whyNone = '';
                    $version = $this->composerSource->selectVersion($vendor, $name, $versionRequirements, $phpToBeChecked, self::createVersionFilter($extension, $origin), $whyNone);
                    if ($version === null) {
                        $notes[$origin] = "no suitable version found ({$fullName}): {$whyNone}";
                        break;
                    }
                    $this->logger->info("Using version {$version} of {$handle} from the Composer repositories");
                    $path = $this->composerSource->fetch($version, $state);

                    return $this->createResolvedFromFetched($extension, $path, $version->getVersion(), $origin, $userOptions, $state, $forcedVersion);
                case Resolved::ORIGIN_PECL:
                    if (!($extension instanceof Extension\AvailableOnPECL)) {
                        break;
                    }
                    $peclName = $extension::getPECLHandle();
                    $whyNone = '';
                    $version = $this->peclSource->selectVersion($peclName, $extension::getPECLCategory(), $versionRequirements, $phpToBeChecked, self::createVersionFilter($extension, $origin), $whyNone);
                    if ($version === null) {
                        $notes[$origin] = "no suitable version found ({$peclName}): {$whyNone}";
                        break;
                    }
                    $this->logger->info("Using version {$version} of {$handle} from PECL");
                    $path = $this->peclSource->fetch($version, $state);

                    return $this->createResolvedFromFetched($extension, $path, $version->getVersion(), $origin, $userOptions, $state, $forcedVersion);
                case Resolved::ORIGIN_BINARY:
                    if (!($extension instanceof Extension\AvailableAsBinary)) {
                        break;
                    }
                    $path = $this->fetchBinary($extension, $versionRequirements, $state);
                    if ($path === '') {
                        $notes[$origin] = "the vendor doesn't provide a binary for this environment";
                        break;
                    }
                    $this->logger->info("Using the binary of {$handle}" . $this->describeRequirements($versionRequirements));

                    return $this->createResolvedFromFetched($extension, $path, $versionRequirements, $origin, $userOptions, $state, $forcedVersion);
                case Resolved::ORIGIN_VENDOR_ARCHIVE:
                    if (!($extension instanceof Extension\CompiledFromVendorArchive)) {
                        break;
                    }
                    $declaredOptions = $extension instanceof Extension\DeclaresOptions ? $extension::getOptions() : [];
                    $optionValues = $this->optionValidator->validate($userOptions, $declaredOptions, false, "the extension {$handle}");
                    $archiveVersion = '';
                    $path = '';
                    try {
                        $archiveVersion = $extension->getVendorArchiveVersion($versionRequirements, $optionValues);
                        $path = $this->downloadVendorArchive($extension->getVendorArchiveURL($archiveVersion, $optionValues), $state);
                    } catch (Exception\IO\NotFound $_) {
                    }
                    if ($path === '') {
                        $description = $archiveVersion === '' ? 'any version' : "the version {$archiveVersion}";
                        $notes[$origin] = "the vendor doesn't provide {$description} for this environment";
                        break;
                    }
                    $this->logger->info("Using version {$archiveVersion} of {$handle} from the archive of its vendor");

                    return $this->createResolvedFromFetched($extension, $path, $archiveVersion, $origin, $userOptions, $state, $forcedVersion);
                default:
                    throw new Exception\InvalidArgument("Invalid origin: {$origin}");
            }
        }

        return $this->failResolution($handle, $versionRequirements, $notes, $state);
    }

    /**
     * @param string $versionRequirements
     * @param array<string, string|true> $userOptions
     * @param array<string, non-empty-string> $notes the keys are the origins, the values explain why they can't be used
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\SystemPackager
     *
     * @return \IPE\Extension\Resolved|null
     */
    private function resolveFromSystemPackage(Extension $extension, $versionRequirements, array $userOptions, array &$notes)
    {
        if ($this->options->getSystemPackages() === Options::SYSTEMPACKAGES_NEVER || $this->phpSystemPackage->getPackage() === null) {
            return null;
        }
        $handle = $extension::getHandle();
        $package = $this->phpSystemPackage->findExtensionPackage($extension);
        if ($package === null) {
            $notes[Resolved::ORIGIN_SYSTEM_PACKAGE] = 'no package is available for the installed PHP';

            return null;
        }
        $description = "the package {$package->getName()} {$package->getVersion()}";
        if ($userOptions !== []) {
            $notes[Resolved::ORIGIN_SYSTEM_PACKAGE] = "{$description} can't be used with options";

            return null;
        }
        $version = $this->phpSystemPackage->getUpstreamVersion($package);
        if ($versionRequirements !== '' && ($version === '' || !$this->isVersionAccepted($version, $versionRequirements))) {
            $notes[Resolved::ORIGIN_SYSTEM_PACKAGE] = "{$description} doesn't satisfy the version requirements";

            return null;
        }
        if ($version !== '' && $extension instanceof Extension\RestrictsVersions && !$extension->canUseVersion(Resolved::ORIGIN_SYSTEM_PACKAGE, $version)) {
            $notes[Resolved::ORIGIN_SYSTEM_PACKAGE] = "{$description} can't be used";

            return null;
        }
        $otherPHPPackages = $this->phpSystemPackage->getPackagesOfOtherPHPVersions($package);
        if ($otherPHPPackages !== []) {
            $notes[Resolved::ORIGIN_SYSTEM_PACKAGE] = "{$description} would install packages of another PHP version (" . implode(', ', $otherPHPPackages) . ')';

            return null;
        }
        $this->logger->info("Using {$description} for {$handle}");

        return new Resolved($extension, $this->phpSystemPackage->getInstallArgument($package), $version, Resolved::ORIGIN_SYSTEM_PACKAGE);
    }

    /**
     * @param non-empty-string $handle
     * @param string $versionRequirements
     * @param array<string, non-empty-string> $notes the keys are the origins, the values explain why they can't be used
     *
     * @throws \IPE\Exception\UnsupportedExtension
     *
     * @return null
     */
    private function failResolution($handle, $versionRequirements, array $notes, State $state)
    {
        $message = "Unable to find a way to install the extension {$handle}" . $this->describeRequirements($versionRequirements) . " for PHP {$this->php->getVersion()}";
        if ($notes !== []) {
            $lines = [];
            foreach ($notes as $origin => $note) {
                $lines[] = "{$origin}: {$note}";
            }
            if ($this->options->getOnUnsupportedExtensions() === Options::ONUNSUPPORTED_SKIP) {
                $this->skip($handle, Extension\Skipped::REASON_UNSUPPORTED, "Skipping the extension {$handle}:\n- " . implode("\n- ", $lines), $state);

                return null;
            }
            $message .= ":\n- " . implode("\n- ", $lines);
        }
        throw new Exception\UnsupportedExtension($message);
    }

    /**
     * Vendors don't tell which PHP versions are supported: we can only try to download the binary and look for the module.
     *
     * @param string $versionRequirements
     *
     * @throws \IPE\Exception\UnsupportedExtension
     * @throws \IPE\Exception\UnsupportedOS
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UnsupportedArchiveFormat
     *
     * @return string the root directory of the archive (empty string if there's no binary for this environment)
     */
    private function fetchBinary(Extension\AvailableAsBinary $extension, $versionRequirements, State $state)
    {
        try {
            $url = $extension->getBinaryArchiveURL($versionRequirements);
            $path = $url === '' ? $state->getVolatileDir()->createSubdir() : $this->archiveFetcher->fetch($url, ArchiveFormat::TAR_GZ, $state->getVolatileDir());
        } catch (Exception\IO\NotFound $_) {
            return '';
        } catch (Exception\IO\Forbidden $x) {
            if ($extension instanceof Extension\ForbidsAccessToMissingBinaries) {
                return '';
            }

            throw $x;
        }
        if ($extension instanceof Extension\ProvidesBinaryModule && $extension->findBinaryModule($path, $versionRequirements) === '') {
            return '';
        }
        if ($extension instanceof Extension\ProvidesBinaryInstaller && !$extension->canRunBinaryInstaller($path)) {
            return '';
        }

        return $path;
    }

    /**
     * @param non-empty-string $url
     *
     * @throws \IPE\Exception\IO
     *
     * @return string the directory containing the downloaded archive (empty string if it doesn't exist)
     */
    private function downloadVendorArchive($url, State $state)
    {
        $directory = $state->getVolatileDir()->createSubdir();
        $this->logger->info("Downloading {$url}");
        $this->downloader->saveAs($url, $directory . $state->getFilesystem()->getDirectorySeparator() . VendorArchivePreparer::ARCHIVE_FILENAME);

        return $directory;
    }

    /**
     * @return bool
     */
    protected function isAlreadyInstalled(Extension $extension, State $state)
    {
        if (!$this->php->isExtensionInstalled($extension)) {
            return false;
        }
        $this->skip($extension::getHandle(), Extension\Skipped::REASON_ALREADY_INSTALLED, 'The extension ' . $extension::getHandle() . ' is already installed - skipping', $state);

        return true;
    }

    /**
     * Like the already installed extensions, the ones that have been disabled are enabled whatever version and options have been asked.
     *
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Extension\Resolved|null NULL if the extension is not installed
     */
    private function resolveDisabled(Extension $extension)
    {
        $iniFile = $this->enabler->getDisabledIniFile($extension);
        if ($iniFile === '') {
            return null;
        }
        $this->logger->info('The extension ' . $extension::getHandle() . " is installed but disabled ({$iniFile}): it will be enabled");

        return new Resolved($extension, $iniFile, '', Resolved::ORIGIN_DISABLED);
    }

    /**
     * The name of an unknown extension is the handle asked by the user, or the one in its manifest (available only after fetching its source code).
     *
     * @param string $name
     *
     * @return bool
     */
    private function isUnknownExtensionAlreadyInstalled($name, State $state)
    {
        if (!$this->isInstalledName($name)) {
            return false;
        }
        $this->skip($name, Extension\Skipped::REASON_ALREADY_INSTALLED, "The extension {$name} is already installed - skipping", $state);

        return true;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    private function isInstalledName($name)
    {
        foreach ($this->php->getInstalledExtensions() as $installed) {
            if (strcasecmp($installed, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param non-empty-string $path
     * @param string $version
     * @param non-empty-string $origin
     * @param array<string, string|true> $userOptions
     * @param bool $forcedVersion true if an exact version has been asked
     *
     * @throws \IPE\Exception\UnsupportedExtension
     * @throws \IPE\Exception\InvalidOptionValues
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Extension\Resolved|null
     */
    private function createResolvedFromFetched(Extension $extension, $path, $version, $origin, array $userOptions, State $state, $forcedVersion = false)
    {
        $manifest = $this->manifestReader->readFromDirectory($path, $state->getFilesystem());

        return $this->createResolved($extension, $path, $version, $origin, $manifest, $userOptions, $state, $forcedVersion);
    }

    /**
     * Whoever asks for an exact version (the user or the extension) knows that it works, whatever its package declares.
     *
     * @param string $versionRequirements
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return bool
     */
    private function isExactVersion($versionRequirements)
    {
        list($constraint) = Selector::parseRequirements($versionRequirements);

        return Selector::isExactVersion($constraint);
    }

    /**
     * @return \IPE\PHPInfo|null null if the extensions must be installed even if they declare that they don't support the current PHP version
     */
    private function getPHPToBeChecked()
    {
        return $this->options->getOnUnsupportedExtensions() === Options::ONUNSUPPORTED_FORCE ? null : $this->php;
    }

    /**
     * The default values of the manifests of the bundled extensions are sometimes wrong (for example --with-mysql=autodetect): the configure scripts know better.
     *
     * @param \IPE\Option\ConfigureOption[] $options
     *
     * @return \IPE\Option\ConfigureOption[]
     */
    private static function withoutDefaults(array $options)
    {
        return array_map(static function (Option\ConfigureOption $option) {
            return new Option\ConfigureOption($option->getName(), $option->getDescription(), $option->needsValue());
        }, $options);
    }

    /**
     * @param string $name
     * @param string $version
     *
     * @throws \IPE\Exception\UnsupportedExtension
     *
     * @return bool false if the extension must be skipped
     */
    private function checkManifestPHPConstraint(Manifest $manifest, $name, $version, State $state)
    {
        $constraint = $manifest->getPHPConstraint();
        if ($constraint === '') {
            return true;
        }
        try {
            if (Semver::satisfies($this->php->getBaseVersion(), $constraint)) {
                return true;
            }
        } catch (\UnexpectedValueException $x) {
            $this->logger->warning("Ignoring the invalid PHP version requirements of the extension {$name} ({$constraint})");

            return true;
        }

        return $this->handleUnsupported("The extension {$name} {$version} requires PHP {$constraint}, but the current version is {$this->php->getVersion()}", $name, $state);
    }

    /**
     * @param non-empty-string $origin
     *
     * @return (callable(\IPE\Extension\Version): bool)|null
     */
    private static function createVersionFilter(Extension $extension, $origin)
    {
        if (!($extension instanceof Extension\RestrictsVersions)) {
            return null;
        }

        return static function (Extension\Version $version) use ($extension, $origin) {
            return $extension->canUseVersion($origin, $version->getVersion());
        };
    }

    /**
     * @throws \IPE\Exception\UnsupportedExtension
     *
     * @return bool false if the extension must be skipped
     */
    private function checkEnvironment(Extension $extension, State $state)
    {
        $phpVersion = (int) ($this->php->getMajorMinorVersion() / 100) . '.' . ($this->php->getMajorMinorVersion() % 100);
        $handle = $extension::getHandle();
        if (!in_array($phpVersion, $extension::getSupportedPHPVersions(), true)) {
            return $this->handleUnsupported("The extension {$handle} is not supported on PHP {$phpVersion} (it's supported on PHP " . implode(', ', $extension::getSupportedPHPVersions()) . ')', $handle, $state);
        }
        if ($extension instanceof Extension\DeclaresUnsupportedEnvironments) {
            foreach ($extension::getUnsupportedEnvironments() as $environment => $reason) {
                if ($this->unsupportedEnvironment->matches($environment, $this->php, $this->os)) {
                    return $this->handleUnsupported("The extension {$handle} is not supported on {$this->os->getDisplayName()} with PHP {$phpVersion}: {$reason}", $handle, $state);
                }
            }
        }

        return true;
    }

    /**
     * @param bool $supportsZTS
     * @param bool $supportsNTS
     * @param string $name
     *
     * @throws \IPE\Exception\UnsupportedExtension
     *
     * @return bool false if the extension must be skipped
     */
    private function checkThreadSafety($supportsZTS, $supportsNTS, $name, State $state)
    {
        if ($supportsZTS && $supportsNTS) {
            return true;
        }
        $threadSafe = $this->php->isThreadSafe();
        if ($threadSafe ? $supportsZTS : $supportsNTS) {
            return true;
        }

        return $this->handleUnsupported("The extension {$name} doesn't support the " . ($threadSafe ? 'ZTS' : 'NTS') . ' builds of PHP', $name, $state);
    }

    /**
     * @param non-empty-string $message
     * @param string $name the name of the extension
     * @param non-empty-string $reason one of the \IPE\Extension\Skipped::REASON_* constants
     *
     * @throws \IPE\Exception\UnsupportedExtension
     *
     * @return bool false if the extension must be skipped
     */
    private function handleUnsupported($message, $name, State $state, $reason = Extension\Skipped::REASON_UNSUPPORTED)
    {
        switch ($this->options->getOnUnsupportedExtensions()) {
            case Options::ONUNSUPPORTED_FORCE:
                $this->logger->warning("{$message}: installing it anyway");

                return true;
            case Options::ONUNSUPPORTED_SKIP:
                $this->skip($name, $reason, "{$message}: skipping it", $state);

                return false;
        }
        throw new Exception\UnsupportedExtension($message);
    }

    /**
     * Validate the user options against the declared ones, apply the build path and build the resolved extension.
     *
     * @param \IPE\Extension|null $known
     * @param non-empty-string $path the directory where the manifest has been looked for
     * @param string $version
     * @param non-empty-string $origin
     * @param \IPE\Extension\Manifest|null $manifest
     * @param array<string, string|true> $userOptions
     * @param bool $forcedVersion true if an exact version has been asked
     *
     * @throws \IPE\Exception\UnsupportedExtension
     * @throws \IPE\Exception\InvalidOptionValues
     * @throws \IPE\Exception\IO
     *
     * @return \IPE\Extension\Resolved|null
     */
    private function createResolved($known, $path, $version, $origin, $manifest, array $userOptions, State $state, $forcedVersion = false)
    {
        if ($version === '' && $manifest !== null) {
            $version = $manifest->getVersion();
        }
        // The manifests of the bundled extensions may be outdated
        if ($manifest !== null && !$forcedVersion && $origin !== Resolved::ORIGIN_BUNDLED) {
            $name = $known === null ? $manifest->getExtensionName() : $known::getHandle();
            if (!$this->checkManifestPHPConstraint($manifest, $name, $version, $state)) {
                return null;
            }
            if (!$known instanceof Extension\DeclaresThreadSafetySupport && !$this->checkThreadSafety($manifest->supportsZTS(), $manifest->supportsNTS(), "{$name} {$version}", $state)) {
                return null;
            }
        }
        if ($manifest !== null && $manifest->getBuildPath() !== '') {
            $fs = $state->getFilesystem();
            $buildPath = str_replace('{version}', $version, $manifest->getBuildPath());
            $path .= str_replace('/', $fs->getDirectorySeparator(), '/' . $buildPath);
            if (!$fs->isDirectory($path)) {
                throw new Exception\IO("The build path '{$buildPath}' declared in the extension manifest doesn't exist");
            }
        }
        // The versions of the Composer packages may be prefixed by v: the extensions compare them with version_compare()
        $version = (string) preg_replace('/^v(?=\d)/i', '', $version);
        $declaredOptions = [];
        if ($known instanceof Extension\DeclaresOptions) {
            $declaredOptions = $known::getOptions();
        }
        if ($manifest !== null) {
            $declaredOptions = array_merge($declaredOptions, $origin === Resolved::ORIGIN_BUNDLED ? self::withoutDefaults($manifest->getConfigureOptions()) : $manifest->getConfigureOptions());
        }
        $description = $known === null ? "the unknown extension in {$path}" : 'the extension ' . $known::getHandle();
        $optionValues = $this->optionValidator->validate($userOptions, $declaredOptions, $known === null && $manifest === null, $description);

        return new Resolved($known, $path, $version, $origin, $manifest, $optionValues);
    }

    /**
     * @param string $versionRequirements
     *
     * @return string
     */
    private function describeRequirements($versionRequirements)
    {
        return $versionRequirements === '' ? '' : " (version requirements: {$versionRequirements})";
    }
}
