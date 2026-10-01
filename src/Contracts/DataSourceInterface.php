<?php

declare(strict_types=1);

namespace DevExtreme\Data\Contracts;

use DevExtreme\Data\LoadOptions;
use DevExtreme\Data\LoadResult;

interface DataSourceInterface
{
    public function load(LoadOptions $options): LoadResult;
}
