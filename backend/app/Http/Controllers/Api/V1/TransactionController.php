<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Finance\ExportTransactionsAction;
use App\Application\Finance\ListTransactionsAction;
use App\Domain\Import\TransactionRowParser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListTransactionsRequest;
use App\Http\Resources\TransactionResource;
use App\Http\Responses\CsvDownload;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    /**
     * The user's transactions, newest first, optionally filtered by type, period and description.
     */
    public function index(ListTransactionsRequest $request, ListTransactionsAction $list): AnonymousResourceCollection
    {
        return TransactionResource::collection(
            $list->execute($request->user()->id, $request->filters(), $request->integer('page', 1))
        );
    }

    /**
     * Download the transactions matching the same filters as the list, as CSV.
     *
     * Same columns and values as the import file (amounts in cents, type Receita or Despesa),
     * so an exported file can be imported again. Streamed: memory stays flat whatever the size.
     */
    public function export(ListTransactionsRequest $request, ExportTransactionsAction $export): StreamedResponse
    {
        $transactions = $export->execute($request->user()->id, $request->filters());

        $rows = (function () use ($transactions) {
            foreach ($transactions as $transaction) {
                yield [
                    $transaction->occurred_on->format('Y-m-d'),
                    $transaction->description,
                    $transaction->amount_cents,
                    $transaction->type->csvLabel(),
                ];
            }
        })();

        return CsvDownload::make(
            'transactions-'.now()->format('Y-m-d-His').'.csv',
            TransactionRowParser::COLUMNS,
            $rows,
            app()->getLocale(),
        );
    }
}
