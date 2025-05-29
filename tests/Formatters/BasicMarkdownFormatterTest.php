<?php

declare(strict_types=1);

namespace Artisense\Tests\Formatters;

use Artisense\Console\Commands\SearchDocsCommand;
use Symfony\Component\Console\Command\Command;

it('formats markdown with proper syntax highlighting', function (): void {
    // Arrange, insert test data with various markdown elements
    $markdown = <<<'MARKDOWN'
# Heading 1

## Heading 2

### Heading 3

**Bold text** and *italic text*

- List item 1
- List item 2

1. Numbered item 1
2. Numbered item 2

```php
echo 'Code block';
```
Inline `code` example

[Link text](https://example.com)",
MARKDOWN;

    $this->db->insert([
        'title' => 'Markdown Test',
        'heading' => 'Formatting',
        'markdown' => $markdown,
        'content' => 'Markdown formatting test',
        'path' => 'markdown-test.md',
        'version' => $this->version->value,
        'link' => 'https://laravel.com/docs/12.x/markdown-test',
    ]);

    // Act & Assert, we're not checking specific formatting here, just that it doesn't error
    // and that the content is still present, but h1 headings should be skipped
    $this->artisan(SearchDocsCommand::class, ['--query' => 'markdown formatting'])
        ->expectsOutput('🔍 Found relevant information:')
        ->expectsOutputToContain('Markdown Test - Formatting')
        ->expectsOutputToContain($markdown)
        ->assertExitCode(Command::SUCCESS);
});
