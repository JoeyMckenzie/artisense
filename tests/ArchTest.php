<?php

declare(strict_types=1);

namespace Artisense\Tests;

arch()->preset()->laravel();
arch()->preset()->php();
arch()->preset()->security();
arch()->preset()->strict();

arch('All test files are strictly typed')
    ->expect('Tests\\')
    ->toUseStrictTypes();

arch('All actions are final')
    ->expect('Artisense\\Actions\\')
    ->toBeFinal()
    ->toHaveSuffix('Action');

arch('All enums are string backed')
    ->expect('Artisense\\Enums\\')
    ->toBeStringBackedEnums();

arch('All contracts are interfaces')
    ->expect('Artisense\\Contracts\\')
    ->toBeInterfaces();
