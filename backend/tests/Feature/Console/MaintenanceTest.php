<?php

use App\Domain\Import\Events\ImportFinished;
use App\Domain\Import\ImportStatus;
use App\Models\Import;
use App\Models\ImportFailure;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

describe('imports:recover-stuck', function () {
    it('fails imports that stopped making progress and announces them', function () {
        Event::fake([ImportFinished::class]);
        $stuck = Import::factory()->create(['status' => ImportStatus::Processing]);
        $stuck->forceFill(['updated_at' => now()->subMinutes(61)])->saveQuietly();
        $busy = Import::factory()->create(['status' => ImportStatus::Processing]);
        $done = Import::factory()->create(['status' => ImportStatus::Completed]);
        $done->forceFill(['updated_at' => now()->subDay()])->saveQuietly();

        $this->artisan('imports:recover-stuck', ['--minutes' => 60])
            ->expectsOutput('Recovered 1 stuck import(s).')
            ->assertSuccessful();

        expect($stuck->refresh()->status)->toBe(ImportStatus::Failed)
            ->and($stuck->failure_reason)->toContain('No progress for 60 minutes')
            ->and($busy->refresh()->status)->toBe(ImportStatus::Processing)
            ->and($done->refresh()->status)->toBe(ImportStatus::Completed);
        Event::assertDispatched(fn (ImportFinished $event) => $event->importId === $stuck->id);
    });
});

describe('imports:discard-failed', function () {
    it('discards a failed import that was not retried, with its file and partial rows', function () {
        Storage::fake(config('imports.disk'));
        $user = User::factory()->create();
        $old = Import::factory()->for($user)->create(['status' => ImportStatus::Failed, 'finished_at' => now()->subDays(8)]);
        Storage::disk(config('imports.disk'))->put($old->stored_path, 'csv');
        Transaction::factory()->for($user)->count(3)->create(['import_id' => $old->id]);
        ImportFailure::query()->create(['import_id' => $old->id, 'line_number' => 2, 'raw' => 'x', 'errors' => ['y']]);
        $recent = Import::factory()->for($user)->create(['status' => ImportStatus::Failed, 'finished_at' => now()->subDays(2)]);
        $completed = Import::factory()->for($user)->create(['status' => ImportStatus::Completed, 'finished_at' => now()->subMonth()]);

        $this->artisan('imports:discard-failed', ['--days' => 7])
            ->expectsOutput('Discarded 1 failed import(s).')
            ->assertSuccessful();

        expect(Import::query()->pluck('id')->sort()->values()->all())->toBe([$recent->id, $completed->id])
            ->and(Transaction::count())->toBe(0)
            ->and(ImportFailure::count())->toBe(0);
        Storage::disk(config('imports.disk'))->assertMissing($old->stored_path);
    });

    it('refreshes the dashboard and lets the same file be uploaded again', function () {
        Storage::fake(config('imports.disk'));
        $user = User::factory()->create();
        $old = Import::factory()->for($user)->create([
            'status' => ImportStatus::Failed, 'finished_at' => now()->subDays(8), 'file_hash' => hash('sha256', dirtyCsv()),
        ]);
        Transaction::factory()->for($user)->income(5_000)->create(['import_id' => $old->id]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/dashboard/summary')->assertJsonPath('data.income_cents', 5_000);

        $this->artisan('imports:discard-failed')->assertSuccessful();

        $this->getJson('/api/v1/dashboard/summary')->assertJsonPath('data.income_cents', 0);
        $this->postJson('/api/v1/imports', ['file' => UploadedFile::fake()->createWithContent('again.csv', dirtyCsv())])
            ->assertAccepted();
    });
});

it('schedules the maintenance tasks', function () {
    $commands = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command)->implode("\n");

    expect($commands)->toContain('sanctum:prune-expired --hours=24')
        ->toContain('imports:recover-stuck --minutes=60')
        ->toContain('imports:discard-failed --days=7');
});
