<?php

use App\Models\Import;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A stored import ready to be processed. The file lives on the faked disk.
 */
function importFromCsv(string $bytes, ?User $user = null): Import
{
    Storage::fake(config('imports.disk'));

    $user ??= User::factory()->create();
    $path = "imports/{$user->id}/".uniqid('', true).'.csv';
    Storage::disk(config('imports.disk'))->put($path, $bytes);

    return Import::factory()->for($user)->create([
        'stored_path' => $path,
        'file_hash' => hash('sha256', $bytes),
        'file_size' => strlen($bytes),
    ]);
}

/**
 * Four valid rows (lines 2, 3, 12, 13), seven invalid ones and a blank line (10).
 */
function dirtyCsv(): string
{
    return implode("\n", [
        'date,description,amount,type',
        '2026-01-05,Salary,500000,Receita',
        '2026-01-06,Rent,150000,Despesa',
        '2026-01-07,Transfer,1000,Transferencia',
        '2026-02-30,Groceries,2000,Despesa',
        '2026-01-08,Refund,-500,Receita',
        '2026-01-09,Consulting,"1234,56",Receita',
        '2026-01-10,Supplies,1.234,56,Despesa',
        '2026-01-11,Missing type,300',
        '',
        '2026-01-12,,700,Despesa',
        '2026-01-13,Bonus,25000,receita',
        '2026-01-14,Taxes,40000,DESPESA',
    ]);
}

function csvUpload(string $bytes, string $name = 'statement.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $bytes);
}

/**
 * A downloaded CSV body without its UTF-8 BOM, split into lines.
 *
 * @return list<string>
 */
function csvLines(string $body): array
{
    expect($body)->toStartWith("\xEF\xBB\xBF");

    return explode("\n", rtrim(substr($body, 3), "\n"));
}
