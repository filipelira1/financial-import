<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Finance\ListTransactionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListTransactionsRequest;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    /**
     * The user's transactions, newest first, optionally filtered by type and period.
     */
    public function index(ListTransactionsRequest $request, ListTransactionsAction $list): AnonymousResourceCollection
    {
        return TransactionResource::collection(
            $list->execute($request->user()->id, $request->filters(), $request->integer('page', 1))
        );
    }
}
