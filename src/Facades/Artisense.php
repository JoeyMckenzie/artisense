<?php

declare(strict_types=1);

namespace Artisense\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Artisense\Artisense
 */
final class Artisense extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Artisense\Artisense::class;
    }
}
