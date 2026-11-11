<?php

namespace IPE\Installer\State;

/**
 * The functions registered with \IPE\Installer\State::registerShutdownCallback(), shared by a state and its clones.
 */
final class ShutdownCallbacks
{
    /**
     * @var list<\Closure(): void>
     */
    private $callbacks = [];

    /**
     * @param \Closure(): void $callback
     *
     * @return void
     */
    public function add(\Closure $callback)
    {
        $this->callbacks[] = $callback;
    }

    /**
     * Remove the last added occurrence of a function.
     *
     * @return bool false if the function wasn't added
     */
    public function remove(\Closure $callback)
    {
        for ($index = count($this->callbacks) - 1; $index >= 0; $index--) {
            if ($this->callbacks[$index] === $callback) {
                array_splice($this->callbacks, $index, 1);

                return true;
            }
        }

        return false;
    }

    /**
     * Call the functions, the last added first, and forget them.
     *
     * @return void
     */
    public function run(\IPE\Logger $logger)
    {
        while (($callback = array_pop($this->callbacks)) !== null) {
            try {
                $callback();
            } catch (\Exception $x) {
                // The other functions may still undo their changes
                $logger->error("A cleanup operation failed: {$x->getMessage()}");
            }
        }
    }
}
