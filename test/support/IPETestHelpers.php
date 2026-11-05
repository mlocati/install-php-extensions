<?php

namespace IPE\Test\Support;

final class IPETestHelpers
{
    /**
     * @var bool|null
     */
    private static $isRunningInsideGithubAcion = null;

    /**
     * @return bool
     */
    public static function isRunningInsideGithubAcion()
    {
        if (self::$isRunningInsideGithubAcion === null) {
            self::$isRunningInsideGithubAcion = true;
            foreach ([
                'CI' => '#^true$#D',
                'GITHUB_ACTIONS' => '#^true$#D',
            ] as $name => $rx) {
                $value = isset($_ENV[$name]) ? $_ENV[$name] : getenv($name);
                if (!is_string($value) || !preg_match($rx, $value)) {
                    self::$isRunningInsideGithubAcion = false;
                    break;
                }
            }
        }

        return self::$isRunningInsideGithubAcion;
    }

    /**
     * @param string $message
     * @param string $title
     * @param bool $exit
     *
     * @return void|noreturn
     */
    public static function writeErrorMessage($message, $title = '', $exit = false)
    {
        $message = trim($message);
        $title = trim($title);
        if ($message === '') {
            list($message, $title) = [$title, ''];
        }
        fflush(STDOUT);
        if (self::isRunningInsideGithubAcion()) {
            echo "\n";
            // @see https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-commands#setting-an-error-message
            echo "::error file=,line=,endLine=,title={$title}::{$message}\n";
            fflush(STDOUT);
        } else {
            fwrite(STDERR, $title === '' ? "{$message}\n" : "{$title}\n\n{$message}\n");
            fflush(STDERR);
        }
        if ($exit) {
            exit(1);
        }
    }

    /**
     * @param string $title
     *
     * @return void
     */
    public static function writeStartSection($title)
    {
        $title = trim($title);
        if (self::isRunningInsideGithubAcion()) {
            echo "::group::{$title}\n";
        } else {
            $delimiter = str_repeat('#', mb_strlen($title) + 4);
            echo "{$delimiter}\n# {$title} #\n{$delimiter}\n";
        }
    }

    /**
     * @return void
     */
    public static function writeEndSection()
    {
        if (self::isRunningInsideGithubAcion()) {
            echo "::endgroup::\n";
        } else {
            echo "\n----\n\n";
        }
    }
}
