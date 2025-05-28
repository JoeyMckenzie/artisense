<?php

declare(strict_types=1);

namespace Artisense\Tests\Support;

use Artisense\Exceptions\ArtisenseException;
use Artisense\Support\Services\OpenAIConnector;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config as ConfigFacade;
use Illuminate\Support\Facades\Http as HttpFacade;
use Mockery;

covers(OpenAIConnector::class);

describe(OpenAIConnector::class, function (): void {
    beforeEach(function (): void {
        // Set up the config for testing
        ConfigFacade::set('artisense.openai.api_key', 'test-api-key');
        HttpFacade::preventStrayRequests();

        $this->connector = new OpenAIConnector(
            app(Http::class),
            app(Config::class)
        );
    });

    it('successfully generates embeddings', function (): void {
        // Arrange
        $expectedEmbedding = array_fill(0, 1536, 0.1);
        $text = 'Test text for embedding';

        HttpFacade::fake([
            OpenAIConnector::EMBEDDINGS_BASE_URL => HttpFacade::response([
                'data' => [
                    [
                        'embedding' => $expectedEmbedding,
                    ],
                ],
            ]),
        ]);

        // Act
        $result = $this->connector->generateEmbedding('Test text for embedding');

        // Assert
        expect($result)->toBe($expectedEmbedding);

        HttpFacade::assertSent(function (Request $request) use ($text) {
            $data = $request->data();

            return $request->url() === OpenAIConnector::EMBEDDINGS_BASE_URL &&
                $request->hasHeader('Authorization', 'Bearer test-api-key') &&
                $request->hasHeader('Content-Type', 'application/json') &&
                isset($data['input']) && $data['input'] === $text &&
                isset($data['model']) && $data['model'] === 'text-embedding-ada-002' &&
                isset($data['encoding_format']) && $data['encoding_format'] === 'float';
        });
    });

    it('throws exception when API returns error response', function (): void {
        // Arrange
        HttpFacade::fake([
            OpenAIConnector::EMBEDDINGS_BASE_URL => Http::response([
                'error' => [
                    'message' => 'Invalid API key',
                ],
            ], 401, ['Content-Type' => 'application/json']),
        ]);

        $text = 'Test text for embedding';

        // Act & Assert
        expect(fn () => $this->connector->generateEmbedding($text))
            ->toThrow(ArtisenseException::class, 'Failed to generate embedding. Response code: 401');

        HttpFacade::assertSent(fn (Request $request): bool => $request->url() === OpenAIConnector::EMBEDDINGS_BASE_URL);
    });

    it('throws exception when connection fails', function (): void {
        // Arrange
        $httpMock = Mockery::mock(Http::class);
        $pendingRequestMock = Mockery::mock(PendingRequest::class);

        $httpMock->shouldReceive('withHeaders')
            ->once()
            ->andReturn($pendingRequestMock);

        $pendingRequestMock->shouldReceive('post')
            ->once()
            ->andThrow(new ConnectionException());

        $connector = new OpenAIConnector($httpMock, app(Config::class));
        $text = 'Test text for embedding';

        // Act & Assert
        expect(fn (): array => $connector->generateEmbedding($text))
            ->toThrow(ConnectionException::class);
    });
});
