<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        return view('products.index', [
            'search' => $search,
            'products' => Product::query()
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('sku', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
                ->orderBy('sku')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }

    public function show(Product $product): View
    {
        return view('products.show', [
            'product' => $product,
            'changes' => $product->priceChanges()->with('priceImport:id,original_filename')->limit(100)->get(),
        ]);
    }
}
