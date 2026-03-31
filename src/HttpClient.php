<?php

declare(strict_types=1);

namespace App;

use JsonException;
use RuntimeException;

final class HttpClient
{
    public function getJson(string $url, int $timeoutInSeconds = 30): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeoutInSeconds,
                'ignore_errors' => true,
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'User-Agent: municipios-api-php/1.0',
                ]),
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];

        if ($response === false) {
            throw new RuntimeException('Falha ao consultar a API oficial do IBGE.');
        }

        $statusCode = $this->extractStatusCode($headers);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf('A API do IBGE respondeu com status %d.', $statusCode));
        }

        try {
            $decoded = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('A resposta da API do IBGE não é um JSON válido.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('A API do IBGE retornou um formato inesperado.');
        }

        return $decoded;
    }

    private function extractStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }
}
