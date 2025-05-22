<?php

declare(strict_types=1);

namespace Artisense\Contracts;

interface OutputFormatterContract
{
    public function format(string $output): string;
}
