<?php

namespace IPE\Test\Extensions;

use IPE\Extensions\snuffleupagus;
use IPE\Filesystem;
use IPE\Option\Values;
use IPE\OS;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class SnuffleupagusTest extends TestCase
{
    /**
     * @var string
     */
    const DEFAULT_RULES = <<<'EOT'
# sp.disable_function.function("commented").drop();
sp.disable_function.function("proc_open").param("command").value_r("[$|;&`\\n\\(\\)\\\\]").drop();
sp.disable_function.function("require").value_r("\.(inc|phtml|php)$").allow();
sp.disable_function.function("require").drop();
sp.disable_function.function("mail").param("additional_parameters").value_r("\\-").drop();
sp.disable_function
  .function("system>base64_decode")
  .drop();

EOT;

    /**
     * @return void
     */
    public function testDefaultRules()
    {
        self::assertSame(self::DEFAULT_RULES, $this->prepare(new Values()));
    }

    /**
     * @return void
     */
    public function testNoIPEExceptionsAsLibrary()
    {
        self::assertSame([], snuffleupagus::getOptions());
        self::assertSame(self::DEFAULT_RULES, $this->prepare(new Values([], [snuffleupagus::OPTION_ALLOW_IPE => true])));
    }

    /**
     * @return void
     */
    public function testIPEExceptions()
    {
        $root = realpath(IPE_ROOT_DIR);
        self::assertIsString($root);
        self::assertSame('', self::buildIPEExceptions("# sp.disable_function.function(\"commented\").drop();\n", $root));
        $exceptions = self::buildIPEExceptions(self::DEFAULT_RULES, "phar://{$root}");
        self::assertStringEndsWith("\n\n", $exceptions);
        $lines = explode("\n", substr($exceptions, 0, -2));
        self::assertSame('# BEGIN install-php-extensions rules (--snuffleupagus:allow-ipe)', array_shift($lines));
        self::assertSame('# END install-php-extensions rules', array_pop($lines));
        $functions = [];
        $regex = null;
        foreach ($lines as $line) {
            self::assertSame(1, preg_match('/^sp\.disable_function\.function\("(\w+)"\)\.filename_r\("(.+)"\)\.allow\(\);$/D', $line, $match), $line);
            $functions[] = $match[1];
            // snuffleupagus removes a backslash before every character
            $regex = '{' . preg_replace('/\\\\(.)/s', '$1', $match[2]) . '}';
        }
        // Sorted, and without the functions that IPE never uses (mail)
        self::assertSame(['base64_decode', 'proc_open', 'require', 'system'], $functions);
        self::assertSame(1, preg_match($regex, "{$root}/src/Filesystem/Local.php"));
        self::assertSame(1, preg_match($regex, "phar://{$root}/src/Filesystem/Local.php"));
        self::assertSame(1, preg_match($regex, $root));
        self::assertSame(0, preg_match($regex, "{$root}x/src/Filesystem/Local.php"));
        self::assertSame(0, preg_match($regex, "/var/www{$root}/index.php"));
    }

    /**
     * The tests can't define IPE_CLI_ROOT_DIR (it would stay defined for the other tests).
     *
     * @param string $rules
     * @param non-empty-string $ipeRoot
     *
     * @return string
     */
    private static function buildIPEExceptions($rules, $ipeRoot)
    {
        $method = new \ReflectionMethod(snuffleupagus::class, 'buildIPEExceptions');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        $result = $method->invoke(null, $rules, $ipeRoot);
        self::assertIsString($result);

        return $result;
    }

    /**
     * @return string the rules written by the extension
     */
    private function prepare(Values $options)
    {
        $written = null;
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('readFile')->with('/src/config/default.rules')->andReturn(self::DEFAULT_RULES);
        $fs->shouldReceive('writeFile')->once()->with('/ini/snuffleupagus.rules', M::on(static function ($contents) use (&$written) {
            $written = $contents;

            return true;
        }));
        $os = M::mock(OS\Debian::class);
        $os->shouldReceive('getFilesystem')->andReturn($fs);
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getIniScanDir')->andReturn('/ini');
        self::assertSame('/src/src', (new snuffleupagus($php, $os))->prepareVendorSource('/src', '0.14.0', $options));
        self::assertIsString($written);

        return $written;
    }
}
