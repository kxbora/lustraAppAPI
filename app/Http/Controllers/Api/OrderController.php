<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
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
            'payment_method' => 'nullable|string|max:100',
            'shipping_address' => 'nullable|string|max:1000',
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
                'payment_method' => $validated['payment_method'] ?? null,
                'shipping_address' => $validated['shipping_address'] ?? null,
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

        Notification::createSafe([
            'user_id' => $targetUserId,
            'title' => 'Order Placed',
            'message' => "Your order #{$order->id} has been placed successfully.",
            'type' => 'order',
            'is_read' => false,
        ]);

        return response()->json([
            'message' => 'Order placed',
            'order' => $order->load('items.product'),
        ], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $order = Order::with(['items.product', 'payment'])->findOrFail($id);

        if (! $this->canAccessOrder($request, $order)) {
            return response()->json([
                'message' => 'Forbidden. You can only update your own orders.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:pending,processing,shipped,delivered,cancelled',
        ]);

        $nextStatus = $validated['status'];
        $currentStatus = strtolower((string) $order->status);

        if (! $request->user()->is_admin && $nextStatus !== 'cancelled') {
            return response()->json([
                'message' => 'Forbidden. You can only cancel your order.',
            ], 403);
        }

        if ($nextStatus === 'cancelled' && in_array($currentStatus, ['shipped', 'delivered', 'cancelled'], true)) {
            return response()->json([
                'message' => 'Order can no longer be cancelled.',
            ], 422);
        }

        $order->update([
            'status' => $nextStatus,
        ]);

        return response()->json([
            'message' => 'Order status updated.',
            'order' => $order->fresh()->load(['items.product', 'payment']),
        ]);
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

