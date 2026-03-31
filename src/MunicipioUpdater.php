<?php

declare(strict_types=1);

namespace App;

use JsonException;
use RuntimeException;

final class MunicipioUpdater
{
    public function __construct(
        private readonly HttpClient $httpClient,
    ) {
    }

    public function update(): array
    {
        $response = $this->httpClient->getJson(Config::IBGE_MUNICIPIOS_URL);
        $municipios = [];

        foreach ($response as $item) {
            if (is_array($item)) {
                $municipios[] = $this->normalizeMunicipio($item);
            }
        }

        usort($municipios, static fn (array $left, array $right): int => strnatcasecmp(
            (string) ($left['nome'] ?? ''),
            (string) ($right['nome'] ?? ''),
        ));

        $payload = [
            'metadata' => [
                'fonte' => 'IBGE - API de Localidades',
                'fonte_url' => Config::IBGE_MUNICIPIOS_URL,
                'gerado_em' => gmdate('c'),
                'total_municipios' => count($municipios),
                'schema_version' => 1,
            ],
            'municipios' => $municipios,
        ];

        $this->persist($payload);

        return $payload;
    }

    private function persist(array $payload): void
    {
        $directory = dirname(Config::dataFile());

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível criar o diretório de dados.');
        }

        try {
            $json = json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Não foi possível serializar o arquivo de municípios.', 0, $exception);
        }

        if (file_put_contents(Config::dataFile(), $json . PHP_EOL) === false) {
            throw new RuntimeException('Não foi possível gravar o arquivo de municípios.');
        }
    }

    private function normalizeMunicipio(array $item): array
    {
        $microrregiao = is_array($item['microrregiao'] ?? null) ? $item['microrregiao'] : [];
        $mesorregiao = is_array($microrregiao['mesorregiao'] ?? null) ? $microrregiao['mesorregiao'] : [];
        $regiaoImediata = is_array($item['regiao-imediata'] ?? null) ? $item['regiao-imediata'] : [];
        $regiaoIntermediaria = is_array($regiaoImediata['regiao-intermediaria'] ?? null)
            ? $regiaoImediata['regiao-intermediaria']
            : [];
        $uf = is_array($mesorregiao['UF'] ?? null)
            ? $mesorregiao['UF']
            : (is_array($regiaoIntermediaria['UF'] ?? null) ? $regiaoIntermediaria['UF'] : []);
        $regiao = is_array($uf['regiao'] ?? null) ? $uf['regiao'] : [];

        return [
            'id' => $item['id'] ?? null,
            'nome' => $item['nome'] ?? null,
            'uf' => $this->pickFields($uf, ['id', 'sigla', 'nome']),
            'regiao' => $this->pickFields($regiao, ['id', 'sigla', 'nome']),
            'mesorregiao' => $this->pickFields($mesorregiao, ['id', 'nome']),
            'microrregiao' => $this->pickFields($microrregiao, ['id', 'nome']),
            'regiao_imediata' => $this->pickFields($regiaoImediata, ['id', 'nome']),
            'regiao_intermediaria' => $this->pickFields($regiaoIntermediaria, ['id', 'nome']),
        ];
    }

    private function pickFields(array $source, array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $source)) {
                $result[$field] = $source[$field];
            }
        }

        return $result;
    }
}
