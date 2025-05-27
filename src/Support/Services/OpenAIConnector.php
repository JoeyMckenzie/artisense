<?php

declare(strict_types=1);

namespace Artisense\Support\Services;

use Artisense\Exceptions\ArtisenseException;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

final readonly class OpenAIConnector
{
    private const string BASE_URL = 'https://api.openai.com/v1';

    public function __construct(
        private Factory $http,
        private Config $config
    ) {
        //
    }

    /**
     * @return float[]
     *
     * @throws ArtisenseException
     * @throws ConnectionException
     */
    public function generateEmbedding(string $text): array
    {
        /** @var string $apiKey */
        $apiKey = $this->config->get('artisense.openai.api_key');

        $response = $this->http
            ->withHeaders([
                'Authorization' => "Bearer $apiKey",
                'Content-Type' => 'application/json',
            ])
            ->post(self::BASE_URL.'/embeddings', [
                'input' => $text,
                'model' => 'text-embedding-ada-002',
                'encoding_format' => 'float',
            ]);

        if (! $response->ok()) {
            throw new ArtisenseException("Failed to generate embedding. Response code: {$response->status()}");
        }

        /** @var array{data: array<int, array{embedding: float[]}>} $responseJson */
        $responseJson = $response->json();

        return $responseJson['data'][0]['embedding'];
    }
}
