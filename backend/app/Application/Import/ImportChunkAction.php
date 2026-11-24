<?php

namespace App\Application\Import;

use App\Domain\Finance\Contracts\TransactionRepository;
use App\Domain\Finance\Events\TransactionsImported;
use App\Domain\Finance\TransactionData;
use App\Domain\Import\RowRejection;
use App\Domain\Import\TransactionRowParser;
use App\Infrastructure\Csv\CsvChunk;
use App\Infrastructure\Csv\CsvReader;
use App\Models\Import;
use App\Models\ImportFailure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;

/**
 * Validate one chunk of rows and store it in a single transaction:
 * valid rows become transactions, invalid rows are kept with their reasons.
 */
final readonly class ImportChunkAction
{
    private const int MAX_RAW_BYTES = 1000;

    public function __construct(
        private TransactionRowParser $parser,
        private TransactionRepository $transactions,
        private ConnectionInterface $db,
        private Dispatcher $events,
        private LoggerInterface $logger,
    ) {}

    public function execute(int $importId, CsvChunk $chunk): void
    {
        $startedAt = hrtime(true);
        $import = Import::query()->findOrFail($importId);
        $reader = CsvReader::open(Storage::disk(config('imports.disk'))->path($import->stored_path));

        $valid = [];
        $rejected = [];

        foreach ($reader->chunk($chunk) as $row) {
            $values = $reader->combine($row->fields);
            $result = $values === null
                ? RowRejection::columnCount(count($reader->columns()), count($row->fields), $this->spansLines($row->fields))
                : $this->parser->parse($values);

            if ($result instanceof TransactionData) {
                $valid[$row->line] = $result;
            } else {
                $rejected[] = [
                    'import_id' => $import->id,
                    'line_number' => $row->line,
                    'raw' => $this->excerpt(implode($reader->delimiter(), $row->fields)),
                    'errors' => json_encode($result->errors, JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                ];
            }
        }

        // Rows of a chunk are stored all or nothing. A deadlock with a concurrent chunk is
        // retried right away (attempts), before the job-level retry kicks in.
        [$inserted, $failed] = $this->db->transaction(function () use ($import, $valid, $rejected) {
            $inserted = $this->transactions->insertImported($import->user_id, $import->id, $valid);

            // Same idempotency rule as the transactions: a line is recorded once.
            $failed = $rejected === [] ? 0 : ImportFailure::query()->toBase()
                ->upsert($rejected, ['import_id', 'line_number'], ['import_id']);

            return [$inserted, $failed];
        }, attempts: 3);

        // Live progress counters, deliberately outside the transaction above. Inserting a
        // child row takes a shared lock on the parent import (foreign key check); updating
        // the parent inside the same transaction needs an exclusive lock, and two chunks
        // committing together deadlock on that upgrade. The counters only feed the progress
        // bar: FinalizeImportAction recounts from the tables, which are the source of truth.
        Import::query()->whereKey($import->id)->incrementEach([
            'imported_rows' => $inserted,
            'failed_rows' => $failed,
        ]);

        // After the outermost commit (even if a caller wrapped this in its own transaction):
        // listeners only ever see committed data.
        if ($inserted > 0) {
            DB::afterCommit(fn () => $this->events->dispatch(
                new TransactionsImported($import->user_id, $import->id, $inserted)
            ));
        }

        $this->logger->info('import.chunk', [
            'import_id' => $import->id,
            'first_line' => $chunk->firstLine,
            'rows' => $chunk->rows,
            'inserted' => $inserted,
            'rejected' => $failed,
            'ms' => round((hrtime(true) - $startedAt) / 1e6, 1),
        ]);
    }

    /**
     * An unclosed quote can swallow the rest of the file into one value: keep the stored
     * copy small (the column is TEXT) and let the reason point to the line.
     */
    private function excerpt(string $raw): string
    {
        return strlen($raw) > self::MAX_RAW_BYTES ? mb_strcut($raw, 0, self::MAX_RAW_BYTES).'…' : $raw;
    }

    /** @param list<string> $fields */
    private function spansLines(array $fields): bool
    {
        foreach ($fields as $field) {
            if (str_contains($field, "\n")) {
                return true;
            }
        }

        return false;
    }
}
