<?php

/*
 * Rejection and failure reasons are stored once, in canonical English, so logs and the
 * database stay greppable whatever language the user picks. The API translates the known
 * ones when it answers; anything not listed here is returned as it was stored.
 *
 * Pattern => replacement, tried in order (the first match wins).
 */

return [
    '/^date: expected a valid YYYY-MM-DD date, got "(.*)"$/s' => 'data: esperada uma data válida AAAA-MM-DD, veio "$1"',
    '/^description: is required$/' => 'descrição: obrigatória',
    '/^description: must be at most (\d+) characters$/' => 'descrição: no máximo $1 caracteres',
    '/^amount: expected a positive integer in cents, got "(.*)"$/s' => 'valor: esperado um inteiro positivo em centavos, veio "$1"',
    '/^type: expected "Receita" or "Despesa", got "(.*)"$/s' => 'tipo: esperado "Receita" ou "Despesa", veio "$1"',
    '/^row: expected (\d+) columns, found (\d+) \(a quoted value runs over several lines: check for a missing closing quote\)$/' => 'linha: esperadas $1 colunas, encontradas $2 (um valor entre aspas ocupa várias linhas: confira se falta fechar uma aspa)',
    '/^row: expected (\d+) columns, found (\d+)$/' => 'linha: esperadas $1 colunas, encontradas $2',
    '/^(\d+) chunk\(s\) could not be processed\. Retry the import to finish it; stored rows are not duplicated\.$/' => '$1 bloco(s) não puderam ser processados. Repita a importação para terminar; as linhas já gravadas não duplicam.',
    '/^Row count mismatch: (\d+) imported \+ (\d+) rejected, but the file has (\d+) rows\.$/' => 'Contagem não confere: $1 importadas + $2 recusadas, mas o arquivo tem $3 linhas.',
    '/^No progress for (\d+) minutes\. Retry the import to finish it; stored rows are not duplicated\.$/' => 'Sem progresso há $1 minutos. Repita a importação para terminar; as linhas já gravadas não duplicam.',
    '/^Missing required column\(s\): (.*)\. Expected header: (.*)\.$/s' => 'Faltam as colunas obrigatórias: $1. Cabeçalho esperado: $2.',
    '/^The file is empty\.$/' => 'O arquivo está vazio.',
    '/^The file could not be opened\.$/' => 'Não foi possível abrir o arquivo.',
    '/^The header has duplicated column names\.$/' => 'O cabeçalho tem nomes de coluna repetidos.',
    '/^The import could not be processed\.$/' => 'A importação não pôde ser processada.',
];
