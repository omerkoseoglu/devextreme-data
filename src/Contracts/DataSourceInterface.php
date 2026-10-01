<?php

declare(strict_types=1);

namespace DevExtreme\Data\Contracts;

use DevExtreme\Data\LoadOptions;
use DevExtreme\Data\LoadResult;

/**
 * Anything that can answer a DevExtreme load request.
 */
interface DataSourceInterface
{
    public function load(LoadOptions $options): LoadResult;
}
