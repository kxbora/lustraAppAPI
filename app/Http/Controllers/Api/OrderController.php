<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['items.product', 'payment']);

        if (! $request->user()->is_admin) {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json($query->latest('id')->get());
    }

    public function show(Request $request, $id)
    {
        $order = Order::with(['items.product', 'payment'])->findOrFail($id);

        if (! $this->canAccessOrder($request, $order)) {
            return response()->json([
                'message' => 'Forbidden. You can only access your own orders.',
            ], 403);
        }

        return response()->json($order);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $targetUserId = (int) ($validated['user_id'] ?? $request->user()->id);

        if (! $this->canAccessUser($request, $targetUserId)) {
            return response()->json([
                'message' => 'Forbidden. You can only create your own orders.',
            ], 403);
        }

        $order = DB::transaction(function () use ($validated, $targetUserId) {
            $lineItems = [];
            $computedTotal = 0;

            foreach ($validated['items'] as $item) {
                $product = Product::whereKey($item['product_id'])->lockForUpdate()->firstOrFail();

                if ($product->stock < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for product {$product->id}"],
                    ]);
                }

                $lineItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                ];

                $computedTotal += ((float) $product->price * (int) $item['quantity']);

                $product->decrement('stock', (int) $item['quantity']);
            }

            $order = Order::create([
                'user_id' => $targetUserId,
                'total' => $computedTotal,
                'status' => $validated['status'] ?? 'pending',
            ]);

            foreach ($lineItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);
            }

            return $order;
        });

        return response()->json([
            'message' => 'Order placed',
            'order' => $order->load('items.product'),
        ], 201);
    }

    private function canAccessOrder(Request $request, Order $order): bool
    {
        return $request->user()->is_admin || (int) $order->user_id === (int) $request->user()->id;
    }

    private function canAccessUser(Request $request, int $targetUserId): bool
    {
        return $request->user()->is_admin || (int) $request->user()->id === $targetUserId;
    }
}

