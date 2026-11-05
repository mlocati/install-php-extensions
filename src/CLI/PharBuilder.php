<?php

namespace IPE\CLI;

use IPE\Exception;
use IPE\Filesystem;
use IPE\Filesystem\Command;

final class PharBuilder
{
    /**
     * The format of the release tags (examples: 1.2.3, 1.2.3-alpha, 1.2.3-RC2).
     *
     * @public
     * @var non-empty-string
     */
    const VERSION_REGEX = '/^(?<major>0|[1-9]\d*)\.(?<minor>0|[1-9]\d*)\.(?<patch>0|[1-9]\d*)(?<prerelease>(?:-(?:alpha|beta|RC)(?:[1-9]\d*)?)?)$/D';

    /**
     * The line of bin/install-php-extensions that defines the version.
     *
     * @public
     * @var non-empty-string
     */
    const VERSION_LINE = "const IPE_VERSION = '';";

    /**
     * @private
     * @var string
     */
    const PHAR_ALIAS = 'ipe.phar';

    /**
     * @var list<non-empty-string>
     */
    private static $excludedPaths = [
        'src/CLI/PharBuilder.php',
        'src/Dev',
        'src/Service/.phpstorm.meta.php',
    ];

    /**
     * @var \IPE\Filesystem
     */
    private $fs;

    /**
     * @var \IPE\Filesystem\VolatileDirectory|null
     */
    private $workingDir = null;

    /**
     * @var int
     */
    private $syntaxChecksCount = 0;

    /**
     * @var int
     */
    private $compressionAlgorithm = \Phar::NONE;

    /**
     * @var bool
     */
    private $compressPHPFiles = false;

    /**
     * @var bool
     */
    private $verifyPHPFiles = false;

    /**
     * @var bool
     */
    private $verbose = false;

    /**
     * @var string
     */
    private $version = '';

    /**
     * @throws \IPE\Exception\IO
     */
    public function __construct()
    {
        $this->fs = new Filesystem\Local(new \IPE\Logger());
    }

    /**
     * @return int
     */
    public function getCompressionAlgorithm()
    {
        return $this->compressionAlgorithm;
    }

    /**
     * @param int $value
     *
     * @throws \IPE\Exception\UserMessage
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return $this
     */
    public function setCompressionAlgorithm($value)
    {
        switch ($value) {
            case \Phar::NONE:
                break;
            case \Phar::BZ2:
                if (array_intersect(['BZIP2', \Phar::BZ2], \Phar::getSupportedCompression()) === []) {
                    throw new Exception\UserMessage('Your PHP does not support the bz2 (bzip2) compression: please enable the bz2 PHP extension');
                }
                break;
            case \Phar::GZ:
                if (array_intersect(['GZ', \Phar::GZ], \Phar::getSupportedCompression()) === []) {
                    throw new Exception\UserMessage('Your PHP does not support the gz (zlib) compression: please enable the zlib PHP extension');
                }
                break;
            default:
                throw new Exception\InvalidArgument('Invalid parameter: $value');
        }
        $this->compressionAlgorithm = $value;

        return $this;
    }

    /**
     * @return bool
     */
    public function isCompressPHPFiles()
    {
        return $this->compressPHPFiles;
    }

    /**
     * @param bool $value
     *
     * @return $this
     */
    public function setCompressPHPFiles($value)
    {
        $this->compressPHPFiles = $value;

        return $this;
    }

    /**
     * @return bool
     */
    public function isVerifyPHPFiles()
    {
        return $this->verifyPHPFiles;
    }

    /**
     * @param bool $value
     *
     * @return $this
     */
    public function setVerifyPHPFiles($value)
    {
        $this->verifyPHPFiles = $value;

        return $this;
    }

    /**
     * @return bool
     */
    public function isVerbose()
    {
        return $this->verbose;
    }

