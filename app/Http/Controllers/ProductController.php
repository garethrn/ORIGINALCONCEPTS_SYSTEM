<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\VatCode;
use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);
        $products = Product::query()
            ->when($request->string('q')->toString(), function ($query, string $q) {
                $like = '%'.addcslashes(mb_strtolower($q), '%_\\').'%';
                $query->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(sku) LIKE ?', [$like]);
            })
            ->orderBy('name')
            ->paginate(30);

        return view('products.index', compact('products'));
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('products.form', ['product' => new Product(['unit' => 'each', 'vat_code' => VatCode::Standard, 'is_active' => true, 'unit_price_cents' => 0])]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['unit_price_cents'] = Money::randsToCents($data['unit_price']);
        unset($data['unit_price']);
        $data['quantity_on_hand'] = $data['quantity_on_hand'] ?? '0';
        Product::query()->create($data);

        return redirect()->route('products.index')->with('status', 'Product saved.');
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('products.form', compact('product'));
    }

    public function update(StoreProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $data['unit_price_cents'] = Money::randsToCents($data['unit_price']);
        unset($data['unit_price']);
        if (! array_key_exists('quantity_on_hand', $data) || $data['quantity_on_hand'] === null) {
            unset($data['quantity_on_hand']);
        }
        $product->update($data);

        return redirect()->route('products.index')->with('status', 'Product updated.');
    }
}
