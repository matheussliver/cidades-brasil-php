<?php

declare(strict_types=1);

namespace App;

use JsonException;
use RuntimeException;

final class MunicipioRepository
{
    public function exists(): bool
    {
        return is_file(Config::dataFile());
    }

    public function load(): array
    {
        if (!$this->exists()) {
            throw new RuntimeException('O arquivo de municípios ainda não foi gerado.');
        }

        $contents = file_get_contents(Config::dataFile());

        if ($contents === false) {
            throw new RuntimeException('Não foi possível ler o arquivo de municípios.');
        }

        try {
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('O arquivo de municípios está corrompido.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('O arquivo de municípios possui um formato inválido.');
        }

        return $decoded;
    }
}
