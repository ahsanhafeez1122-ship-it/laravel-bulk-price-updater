@extends('layouts.app')

@section('title', 'Price imports')

@section('content')
    <h1>Price imports</h1>

    <section class="card">
        <h2>Upload a price file</h2>
        <p class="muted">CSV with a header row: <span class="mono">sku,price,compare_at_price</span> (compare-at is optional).
            Nothing changes until you review the preview and click Apply. {{ number_format($productCount) }} products in the catalogue.</p>
        <form method="post" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="row">
            @csrf
            <label for="file" class="sr-only">CSV file</label>
            <input id="file" type="file" name="file" accept=".csv,text/csv" required>
            <label for="flag_threshold_pct">Flag changes over</label>
            <input id="flag_threshold_pct" type="number" name="flag_threshold_pct" min="1" max="100" step="1" value="{{ old('flag_threshold_pct', $threshold) }}" style="width: 80px"> %
            <button class="btn primary" type="submit">Upload and preview</button>
        </form>
    </section>

    <section class="card">
        <h2>Recent imports</h2>
        @if ($imports->isEmpty())
            <p class="muted">No imports yet. Upload your first file above. There is a sample in <span class="mono">storage/samples/prices-sample.csv</span>.</p>
        @else
            <div class="table-scroll">
                <table>
                    <thead>
                    <tr>
                        <th>#</th><th>File</th><th>Status</th>
                        <th class="num">Rows</th><th class="num">Changes</th><th class="num">Errors</th><th>Uploaded</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($imports as $import)
                        <tr>
                            <td class="mono">{{ $import->id }}</td>
                            <td><a href="{{ route('imports.show', $import) }}">{{ $import->original_filename }}</a></td>
                            <td><span class="pill {{ $import->status->value }}">{{ $import->status->label() }}</span></td>
                            <td class="num mono">{{ $import->rows_total }}</td>
                            <td class="num mono">{{ $import->rows_changed + $import->rows_flagged }}</td>
                            <td class="num mono">{{ $import->rows_error }}</td>
                            <td class="muted">{{ $import->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination">{{ $imports->links() }}</div>
        @endif
    </section>
@endsection
