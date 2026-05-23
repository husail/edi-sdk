<?php

namespace Husail\EdiSdk\Contracts;

use Husail\EdiSdk\Schema\FileLayout;

interface LayoutDriverInterface
{
    /**
     * @param mixed $source File path or raw layout content — format depends on the driver.
     */
    public function load(mixed $source): FileLayout;
}
