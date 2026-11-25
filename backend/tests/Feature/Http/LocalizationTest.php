<?php

use App\Models\Import;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake(config('imports.disk'));
    $this->user = User::factory()->create();
});

it('answers in English when the client sends no language', function () {
    $this->postJson('/api/v1/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'The email field is required.')
        ->assertHeader('Content-Language', 'en');
});

it('answers validation errors in Portuguese when asked', function () {
    $this->postJson('/api/v1/auth/login', [], ['Accept-Language' => 'pt-BR,pt;q=0.9'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'O campo e-mail é obrigatório.')
        ->assertJsonPath('errors.password.0', 'O campo senha é obrigatório.')
        ->assertHeader('Content-Language', 'pt-BR');
});

it('picks the language from a regional tag', function () {
    $this->postJson('/api/v1/auth/login', [], ['Accept-Language' => 'fr-FR, pt-PT;q=0.8'])
        ->assertJsonPath('errors.email.0', 'O campo e-mail é obrigatório.');
});

it('falls back to English for a language it does not support', function () {
    $this->postJson('/api/v1/auth/login', [], ['Accept-Language' => 'fr-FR'])
        ->assertJsonPath('errors.email.0', 'The email field is required.');
});

it('translates the file checks, the duplicate upload and the 404', function () {
    Queue::fake();
    Sanctum::actingAs($this->user);
    $portuguese = ['Accept-Language' => 'pt-BR'];

    $this->postJson('/api/v1/imports', ['file' => UploadedFile::fake()->createWithContent('a.csv', "date,description,value\n2026-01-05,Salary,500000\n")], $portuguese)
        ->assertUnprocessable()
        ->assertJsonPath('errors.file.0', 'Faltam as colunas obrigatórias: amount, type. Cabeçalho esperado: date,description,amount,type.');

    $csv = "date,description,amount,type\n2026-01-05,Salary,500000,Receita\n";
    $this->postJson('/api/v1/imports', ['file' => UploadedFile::fake()->createWithContent('a.csv', $csv)], $portuguese)->assertAccepted();
    $first = Import::sole();

    $this->postJson('/api/v1/imports', ['file' => UploadedFile::fake()->createWithContent('b.csv', $csv)], $portuguese)
        ->assertConflict()
        ->assertJsonPath('message', "Este arquivo já foi enviado (importação #{$first->id}).")
        ->assertJsonPath('import_id', $first->id);

    $this->getJson('/api/v1/imports/999999', $portuguese)
        ->assertNotFound()
        ->assertJsonPath('message', 'Não encontrado.');
});

it('keeps the messages stored by the queue in English, whatever the request language', function () {
    Sanctum::actingAs($this->user);
    $csv = "date,description,amount,type\n2026-01-05,Salary,-5,Receita\n";

    $this->postJson('/api/v1/imports', ['file' => UploadedFile::fake()->createWithContent('a.csv', $csv)], ['Accept-Language' => 'pt-BR'])
        ->assertAccepted();

    expect(Import::sole()->failures()->sole()->errors)->toBe(['amount: expected a positive integer in cents, got "-5"']);
});

it('translates the stored rejection reasons in the answer, keeping English in the database', function () {
    Queue::fake();
    Sanctum::actingAs($this->user);
    $import = Import::factory()->for($this->user)->create(['failure_reason' => 'No progress for 30 minutes. Retry the import to finish it; stored rows are not duplicated.']);
    $import->failures()->create(['line_number' => 3, 'raw' => 'x', 'errors' => ['row: expected 4 columns, found 1', 'something new']]);

    $this->getJson("/api/v1/imports/{$import->id}", ['Accept-Language' => 'pt-BR'])
        ->assertJsonPath('data.failure_reason', 'Sem progresso há 30 minutos. Repita a importação para terminar; as linhas já gravadas não duplicam.');

    $this->getJson("/api/v1/imports/{$import->id}/failures", ['Accept-Language' => 'pt-BR'])
        ->assertJsonPath('data.0.errors', ['linha: esperadas 4 colunas, encontradas 1', 'something new']);

    $this->getJson("/api/v1/imports/{$import->id}/failures")
        ->assertJsonPath('data.0.errors.0', 'row: expected 4 columns, found 1');

    expect($import->failures()->sole()->errors[0])->toBe('row: expected 4 columns, found 1');
});
