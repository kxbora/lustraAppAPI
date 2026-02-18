<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with('order');

        if (! $request->user()->is_admin) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            });
        }

        return response()->json($query->latest('id')->get());
    }

    public function show(Request $request, $id)
    {
        $payment = Payment::with('order')->findOrFail($id);

        if (! $this->canAccessPayment($request, $payment)) {
            return response()->json([
                'message' => 'Forbidden. You can only access your own payments.',
            ], 403);
        }

        return response()->json($payment);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id|unique:payments,order_id',
            'payment_method' => 'nullable|string|max:50',
            'payment_status' => 'nullable|string|max:50',
            'paid_at' => 'nullable|date',
        ]);

        $order = Order::findOrFail($validated['order_id']);

        if (! $request->user()->is_admin && (int) $order->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Forbidden. You can only create payment for your own order.',
            ], 403);
        }

        $payment = Payment::create($validated);

        return response()->json($payment, 201);
    }

    public function update(Request $request, $id)
    {
        if (! $request->user()->is_admin) {
            return response()->json([
                'message' => 'Forbidden. Admin access required.',
            ], 403);
        }

        $payment = Payment::findOrFail($id);

        $validated = $request->validate([
            'payment_method' => 'nullable|string|max:50',
            'payment_status' => 'nullable|string|max:50',
            'paid_at' => 'nullable|date',
        ]);

        $payment->update($validated);

        return response()->json($payment);
    }

    public function destroy(Request $request, $id)
    {
        if (! $request->user()->is_admin) {
            return response()->json([
                'message' => 'Forbidden. Admin access required.',
            ], 403);
        }

        $payment = Payment::findOrFail($id);
        $payment->delete();

        return response()->json(['message' => 'Payment deleted']);
    }

    private function canAccessPayment(Request $request, Payment $payment): bool
    {
        if ($request->user()->is_admin) {
            return true;
        }

        return (int) optional($payment->order)->user_id === (int) $request->user()->id;
    }
}
