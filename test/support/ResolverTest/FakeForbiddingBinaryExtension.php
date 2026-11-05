<?php

namespace IPE\Test\Support\ResolverTest;

use IPE\Extension;

/**
 * An extension whose vendor replies with a 403 error when there's no binary.
 */
final class FakeForbiddingBinaryExtension extends FakeBinaryExtension implements Extension\ForbidsAccessToMissingBinaries
{
}
