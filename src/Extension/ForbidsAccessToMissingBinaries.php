<?php

namespace IPE\Extension;

/**
 * Interface for extensions whose vendor replies with a 403 error (instead of a 404 error) when there's no binary for the environment.
 */
interface ForbidsAccessToMissingBinaries extends AvailableAsBinary
{
}
