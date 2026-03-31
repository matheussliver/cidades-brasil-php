#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Config;
use App\HttpClient;
use App\MunicipioUpdater;

$updater = new MunicipioUpdater(new HttpClient());

try {
    $payload = $updater->update();
    $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

    fwrite(STDOUT, sprintf(
        "Arquivo atualizado com sucesso.\nArquivo: %s\nTotal de municípios: %d\nGerado em: %s\n",
        Config::dataFile(),
        (int) ($metadata['total_municipios'] ?? 0),
        (string) ($metadata['gerado_em'] ?? ''),
    ));

    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("Erro ao atualizar municípios: %s\n", $exception->getMessage()));

    exit(1);
}
