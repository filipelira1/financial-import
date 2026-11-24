<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Finance\GetDashboardSummaryAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardSummaryResource;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Total income, total expense and balance of the user's transactions, in cents.
     * Served from cache; refreshed whenever new transactions are stored.
     */
    public function summary(Request $request, GetDashboardSummaryAction $summary): DashboardSummaryResource
    {
        return new DashboardSummaryResource($summary->execute($request->user()->id));
    }
}
