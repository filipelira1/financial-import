<?php

use App\Models\Import;
use App\Models\ImportFailure;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake(config('imports.disk'));
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('exports the rejected lines in line order with their reasons', function () {
    config(['imports.chunk_size' => 3]); // the test queue runs jobs synchronously
    $id = $this->postJson('/api/v1/imports', ['file' => csvUpload(dirtyCsv())])->json('data.id');

    $response = $this->get("/api/v1/imports/{$id}/failures/export")->assertOk();
    $lines = csvLines($response->streamedContent());

    expect($response->headers->get('Content-Disposition'))->toContain("import-{$id}-rejected-lines.csv")
        ->and($lines)->toHaveCount(8)
        ->and($lines[0])->toBe('line,reasons,content')
        ->and($lines[1])->toBe('4,"type: expected ""Receita"" or ""Despesa"", got ""Transferencia""","2026-01-07,Transfer,1000,Transferencia"');
});

it('writes the header and the reasons in Portuguese when asked', function () {
    config(['imports.chunk_size' => 3]);
    $id = $this->postJson('/api/v1/imports', ['file' => csvUpload(dirtyCsv())])->json('data.id');

    $lines = csvLines($this->get("/api/v1/imports/{$id}/failures/export", ['Accept-Language' => 'pt-BR'])->streamedContent());

    expect($lines[0])->toBe('linha;motivos;conteúdo')
        ->and($lines[1])->toBe('4;"tipo: esperado ""Receita"" ou ""Despesa"", veio ""Transferencia""";2026-01-07,Transfer,1000,Transferencia');
});

it('joins several reasons for the same line', function () {
    $failure = ImportFailure::query()->create([
        'import_id' => Import::factory()->for($this->user)->create()->id,
        'line_number' => 2,
        'raw' => 'x,,y,z',
        'errors' => ['date: expected a valid YYYY-MM-DD date, got "x"', 'description: is required'],
    ]);

    $lines = csvLines($this->get("/api/v1/imports/{$failure->import_id}/failures/export")->streamedContent());

    expect($lines[1])->toBe('2,"date: expected a valid YYYY-MM-DD date, got ""x"" | description: is required","x,,y,z"');
});
