<?php

declare(strict_types=1);

namespace Artisense\Enums;

use Artisense\Contracts\StaticallyArrayable;

enum DocumentationVersion: string implements StaticallyArrayable
{
    case VERSION_12 = '12.x';

    case VERSION_11 = '11.x';

    case VERSION_10 = '10.x';

    case MASTER = 'master';

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

    public function getExtractedFolderName(): string
    {
        return match ($this) {
            self::VERSION_12 => 'docs-12.x',
            self::VERSION_11 => 'docs-11.x',
            self::VERSION_10 => 'docs-10.x',
            self::MASTER => 'docs-master'
        };
    }

    public function getDocumentationBaseUrl(): string
    {
        return match ($this) {
            self::VERSION_12 => 'https://laravel.com/docs/12.x/',
            self::VERSION_11 => 'https://laravel.com/docs/11.x/',
            self::VERSION_10 => 'https://laravel.com/docs/10.x/',
            self::MASTER => 'https://laravel.com/docs/master/'
        };
    }

    public function getZipUrl(): string
    {
        return match ($this) {
            self::VERSION_12 => 'https://github.com/laravel/docs/archive/refs/heads/12.x.zip',
            self::VERSION_11 => 'https://github.com/laravel/docs/archive/refs/heads/11.x.zip',
            self::VERSION_10 => 'https://github.com/laravel/docs/archive/refs/heads/10.x.zip',
            self::MASTER => 'https://github.com/laravel/docs/archive/refs/heads/master.zip'
        };
    }
}
