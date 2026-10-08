@extends('layouts.app')

@section('title', 'Import #'.$import->id)

@php
    use App\Enums\ImportStatus;
    use App\Enums\RowStatus;
    use App\Support\Money;
    $tabs = [
        null => ['All rows', $import->rows_total],
        RowStatus::Changed->value => ['Will change', $import->rows_changed],
        RowStatus::Flagged->value => ['Large changes', $import->rows_flagged],
        RowStatus::Unchanged->value => ['No change', $import->rows_unchanged],
        RowStatus::Error->value => ['Errors', $import->rows_error],
    ];
@endphp

@section('content')
    <div class="row">
        <h1>Import #{{ $import->id }}</h1>
        <span class="pill {{ $import->status->value }}">{{ $import->status->label() }}</span>
    </div>
    <p class="muted" style="margin:0">{{ $import->original_filename }} · uploaded {{ $import->created_at->format('j M Y, H:i') }}
        @if ($import->applied_at) · applied {{ $import->applied_at->format('j M Y, H:i') }} ({{ $import->rows_applied }} updated, {{ $import->rows_skipped }} skipped) @endif
        @if ($import->rolled_back_at) · rolled back {{ $import->rolled_back_at->format('j M Y, H:i') }} @endif
    </p>

    <section class="card">
        <div class="stats">
            @foreach ($tabs as $value => [$label, $count])
                <a class="stat {{ ($filter?->value) === ($value ?: null) ? 'active' : '' }}"
                   href="{{ route('imports.show', [$import] + ($value ? ['status' => $value] : [])) }}">
                    <b>{{ number_format($count) }}</b><span class="muted">{{ $label }}</span>
                </a>
            @endforeach
        </div>

        <div class="row" style="margin-top:16px">
            @if ($import->status === ImportStatus::Previewed)
                <form method="post" action="{{ route('imports.apply', $import) }}" class="row">
                    @csrf
                    @if ($import->needsConfirmation())
                        <label style="font-weight:400">
                            <input type="checkbox" name="confirm_flagged" value="1" required>
                            I've checked the {{ $import->rows_flagged }} large change(s) over {{ rtrim(rtrim(number_format($import->flag_threshold_pct, 2), '0'), '.') }}%
                        </label>
                    @endif
                    <button class="btn primary" type="submit" @disabled(! $import->canApply())>
                        Apply {{ $import->rows_changed + $import->rows_flagged }} price change(s)
                    </button>
                </form>
                <form method="post" action="{{ route('imports.discard', $import) }}">
                    @csrf
                    <button class="btn" type="submit">Discard</button>
                </form>
            @endif
            @if ($import->canRollBack())
                <form method="post" action="{{ route('imports.rollback', $import) }}">
                    @csrf
                    <button class="btn danger" type="submit">Roll back this import</button>
                </form>
            @endif
            @if ($import->rows_error > 0)
                <a class="btn" href="{{ route('imports.errors', $import) }}">Download errors as CSV</a>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th class="num">Line</th><th>SKU</th><th>Product</th>
                    <th class="num">Current</th><th class="num">New</th><th class="num">Change</th>
                    <th>Status</th><th>Note</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    @php $pct = $row->changePercent(); @endphp
                    <tr>
                        <td class="num mono">{{ $row->line }}</td>
                        <td class="mono">{{ $row->sku }}</td>
                        <td>
                            @if ($row->product)
                                <a href="{{ route('products.show', $row->product_id) }}">{{ $row->product->name }}</a>
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                        <td class="num mono">{{ Money::format($row->old_price_pence) }}</td>
                        <td class="num mono">
                            {{ Money::format($row->new_price_pence) }}
                            @if ($row->new_compare_at_pence)
                                <br><s class="muted">{{ Money::format($row->new_compare_at_pence) }}</s>
                            @endif
                        </td>
                        <td class="num mono {{ $pct > 0 ? 'up' : ($pct < 0 ? 'down' : '') }}">
                            {{ $pct === null ? '' : ($pct > 0 ? '+' : '').$pct.'%' }}
                        </td>
                        <td>
                            <span class="pill {{ $row->status->value }}">{{ $row->status->label() }}</span>
                            @if ($row->applied && ! $row->rolled_back) <span class="pill applied">Applied</span> @endif
                            @if ($row->rolled_back) <span class="pill rolled_back">Restored</span> @endif
                        </td>
                        <td class="muted">{{ $row->message }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No rows in this view.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $rows->links() }}</div>
    </section>
@endsection
