<?php

namespace IPE\Extension;

/**
 * Interface for extensions that, once loaded, require some arguments for php when IPE runs PHP scripts.
 */
interface RequiresPHPScriptArguments
{
    /**
     * @return list<non-empty-string>
     */
    public function getPHPScriptArguments();
}
