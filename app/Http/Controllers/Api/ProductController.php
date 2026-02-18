<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $products = Product::with('category')
            ->latest('id')
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json($products);
    }

    public function show($id)
    {
        $product = Cache::remember("products.{$id}", now()->addMinutes(5), function () use ($id) {
            return Product::with('category')->findOrFail($id);
        });

        return response()->json($product);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'old_price' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'string', 'max:255'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'reviews_count' => ['nullable', 'integer', 'min:0'],
        ]);

        $product = Product::create($validated);
        Cache::forget("products.{$product->id}");

        return response()->json($product, 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'category_id' => ['sometimes', 'required', 'exists:categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'old_price' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'string', 'max:255'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'reviews_count' => ['nullable', 'integer', 'min:0'],
        ]);

        $product->update($validated);
        Cache::forget("products.{$product->id}");

        return response()->json($product);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $productId = $product->id;
        $product->delete();
        Cache::forget("products.{$productId}");

        return response()->json(['message' => 'Product deleted successfully']);
    }
}
