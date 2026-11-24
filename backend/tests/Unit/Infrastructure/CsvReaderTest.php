<?php

use App\Infrastructure\Csv\CsvReader;
use App\Infrastructure\Csv\CsvRow;
use App\Infrastructure\Csv\InvalidCsvFile;

function csvFile(string $bytes): string
{
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $bytes);

    return $path;
}

/** @return list<array{int, list<string>}> */
function readAll(CsvReader $reader, int $chunkSize = 1000): array
{
    $rows = [];

    foreach ($reader->plan($chunkSize)['chunks'] as $chunk) {
        foreach ($reader->chunk($chunk) as $row) {
            $rows[] = [$row->line, $row->fields];
        }
    }

    return $rows;
}

it('reads the header and the rows', function () {
    $reader = CsvReader::open(csvFile("date,description,amount,type\n2026-05-01,Rent,500,Despesa\n2026-05-02,Sale,900,Receita"));

    expect($reader->columns())->toBe(['date', 'description', 'amount', 'type'])
        ->and(readAll($reader))->toBe([
            [2, ['2026-05-01', 'Rent', '500', 'Despesa']],
            [3, ['2026-05-02', 'Sale', '900', 'Receita']],
        ]);
});

it('strips a UTF-8 BOM and CRLF line endings', function () {
    $reader = CsvReader::open(csvFile("\xEF\xBB\xBFdate,description,amount,type\r\n2026-05-01,Rent,500,Despesa\r\n"));

    expect($reader->columns()[0])->toBe('date')
        ->and(readAll($reader))->toBe([[2, ['2026-05-01', 'Rent', '500', 'Despesa']]]);
});

it('detects a semicolon separator', function () {
    $reader = CsvReader::open(csvFile("date;description;amount;type\n2026-05-01;Rent, office;500;Despesa\n"));

    expect($reader->delimiter())->toBe(';')
        ->and(readAll($reader)[0][1])->toBe(['2026-05-01', 'Rent, office', '500', 'Despesa']);
});

it('converts Latin-1 text to UTF-8', function () {
    $reader = CsvReader::open(csvFile("date,description,amount,type\n2026-05-01,Servi\xE7os Prestados,500,Receita\n"));

    expect(readAll($reader)[0][1][1])->toBe('Serviços Prestados');
});

it('normalizes header names and maps columns in any order', function () {
    $reader = CsvReader::open(csvFile(" Type , AMOUNT,Date,Description\nReceita,500,2026-05-01,Sale\n"));

    $row = readAll($reader)[0];

    expect($reader->combine($row[1]))->toBe([
        'type' => 'Receita', 'amount' => '500', 'date' => '2026-05-01', 'description' => 'Sale',
    ]);
});

it('returns null when a row has a different number of columns', function () {
    $reader = CsvReader::open(csvFile("date,description,amount,type\n2026-05-01,Rent,1.234,56,Despesa\n"));

    expect($reader->combine(readAll($reader)[0][1]))->toBeNull();
});

it('keeps quoted separators and line breaks, and counts the lines they span', function () {
    $reader = CsvReader::open(csvFile("date,description,amount,type\n2026-05-01,\"Rent, office\nsecond line\",500,Despesa\n2026-05-02,Sale,900,Receita\n"));

    expect(readAll($reader))->toBe([
        [2, ['2026-05-01', "Rent, office\nsecond line", '500', 'Despesa']],
        [4, ['2026-05-02', 'Sale', '900', 'Receita']],
    ]);
});

it('skips blank lines but keeps the line numbers of the file', function () {
    $reader = CsvReader::open(csvFile("date,description,amount,type\n\n2026-05-01,Rent,500,Despesa\n\n\n2026-05-02,Sale,900,Receita\n\n"));

    expect(array_column(readAll($reader), 0))->toBe([3, 6])
        ->and($reader->plan(1000)['total'])->toBe(2);
});

it('splits the file in chunks that together read every row exactly once', function () {
    $lines = ['date,description,amount,type'];
    foreach (range(1, 10) as $i) {
        $lines[] = "2026-05-01,Row {$i},{$i}00,Receita";
        if ($i === 4) {
            $lines[] = ''; // a blank line in the middle of a chunk boundary
        }
    }
    $reader = CsvReader::open(csvFile(implode("\n", $lines)));

    $plan = $reader->plan(3);

    expect($plan['total'])->toBe(10)
        ->and(array_map(fn ($chunk) => $chunk->rows, $plan['chunks']))->toBe([3, 3, 3, 1])
        ->and(array_map(fn ($row) => $row[1][1], readAll($reader, 3)))->toBe(array_map(fn ($i) => "Row {$i}", range(1, 10)));
});

it('reads a single chunk on its own', function () {
    $path = csvFile("date,description,amount,type\n2026-05-01,A,1,Receita\n2026-05-01,B,2,Receita\n2026-05-01,C,3,Receita\n");
    $second = CsvReader::open($path)->plan(2)['chunks'][1];

    // A fresh reader, like a queued job in another worker, jumps straight to the offset.
    $rows = iterator_to_array(CsvReader::open($path)->chunk($second), false);

    expect($second->firstLine)->toBe(4)
        ->and($rows)->toEqual([new CsvRow(4, ['2026-05-01', 'C', '3', 'Receita'])]);
});

it('tells whether there is any data after the header', function (string $bytes, bool $expected) {
    expect(CsvReader::open(csvFile($bytes))->hasDataRows())->toBe($expected);
})->with([
    'header only' => ["date,description,amount,type\n", false],
    'header and blank lines' => ["date,description,amount,type\n\n\n", false],
    'one row' => ["date,description,amount,type\n2026-05-01,A,1,Receita", true],
]);

it('rejects a file without the required columns', function () {
    CsvReader::open(csvFile("date,description,value\n2026-05-01,A,1\n"))
        ->requireColumns(['date', 'description', 'amount', 'type']);
})->throws(InvalidCsvFile::class, 'Missing required column(s): amount, type');

it('rejects an empty file', function (string $bytes) {
    CsvReader::open(csvFile($bytes));
})->with(['', "\n\n", "\xEF\xBB\xBF"])->throws(InvalidCsvFile::class, 'The file is empty.');

it('rejects duplicated column names', function () {
    CsvReader::open(csvFile("date,date,amount,type\n"));
})->throws(InvalidCsvFile::class, 'duplicated');
