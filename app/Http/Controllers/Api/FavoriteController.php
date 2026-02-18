<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request, $userId = null)
    {
        $userId = $userId ?? $request->query('user_id') ?? $request->user()->id;

        if (!$userId) {
            return response()->json([
                'message' => 'user_id is required',
            ], 422);
        }

        if (! $this->canAccessUser($request, (int) $userId)) {
            return response()->json([
                'message' => 'Forbidden. You can only access your own favorites.',
            ], 403);
        }

        $favorites = Favorite::with('product')
            ->where('user_id', $userId)
            ->get();

        return response()->json($favorites);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'product_id' => 'required|exists:products,id',
        ]);

        $targetUserId = (int) ($validated['user_id'] ?? $request->user()->id);

        if (! $this->canAccessUser($request, $targetUserId)) {
            return response()->json([
                'message' => 'Forbidden. You can only modify your own favorites.',
            ], 403);
        }

        $favorite = Favorite::firstOrCreate([
            'user_id' => $targetUserId,
            'product_id' => $validated['product_id'],
        ]);

        return response()->json($favorite, 201);
    }

    public function destroy(Request $request, $id)
    {
        $favorite = Favorite::findOrFail($id);

        if (! $this->canAccessUser($request, (int) $favorite->user_id)) {
            return response()->json([
                'message' => 'Forbidden. You can only modify your own favorites.',
            ], 403);
        }

        $favorite->delete();

        return response()->json(['message' => 'Favorite removed']);
    }

    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'product_id' => 'required|exists:products,id',
        ]);

        $targetUserId = (int) ($validated['user_id'] ?? $request->user()->id);

        if (! $this->canAccessUser($request, $targetUserId)) {
            return response()->json([
                'message' => 'Forbidden. You can only modify your own favorites.',
            ], 403);
        }

        $favorite = Favorite::where([
            'user_id' => $targetUserId,
            'product_id' => $validated['product_id'],
        ])->first();

        if ($favorite) {
            $favorite->delete();

            return response()->json([
                'message' => 'Removed from favorites',
                'is_favorite' => false,
            ]);
        }

        Favorite::create([
            'user_id' => $targetUserId,
            'product_id' => $validated['product_id'],
        ]);

        return response()->json([
            'message' => 'Added to favorites',
            'is_favorite' => true,
        ], 201);
    }

    private function canAccessUser(Request $request, int $targetUserId): bool
    {
        $currentUser = $request->user();

        return $currentUser !== null && ($currentUser->is_admin || (int) $currentUser->id === $targetUserId);
    }
}

