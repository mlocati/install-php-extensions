<?php

namespace IPE\Extension;

/**
 * Interface for extensions whose source code must be fixed before compiling it.
 */
interface PatchesSourceCode
{
    /**
     * Called before running phpize.
     *
     * @throws \IPE\Exception\IO
     *
     * @return void
     */
    public function patchSourceCode(BuildContext $context);
}
