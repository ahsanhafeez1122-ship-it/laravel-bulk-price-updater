@extends('layouts.app')

@section('title', 'Products')

@section('content')
    <div class="row" style="justify-content: space-between">
        <h1>Products</h1>
        <form method="get" class="row" role="search">
            <label for="q" class="muted" style="font-weight:400">Search</label>
            <input id="q" type="search" name="q" value="{{ $search }}" placeholder="SKU or name">
            <button class="btn" type="submit">Search</button>
        </form>
    </div>

    <section class="card">
        <div class="table-scroll">
            <table>
                <thead><tr><th>SKU</th><th>Name</th><th class="num">Price</th><th class="num">Compare at</th><th>Last updated</th></tr></thead>
                <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td class="mono"><a href="{{ route('products.show', $product) }}">{{ $product->sku }}</a></td>
                        <td>{{ $product->name }}</td>
                        <td class="num mono">{{ \App\Support\Money::format($product->price_pence) }}</td>
                        <td class="num mono muted">{{ \App\Support\Money::format($product->compare_at_pence) }}</td>
                        <td class="muted">{{ $product->updated_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No products match "{{ $search }}".</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $products->links() }}</div>
    </section>
@endsection
