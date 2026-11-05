<?php

namespace IPE\Installer;

use IPE\Exception;

/**
 * How PHP extensions and system dependencies should be compiled.
 *
 * Instances are immutable: the with... methods return a modified copy.
 */
final class BuildOptions
{
    /**
     * Build type: optimized, with the debug symbols stripped from the module.
     *
     * @public
     * @var string
     */
    const TYPE_OPTIMIZED = 'optimized';

    /**
     * Build type: optimized, keeping the debug symbols in the module.
     *
     * @public
     * @var string
     */
    const TYPE_WITH_SYMBOLS = 'with-symbols';

    /**
     * Build type: no optimizations, with the debug symbols.
     *
     * @public
     * @var string
     */
    const TYPE_DEBUG = 'debug';

    /**
     * @var non-negative-int
     */
    private $parallelJobs = 0;

    /**
     * @var string
     */
    private $type = self::TYPE_OPTIMIZED;

    /**
     * @return non-empty-list<non-empty-string>
     */
    public static function getTypes()
    {
        return [
            self::TYPE_OPTIMIZED,
            self::TYPE_WITH_SYMBOLS,
            self::TYPE_DEBUG,
        ];
    }

    /**
     * @return non-negative-int 0 for one job per available processor
     */
    public function getParallelJobs()
    {
        return $this->parallelJobs;
    }

    /**
     * @param int $value 0 for one job per available processor
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return self
     */
    public function withParallelJobs($value)
    {
        if ($value < 0) {
            throw new Exception\InvalidArgument('Invalid argument $value');
        }
        $result = clone $this;
        $result->parallelJobs = $value;

        return $result;
    }

    /**
     * @return string one of the TYPE_... constants
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $value one of the TYPE_... constants
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return self
     */
    public function withType($value)
    {
        if (!in_array($value, self::getTypes(), true)) {
            throw new Exception\InvalidArgument('Invalid argument $value');
        }
        $result = clone $this;
        $result->type = $value;

        return $result;
    }
}
