<?php

namespace App\Http\Controllers;

use App\Enums\RowStatus;
use App\Exceptions\CsvFormatException;
use App\Exceptions\ImportStateException;
use App\Http\Requests\StoreImportRequest;
use App\Models\PriceImport;
use App\Models\Product;
use App\Services\PriceImportService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function __construct(private readonly PriceImportService $service)
    {
    }

    public function index(): View
    {
        return view('imports.index', [
            'imports' => PriceImport::query()->latest('id')->paginate(15),
            'productCount' => Product::count(),
            'threshold' => config('prices.flag_threshold_pct'),
        ]);
    }

    public function store(StoreImportRequest $request): RedirectResponse
    {
        $file = $request->file('file');

        try {
            $import = $this->service->preview($file->getRealPath(), $file->getClientOriginalName(), $request->user(), $request->threshold());
        } catch (CsvFormatException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('imports.show', $import);
    }

    public function show(Request $request, PriceImport $import): View
    {
        $filter = RowStatus::tryFrom((string) $request->query('status'));

        return view('imports.show', [
            'import' => $import,
            'filter' => $filter,
            'rows' => $import->rows()
                ->with('product:id,name')
                ->when($filter, fn ($q) => $q->where('status', $filter))
                ->paginate(50)
                ->withQueryString(),
        ]);
    }

    public function apply(Request $request, PriceImport $import): RedirectResponse
    {
        try {
            $result = $this->service->apply($import, $request->boolean('confirm_flagged'));
        } catch (ImportStateException $e) {
            return back()->withErrors(['import' => $e->getMessage()]);
        }

        $message = "Updated {$result['applied']} price(s).";
        if ($result['skipped'] > 0) {
            $message .= " Skipped {$result['skipped']} that changed after the preview.";
        }

        return redirect()->route('imports.show', $import)->with('status', $message);
    }

    public function rollback(PriceImport $import): RedirectResponse
    {
        try {
            $result = $this->service->rollback($import);
        } catch (ImportStateException $e) {
            return back()->withErrors(['import' => $e->getMessage()]);
        }

        $message = "Restored {$result['restored']} price(s).";
        if ($result['conflicts'] > 0) {
            $message .= " Left {$result['conflicts']} alone because they were changed again after this import.";
        }

        return redirect()->route('imports.show', $import)->with('status', $message);
    }

    public function discard(PriceImport $import): RedirectResponse
    {
        try {
            $this->service->discard($import);
        } catch (ImportStateException $e) {
            return back()->withErrors(['import' => $e->getMessage()]);
        }

        return redirect()->route('imports.index')->with('status', 'Import discarded. No prices were changed.');
    }

    /**
     * Rows with errors as CSV, so they can be fixed in Excel and uploaded again.
     */
    public function errors(PriceImport $import): StreamedResponse
    {
        $filename = pathinfo($import->original_filename, PATHINFO_FILENAME).'-errors.csv';

        return response()->streamDownload(function () use ($import) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['line', 'sku', 'current_price', 'new_price', 'problem'], ',', '"', '');
            $import->rows()->where('status', RowStatus::Error)->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->line,
                        $row->sku,
                        $row->old_price_pence !== null ? number_format($row->old_price_pence / 100, 2, '.', '') : '',
                        $row->new_price_pence !== null ? number_format($row->new_price_pence / 100, 2, '.', '') : '',
                        $row->message,
                    ], ',', '"', '');
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
