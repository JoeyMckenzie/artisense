<?php

declare(strict_types=1);

namespace Artisense\Tests\Formatters;

use Artisense\Formatters\BasicMarkdownFormatter;

covers(BasicMarkdownFormatter::class);

describe(BasicMarkdownFormatter::class, function (): void {
    beforeEach(function (): void {
        $this->formatter = new BasicMarkdownFormatter();
    });

    it('formats code blocks with cyan color', function (): void {
        // Arrange
        $markdown = "Some text\n```\ncode block\n```\nMore text";

        // Act
        $result = $this->formatter->format($markdown);

        // Assert
        expect($result)->toContain('Some text')
            ->toContain('<fg=cyan>```</>')
            ->toContain('<fg=cyan>code block</>')
            ->toContain('More text');
    });

    it('formats headings with magenta color', function (): void {
        // Arrange
        $markdown = "# H1 Heading\n## H2 Heading\n### H3 Heading";

        // Act
        $result = $this->formatter->format($markdown);

        // Assert
        // H1 headings are skipped
        expect($result)->not->toContain('H1 Heading')
            ->toContain('<fg=magenta;options=bold>## H2 Heading</>')
            ->toContain('<fg=magenta>### H3 Heading</>');
    });

    it('formats inline code with cyan color', function (): void {
        // Arrange
        $markdown = 'Text with `inline code` in it';

        // Act
        $result = $this->formatter->format($markdown);

        // Assert
        expect($result)->toContain('Text with <fg=cyan>`inline code`</> in it');
    });

    it('formats bold text with bold option', function (): void {
        // Arrange
        $markdown = 'Text with **bold** in it';

        // Act
        $result = $this->formatter->format($markdown);

        // Assert
        expect($result)->toContain('Text with <options=bold>**bold**</> in it');
    });

    it('formats bullet lists with yellow bullets', function (): void {
        // Arrange
        $markdown = "- Item 1\n- Item 2\n  - Nested item";

        // Act
        $result = $this->formatter->format($markdown);

        // Assert
        expect($result)->toContain('<fg=yellow>-</> Item 1')
            ->toContain('<fg=yellow>-</> Item 2')
            ->toContain('  <fg=yellow>-</> Nested item');
    });

    it('formats numbered lists with yellow numbers', function (): void {
        // Arrange
        $markdown = "1. First item\n2. Second item";

        // Act
        $result = $this->formatter->format($markdown);

        // Assert
        expect($result)->toContain('<fg=yellow>1.</> First item')
            ->toContain('<fg=yellow>2.</> Second item');
    });

    it('formats links with blue color', function (): void {
        // Arrange
        $markdown = 'Check out [Laravel](https://laravel.com)';

        // Act
        $result = $this->formatter->format($markdown);

        // Assert
        expect($result)->toContain('Check out [<fg=blue>Laravel</>](<fg=blue>https://laravel.com</>)');
    });

    it('preserves empty lines', function (): void {
        // Arrange
        $markdown = "Line 1\n\nLine 2";

        // Act
        $result = $this->formatter->format($markdown);

        // Assert
        expect($result)->toContain('Line 1')
            ->toContain('Line 2')
            ->toContain("\n\n"); // Two newlines between the lines
    });

    it('escapes angle brackets in code to prevent console formatting interference', function (): void {
        // Arrange
        $markdown = "```\n<div>HTML</div>\n```";

        // Act
        $result = $this->formatter->format($markdown);

        // Assert
        expect($result)->toContain('<fg=cyan>\\<div\\>HTML\\</div\\></>');
    });

    it('handles complex nested formatting correctly', function (): void {
        // Arrange
        $markdown = "## Heading with `code` and **bold**\n- List item with [link](url)";

        // Act
        $result = $this->formatter->format($markdown);

        // Assert, just check that the result is a non-empty string
        expect($result)->toBeString()->not->toBeEmpty();
    });

    it('handles empty input', function (): void {
        // Arrange
        $markdown = '';

        // Act
        $result = $this->formatter->format($markdown);

        // Assert, for empty input, just check that we get a string back
        expect($result)->toBeString();
    });
});
