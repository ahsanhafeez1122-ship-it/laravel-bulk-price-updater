<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CsvFormatException;
use App\Exceptions\ImportStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImportRequest;
use App\Http\Resources\ImportResource;
use App\Http\Resources\ImportRowResource;
use App\Models\PriceImport;
use App\Services\PriceImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * JSON API for the same preview → apply → rollback flow, e.g. for an ERP or a nightly script.
 */
class ImportController extends Controller
{
    public function __construct(private readonly PriceImportService $service)
    {
    }

    public function store(StoreImportRequest $request): JsonResponse
    {
        $file = $request->file('file');

        try {
            $import = $this->service->preview($file->getRealPath(), $file->getClientOriginalName(), $request->user(), $request->threshold());
        } catch (CsvFormatException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new ImportResource($import))->response()->setStatusCode(201);
    }

    public function show(PriceImport $import): ImportResource
    {
        return new ImportResource($import);
    }

    public function rows(Request $request, PriceImport $import): AnonymousResourceCollection
    {
        $rows = $import->rows()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->paginate(min(200, max(1, (int) $request->query('per_page', 50))));

        return ImportRowResource::collection($rows);
    }

    public function apply(Request $request, PriceImport $import): JsonResponse
    {
        try {
            $result = $this->service->apply($import, $request->boolean('confirm_flagged'));
        } catch (ImportStateException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $result + ['import' => new ImportResource($import->refresh())]]);
    }

    public function rollback(PriceImport $import): JsonResponse
    {
        try {
            $result = $this->service->rollback($import);
        } catch (ImportStateException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $result + ['import' => new ImportResource($import->refresh())]]);
    }
}
