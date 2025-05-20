<?php

declare(strict_types=1);

arch()->preset()->laravel();

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();