    /**
     * @param bool $value
     *
     * @return $this
     */
    public function setVerbose($value)
    {
        $this->verbose = $value;

        return $this;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param string $value
     *
     * @throws \IPE\Exception\UserMessage
     *
     * @return $this
     */
    public function setVersion($value)
    {
        if ($value !== '' && !preg_match(self::VERSION_REGEX, $value)) {
            throw new Exception\UserMessage("Invalid version: {$value}");
        }
        $this->version = $value;

        return $this;
    }

    /**
     * @param non-empty-string $outputPharFile
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UserMessage
     *
     * @return non-empty-string the real path of the generated PHAR file
     */
    public function build($outputPharFile)
    {
        if ($this->fs->exists($outputPharFile)) {
            throw new Exception\UserMessage("The file {$outputPharFile} already exists");
        }
        $this->workingDir = $this->fs->createVolatileDirectory();
        try {
            $pharFile = $this->workingDir->getPath() . DIRECTORY_SEPARATOR . 'built.phar';
            if (!in_array('phar', stream_get_wrappers(), true) && !stream_wrapper_restore('phar')) {
                throw new Exception\IO('Failed to configure the phar stream wrapper');
            }
            try {
                $phar = new \Phar($pharFile, \FilesystemIterator::UNIX_PATHS | \FilesystemIterator::SKIP_DOTS);
                // The default depends on the PHP version (SHA-1 before PHP 8.1)
                $phar->setSignatureAlgorithm(\Phar::SHA256);
                $this->addContentToPhar($phar);
                $this->addPharStub($phar);
                $this->compressPhar($phar, $pharFile);
            } catch (\PharException $x) {
                throw new Exception\IO($x->getMessage());
            } catch (\UnexpectedValueException $x) {
                throw new Exception\IO($x->getMessage());
            } catch (\BadMethodCallException $x) {
                throw new Exception\IO($x->getMessage());
            } finally {
                unset($phar);
                gc_collect_cycles();
            }
            $this->checkPharWorks($pharFile);
            $this->fs->copyFile($pharFile, $outputPharFile);
        } finally {
            $this->workingDir = null;
        }

        return $this->fs->getRealPath($outputPharFile);
    }

    /**
     * @throws \PharException
     * @throws \UnexpectedValueException
     *
     * @return void
     */
    private function addPharStub(\Phar $phar)
    {
        $this->logVerbose('# ADDING PHAR STUB');
        $alias = self::PHAR_ALIAS;
        $phar->setStub(implode("\n", [
            '#!/usr/bin/env php',
            '<?php',
            "Phar::mapPhar('{$alias}');",
            "require 'phar://{$alias}/bin/install-php-extensions';",
            '__HALT_COMPILER();',
            '',
        ]));
    }

    /**
     * @param non-empty-string $filename
     *
     * @throws \BadMethodCallException
     *
     * @return void
     */
    private function compressPhar(\Phar $phar, $filename)
    {
        if ($this->compressionAlgorithm === \Phar::NONE) {
            return;
        }
        $this->logVerbose('# COMPRESSING PHAR');
        clearstatcache();
        $sizeBefore = (int) filesize($filename);
        $phar->compressFiles($this->compressionAlgorithm);
        clearstatcache();
        $sizeAfter = (int) filesize($filename);
        $deltaSize = $sizeBefore - $sizeAfter;
        $this->logVerbose("Size reduced from {$sizeBefore} to {$sizeAfter} ({$deltaSize} bytes saved)");
    }

    /**
     * @param non-empty-string $filename
     *
     * @throws \IPE\Exception\UserMessage
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    private function checkPharWorks($filename)
    {
        $this->logVerbose('# CHECKING THAT PHAR WORKS');
        list($rc, $output) = $this->fs->run(Command::create(PHP_BINARY, [$filename, '--help']));
        if ($rc !== 0 || strpos($output, 'install-php-extensions ') !== 0) {
            throw new Exception\UserMessage("Error testing the generated PHAR:\n{$output}");
        }
        $this->logVerbose('It works ;)');
    }

    /**
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UserMessage
     *
     * @return void
     */
    private function addContentToPhar(\Phar $phar)
    {
        foreach ($this->buildContentForPhar() as $pharPath => $pharContent) {
            $phar->addFromString($pharPath, $pharContent);
        }
    }

    /**
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UserMessage
     *
     * @return \Generator<non-empty-string, string>
     */
    private function buildContentForPhar()
    {
        $root = dirname(dirname(__DIR__));
        $semver = implode(DIRECTORY_SEPARATOR, [$root, 'vendor', 'composer', 'semver']);
        foreach ([
            $root . DIRECTORY_SEPARATOR . 'LICENSE' => 'LICENSE',
            implode(DIRECTORY_SEPARATOR, [$root, 'bin', 'install-php-extensions']) => 'bin/install-php-extensions',
            $root . DIRECTORY_SEPARATOR . 'src' => 'src',
            $semver . DIRECTORY_SEPARATOR . 'LICENSE' => 'vendor/composer/semver/LICENSE',
            $semver . DIRECTORY_SEPARATOR . 'src' => 'vendor/composer/semver/src',
        ] as $realPath => $pharPathPrefix) {
            foreach ($this->buildContentForPharIn($realPath, $pharPathPrefix) as $pharPath => $pharContent) {
                yield $pharPath => $pharContent;
            }
        }
    }

    /**
     * @param non-empty-string $realPathPrefix
     * @param non-empty-string $pharPathPrefix
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UserMessage
     *
     * @return \Generator<non-empty-string, string>
     */
    private function buildContentForPharIn($realPathPrefix, $pharPathPrefix)
    {
        if (in_array($pharPathPrefix, self::$excludedPaths, true)) {
            return;
        }
        if (is_file($realPathPrefix)) {
            yield $pharPathPrefix => $this->processFile($realPathPrefix, $pharPathPrefix);

            return;
        }
        $names = is_dir($realPathPrefix) ? scandir($realPathPrefix) : false;
        if ($names === false) {
            throw new Exception\IO('Unable to find path ' . $realPathPrefix);
        }
        foreach ($names as $name) {
            if ($name[0] === '.') {
                continue;
            }
            foreach ($this->buildContentForPharIn($realPathPrefix . DIRECTORY_SEPARATOR . $name, "{$pharPathPrefix}/{$name}") as $pharPath => $pharContent) {
                yield $pharPath => $pharContent;
            }
        }
    }

    /**
     * @param non-empty-string $realPath
     * @param non-empty-string $pharPath
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UserMessage
     *
     * @return string
     */
    private function processFile($realPath, $pharPath)
    {
        $this->logVerbose("# PROCESSING {$realPath}");
        $content = file_get_contents($realPath);
        if ($content === false) {
            throw new Exception\IO("Failed to read file {$realPath}");
        }
        if ($pharPath === 'bin/install-php-extensions') {
            if ($this->version !== '') {
                $content = self::injectVersion($content, $this->version);
            }
            // The file is included by the stub, and PHP < 8 would print its shebang line
            $eol = strpos($content, "\n");
            if (substr($content, 0, 2) === '#!' && $eol !== false) {
                // @phpstan-ignore cast.useless (substr() may return false before PHP 8)
                $content = (string) substr($content, $eol + 1);
            }
        }
        if ($this->compressPHPFiles && (preg_match('#.\.php#iD', $realPath) || strpos($content, '#!/usr/bin/env php') !== 0)) {
            $content = $this->compressPHP($content);
        }
        if ($this->verifyPHPFiles) {
            $this->checkPHPSyntax($content);
        }

        return $content;
    }

    /**
     * Set the version in the source code of bin/install-php-extensions.
     *
     * @param string $content
     * @param string $version
     *
     * @throws \IPE\Exception\UserMessage
     *
     * @return string
     */
    public static function injectVersion($content, $version)
    {
        if (substr_count($content, self::VERSION_LINE) !== 1) {
            throw new Exception\UserMessage('bin/install-php-extensions must contain this line exactly once: ' . self::VERSION_LINE);
        }

        return str_replace(self::VERSION_LINE, 'const IPE_VERSION = ' . var_export($version, true) . ';', $content);
    }

    /**
     * @param string $content
     *
     * @return string
     */
    private function compressPHP($content)
    {
        $sizeBefore = strlen($content);
        $chunks = [];
        foreach (self::tokenize($content) as $token) {
            $chunks[] = is_array($token) ? self::processToken($token) : $token;
        }
        $content = implode('', $chunks);
        $sizeAfter = strlen($content);
        $deltaSize = $sizeBefore - $sizeAfter;
        $this->logVerbose("Size reduced from {$sizeBefore} to {$sizeAfter} ({$deltaSize} bytes saved)");

        return $content;
    }

    /**
     * Tokenize PHP code so that the result is the same with every PHP version (so that the PHAR doesn't depend on it):
     * - attributes are comments that end at the end of the line (as PHP < 8 parses them)
     * - single-line comments don't include the newline (as PHP 8+ parses them)
     *
     * @param string $content
     *
     * @return list<array{int, string, int}|string>
     */
    private static function tokenize($content)
    {
        $result = [];
        $attribute = null;
        $newline = '';
        foreach (token_get_all($content) as $token) {
            if ($attribute !== null) {
                $text = is_array($token) ? $token[1] : $token;
                $p = is_array($token) && $token[0] === T_WHITESPACE ? strpos($text, "\n") : false;
                if ($p === false) {
                    $attribute[1] .= $text;
                    continue;
                }
                $attribute[1] .= substr($text, 0, $p);
                $result[] = $attribute;
                $attribute = null;
                $token[1] = substr($text, $p);
            }
            if ($newline !== '') {
                if (is_array($token) && $token[0] === T_WHITESPACE) {
                    $token[1] = $newline . $token[1];
                } else {
                    $result[] = [T_WHITESPACE, $newline, 0];
                }
                $newline = '';
            }
            if (!is_array($token)) {
                $result[] = $token;
                continue;
            }
            if (PHP_VERSION_ID >= 80000 && $token[0] === T_ATTRIBUTE) {
                $attribute = [T_COMMENT, $token[1], $token[2]];
                continue;
            }
            if ($token[0] === T_COMMENT && preg_match('/^(#|\/\/)/', $token[1])) {
                $text = rtrim($token[1], "\r\n");
                $newline = substr($token[1], strlen($text));
                $token[1] = $text;
            }
            $result[] = $token;
        }
        if ($attribute !== null) {
            $result[] = $attribute;
        } elseif ($newline !== '') {
            $result[] = [T_WHITESPACE, $newline, 0];
        }

        return $result;
    }

    /**
     * @param array{int, string, int} $token
     *
     * @return string
     */
    private static function processToken(array $token)
    {
        switch ($token[0]) {
            case T_COMMENT:
                if (substr($token[1], 0, 2) === '#[') {
                    return rtrim($token[1]);
                }

                return strpos($token[1], '@license') === false ? '' : $token[1];
            case T_DOC_COMMENT:
                return strpos($token[1], '@license') === false ? '' : $token[1];
            case T_WHITESPACE:
                // On PHP < 8, attributes are comments that end at the end of the line
                return strpos($token[1], "\n") === false ? ' ' : "\n";
            case T_OPEN_TAG:
                return trim($token[1]) . ' ';
            default:
                return $token[1];
        }
    }

    /**
     * @param string $content
     *
     * @throws \IPE\Exception\IO
     * @throws \IPE\Exception\UserMessage
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return void
     */
    private function checkPHPSyntax($content)
    {
        $workingDir = $this->workingDir;
        if ($workingDir === null) {
            throw new Exception\InvalidArgument('checkPHPSyntax() can only be called while building a PHAR');
        }
        $this->syntaxChecksCount++;
        $file = $workingDir->getPath() . DIRECTORY_SEPARATOR . "checksyntax-{$this->syntaxChecksCount}.php";
        $this->fs->writeFile($file, $content);
        try {
            list($rc, $output) = $this->fs->run(Command::create(
                PHP_BINARY,
                [
                    '-n',
                    '-d', 'error_reporting=-1',
                    '-d', 'display_startup_errors=1',
                    '-d', 'display_errors=1',
                    '-l', $file,
                ]
            ));
        } finally {
            $this->fs->deleteFile($file);
        }
        if ($rc !== 0) {
            throw new Exception\UserMessage("Syntax errors in PHP file:\n" . str_replace($file, 'PHP file', $output));
        }
        $this->logVerbose('File is syntactically correct');
    }

    /**
     * @param string $message
     *
     * @return void
     */
    private function logVerbose($message)
    {
        if ($this->verbose) {
            echo $message, "\n";
        }
    }
}
