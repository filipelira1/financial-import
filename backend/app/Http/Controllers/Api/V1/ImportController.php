<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Import\RetryImportAction;
use App\Application\Import\StartImportAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreImportRequest;
use App\Http\Resources\ImportFailureResource;
use App\Http\Resources\ImportResource;
use App\Models\Import;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ImportController extends Controller
{
    /**
     * The user's imports, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ImportResource::collection(
            $request->user()->imports()->latest('id')->paginate(15)
        );
    }

    /**
     * Upload a CSV file. It is processed in the background: poll the returned import for progress.
     *
     * @status 202
     */
    #[Response(409, 'The same file was already uploaded by this user', type: 'array{message: string, import_id: int}')]
    public function store(StoreImportRequest $request, StartImportAction $start): JsonResponse
    {
        $import = $start->execute($request->user(), $request->file('file'));

        return (new ImportResource($import->refresh()))
            ->response()
            ->setStatusCode(202)
            ->header('Location', route('api.v1.imports.show', $import));
    }

    /**
     * Status and progress of one import.
     */
    #[Response(404, 'Not found, or owned by another user', type: 'array{message: string}')]
    public function show(Request $request, int $import): ImportResource
    {
        return new ImportResource($this->ownedImport($request, $import));
    }

    /**
     * Lines of the file that were rejected, with the reasons.
     */
    #[Response(404, 'Not found, or owned by another user', type: 'array{message: string}')]
    public function failures(Request $request, int $import): AnonymousResourceCollection
    {
        return ImportFailureResource::collection(
            $this->ownedImport($request, $import)->failures()->orderBy('line_number')->paginate(50)
        );
    }

    /**
     * Run a failed import again. Rows already stored are not duplicated.
     *
     * @status 202
     */
    #[Response(404, 'Not found, or owned by another user', type: 'array{message: string}')]
    #[Response(409, 'Only a failed import can be retried', type: 'array{message: string}')]
    public function retry(Request $request, int $import, RetryImportAction $retry): JsonResponse
    {
        $import = $retry->execute($this->ownedImport($request, $import));

        return (new ImportResource($import))->response()->setStatusCode(202);
    }

    /**
     * Another user's import answers 404, exactly like one that does not exist.
     */
    private function ownedImport(Request $request, int $id): Import
    {
        return $request->user()->imports()->findOrFail($id);
    }
}
