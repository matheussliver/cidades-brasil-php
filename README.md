# API simples de municípios do IBGE

Projeto PHP minimalista para baixar a lista oficial de municípios do IBGE, gerar um JSON local e expor esse conteúdo por uma API HTTP simples.

## Fonte oficial

- Documentação: https://servicodados.ibge.gov.br/api/docs/localidades
- Endpoint usado: `https://servicodados.ibge.gov.br/api/v1/localidades/municipios?orderBy=nome`

## Requisitos

- PHP 8.1 ou superior

## Atualizar o JSON local

```bash
php scripts/update_municipios.php
```

Esse comando baixa a lista mais recente da API do IBGE e grava o arquivo em `data/municipios.json`.

## Subir a API localmente

```bash
php -S localhost:8000 -t public
```

## Rotas disponíveis

- `GET /`
- `GET /api/status`
- `GET /api/municipios`

## Exemplos de uso

Listar todos os municípios:

```bash
curl "http://localhost:8000/api/municipios"
```

Filtrar por UF:

```bash
curl "http://localhost:8000/api/municipios?uf=SP"
```

Buscar por nome:

```bash
curl "http://localhost:8000/api/municipios?q=porto"
```

Paginar:

```bash
curl "http://localhost:8000/api/municipios?page=2&per_page=50"
```

Forçar atualização no momento da consulta:

```bash
curl "http://localhost:8000/api/municipios?refresh=1"
```

## Estrutura do JSON

O arquivo gerado contém:

- `metadata`: origem dos dados, data de geração, versão do schema e total de municípios.
- `municipios`: lista de municípios com `id`, `nome`, `uf`, `regiao`, `mesorregiao`, `microrregiao`, `regiao_imediata` e `regiao_intermediaria`.

## Observação prática

Para manter o arquivo sempre atualizado sem depender do parâmetro `refresh`, o fluxo ideal é agendar `php scripts/update_municipios.php` em cron.
