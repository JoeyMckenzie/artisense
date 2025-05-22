<?php

declare(strict_types=1);

namespace Artisense\Tests\Fixtures;

use Artisense\Contracts\OutputFormatterContract;

final class TestOutputFormatter implements OutputFormatterContract
{
    public function format(string $output): string
    {
        return "FORMATTED: $output";
    }
}
