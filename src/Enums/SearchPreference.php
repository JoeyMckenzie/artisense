<?php

declare(strict_types=1);

namespace Artisense\Enums;

use Artisense\Contracts\StaticallyArrayable;

enum SearchPreference: string implements StaticallyArrayable
{
    case ORDERED = 'ordered';

    case UNORDERED = 'unordered';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        /** @var string[] $values */
        $values = collect(self::cases())
            ->map(fn (self $case): string => $case->value)
            ->toArray();

        return $values;
    }
}
