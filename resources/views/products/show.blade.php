@extends('layouts.app')

@section('title', $product->sku)

@php use App\Support\Money; @endphp

@section('content')
    <div>
        <a href="{{ route('products.index') }}">← Products</a>
        <h1 style="margin-top:8px">{{ $product->name }}</h1>
        <p class="muted mono" style="margin:0">{{ $product->sku }} · now {{ Money::format($product->price_pence) }}
            @if ($product->compare_at_pence) (was {{ Money::format($product->compare_at_pence) }}) @endif</p>
    </div>

    <section class="card">
        <h2>Price history</h2>
        @if ($changes->isEmpty())
            <p class="muted">No price changes yet.</p>
        @else
            <div class="table-scroll">
                <table>
                    <thead><tr><th>When</th><th class="num">From</th><th class="num">To</th><th>Why</th></tr></thead>
                    <tbody>
                    @foreach ($changes as $change)
                        <tr>
                            <td class="muted">{{ $change->created_at->format('j M Y, H:i') }}</td>
                            <td class="num mono">{{ Money::format($change->old_price_pence) }}</td>
                            <td class="num mono">{{ Money::format($change->new_price_pence) }}</td>
                            <td>
                                {{ $change->reason === 'rollback' ? 'Rolled back' : 'Imported' }}
                                @if ($change->priceImport)
                                    from <a href="{{ route('imports.show', $change->price_import_id) }}">#{{ $change->price_import_id }} {{ $change->priceImport->original_filename }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
