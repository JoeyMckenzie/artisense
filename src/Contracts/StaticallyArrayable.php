<?php

declare(strict_types=1);

namespace Artisense\Contracts;

interface StaticallyArrayable
{
    /**
     * @return string[]
     */
    public static function values(): array;
}
