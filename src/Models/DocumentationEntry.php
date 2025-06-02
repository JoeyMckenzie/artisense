<?php

declare(strict_types=1);

namespace Artisense\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $rowid
 * @property string $title
 * @property string $heading
 * @property string $markdown
 * @property string $content
 * @property string $path
 * @property string $version
 * @property string $link
 *
 * @internal
 */
final class DocumentationEntry extends Model
{
    public $timestamps = false;

    protected $table = 'docs';

    protected $connection = 'artisense';

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }
}
