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
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;

/**
 * Validate one chunk of rows and store it in a single transaction:
 * valid rows become transactions, invalid rows are kept with their reasons.
 */
final readonly class ImportChunkAction
{
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
                ? RowRejection::columnCount(count($reader->columns()), count($row->fields))
                : $this->parser->parse($values);

            if ($result instanceof TransactionData) {
                $valid[$row->line] = $result;
            } else {
                $rejected[] = [
                    'import_id' => $import->id,
                    'line_number' => $row->line,
                    'raw' => implode($reader->delimiter(), $row->fields),
                    'errors' => json_encode($result->errors, JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                ];
            }
        }

        [$inserted, $failed] = $this->db->transaction(function () use ($import, $valid, $rejected) {
            $inserted = $this->transactions->insertImported($import->user_id, $import->id, $valid);

            // Same idempotency rule as the transactions: a line is recorded once.
            $failed = $rejected === [] ? 0 : ImportFailure::query()->toBase()
                ->upsert($rejected, ['import_id', 'line_number'], ['import_id']);

            // Counters grow by what this run really stored, so a re-run does not inflate them.
            Import::query()->whereKey($import->id)->incrementEach([
                'imported_rows' => $inserted,
                'failed_rows' => $failed,
            ]);

            return [$inserted, $failed];
        });

        // Outside the transaction: listeners only ever see committed data.
        if ($inserted > 0) {
            $this->events->dispatch(new TransactionsImported($import->user_id, $import->id, $inserted));
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
}
