<?php

declare(strict_types=1);

namespace Artisense\Tests;

arch()->preset()->laravel();

arch('All test files are strictly typed')
    ->expect('Tests\\')
    ->toUseStrictTypes();

arch('All actions are immutable')
    ->expect('Artisense\\Actions\\')
    ->toBeFinal()
    ->toBeReadonly()
    ->toHaveSuffix('Action');

arch('All enums are string backed')
    ->expect('Artisense\\Enums\\')
    ->toBeStringBackedEnums();

arch('All contracts are interfaces')
    ->expect('Artisense\\Contracts\\')
    ->toBeInterfaces();
