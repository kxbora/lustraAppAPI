<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request, $userId)
    {
        if (! $this->canAccessUser($request, (int) $userId)) {
            return response()->json([
                'message' => 'Forbidden. You can only access your own cart.',
            ], 403);
        }

        $carts = Cart::with('product')
            ->where('user_id', $userId)
            ->get();

        return response()->json($carts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $targetUserId = (int) ($validated['user_id'] ?? $request->user()->id);

        if (! $this->canAccessUser($request, $targetUserId)) {
            return response()->json([
                'message' => 'Forbidden. You can only modify your own cart.',
            ], 403);
        }

        $cart = Cart::where([
            'user_id' => $targetUserId,
            'product_id' => $validated['product_id'],
        ])->first();

        if ($cart) {
            $cart->quantity += $validated['quantity'] ?? 1;
            $cart->save();

            return response()->json($cart);
        }

        $newCart = Cart::create([
            'user_id' => $targetUserId,
            'product_id' => $validated['product_id'],
            'quantity' => $validated['quantity'] ?? 1,
        ]);

        return response()->json($newCart, 201);
    }

    public function update(Request $request, $id)
    {
        $cart = Cart::findOrFail($id);

        if (! $this->canAccessUser($request, (int) $cart->user_id)) {
            return response()->json([
                'message' => 'Forbidden. You can only modify your own cart.',
            ], 403);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart->update($validated);

        return response()->json($cart);
    }

    public function destroy(Request $request, $id)
    {
        $cart = Cart::findOrFail($id);

        if (! $this->canAccessUser($request, (int) $cart->user_id)) {
            return response()->json([
                'message' => 'Forbidden. You can only modify your own cart.',
            ], 403);
        }

        $cart->delete();

        return response()->json(['message' => 'Cart item removed']);
    }

    public function clear(Request $request, $userId)
    {
        if (! $this->canAccessUser($request, (int) $userId)) {
            return response()->json([
                'message' => 'Forbidden. You can only modify your own cart.',
            ], 403);
        }

        Cart::where('user_id', $userId)->delete();

        return response()->json(['message' => 'Cart cleared']);
    }

    private function canAccessUser(Request $request, int $targetUserId): bool
    {
        $currentUser = $request->user();

        return $currentUser !== null && ($currentUser->is_admin || (int) $currentUser->id === $targetUserId);
    }
}
