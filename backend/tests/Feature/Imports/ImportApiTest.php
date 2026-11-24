<?php

use App\Domain\Import\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function csvUpload(string $bytes, string $name = 'statement.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $bytes);
}

beforeEach(function () {
    Storage::fake(config('imports.disk'));
    $this->user = User::factory()->create();
});

it('requires a token on every import endpoint', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/v1/imports'],
    ['POST', '/api/v1/imports'],
    ['GET', '/api/v1/imports/1'],
    ['GET', '/api/v1/imports/1/failures'],
    ['POST', '/api/v1/imports/1/retry'],
]);

describe('upload', function () {
    it('stores the file, answers 202 at once and queues the processing', function () {
        Queue::fake();
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/imports', ['file' => csvUpload(dirtyCsv())]);

        $import = Import::sole();
        $response->assertAccepted()
            ->assertHeader('Location', route('api.v1.imports.show', $import))
            ->assertJsonPath('data.id', $import->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.original_name', 'statement.csv')
            ->assertJsonPath('data.progress', 0);

        Queue::assertPushedOn('imports', ProcessImportJob::class, fn (ProcessImportJob $job) => $job->importId === $import->id);
        Storage::disk(config('imports.disk'))->assertExists($import->stored_path);
        expect($import->file_hash)->toBe(hash('sha256', dirtyCsv()))
            ->and($import->user_id)->toBe($this->user->id);
    });

    it('refuses an invalid file before queueing anything', function (Closure $file, string $message) {
        Queue::fake();
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/imports', ['file' => $file()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => $message]);

        Queue::assertNothingPushed();
        expect(Import::count())->toBe(0);
    })->with([
        'no file' => [fn () => null, 'required'],
        'an image' => [fn () => UploadedFile::fake()->createWithContent('photo.png', "\x89PNG\r\n\x1a\n".str_repeat("\0", 64)), 'must be a file of type'],
        // The content is checked too, not only the name and type the client sent.
        'binary content named .csv' => [fn () => UploadedFile::fake()->createWithContent('fake.csv', "\x89PNG\r\n\x1a\n".str_repeat("\0", 64)), 'Missing required column(s)'],
        'wrong columns' => [fn () => csvUpload("date,description,value\n2026-01-05,Salary,500000\n"), 'Missing required column(s): amount, type'],
        'header only' => [fn () => csvUpload("date,description,amount,type\n"), 'no data rows'],
        'empty file' => [fn () => csvUpload(''), 'The file is empty.'],
    ]);

    it('refuses a file over the size limit', function () {
        config(['imports.max_upload_kb' => 1]);
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/imports', ['file' => csvUpload(dirtyCsv().str_repeat("\n2026-01-05,Salary,500000,Receita", 60))])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'must not be greater than 1 kilobytes']);
    });

    it('refuses the same file twice for the same user with 409', function () {
        Queue::fake();
        Sanctum::actingAs($this->user);

        $first = $this->postJson('/api/v1/imports', ['file' => csvUpload(dirtyCsv(), 'january.csv')])->assertAccepted();

        $this->postJson('/api/v1/imports', ['file' => csvUpload(dirtyCsv(), 'renamed.csv')])
            ->assertConflict()
            ->assertJsonPath('import_id', $first->json('data.id'));

        expect(Import::count())->toBe(1)
            ->and(Storage::disk(config('imports.disk'))->allFiles())->toHaveCount(1);
        Queue::assertPushed(ProcessImportJob::class, 1);
    });

    it('lets another user upload the same file', function () {
        Queue::fake();
        Import::factory()->create(['file_hash' => hash('sha256', dirtyCsv())]);
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/imports', ['file' => csvUpload(dirtyCsv())])->assertAccepted();
    });

    it('processes the upload end to end', function () {
        config(['imports.chunk_size' => 3]); // the test queue runs jobs synchronously
        Sanctum::actingAs($this->user);

        $id = $this->postJson('/api/v1/imports', ['file' => csvUpload(dirtyCsv())])->json('data.id');

        $this->getJson("/api/v1/imports/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed_with_errors')
            ->assertJsonPath('data.is_finished', true)
            ->assertJsonPath('data.total_rows', 11)
            ->assertJsonPath('data.imported_rows', 4)
            ->assertJsonPath('data.failed_rows', 7)
            ->assertJsonPath('data.progress', 100);

        $this->getJson("/api/v1/imports/{$id}/failures")
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonPath('data.0.line_number', 4)
            ->assertJsonPath('data.0.raw', '2026-01-07,Transfer,1000,Transferencia')
            ->assertJsonPath('data.0.errors.0', 'type: expected "Receita" or "Despesa", got "Transferencia"');
    });
});

describe('reading', function () {
    it('lists only the user\'s own imports, newest first', function () {
        $older = Import::factory()->for($this->user)->create();
        $newer = Import::factory()->for($this->user)->create();
        Import::factory()->create(); // someone else's
        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/imports')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('meta.total', 2);
    });

    it('reports progress while processing', function () {
        $import = Import::factory()->for($this->user)->create([
            'status' => ImportStatus::Processing, 'total_rows' => 15_000, 'imported_rows' => 5_990, 'failed_rows' => 10,
        ]);
        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/imports/{$import->id}")
            ->assertJsonPath('data.processed_rows', 6_000)
            ->assertJsonPath('data.progress', 40)
            ->assertJsonPath('data.is_finished', false);
    });

    it('answers 404 for another user\'s import, as if it did not exist', function (string $suffix) {
        $foreign = Import::factory()->create();
        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/imports/{$foreign->id}{$suffix}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not found.']);
    })->with(['', '/failures']);
});

describe('retry', function () {
    it('queues a failed import again', function () {
        Queue::fake();
        $import = Import::factory()->for($this->user)->create(['status' => ImportStatus::Failed, 'failure_reason' => 'boom']);
        Sanctum::actingAs($this->user);

        $this->postJson("/api/v1/imports/{$import->id}/retry")
            ->assertAccepted()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.failure_reason', null);

        Queue::assertPushed(ProcessImportJob::class, fn (ProcessImportJob $job) => $job->importId === $import->id);
    });

    it('refuses to retry an import that did not fail', function (ImportStatus $status) {
        Queue::fake();
        $import = Import::factory()->for($this->user)->create(['status' => $status]);
        Sanctum::actingAs($this->user);

        $this->postJson("/api/v1/imports/{$import->id}/retry")->assertConflict();

        Queue::assertNothingPushed();
    })->with([ImportStatus::Pending, ImportStatus::Processing, ImportStatus::Completed, ImportStatus::CompletedWithErrors]);
});
