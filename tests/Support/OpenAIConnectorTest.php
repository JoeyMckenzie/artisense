<?php

declare(strict_types=1);

namespace Artisense\Tests\Support;

use Artisense\Exceptions\ArtisenseException;
use Artisense\Support\Services\OpenAIConnector;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;

covers(OpenAIConnector::class);

describe(OpenAIConnector::class, function (): void {
    beforeEach(function (): void {
        // Set up the config for testing
        Config::set('artisense.openai.api_key', 'test-api-key');

        // Clear any existing fake responses
        Http::fake()->assertNothingSent();
    });

    it('successfully generates embeddings', function (): void {
        // Arrange
        $expectedEmbedding = array_fill(0, 1536, 0.1);

        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    [
                        'embedding' => $expectedEmbedding,
                    ],
                ],
            ], 200),
        ]);

        $connector = app(OpenAIConnector::class);
        $text = 'Test text for embedding';

        // Act
        $result = $connector->generateEmbedding($text);

        // Assert
        expect($result)->toBe($expectedEmbedding);

        Http::assertSent(function ($request) use ($text) {
            $data = $request->data();

            return $request->url() === 'https://api.openai.com/v1/embeddings' &&
                   $request->hasHeader('Authorization', 'Bearer test-api-key') &&
                   $request->hasHeader('Content-Type', 'application/json') &&
                   isset($data['input']) && $data['input'] === $text &&
                   isset($data['model']) && $data['model'] === 'text-embedding-ada-002' &&
                   isset($data['encoding_format']) && $data['encoding_format'] === 'float';
        });
    });

    it('throws exception when API returns error response', function (): void {
        // Arrange
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'error' => [
                    'message' => 'Invalid API key',
                ],
            ], 401),
        ]);

        $connector = app(OpenAIConnector::class);
        $text = 'Test text for embedding';

        // Act & Assert
        expect(fn () => $connector->generateEmbedding($text))
            ->toThrow(ArtisenseException::class, 'Failed to generate embedding. Response code: 401');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.openai.com/v1/embeddings');
    });

    it('throws exception when connection fails', function (): void {
        // Arrange
        $httpMock = Mockery::mock(Factory::class);
        $pendingRequestMock = Mockery::mock(PendingRequest::class);

        $httpMock->shouldReceive('withHeaders')
            ->once()
            ->andReturn($pendingRequestMock);

        $pendingRequestMock->shouldReceive('post')
            ->once()
            ->andThrow(new ConnectionException());

        $connector = new OpenAIConnector($httpMock, app('config'));
        $text = 'Test text for embedding';

        // Act & Assert
        expect(fn (): array => $connector->generateEmbedding($text))
            ->toThrow(ConnectionException::class);
    });

    it('correctly formats the request to OpenAI API', function (): void {
        // Arrange
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    [
                        'embedding' => array_fill(0, 10, 0.1),
                    ],
                ],
            ], 200),
        ]);

        $connector = app(OpenAIConnector::class);
        $text = 'Test text for embedding';

        // Act
        $connector->generateEmbedding($text);

        // Assert
        Http::assertSent(function ($request) use ($text) {
            $data = $request->data();

            return $request->url() === 'https://api.openai.com/v1/embeddings' &&
                   $request->hasHeader('Authorization', 'Bearer test-api-key') &&
                   $request->hasHeader('Content-Type', 'application/json') &&
                   isset($data['input']) && $data['input'] === $text &&
                   isset($data['model']) && $data['model'] === 'text-embedding-ada-002' &&
                   isset($data['encoding_format']) && $data['encoding_format'] === 'float';
        });
    });
});
