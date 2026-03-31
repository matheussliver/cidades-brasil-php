<?php

declare(strict_types=1);

namespace App;

final class Config
{
    public const IBGE_MUNICIPIOS_URL = 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios?orderBy=nome';

    private function __construct()
    {
    }

    public static function dataFile(): string
    {
        return APP_BASE_PATH . '/data/municipios.json';
    }
}
