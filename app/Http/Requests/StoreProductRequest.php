<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProductType;
use App\Enums\VatCode;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? ($this->user()?->can('update', $product) ?? false)
            : ($this->user()?->can('create', Product::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'sku' => ['required', 'string', 'max:40', Rule::unique('products', 'sku')->ignore($product instanceof Product ? $product->id : null)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(ProductType::class)],
            'unit' => ['required', 'string', 'max:20'],
            'unit_price' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'price_includes_vat' => ['required', 'boolean'],
            'vat_code' => ['required', Rule::enum(VatCode::class)],
            'is_active' => ['required', 'boolean'],
            'track_stock' => ['nullable', 'boolean'],
            'quantity_on_hand' => ['nullable', 'regex:/^-?\d+(\.\d{1,3})?$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'price_includes_vat' => filter_var($this->input('price_includes_vat', false), FILTER_VALIDATE_BOOLEAN),
            'is_active' => filter_var($this->input('is_active', true), FILTER_VALIDATE_BOOLEAN),
            'track_stock' => filter_var($this->input('track_stock', false), FILTER_VALIDATE_BOOLEAN),
            'sku' => strtoupper(trim((string) $this->input('sku'))),
        ]);
    }
}
