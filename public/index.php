<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\HttpClient;
use App\MunicipioRepository;
use App\MunicipioUpdater;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    sendCorsHeaders();
    http_response_code(204);

    return;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    respondJson([
        'erro' => 'Método não permitido.',
    ], 405);

    return;
}

$repository = new MunicipioRepository();
$updater = new MunicipioUpdater(new HttpClient());
$route = normalizeRoute($_SERVER['REQUEST_URI'] ?? '/');

try {
    if ($route === '/') {
        respondJson([
            'servico' => 'API de municípios brasileiros',
            'fonte' => 'IBGE - API de Localidades',
            'rotas' => [
                '/api/status',
                '/api/municipios',
            ],
            'exemplos' => [
                '/api/municipios',
                '/api/municipios?uf=SP',
                '/api/municipios?q=porto',
                '/api/municipios?page=2&per_page=50',
                '/api/municipios?refresh=1',
            ],
        ]);

        return;
    }

    if ($route === '/api/status') {
        $payload = loadPayload($repository, $updater, shouldRefresh());

        respondJson([
            'metadata' => $payload['metadata'] ?? [],
        ]);

        return;
    }

    if ($route === '/api/municipios') {
        $payload = loadPayload($repository, $updater, shouldRefresh());
        $municipios = is_array($payload['municipios'] ?? null) ? $payload['municipios'] : [];
        $municipios = filterByUf($municipios, (string) ($_GET['uf'] ?? ''));
        $municipios = filterByQuery($municipios, (string) ($_GET['q'] ?? ''));

        $totalFiltrado = count($municipios);
        $perPage = getIntQueryParam('per_page', 100, 1, 1000);
        $totalPages = max(1, (int) ceil($totalFiltrado / $perPage));
        $page = min(getIntQueryParam('page', 1, 1), $totalPages);
        $offset = ($page - 1) * $perPage;

        respondJson([
            'metadata' => array_merge(
                is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
                [
                    'total_filtrado' => $totalFiltrado,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $totalPages,
                    'filtros' => array_filter([
                        'uf' => normalizeUf((string) ($_GET['uf'] ?? '')),
                        'q' => trim((string) ($_GET['q'] ?? '')),
                    ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                ],
            ),
            'municipios' => array_slice($municipios, $offset, $perPage),
        ]);

        return;
    }

    respondJson([
        'erro' => 'Rota não encontrada.',
    ], 404);
} catch (Throwable $exception) {
    respondJson([
        'erro' => $exception->getMessage(),
    ], 500);
}

function loadPayload(MunicipioRepository $repository, MunicipioUpdater $updater, bool $forceRefresh): array
{
    if ($forceRefresh || !$repository->exists()) {
        return $updater->update();
    }

    return $repository->load();
}

function normalizeRoute(string $uri): string
{
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $path = rtrim($path, '/');

    return $path === '' ? '/' : $path;
}

function shouldRefresh(): bool
{
    return filter_var($_GET['refresh'] ?? false, FILTER_VALIDATE_BOOL);
}

function getIntQueryParam(string $key, int $default, int $min, ?int $max = null): int
{
    $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);

    if (!is_int($value)) {
        return $default;
    }

    $value = max($min, $value);

    if ($max !== null) {
        $value = min($max, $value);
    }

    return $value;
}

function filterByUf(array $municipios, string $uf): array
{
    $uf = normalizeUf($uf);

    if ($uf === null) {
        return array_values($municipios);
    }

    return array_values(array_filter(
        $municipios,
        static fn (array $municipio): bool => strtoupper((string) ($municipio['uf']['sigla'] ?? '')) === $uf,
    ));
}

function filterByQuery(array $municipios, string $query): array
{
    $needle = normalizeSearchTerm($query);

    if ($needle === '') {
        return array_values($municipios);
    }

    return array_values(array_filter(
        $municipios,
        static fn (array $municipio): bool => str_contains(
            normalizeSearchTerm((string) ($municipio['nome'] ?? '')),
            $needle,
        ),
    ));
}

function normalizeUf(string $uf): ?string
{
    $uf = strtoupper(trim($uf));

    return $uf === '' ? null : $uf;
}

function normalizeSearchTerm(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if (function_exists('transliterator_transliterate')) {
        $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);

        if (is_string($transliterated)) {
            $value = $transliterated;
        }
    } else {
        $value = mb_strtolower($value);
    }

    $normalized = preg_replace('/\s+/', ' ', $value);

    return $normalized === null ? trim($value) : trim($normalized);
}

function respondJson(array $payload, int $statusCode = 200): void
{
    sendCorsHeaders();
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode(
        $payload,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    );
}

function sendCorsHeaders(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
