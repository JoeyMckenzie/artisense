<?php

declare(strict_types=1);

namespace Artisense\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $title
 * @property string $heading
 * @property string $markdown
 * @property string $content
 * @property float[] $embedding
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
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }
}
