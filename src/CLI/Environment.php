<?php

namespace IPE\CLI;

final class Environment
{
    /**
     * Prepare the environment for the CLI scripts.
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public static function prepare()
    {
        self::checkSAPI();
        self::disableOutputBuffering();
        self::allowExit();
    }

    /**
     * @throws \RuntimeException
     *
     * @return void
     */
    private static function checkSAPI()
    {
        if (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed', 'micro'], true)) {
            // Not fwrite(STDERR) and exit(): STDERR is defined only by the CLI SAPIs
            throw new \RuntimeException('This script may only be invoked from a command line.');
        }
    }

    /**
     * Show the output immediately.
     *
     * @return void
     */
    private static function disableOutputBuffering()
    {
        if (function_exists('ob_get_level')) {
            while (ob_get_level()) {
                ob_end_flush();
            }
        }
        if (function_exists('ob_implicit_flush')) {
            ob_implicit_flush();
        }
    }

    /**
     * The uopz extension may ignore the exit calls: the CLI scripts would continue running after exit().
     *
     * @throws \RuntimeException if uopz ignores the exit calls and they can't be allowed
     *
     * @return void
     */
    private static function allowExit()
    {
        if (!extension_loaded('uopz') || ini_get('uopz.disable') || ini_get('uopz.exit')) {
            return;
        }
        if (!function_exists('uopz_allow_exit')) {
            // Not an IPE exception: the scripts catch them and call exit(), which uopz would ignore
            throw new \RuntimeException('The uopz extension ignores the exit calls: please disable it, or set uopz.exit=1');
        }
        uopz_allow_exit(true);
    }
}
